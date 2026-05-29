{extends file='page.tpl'}
{block name='page_content'}
<div class="gpwebpay-redirect text-center">
  <img src="{$module_dir|escape:'html':'UTF-8'}views/img/logo.png" alt="GP webpay" style="max-height:45px;margin-bottom:20px;">
  <h2>{l s='Redirecting to payment gateway...' mod='jooim_gpwebpay'}</h2>
  <p>{l s='Please wait. Do not close this page.' mod='jooim_gpwebpay'}</p>
  <form id="gpwebpay-form" action="{$endpoint|escape:'html':'UTF-8'}" method="post">
    {foreach from=$params key=name item=value}
      <input type="hidden" name="{$name|escape:'html':'UTF-8'}" value="{$value|escape:'html':'UTF-8'}">
    {/foreach}
    <button type="submit" class="btn btn-primary">{l s='Continue to payment' mod='jooim_gpwebpay'}</button>
  </form>
</div>
<script>document.getElementById('gpwebpay-form').submit();</script>
{/block}
