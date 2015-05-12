{*
* 2015 Smart2Pay
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this plugin
* in the future.
*
*  @author Smart2Pay
*  @copyright  2015 Smart2Pay
*}
{if $smarty.const._PS_VERSION_ >= 1.6}
<div class="tab-pane" id="s2p-payment-logs">
    <h4 class="visible-print">{l s='Payment Logs' mod='smart2pay'}</h4>
    <div class="table-responsive">
{else}
<br/>
<fieldset>
    <legend>{l s='Payment Logs' mod='smart2pay'}</legend>
{/if}
    <table class="table s2p-payment-logs row-margin-bottom" {if $smarty.const._PS_VERSION_ < 1.6}style="width: 100%"{/if}>
    <thead>
    <tr>
        <td style="width:180px; text-align: center;">{l s='Date' mod='smart2pay'}</td>
        <td>{l s='Log' mod='smart2pay'}</td>
    </tr>
    </thead>
    <tbody>
    {if empty( $order_logs )}
        <tr>
            <td colspan="2" style="text-align: center;"><p>{l s='No logs in database for this order.' mod='smart2pay'}</p></td>
        </tr>
    {else}
        {foreach from=$order_logs key=key item=log_item name=s2p_order_logs}
        <tr>
            <td style="white-space: nowrap;">{Tools::displayDate( $log_item.log_created, null, true )}</td>
            <td>[{$log_item.log_type}] {$log_item.log_data}</td>
        </tr>
        {/foreach}
    {/if}
    </tbody>
    </table>
{if $smarty.const._PS_VERSION_ < 1.6}
</fieldset>
{else}
    </div>
</div>
{/if}
