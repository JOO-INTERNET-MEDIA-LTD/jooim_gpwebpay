<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once __DIR__.'/classes/GpwebpayClient.php';
require_once __DIR__.'/classes/GpwebpayRepository.php';

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Jooim_Gpwebpay extends PaymentModule
{
    const VERSION = '1.00.00';
    private $html = '';

    public function __construct()
    {
        $this->name = 'jooim_gpwebpay';
        $this->tab = 'payments_gateways';
        $this->version = self::VERSION;
        $this->author = 'JOO INTERNET MEDIA LTD';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = array('payment','validation','repeat','cron');
        parent::__construct();
        $this->displayName = $this->l('Payment gateway GP webpay / ČSOB');
        $this->description = $this->l('Accept card payments via GP webpay / ČSOB with repeat payment, logging and refund support.');
        $this->ps_versions_compliancy = array('min'=>'1.7.0.0','max'=>'9.99.99');
    }

    public function install()
    {
        return parent::install()
            && GpwebpayRepository::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayHeader')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayOrderDetail')
            && $this->registerFooterAttributionHook()
            && $this->installOrderStates()
            && $this->setDefaults();
    }

    public function uninstall()
    {
        $keys = array('DM_GPWEBPAY_TEST_MODE','DM_GPWEBPAY_MERCHANT_NUMBER','DM_GPWEBPAY_PROVIDER','DM_GPWEBPAY_PRIVATE_KEY','DM_GPWEBPAY_PRIVATE_KEY_PASSWORD','DM_GPWEBPAY_PUBLIC_KEY','DM_GPWEBPAY_ALLOWED_CURRENCIES','DM_GPWEBPAY_ALLOWED_CARRIERS','DM_GPWEBPAY_TEST_IPS','DM_GPWEBPAY_PAYMETHODS','DM_GPWEBPAY_DEPOSIT_FLAG','DM_GPWEBPAY_OS_PENDING','DM_GPWEBPAY_OS_PAID','DM_GPWEBPAY_OS_FAILED','DM_GPWEBPAY_OS_AUTHORIZED','DM_GPWEBPAY_OS_REFUNDED','DM_GPWEBPAY_CRON_TOKEN');
        foreach ($keys as $k) { Configuration::deleteByName($k); }
        return GpwebpayRepository::uninstall() && parent::uninstall();
    }

    private function setDefaults()
    {
        Configuration::updateValue('DM_GPWEBPAY_TEST_MODE', 1);
        Configuration::updateValue('DM_GPWEBPAY_PROVIDER', '0300');
        Configuration::updateValue('DM_GPWEBPAY_DEPOSIT_FLAG', 1);
        Configuration::updateValue('DM_GPWEBPAY_ALLOWED_CURRENCIES', '');
        Configuration::updateValue('DM_GPWEBPAY_ALLOWED_CARRIERS', '');
        Configuration::updateValue('DM_GPWEBPAY_PAYMETHODS', 'CRD,APAY,GPAY');
        Configuration::updateValue('DM_GPWEBPAY_OS_PENDING', (int)Configuration::get('PS_OS_BANKWIRE'));
        Configuration::updateValue('DM_GPWEBPAY_OS_PAID', (int)Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('DM_GPWEBPAY_OS_FAILED', (int)Configuration::get('PS_OS_ERROR'));
        Configuration::updateValue('DM_GPWEBPAY_OS_AUTHORIZED', (int)Configuration::get('PS_OS_PAYMENT'));
        Configuration::updateValue('DM_GPWEBPAY_OS_REFUNDED', (int)Configuration::get('PS_OS_REFUND'));
        Configuration::updateValue('DM_GPWEBPAY_CRON_TOKEN', Tools::passwdGen(32));
        return true;
    }

    private function installOrderStates()
    {
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart']) || !$this->checkCarrier($params['cart']) || !$this->checkTestIp()) { return array(); }
        $option = new PaymentOption();
        $option->setCallToActionText($this->l('Pay by card via GP webpay / ČSOB'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/payment.png'))
            ->setAdditionalInformation($this->l('You will be redirected to the secure GP webpay payment gateway.'));
        return array($option);
    }

    public function hookDisplayHeader($params)
    {
        if (!$this->active) { return ''; }
        $this->context->controller->registerStylesheet(
            'module-jooim-gpwebpay-front',
            'modules/'.$this->name.'/views/css/front.css',
            array('media' => 'all', 'priority' => 150)
        );
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) { return ''; }
        $order = isset($params['order']) ? $params['order'] : null;
        if (!Validate::isLoadedObject($order)) { return ''; }
        $payment = GpwebpayRepository::getByOrderId((int)$order->id);
        $this->context->smarty->assign(array('gpwebpay_payment'=>$payment));
        return $this->display(__FILE__, 'views/templates/front/payment_return.tpl');
    }

    public function hookDisplayOrderDetail($params)
    {
        $order = isset($params['order']) ? $params['order'] : null;
        if (!Validate::isLoadedObject($order)) { return ''; }
        $payment = GpwebpayRepository::getByOrderId((int)$order->id);
        if (!$payment || !in_array($payment['status'], array('failed','pending','created'))) { return ''; }
        $this->context->smarty->assign(array('repeat_url'=>$this->context->link->getModuleLink($this->name, 'repeat', array('id_order'=>(int)$order->id, 'key'=>$order->secure_key), true)));
        return $this->display(__FILE__, 'views/templates/hook/customer_order.tpl');
    }

    public function hookDisplayAdminOrder($params)
    {
        $idOrder = (int)(isset($params['id_order']) ? $params['id_order'] : Tools::getValue('id_order'));
        GpwebpayRepository::ensureSchema();
        $payment = GpwebpayRepository::getByOrderId($idOrder);
        if (!$payment) { return ''; }
        $payment = $this->reconcileRefundedAmountFromGateway($payment);

        $adminMessage = '';
        if (Tools::isSubmit('submitGpwebpayRefund') && (int)Tools::getValue('id_order') === $idOrder) {
            $this->postProcessRefund();
            $adminMessage = $this->html;
            $this->html = '';
            $payment = GpwebpayRepository::getByOrderId($idOrder);
        }

        $logs = GpwebpayRepository::getLogs($idOrder);
        $order = new Order($idOrder);
        $currency = Validate::isLoadedObject($order) ? new Currency((int)$order->id_currency) : $this->context->currency;
        $refundedMinor = isset($payment['refunded_amount']) ? (int)$payment['refunded_amount'] : 0;
        $availableRefundMinor = max(0, (int)$payment['amount'] - $refundedMinor);
        $refundAction = $this->context->link->getAdminLink('AdminOrders', true, array(), array('vieworder'=>1, 'id_order'=>$idOrder));
        $this->context->smarty->assign(array(
            'gpwebpay_payment'=>$payment,
            'gpwebpay_logs'=>$logs,
            'gpwebpay_admin_message'=>$adminMessage,
            'gpwebpay_refund_action'=>$refundAction.'#jooim-gpwebpay-admin-card',
            'gpwebpay_token'=>Tools::getAdminTokenLite('AdminOrders'),
            'gpwebpay_repeat_url'=>$this->context->link->getModuleLink($this->name, 'repeat', array('id_order'=>$idOrder, 'key'=>$order->secure_key), true),
            'gpwebpay_refunded_amount_display'=>$this->minorToDisplay($refundedMinor, $currency),
            'gpwebpay_available_refund_display'=>$this->minorToDisplay($availableRefundMinor, $currency),
            'gpwebpay_available_refund_minor'=>$availableRefundMinor,
        ));
        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }


    private function registerFooterAttributionHook()
    {
        if (class_exists('Hook') && (int)Hook::getIdByName('displayFooterAfter')) {
            return $this->registerHook('displayFooterAfter');
        }

        return $this->registerHook('displayFooter');
    }

    private function renderFooterAttribution()
    {
        if (!$this->active) { return ''; }
        $this->context->smarty->assign(array(
            'joobox_home_url' => 'https://joobox.eu',
        ));
        return $this->display(__FILE__, 'views/templates/hook/footer_attribution.tpl');
    }

    public function hookDisplayFooterAfter($params)
    {
        return $this->renderFooterAttribution();
    }

    public function hookDisplayFooter($params)
    {
        // Fallback only for installations/themes where displayFooterAfter is not available.
        // On standard Classic/Hummingbird themes this prevents duplicate attribution output.
        if (class_exists('Hook') && (int)Hook::getIdByName('displayFooterAfter')) { return ''; }
        return $this->renderFooterAttribution();
    }

    protected function getJooboxSupportUrl()
    {
        $language = isset($this->context->language) ? $this->context->language : null;
        $iso = $language && isset($language->iso_code) ? Tools::strtolower((string) $language->iso_code) : 'en';
        $locale = '';

        if ($language) {
            if (isset($language->locale) && $language->locale) {
                $locale = (string) $language->locale;
            } elseif (isset($language->language_code) && $language->language_code) {
                $locale = (string) $language->language_code;
            }
        }

        $locale = Tools::strtolower(str_replace('_', '-', $locale));

        if ($locale === 'de-at') {
            return 'https://joobox.eu/de-at/unterstutzung';
        }
        if ($locale === 'de-ch') {
            return 'https://joobox.eu/de-ch/unterstutzung';
        }
        if ($locale === 'en-ca') {
            return 'https://joobox.eu/en-ca/support';
        }
        if ($locale === 'fr-ca') {
            return 'https://joobox.eu/fr-ca/support';
        }

        switch ($iso) {
            case 'sk':
                return 'https://joobox.eu/sk-sk/podpora';
            case 'cs':
                return 'https://joobox.eu/cs-cz/podpora';
            case 'de':
                return 'https://joobox.eu/de-de/unterstutzung';
            case 'fr':
                return 'https://joobox.eu/fr-fr/soutien';
            case 'it':
                return 'https://joobox.eu/it-it/supporto';
            case 'pl':
                return 'https://joobox.eu/pl-pl/wsparcie';
            case 'en':
            default:
                return 'https://joobox.eu/en-gb/support';
        }
    }

    private function renderJooboxSupportPanel()
    {
        $url = $this->getJooboxSupportUrl();
        $github = 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay';

        return '<div class="panel jooim-gpwebpay-support-panel">'
            .'<h3><i class="icon-life-ring"></i> '.$this->l('Joobox support for this module').'</h3>'
            .'<p>'.$this->l('This free GitHub module is provided without individual technical support. Installation, configuration, production testing, updates and troubleshooting are available for customers using Joobox PrestaShop Cloud or another paid service explicitly agreed with JOO INTERNET MEDIA LTD.').'</p>'
            .'<p><a class="btn btn-primary" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">'.$this->l('Get Joobox support').'</a> '
            .'<a class="btn btn-default" href="'.htmlspecialchars($github, ENT_QUOTES, 'UTF-8').'" target="_blank" rel="noopener">GitHub</a></p>'
            .'</div>';
    }

    public function getContent()
    {
        $this->ensureRequiredHooks();
        if (Tools::isSubmit('submitGpwebpayConfig')) { $this->postProcessConfig(); }
        if (Tools::isSubmit('submitGpwebpayRefund')) { $this->postProcessRefund(); }
        return $this->html.$this->renderJooboxSupportPanel().$this->renderForm().$this->renderInfo();
    }

    private function postProcessConfig()
    {
        $fields = array('DM_GPWEBPAY_MERCHANT_NUMBER','DM_GPWEBPAY_PROVIDER','DM_GPWEBPAY_ALLOWED_CURRENCIES','DM_GPWEBPAY_ALLOWED_CARRIERS','DM_GPWEBPAY_TEST_IPS','DM_GPWEBPAY_PAYMETHODS','DM_GPWEBPAY_OS_PENDING','DM_GPWEBPAY_OS_PAID','DM_GPWEBPAY_OS_FAILED','DM_GPWEBPAY_OS_AUTHORIZED','DM_GPWEBPAY_OS_REFUNDED');
        foreach ($fields as $f) { Configuration::updateValue($f, Tools::getValue($f), false); }
        // Password fields are intentionally write-only. Do not erase the saved password
        // when the administrator saves another setting and leaves this field empty.
        if (Tools::getValue('DM_GPWEBPAY_PRIVATE_KEY_PASSWORD') !== '') {
            Configuration::updateValue('DM_GPWEBPAY_PRIVATE_KEY_PASSWORD', Tools::getValue('DM_GPWEBPAY_PRIVATE_KEY_PASSWORD'), false);
        }
        Configuration::updateValue('DM_GPWEBPAY_TEST_MODE', (int)Tools::getValue('DM_GPWEBPAY_TEST_MODE'));
        Configuration::updateValue('DM_GPWEBPAY_DEPOSIT_FLAG', (int)Tools::getValue('DM_GPWEBPAY_DEPOSIT_FLAG'));
        $privateKeyFromFile = $this->readUploadedPemFile('DM_GPWEBPAY_PRIVATE_KEY_FILE');
        $publicKeyFromFile = $this->readUploadedPemFile('DM_GPWEBPAY_PUBLIC_KEY_FILE');

        if ($privateKeyFromFile !== null) {
            Configuration::updateValue('DM_GPWEBPAY_PRIVATE_KEY', $privateKeyFromFile, false);
        } elseif (Tools::getValue('DM_GPWEBPAY_PRIVATE_KEY') !== '') {
            Configuration::updateValue('DM_GPWEBPAY_PRIVATE_KEY', Tools::getValue('DM_GPWEBPAY_PRIVATE_KEY'), false);
        }

        if ($publicKeyFromFile !== null) {
            Configuration::updateValue('DM_GPWEBPAY_PUBLIC_KEY', $publicKeyFromFile, false);
        } elseif (Tools::getValue('DM_GPWEBPAY_PUBLIC_KEY') !== '') {
            Configuration::updateValue('DM_GPWEBPAY_PUBLIC_KEY', Tools::getValue('DM_GPWEBPAY_PUBLIC_KEY'), false);
        }

        $this->html .= $this->displayConfirmation($this->l('Settings updated.'));
    }

    private function readUploadedPemFile($fieldName)
    {
        if (!isset($_FILES[$fieldName]) || empty($_FILES[$fieldName]['tmp_name'])) {
            return null;
        }

        $file = $_FILES[$fieldName];
        if (!empty($file['error'])) {
            $this->html .= $this->displayError($this->l('Key file upload failed.'));
            return null;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $this->html .= $this->displayError($this->l('Invalid uploaded key file.'));
            return null;
        }

        if ((int) $file['size'] <= 0 || (int) $file['size'] > 524288) {
            $this->html .= $this->displayError($this->l('Key file is empty or too large.'));
            return null;
        }

        $content = Tools::file_get_contents($file['tmp_name']);
        if ($content === false) {
            $this->html .= $this->displayError($this->l('Unable to read uploaded key file.'));
            return null;
        }

        $content = str_replace(array("\r\n", "\r"), "\n", trim($content));
        if ($content === '') {
            $this->html .= $this->displayError($this->l('Uploaded key file is empty.'));
            return null;
        }

        return $content;
    }


    private function reconcileRefundedAmountFromGateway(array $payment)
    {
        $localRefunded = isset($payment['refunded_amount']) ? (int)$payment['refunded_amount'] : 0;
        if (!in_array($payment['status'], array('partial_refunded','refunded'), true)) {
            return $payment;
        }
        if ($localRefunded > 0) {
            return $payment;
        }

        try {
            $client = new GpwebpayClient($this);
            $detail = $client->getPaymentDetail($payment['order_number']);
            if (!$client->isWsSuccessful($detail) || !isset($detail['parsed']) || !is_array($detail['parsed'])) {
                return $payment;
            }

            $parsed = $detail['parsed'];
            $original = (int)$payment['amount'];
            $captureAmount = isset($parsed['captureAmount']) && $parsed['captureAmount'] !== '' ? (int)$parsed['captureAmount'] : $original;
            $refundAmount = isset($parsed['refundAmount']) && $parsed['refundAmount'] !== '' ? (int)$parsed['refundAmount'] : 0;
            $inferredRefunded = max(0, min($original, ($original - $captureAmount) + $refundAmount));

            if ($inferredRefunded > $localRefunded) {
                $payment['refunded_amount'] = $inferredRefunded;
                GpwebpayRepository::upsertPayment(array(
                    'order_number'=>$payment['order_number'],
                    'id_shop'=>(int)$payment['id_shop'],
                    'id_cart'=>(int)$payment['id_cart'],
                    'id_order'=>(int)$payment['id_order'],
                    'amount'=>$original,
                    'refunded_amount'=>$inferredRefunded,
                    'currency'=>pSQL($payment['currency']),
                    'status'=>pSQL($payment['status']),
                ));
            }
        } catch (Exception $e) {
            // Do not break the order view because of a reconciliation helper.
        }

        return $payment;
    }

    private function postProcessRefund()
    {
        GpwebpayRepository::ensureSchema();
        $idOrder = (int)Tools::getValue('id_order');
        $amount = (float)str_replace(',', '.', Tools::getValue('refund_amount'));
        $payment = GpwebpayRepository::getByOrderId($idOrder);
        if (!$payment || $amount <= 0) { $this->html .= $this->displayError($this->l('Invalid refund request.')); return; }
        try {
            $order = new Order($idOrder);
            if (!Validate::isLoadedObject($order)) { $this->html .= $this->displayError($this->l('Invalid refund request.')); return; }
            $payment = $this->reconcileRefundedAmountFromGateway($payment);
            if (!in_array($payment['status'], array('paid','partial_refunded','refunded'))) {
                $this->html .= $this->displayError($this->l('Refund is allowed only for a paid GP webpay payment.'));
                return;
            }

            $currency = new Currency($order->id_currency);
            $minor = $this->amountToMinor($amount, $currency);
            $alreadyRefundedMinor = isset($payment['refunded_amount']) ? (int)$payment['refunded_amount'] : 0;
            $availableRefundMinor = max(0, (int)$payment['amount'] - $alreadyRefundedMinor);
            if ($minor <= 0 || $minor > $availableRefundMinor) {
                $this->html .= $this->displayError(sprintf($this->l('Invalid refund amount. Remaining refundable amount is %s.'), $this->minorToDisplay($availableRefundMinor, $currency)));
                return;
            }

            $client = new GpwebpayClient($this);
            $result = $client->refundSmart($payment['order_number'], $minor, (int)$payment['amount']);

            if (!$client->isWsSuccessful($result)) {
                $message = $client->getWsErrorMessage($result);
                GpwebpayRepository::log('error', 'Refund WS request failed', array('error'=>$message, 'operation'=>isset($result['operation']) ? $result['operation'] : null, 'result'=>$result), $idOrder, $payment['order_number']);
                $this->html .= $this->displayError($this->l('Refund was not accepted by GP webpay.').' '.$message);
                return;
            }

            $newRefundedMinor = min((int)$payment['amount'], $alreadyRefundedMinor + $minor);
            $isFullRefund = ($newRefundedMinor >= (int)$payment['amount']);
            $newStatus = $isFullRefund ? 'refunded' : 'partial_refunded';
            if ($isFullRefund) {
                $this->setOrderState($order, (int)Configuration::get('DM_GPWEBPAY_OS_REFUNDED'));
            }

            GpwebpayRepository::upsertPayment(array(
                'order_number'=>$payment['order_number'],
                'id_shop'=>(int)$this->context->shop->id,
                'id_cart'=>(int)$payment['id_cart'],
                'id_order'=>$idOrder,
                'amount'=>(int)$payment['amount'],
                'refunded_amount'=>$newRefundedMinor,
                'currency'=>pSQL($payment['currency']),
                'status'=>$newStatus,
                'raw_response'=>$result['response']
            ));
            GpwebpayRepository::log('info', 'Refund WS request accepted', array('amount_minor'=>$minor, 'refunded_total_minor'=>$newRefundedMinor, 'remaining_refundable_minor'=>max(0, (int)$payment['amount'] - $newRefundedMinor), 'operation'=>isset($result['operation']) ? $result['operation'] : null, 'result'=>$result), $idOrder, $payment['order_number']);
            $this->html .= $this->displayConfirmation($this->l('Refund request was accepted by GP webpay. Check the GP webpay portal for settlement status.'));
        } catch (Exception $e) { GpwebpayRepository::log('error', $e->getMessage(), array(), $idOrder, $payment ? $payment['order_number'] : null); $this->html .= $this->displayError($e->getMessage()); }
    }

    private function ensureRequiredHooks()
    {
        if (!$this->isRegisteredInHook('displayHeader')) {
            $this->registerHook('displayHeader');
        }
        if (!$this->isRegisteredInHook('paymentOptions')) {
            $this->registerHook('paymentOptions');
        }
    }

    private function renderForm()
    {
        $orderStates = OrderState::getOrderStates((int)$this->context->language->id);
        $stateOptions = array(); foreach ($orderStates as $s) { $stateOptions[] = array('id'=>(int)$s['id_order_state'],'name'=>$s['name']); }
        $fieldsForm = array('form'=>array('legend'=>array('title'=>$this->l('GP webpay / ČSOB settings'),'icon'=>'icon-credit-card'),'input'=>array(
            array('type'=>'switch','label'=>$this->l('Test mode'),'name'=>'DM_GPWEBPAY_TEST_MODE','values'=>array(array('id'=>'on','value'=>1,'label'=>$this->l('Yes')),array('id'=>'off','value'=>0,'label'=>$this->l('No')))),
            array('type'=>'text','label'=>$this->l('Merchant number'),'name'=>'DM_GPWEBPAY_MERCHANT_NUMBER','required'=>true),
            array('type'=>'text','label'=>$this->l('Provider code'),'name'=>'DM_GPWEBPAY_PROVIDER','desc'=>$this->l('Provider code for WS operations. ČSOB CZ is usually 0300, ČSOB SK is usually 7500. Verify the exact provider with the bank.')),
            array('type'=>'textarea','label'=>$this->l('Private key PEM'),'name'=>'DM_GPWEBPAY_PRIVATE_KEY','rows'=>8,'cols'=>80,'desc'=>$this->l('Paste gpwebpay-pvk.key content or upload the key file below. Leave empty to keep current value.')),
            array('type'=>'file','label'=>$this->l('Upload private key PEM file'),'name'=>'DM_GPWEBPAY_PRIVATE_KEY_FILE','desc'=>$this->l('Upload the GP webpay private key file downloaded from the bank or test package. If a file is uploaded, it has priority over the text field.')),
            array('type'=>'password','label'=>$this->l('Private key password'),'name'=>'DM_GPWEBPAY_PRIVATE_KEY_PASSWORD','desc'=>$this->l('Leave empty to keep the current saved password. Enter it again only when changing or restoring the private key.')),
            array('type'=>'textarea','label'=>$this->l('GP webpay public key PEM'),'name'=>'DM_GPWEBPAY_PUBLIC_KEY','rows'=>6,'cols'=>80,'desc'=>$this->l('Used to verify DIGEST/DIGEST1 in return responses. Paste the key or upload the public key file below. Leave empty to keep current value.')),
            array('type'=>'file','label'=>$this->l('Upload GP webpay public key PEM file'),'name'=>'DM_GPWEBPAY_PUBLIC_KEY_FILE','desc'=>$this->l('Upload the GP webpay public key file. If a file is uploaded, it has priority over the text field.')),
            array('type'=>'switch','label'=>$this->l('Immediate capture / deposit'),'name'=>'DM_GPWEBPAY_DEPOSIT_FLAG','values'=>array(array('id'=>'dfon','value'=>1,'label'=>$this->l('Yes')),array('id'=>'dfoff','value'=>0,'label'=>$this->l('No')))),
            array('type'=>'text','label'=>$this->l('Allowed currencies'),'name'=>'DM_GPWEBPAY_ALLOWED_CURRENCIES','desc'=>$this->l('Comma separated ISO codes. Empty = all currencies. Example: EUR,CZK')),
            array('type'=>'text','label'=>$this->l('Allowed carriers'),'name'=>'DM_GPWEBPAY_ALLOWED_CARRIERS','desc'=>$this->l('Comma separated carrier IDs. Empty = all carriers.')),
            array('type'=>'text','label'=>$this->l('Test IP whitelist'),'name'=>'DM_GPWEBPAY_TEST_IPS','desc'=>$this->l('Comma separated IPs. In test mode, empty = visible to everyone.')),
            array('type'=>'text','label'=>$this->l('Allowed GP webpay methods'),'name'=>'DM_GPWEBPAY_PAYMETHODS','desc'=>$this->l('Example: CRD,APAY,GPAY. Empty = gateway default.')),
            array('type'=>'select','label'=>$this->l('Pending order state'),'name'=>'DM_GPWEBPAY_OS_PENDING','options'=>array('query'=>$stateOptions,'id'=>'id','name'=>'name')),
            array('type'=>'select','label'=>$this->l('Paid order state'),'name'=>'DM_GPWEBPAY_OS_PAID','options'=>array('query'=>$stateOptions,'id'=>'id','name'=>'name')),
            array('type'=>'select','label'=>$this->l('Failed order state'),'name'=>'DM_GPWEBPAY_OS_FAILED','options'=>array('query'=>$stateOptions,'id'=>'id','name'=>'name')),
            array('type'=>'select','label'=>$this->l('Authorized order state'),'name'=>'DM_GPWEBPAY_OS_AUTHORIZED','options'=>array('query'=>$stateOptions,'id'=>'id','name'=>'name')),
            array('type'=>'select','label'=>$this->l('Refunded order state'),'name'=>'DM_GPWEBPAY_OS_REFUNDED','options'=>array('query'=>$stateOptions,'id'=>'id','name'=>'name')),
        ),'submit'=>array('title'=>$this->l('Save'))));
        $helper = new HelperForm(); $helper->show_toolbar = false; $helper->table = $this->table; $helper->module = $this; $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT'); $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0; $helper->identifier = $this->identifier; $helper->submit_action = 'submitGpwebpayConfig'; $helper->enctype = 'multipart/form-data'; $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name; $helper->token = Tools::getAdminTokenLite('AdminModules');
        foreach (array('DM_GPWEBPAY_TEST_MODE','DM_GPWEBPAY_MERCHANT_NUMBER','DM_GPWEBPAY_PROVIDER','DM_GPWEBPAY_ALLOWED_CURRENCIES','DM_GPWEBPAY_ALLOWED_CARRIERS','DM_GPWEBPAY_TEST_IPS','DM_GPWEBPAY_PAYMETHODS','DM_GPWEBPAY_DEPOSIT_FLAG','DM_GPWEBPAY_OS_PENDING','DM_GPWEBPAY_OS_PAID','DM_GPWEBPAY_OS_FAILED','DM_GPWEBPAY_OS_AUTHORIZED','DM_GPWEBPAY_OS_REFUNDED') as $k) { $helper->fields_value[$k] = Configuration::get($k); }
        $helper->fields_value['DM_GPWEBPAY_PRIVATE_KEY_PASSWORD'] = '';
        $helper->fields_value['DM_GPWEBPAY_PRIVATE_KEY'] = ''; $helper->fields_value['DM_GPWEBPAY_PUBLIC_KEY'] = '';
        return $helper->generateForm(array($fieldsForm));
    }

    private function renderInfo()
    {
        $cron = $this->context->link->getModuleLink($this->name, 'cron', array('token'=>Configuration::get('DM_GPWEBPAY_CRON_TOKEN')), true);
        return '<div class="panel"><h3>'.$this->l('Cron URL').'</h3><p><code>'.htmlspecialchars($cron, ENT_QUOTES, 'UTF-8').'</code></p><p>'.$this->l('Run every 5-15 minutes to reconcile unfinished payments via WS getPaymentStatus.').'</p></div>';
    }

    public function checkCurrency($cart)
    {
        $allowed = trim((string)Configuration::get('DM_GPWEBPAY_ALLOWED_CURRENCIES'));
        if ($allowed === '') { return true; }
        $currency = new Currency((int)$cart->id_currency);
        return in_array(strtoupper($currency->iso_code), array_map('trim', array_map('strtoupper', explode(',', $allowed))));
    }
    public function checkCarrier($cart)
    {
        $allowed = trim((string)Configuration::get('DM_GPWEBPAY_ALLOWED_CARRIERS'));
        if ($allowed === '') { return true; }
        return in_array((string)$cart->id_carrier, array_map('trim', explode(',', $allowed)));
    }
    private function checkTestIp()
    {
        if (!(int)Configuration::get('DM_GPWEBPAY_TEST_MODE')) { return true; }
        $ips = trim((string)Configuration::get('DM_GPWEBPAY_TEST_IPS'));
        if ($ips === '') { return true; }
        return in_array(Tools::getRemoteAddr(), array_map('trim', explode(',', $ips)));
    }

    public function amountToMinor($amount, Currency $currency)
    {
        $zero = array('JPY','HUF');
        return in_array(strtoupper($currency->iso_code), $zero) ? (int)round($amount) : (int)round($amount * 100);
    }
    public function minorToDisplay($minor, Currency $currency)
    {
        $zero = array('JPY','HUF');
        $amount = in_array(strtoupper($currency->iso_code), $zero) ? (float)$minor : ((float)$minor / 100);
        return Tools::displayPrice($amount, $currency);
    }

    public function createOrderNumber($idCart)
    {
        return substr((string)time().str_pad((string)$idCart, 5, '0', STR_PAD_LEFT), 0, 15);
    }
    public function buildAddInfo(Cart $cart, Order $order)
    {
        $customer = new Customer((int) $cart->id_customer);
        $address = new Address((int) $cart->id_address_invoice);
        $country = new Country((int) $address->id_country);

        $name = $this->limitText(trim($address->firstname.' '.$address->lastname), 45);
        $street = $this->limitText(trim($address->address1.' '.$address->address2), 50);
        $city = $this->limitText((string) $address->city, 50);
        $postcode = $this->limitText((string) $address->postcode, 16);
        $countryIso = strtoupper((string) $country->iso_code);

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><additionalInfoRequest xmlns="http://gpe.cz/gpwebpay/additionalInfo/request" version="5.0"></additionalInfoRequest>');
        $cardholderInfo = $xml->addChild('cardholderInfo');
        $cardholderDetails = $cardholderInfo->addChild('cardholderDetails');

        if ($name !== '') {
            $cardholderDetails->addChild('name', htmlspecialchars($name, ENT_XML1, 'UTF-8'));
        }
        if (Validate::isEmail($customer->email)) {
            $cardholderDetails->addChild('email', htmlspecialchars($customer->email, ENT_XML1, 'UTF-8'));
        }

        if ($name !== '' || $street !== '' || $city !== '' || $postcode !== '' || $countryIso !== '') {
            $billing = $cardholderInfo->addChild('billingDetails');
            if ($name !== '') { $billing->addChild('name', htmlspecialchars($name, ENT_XML1, 'UTF-8')); }
            if ($street !== '') { $billing->addChild('address1', htmlspecialchars($street, ENT_XML1, 'UTF-8')); }
            if ($city !== '') { $billing->addChild('city', htmlspecialchars($city, ENT_XML1, 'UTF-8')); }
            if ($postcode !== '') { $billing->addChild('postalCode', htmlspecialchars($postcode, ENT_XML1, 'UTF-8')); }
            if ($countryIso !== '') { $billing->addChild('country', htmlspecialchars($countryIso, ENT_XML1, 'UTF-8')); }
        }

        $xmlString = $xml->asXML();
        if ($xmlString === false) { return ''; }
        return preg_replace('/[\r\n\t]+/', '', $xmlString);
    }

    private function limitText($value, $length)
    {
        $value = trim((string) $value);
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, (int) $length, 'UTF-8');
        }
        return Tools::substr($value, 0, (int) $length);
    }


    public function setOrderState(Order $order, $idState)
    {
        if ((int)$order->current_state === (int)$idState || !(int)$idState) { return; }
        $history = new OrderHistory(); $history->id_order = (int)$order->id; $history->changeIdOrderState((int)$idState, (int)$order->id); $history->addWithemail(true);
    }
}
