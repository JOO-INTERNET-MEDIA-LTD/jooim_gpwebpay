<?php
if (!defined('_PS_VERSION_')) { exit; }
final class JooimGpwebpayPortfolioRelease
{
    const KEY = 'JOOIM_GPWEBPAY_RELEASE';
    public static function check($context)
    {
        $result = ['checked'=>gmdate('Y-m-d H:i:s'), 'state'=>'unavailable', 'version'=>'', 'installable'=>false];
        if (function_exists('curl_init')) {
            $handle=curl_init('https://api.github.com/repos/JOO-INTERNET-MEDIA-LTD/jooim_gpwebpay/releases/latest');
            $body='';
            curl_setopt_array($handle,[CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'JOOIM-GPwebpay-release-check',
                CURLOPT_WRITEFUNCTION=>static function ($curl,$chunk) use (&$body) {
                    if(strlen($body)+strlen($chunk)>262144){return 0;} $body.=$chunk;return strlen($chunk);
                }]);
            $ok=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_HTTP_CODE);curl_close($handle);
            $data=json_decode($body,true);
            if($ok!==false && $status===404){$result['state']='none';}
            elseif($ok!==false && $status===200 && is_array($data) && empty($data['draft']) && empty($data['prerelease'])
                && is_string($data['tag_name'] ?? null) && preg_match('/^v?(\d+\.\d+\.\d+)$/D',$data['tag_name'] ?? '',$match)) {
                $result['state']='available';$result['version']=$match[1];
                $names=array_column((array)($data['assets'] ?? []),'name');
                foreach($names as $name){
                    if(is_string($name) && preg_match('/^jooim_gpwebpay[-_].+\.zip$/D',$name)
                        && in_array($name.'.sha256',$names,true)){$result['installable']=true;}
                }
            }
        }
        return Configuration::updateValue(self::KEY,json_encode($result),false,(int)$context->shop->id_shop_group,(int)$context->shop->id);
    }
    public static function panel($module,$context)
    {
        $result=json_decode((string)Configuration::get(self::KEY,null,(int)$context->shop->id_shop_group,(int)$context->shop->id),true) ?: [];
        $t=static function($text) use($module){return htmlspecialchars($module->l($text,'portfoliorelease'),ENT_QUOTES,'UTF-8');};
        $e=static function($text){return htmlspecialchars((string)$text,ENT_QUOTES,'UTF-8');};
        $state=$result['state'] ?? 'unchecked';
        $status='No successful update check yet.';
        if($state==='none'){$status='No published release is available.';}
        elseif($state==='unavailable'){$status='Update check unavailable.';}
        elseif(!empty($result['version'])){$status=version_compare($result['version'],$module->version,'>') ? 'A new module version is available.' : 'The module is up to date.';}
        $action=$context->link->getAdminLink('AdminModules',true,[],['configure'=>$module->name]);
        return '<div class="panel jooim-updates"><h3>'.$t('Update check').'</h3><p>'.$t('Current module version').': '.$e($module->version).'</p><p>'.$t('Latest version on GitHub').': '.$e($result['version'] ?? '—').'</p><p>'.$t('Last checked').': '.$e($result['checked'] ?? '—').' UTC</p><p><strong>'.$t($status).'</strong></p>'
            .($state==='available' && empty($result['installable']) ? '<p>'.$t('The release has no installation ZIP and matching SHA256 asset.').'</p>' : '')
            .'<p>'.$t('For PrestaShop installation, do not use GitHub automatic Source code ZIP. Use only the installation ZIP file from the release assets.').'</p>'
            .'<form method="post" action="'.$e($action).'"><input type="hidden" name="token" value="'.$e(Tools::getAdminTokenLite('AdminModules')).'"><button class="btn btn-default" name="checkPortfolioRelease" value="1">'.$t('Check now').'</button></form></div>';
    }
}
