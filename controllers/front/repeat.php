<?php
class Jooim_GpwebpayRepeatModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public function initContent()
    {
        $idOrder = (int)Tools::getValue('id_order'); $key = (string)Tools::getValue('key');
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || $order->secure_key !== $key) { Tools::redirect('index.php?controller=history'); }
        $oldCart = new Cart((int)$order->id_cart);
        $dup = $oldCart->duplicate();
        if (!isset($dup['cart']) || !Validate::isLoadedObject($dup['cart'])) { Tools::redirect('index.php?controller=history'); }
        $this->context->cart = $dup['cart'];
        $this->context->cookie->id_cart = (int)$dup['cart']->id;
        $this->context->cookie->write();
        Tools::redirect($this->context->link->getModuleLink($this->module->name, 'payment', array(), true));
    }
}
