{if isset($gpwebpay_payment_status) && $gpwebpay_payment_status}
  <section class="gpwebpay-return alert {$gpwebpay_payment_status.alert_class|escape:'html':'UTF-8'}" role="status">
    <p class="gpwebpay-return__status">
      <strong>{l s='Payment status:' mod='jooim_gpwebpay'}</strong>
      {$gpwebpay_payment_status.label|escape:'html':'UTF-8'}
    </p>
    <p class="gpwebpay-return__message">{$gpwebpay_payment_status.message|escape:'html':'UTF-8'}</p>
  </section>
{/if}
