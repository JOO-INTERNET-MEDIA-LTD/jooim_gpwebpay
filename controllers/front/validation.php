<?php
class Jooim_GpwebpayValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public function postProcess()
    {
        $params = $this->getGatewayParams();
        if (!isset($params['ORDERNUMBER'])) { Tools::redirect('index.php'); }
        $orderNumber = (string)$params['ORDERNUMBER'];
        $payment = GpwebpayRepository::getByOrderNumber($orderNumber);
        if (!$payment) { GpwebpayRepository::log('error','Unknown GP webpay order number',$params,null,$orderNumber); Tools::redirect('index.php?controller=order'); }
        if ((int)$payment['id_shop'] !== (int)$this->context->shop->id) {
            http_response_code(400); exit('Invalid payment response.');
        }
        $order = new Order((int)$payment['id_order']);
        $client = new GpwebpayClient($this->module);
        $verification = $client->verifyHttpResponseDetailed($params, (string)Configuration::get('DM_GPWEBPAY_MERCHANT_NUMBER'));
        $verified = (bool)$verification['verified'];
        GpwebpayRepository::log($verified ? 'info':'error', $verified ? 'Return signature verified':'Return signature failed', array('params'=>$params, 'verification'=>$verification), (int)$order->id, $orderNumber);
        if (!$verified) {
            // Untrusted callbacks must never change an order or expose its confirmation key.
            http_response_code(400); exit('Invalid payment response.');
        } elseif ((string)$params['PRCODE'] === '0' && (string)$params['SRCODE'] === '0') {
            $status = ((int)Configuration::get('DM_GPWEBPAY_DEPOSIT_FLAG') === 1) ? 'paid' : 'authorized';
            $this->module->setOrderState($order, (int)Configuration::get($status === 'paid' ? 'DM_GPWEBPAY_OS_PAID' : 'DM_GPWEBPAY_OS_AUTHORIZED'));
        } else {
            $status = 'failed';
            $this->module->setOrderState($order, (int)Configuration::get('DM_GPWEBPAY_OS_FAILED'));
        }
        GpwebpayRepository::upsertPayment(array('id_shop'=>(int)$this->context->shop->id,'id_cart'=>(int)$payment['id_cart'],'id_order'=>(int)$order->id,'order_number'=>$orderNumber,'amount'=>(int)$payment['amount'],'currency'=>$payment['currency'],'status'=>$status,'prcode'=>isset($params['PRCODE'])?$params['PRCODE']:null,'srcode'=>isset($params['SRCODE'])?$params['SRCODE']:null,'result_text'=>isset($params['RESULTTEXT'])?$params['RESULTTEXT']:null,'raw_response'=>json_encode($params, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)));
        Tools::redirect('index.php?controller=order-confirmation&id_cart='.(int)$order->id_cart.'&id_module='.(int)$this->module->id.'&id_order='.(int)$order->id.'&key='.$order->secure_key);
    }
    private function getGatewayParams()
    {
        $params = array();

        if (!empty($_SERVER['QUERY_STRING'])) {
            parse_str((string)$_SERVER['QUERY_STRING'], $params);
        }

        if (!empty($_POST)) {
            $params = array_merge($params, $_POST);
        }

        if (empty($params)) {
            $params = Tools::getAllValues();
        }

        foreach (array('DIGEST', 'DIGEST1') as $field) {
            if (isset($params[$field])) {
                $params[$field] = str_replace(' ', '+', (string)$params[$field]);
            }
        }

        return $params;
    }

}
