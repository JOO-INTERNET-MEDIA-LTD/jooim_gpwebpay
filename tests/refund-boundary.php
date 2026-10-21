<?php
// Invoke the actual refund entry boundary with synthetic infrastructure; no gateway call is possible.
define('_PS_VERSION_', '8.2.0');
class PaymentModule {
 protected $context;
 public $permission=true;
 public function getPermission($name){return $this->permission;}
 public function l($text){return $text;}
 public function displayError($text){return 'ERROR:'.$text;}
}
class Tools {
 public static $input=[];
 public static function getValue($key,$default=false){return self::$input[$key] ?? $default;}
 public static function getAdminTokenLite($name){return 'synthetic-'.$name;}
}
class Db {
 public static $shop=2,$reads=0,$writes=0;
 public static function getInstance(){return new self();}
 public function getRow($sql){self::$reads++;return ['id_shop'=>self::$shop,'id_order'=>1];}
 public function execute($sql){self::$writes++;return true;}
}
define('_DB_PREFIX_', 'synthetic_');
require __DIR__.'/../jooim_gpwebpay.php';
class RefundProbe extends Jooim_Gpwebpay { public function setup($context){$this->context=$context;} }
$m=(new ReflectionClass('RefundProbe'))->newInstanceWithoutConstructor();
$employee=new class{public $edit=true,$shop=true;public function can($a,$b){return $this->edit&&$a==='edit'&&$b==='AdminOrders';}public function hasAuthOnShop($id){return $this->shop;}};
$context=(object)['shop'=>(object)['id'=>1], 'employee'=>$employee];$m->setup($context);
$method=new ReflectionMethod($m,'postProcessRefund');$method->setAccessible(true);
$valid=['token'=>'synthetic-AdminOrders','id_order'=>1,'refund_amount'=>'1'];$count=0;
foreach(['get','csrf','array-token','readonly','module','foreign','unassigned','array-amount','nan-amount','infinite-amount'] as $case){
 Tools::$input=$valid;$_SERVER['REQUEST_METHOD']='POST';$employee->edit=true;$employee->shop=true;$m->permission=true;Db::$shop=1;
 if($case==='get'){$_SERVER['REQUEST_METHOD']='GET';}
 if($case==='csrf'){Tools::$input['token']='invalid';}
 if($case==='array-token'){Tools::$input['token']=[];}
 if($case==='readonly'){$employee->edit=false;}
 if($case==='module'){$m->permission=false;}
 if($case==='foreign'){Db::$shop=2;}
 if($case==='unassigned'){$employee->shop=false;}
 if($case==='array-amount'){Tools::$input['refund_amount']=[];}
 if($case==='nan-amount'){Tools::$input['refund_amount']='not-a-number';}
 if($case==='infinite-amount'){Tools::$input['refund_amount']='1e999';}
 $method->invoke($m);if(Db::$writes!==0){throw new RuntimeException('Unauthorized mutation:'.$case);}++$count;
}
echo 'PASS '.$count." refund denial checks (no network)\n";
