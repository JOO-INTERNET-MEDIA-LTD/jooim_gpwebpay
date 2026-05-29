<?php
class Jooim_GpwebpayPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$cart || !$cart->id || !$cart->id_customer || !$this->module->active) { Tools::redirect('index.php?controller=order&step=1'); }
        if (!$this->module->checkCurrency($cart)) { Tools::redirect('index.php?controller=order&step=3'); }
        $customer = new Customer((int)$cart->id_customer);
        if (!Validate::isLoadedObject($customer)) { Tools::redirect('index.php?controller=order&step=1'); }
        $currency = new Currency((int)$cart->id_currency);
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $pending = (int)Configuration::get('DM_GPWEBPAY_OS_PENDING');
        $this->module->validateOrder((int)$cart->id, $pending, $total, $this->module->displayName, null, array(), (int)$currency->id, false, $customer->secure_key);
        $order = new Order((int)$this->module->currentOrder);
        $orderNumber = $this->module->createOrderNumber((int)$cart->id);
        $amount = $this->module->amountToMinor($total, $currency);
        $client = new GpwebpayClient($this->module);
        $returnUrl = $this->context->link->getModuleLink($this->module->name, 'validation', array(), true);
        $params = array(
            'MERCHANTNUMBER'=>(string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'),
            'OPERATION'=>'CREATE_ORDER',
            'ORDERNUMBER'=>$orderNumber,
            'AMOUNT'=>$amount,
            'CURRENCY'=>$currency->numeric_iso_code ?: $this->isoToNumeric($currency->iso_code),
            'DEPOSITFLAG'=>(int)Configuration::get('DM_GPWEBPAY_DEPOSIT_FLAG'),
            'MERORDERNUM'=>$orderNumber,
            'URL'=>$returnUrl,
            'DESCRIPTION'=>$this->ascii(Tools::substr('Order '.$order->reference, 0, 255)),
            'MD'=>base64_encode((int)$order->id.'|'.$order->secure_key),
            'EMAIL'=>$customer->email,
            'REFERENCENUMBER'=>$this->ascii(Tools::substr($order->reference, 0, 20)),
        );
        $payMethods = trim((string)Configuration::get('DM_GPWEBPAY_PAYMETHODS'));
        if ($payMethods !== '') { $params['PAYMETHODS'] = $payMethods; }
        $digestOrder = array('MERCHANTNUMBER','OPERATION','ORDERNUMBER','AMOUNT','CURRENCY','DEPOSITFLAG','MERORDERNUM','URL','DESCRIPTION','MD','PAYMETHOD','PAYMETHODS','EMAIL','REFERENCENUMBER','PANPATTERN','TOKEN','FASTTOKEN');
        $params['DIGEST'] = $client->createDigest($params, $digestOrder);
        $params['LANG'] = $this->getGatewayLang();
        GpwebpayRepository::upsertPayment(array('id_shop'=>(int)$this->context->shop->id,'id_cart'=>(int)$cart->id,'id_order'=>(int)$order->id,'order_number'=>$orderNumber,'amount'=>$amount,'currency'=>$currency->iso_code,'status'=>'created','raw_request'=>json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
        GpwebpayRepository::log('info','Payment created',array('params'=>$params),(int)$order->id,$orderNumber);
        $this->context->smarty->assign(array('endpoint'=>$client->getHttpEndpoint(),'params'=>$params));
        $this->setTemplate('module:jooim_gpwebpay/views/templates/front/redirect.tpl');
    }
    private function getGatewayLang()
    {
        $iso = isset($this->context->language->iso_code) ? strtolower((string) $this->context->language->iso_code) : 'en';
        $allowed = array('cs','en','de','sk','fr','it','pl','es','hu','ro');
        return in_array($iso, $allowed) ? $iso : 'en';
    }

    private function ascii($value)
    {
        $value = trim((string) $value);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) { $value = $converted; }
        }
        $value = preg_replace('/[^\x20-\x7E]/', '', $value);
        return trim($value);
    }

    private function isoToNumeric($iso)
    {
        $map = array('EUR'=>'978','CZK'=>'203','USD'=>'840','GBP'=>'826','PLN'=>'985','HUF'=>'348','RON'=>'946');
        return isset($map[strtoupper($iso)]) ? $map[strtoupper($iso)] : '';
    }
}
