<?php
class Jooim_GpwebpayCronModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public function initContent()
    {
        header('Content-Type: application/json');
        if (Tools::getValue('token') !== Configuration::get('DM_GPWEBPAY_CRON_TOKEN')) { http_response_code(403); die(json_encode(array('error'=>'forbidden'))); }
        $client = new GpwebpayClient($this->module); $checked = 0; $updated = 0; $errors = array();
        foreach (GpwebpayRepository::getPending(20) as $payment) {
            try {
                $r = $client->getPaymentStatus($payment['order_number']); $checked++;
                GpwebpayRepository::log('info','Cron status check',$r,(int)$payment['id_order'],$payment['order_number']);
                if (isset($r['parsed']['state'])) {
                    $state = (int)$r['parsed']['state']; $order = new Order((int)$payment['id_order']);
                    if (in_array($state, array(7,8,9))) { $this->module->setOrderState($order, (int)Configuration::get('DM_GPWEBPAY_OS_PAID')); $status = 'paid'; }
                    elseif (in_array($state, array(5,10))) { $this->module->setOrderState($order, (int)Configuration::get('DM_GPWEBPAY_OS_FAILED')); $status = 'failed'; }
                    else { $status = 'pending'; }
                    GpwebpayRepository::upsertPayment(array('id_shop'=>(int)$payment['id_shop'],'id_cart'=>(int)$payment['id_cart'],'id_order'=>(int)$payment['id_order'],'order_number'=>$payment['order_number'],'amount'=>(int)$payment['amount'],'currency'=>$payment['currency'],'status'=>$status,'raw_response'=>$r['response'])); $updated++;
                }
            } catch (Exception $e) { $errors[] = $e->getMessage(); GpwebpayRepository::log('error',$e->getMessage(),array(),(int)$payment['id_order'],$payment['order_number']); }
        }
        die(json_encode(array('checked'=>$checked,'updated'=>$updated,'errors'=>$errors)));
    }
}
