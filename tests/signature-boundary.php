<?php
define('_PS_VERSION_','8.2.0');
require __DIR__.'/../classes/GpwebpayClient.php';
class SignatureProbe extends GpwebpayClient {
    public function __construct() {}
    public function verify(array $values, $signature) { return $signature === 'synthetic-valid-signature'; }
}
$p=new SignatureProbe();$base=['ORDERNUMBER'=>'123','PRCODE'=>'0','SRCODE'=>'0','DIGEST'=>'synthetic-valid-signature','DIGEST1'=>'synthetic-valid-signature'];
$checks=0;function check($ok,$label){global $checks;++$checks;if(!$ok){throw new RuntimeException($label);}}
check($p->verifyHttpResponseDetailed($base,'synthetic-merchant')['verified'],'Both signatures verified');
foreach(['DIGEST','DIGEST1'] as $field){
 foreach([null,'',[], 'synthetic-invalid-signature'] as $bad){$data=$base;if($bad===null){unset($data[$field]);}else{$data[$field]=$bad;}check(!$p->verifyHttpResponseDetailed($data,'synthetic-merchant')['verified'],'Invalid or missing signature denied');}
}
$data=$base;$data['PRCODE']=[];check(!$p->verifyHttpResponseDetailed($data,'synthetic-merchant')['verified'],'Nested payload denied');
echo 'PASS '.$checks." signature boundary checks (synthetic verifier, no cryptographic certification)\n";
