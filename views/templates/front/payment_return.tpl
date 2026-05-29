{if $gpwebpay_payment}
  <section class="gpwebpay-return">
    <p><strong>{l s='Payment status:' mod='jooim_gpwebpay'}</strong> {$gpwebpay_payment.status|escape:'html':'UTF-8'}</p>
    {if $gpwebpay_payment.result_text}<p>{$gpwebpay_payment.result_text|escape:'html':'UTF-8'}</p>{/if}
  </section>
{/if}
