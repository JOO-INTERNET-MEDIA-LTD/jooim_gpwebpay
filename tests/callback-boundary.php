<?php
$mode=$argv[1] ?? 'invalid';
class ModuleFrontController{public $context,$module;}
class Tools{public static function redirect($url){throw new RuntimeException('redirect');}}
class Configuration{public static function get($key){return 1;}}
class Order{public $id=1,$id_cart=2,$secure_key='synthetic-confirmation';public function __construct($id){$this->id=$id;}}
class GpwebpayRepository{
 public static $writes=0;
 public static function getByOrderNumber($n){global $mode;return ['id_order'=>1,'id_shop'=>$mode==='foreign'?2:1,'id_cart'=>2,'amount'=>100,'currency'=>'EUR'];}
 public static function log(...$args){}public static function upsertPayment($data){self::$writes++;}
}
class GpwebpayClient{public function __construct($m){}public function verifyHttpResponseDetailed(...$args){global $mode;return ['verified'=>$mode==='valid'];}}
require __DIR__.'/../controllers/front/validation.php';
$m=new class{public $id=3,$writes=0;public function setOrderState(...$args){$this->writes++;}};
$c=new Jooim_GpwebpayValidationModuleFrontController();$c->context=(object)['shop'=>(object)['id'=>1]];$c->module=$m;
$_SERVER['QUERY_STRING']='ORDERNUMBER=123&PRCODE=0&SRCODE=0';$_POST=[];
register_shutdown_function(static function()use($m,$mode){
 $expected=$mode==='valid'?1:0;
 if($m->writes!==$expected || GpwebpayRepository::$writes!==$expected){fwrite(STDERR,'Unexpected payment mutation');exit(1);}
 echo "\nPASS ".$mode." callback mutation boundary\n";
});
try{$c->postProcess();}catch(RuntimeException $e){if($mode!=='valid'){throw $e;}}
