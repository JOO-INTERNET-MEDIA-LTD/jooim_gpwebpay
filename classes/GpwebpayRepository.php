<?php
if (!defined('_PS_VERSION_')) { exit; }
class GpwebpayRepository
{
    public static function install()
    {
        $sql = array();
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dm_gpwebpay_payment` (
          `id_dm_gpwebpay_payment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_shop` INT UNSIGNED NOT NULL,
          `id_cart` INT UNSIGNED NOT NULL,
          `id_order` INT UNSIGNED DEFAULT NULL,
          `order_number` VARCHAR(15) NOT NULL,
          `amount` INT UNSIGNED NOT NULL,
          `refunded_amount` INT UNSIGNED NOT NULL DEFAULT 0,
          `currency` VARCHAR(3) NOT NULL,
          `status` VARCHAR(32) NOT NULL DEFAULT "created",
          `prcode` VARCHAR(16) DEFAULT NULL,
          `srcode` VARCHAR(16) DEFAULT NULL,
          `result_text` VARCHAR(255) DEFAULT NULL,
          `raw_request` MEDIUMTEXT NULL,
          `raw_response` MEDIUMTEXT NULL,
          `date_add` DATETIME NOT NULL,
          `date_upd` DATETIME NOT NULL,
          PRIMARY KEY (`id_dm_gpwebpay_payment`),
          KEY `idx_order_number` (`order_number`),
          KEY `idx_id_order` (`id_order`),
          KEY `idx_id_cart` (`id_cart`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4';
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'dm_gpwebpay_log` (
          `id_dm_gpwebpay_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `id_shop` INT UNSIGNED NOT NULL,
          `id_order` INT UNSIGNED DEFAULT NULL,
          `order_number` VARCHAR(15) DEFAULT NULL,
          `level` VARCHAR(16) NOT NULL,
          `message` VARCHAR(255) NOT NULL,
          `context` MEDIUMTEXT NULL,
          `date_add` DATETIME NOT NULL,
          PRIMARY KEY (`id_dm_gpwebpay_log`), KEY `idx_order_number` (`order_number`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4';
        foreach ($sql as $q) { if (!Db::getInstance()->execute($q)) { return false; } }
        return self::ensureSchema();
    }

    public static function ensureSchema()
    {
        $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.'dm_gpwebpay_payment` LIKE "refunded_amount"');
        if (!$columns) {
            return Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'dm_gpwebpay_payment` ADD `refunded_amount` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `amount`');
        }
        return true;
    }
    public static function uninstall()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'dm_gpwebpay_payment`') && Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'dm_gpwebpay_log`');
    }
    public static function upsertPayment(array $data)
    {
        $existing = self::getByOrderNumber($data['order_number']);
        $data['date_upd'] = date('Y-m-d H:i:s');
        if ($existing) {
            return Db::getInstance()->update('dm_gpwebpay_payment', $data, 'id_dm_gpwebpay_payment='.(int)$existing['id_dm_gpwebpay_payment']);
        }
        $data['date_add'] = date('Y-m-d H:i:s');
        return Db::getInstance()->insert('dm_gpwebpay_payment', $data);
    }
    public static function getByOrderNumber($orderNumber)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'dm_gpwebpay_payment` WHERE order_number="'.pSQL($orderNumber).'"');
    }
    public static function getByOrderId($idOrder)
    {
        return Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'dm_gpwebpay_payment` WHERE id_order='.(int)$idOrder.' ORDER BY id_dm_gpwebpay_payment DESC');
    }
    public static function log($level, $message, array $context=array(), $idOrder=null, $orderNumber=null)
    {
        return Db::getInstance()->insert('dm_gpwebpay_log', array(
            'id_shop'=>(int)Context::getContext()->shop->id,
            'id_order'=>$idOrder ? (int)$idOrder : null,
            'order_number'=>$orderNumber ? pSQL($orderNumber) : null,
            'level'=>pSQL($level), 'message'=>pSQL($message),
            'context'=>pSQL(json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
            'date_add'=>date('Y-m-d H:i:s')
        ));
    }
    public static function getLogs($idOrder)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'dm_gpwebpay_log` WHERE id_order='.(int)$idOrder.' ORDER BY id_dm_gpwebpay_log DESC LIMIT 50');
    }
    public static function getPending($limit=20)
    {
        return Db::getInstance()->executeS('SELECT * FROM `'._DB_PREFIX_.'dm_gpwebpay_payment` WHERE status IN ("created","pending") ORDER BY id_dm_gpwebpay_payment ASC LIMIT '.(int)$limit);
    }
}
