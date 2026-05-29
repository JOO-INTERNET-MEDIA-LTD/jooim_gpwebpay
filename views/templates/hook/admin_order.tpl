<div id="jooim-gpwebpay-admin-card" class="jooim-gpwebpay-admin-card" style="background:#fff;border:1px solid #d3d8db;border-radius:4px;margin:15px 0;padding:0;box-shadow:0 1px 1px rgba(0,0,0,.05);clear:both;">
  <div style="border-bottom:1px solid #eee;padding:12px 15px;background:#f8f9fb;">
    <h3 style="margin:0;font-size:16px;line-height:22px;color:#363a41;"><i class="icon-credit-card"></i> GP webpay / ČSOB</h3>
  </div>
  <div style="padding:15px;">
    {if isset($gpwebpay_admin_message) && $gpwebpay_admin_message}
      <div class="jooim-gpwebpay-admin-message" style="margin-bottom:12px;">{$gpwebpay_admin_message nofilter}</div>
    {/if}
    <table class="table" style="background:#fff;margin-bottom:15px;">
      <tr><th style="width:220px;">{l s='Payment number' mod='jooim_gpwebpay'}</th><td>{$gpwebpay_payment.order_number|escape:'html':'UTF-8'}</td></tr>
      <tr><th>{l s='Status' mod='jooim_gpwebpay'}</th><td><span class="badge badge-info" style="font-size:12px;">{$gpwebpay_payment.status|escape:'html':'UTF-8'}</span></td></tr>
      <tr><th>{l s='PRCODE / SRCODE' mod='jooim_gpwebpay'}</th><td>{$gpwebpay_payment.prcode|escape:'html':'UTF-8'} / {$gpwebpay_payment.srcode|escape:'html':'UTF-8'}</td></tr>
      <tr><th>{l s='Already refunded' mod='jooim_gpwebpay'}</th><td>{$gpwebpay_refunded_amount_display|escape:'html':'UTF-8'}</td></tr>
      <tr><th>{l s='Remaining refundable amount' mod='jooim_gpwebpay'}</th><td><strong>{$gpwebpay_available_refund_display|escape:'html':'UTF-8'}</strong></td></tr>
      <tr><th>{l s='Repeat payment URL' mod='jooim_gpwebpay'}</th><td><input class="form-control" readonly value="{$gpwebpay_repeat_url|escape:'html':'UTF-8'}" style="background:#fff;"></td></tr>
    </table>

    <form method="post" action="{$gpwebpay_refund_action|escape:'html':'UTF-8'}" class="form-inline" style="background:#f8f9fb;border:1px solid #eee;border-radius:4px;padding:12px;margin-bottom:18px;">
      <input type="hidden" name="token" value="{$gpwebpay_token|escape:'html':'UTF-8'}">
      <input type="hidden" name="id_order" value="{$gpwebpay_payment.id_order|intval}">
      <div class="form-group" style="margin-right:8px;">
        <label style="margin-right:6px;">{l s='Refund amount' mod='jooim_gpwebpay'}</label>
        <input type="text" name="refund_amount" class="form-control" placeholder="0.00" {if isset($gpwebpay_available_refund_minor) && $gpwebpay_available_refund_minor <= 0}disabled="disabled"{/if}>
      </div>
      <button type="submit" name="submitGpwebpayRefund" class="btn btn-default" {if isset($gpwebpay_available_refund_minor) && $gpwebpay_available_refund_minor <= 0}disabled="disabled"{/if}><i class="icon-undo"></i> {l s='Send refund' mod='jooim_gpwebpay'}</button>
      <p class="help-block" style="display:block;width:100%;margin:8px 0 0;">{l s='The module stores the accepted refund total locally and blocks refund requests above the remaining refundable amount. Refund through GP webpay WS is available only when the gateway allows the operation. For freshly paid transactions before batch settlement, use the GP webpay portal action for payment reversal if refund is not accepted.' mod='jooim_gpwebpay'}</p>
    </form>

    <h4 style="margin:0 0 10px;font-size:14px;color:#363a41;">{l s='Gateway log' mod='jooim_gpwebpay'}</h4>
    <div style="max-height:260px;overflow:auto;background:#fff;border:1px solid #eee;border-radius:4px;">
      <table class="table table-condensed" style="margin:0;background:#fff;">
        <thead><tr><th>{l s='Date' mod='jooim_gpwebpay'}</th><th>{l s='Level' mod='jooim_gpwebpay'}</th><th>{l s='Message' mod='jooim_gpwebpay'}</th></tr></thead>
        <tbody>
          {foreach from=$gpwebpay_logs item=log}<tr><td>{$log.date_add|escape:'html':'UTF-8'}</td><td>{$log.level|escape:'html':'UTF-8'}</td><td>{$log.message|escape:'html':'UTF-8'}</td></tr>{foreachelse}<tr><td colspan="3">{l s='No log records.' mod='jooim_gpwebpay'}</td></tr>{/foreach}
        </tbody>
      </table>
    </div>
  </div>
</div>
