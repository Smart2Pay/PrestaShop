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
<div class="tab-pane" id="s2p-payment-details">
    <h4 class="visible-print">{l s='Payment Details' mod='smart2pay'}</h4>
    <div class="table-responsive">
    <table class="table s2p-payment-details row-margin-bottom">
    <tbody>
        <tr>
            <td><strong>{l s='Environment' mod='smart2pay'}</strong></td>
            <td>{$transaction_arr.environment}</td>
        </tr>
        <tr>
            <td><strong>{l s='Payment Method' mod='smart2pay'}</strong></td>
            <td>{$method_details.display_name}</td>
        </tr>
        {if $transaction_arr.surcharge_amount != 0 || $transaction_arr.surcharge_percent != 0}
        <tr>
            <td><strong>{l s='Method Surcharge' mod='smart2pay'}</strong></td>
            <td>
                {if $transaction_arr.surcharge_amount != 0}
                    {if !empty( $surcharge_currency_id )}
                        {displayPrice price=$transaction_arr.surcharge_amount currency=$surcharge_currency_id}
                    {else}
                        {$transaction_arr.surcharge_amount} {$surcharge_currency_iso}
                    {/if}
                    {if $surcharge_currency_id != $order_currency_id && $surcharge_currency_iso != $order_currency_iso}
                        ({if !empty( $order_currency_id )}{displayPrice price=$transaction_arr.surcharge_order_amount currency=$order_currency_id}{else}{$transaction_arr.surcharge_order_amount} {$order_currency_iso}{/if})
                    {/if}
                {/if}

                {if $transaction_arr.surcharge_amount != 0 && $transaction_arr.surcharge_percent != 0}
                    +
                {/if}

                {if $transaction_arr.surcharge_percent != 0}
                    {$transaction_arr.surcharge_percent}%
                    ({if !empty( $order_currency_id )}{displayPrice price=$transaction_arr.surcharge_order_percent currency=$order_currency_id}{else}{$transaction_arr.surcharge_order_percent} {$order_currency_iso}{/if})
                {/if}

                {if $transaction_arr.surcharge_order_amount != 0 && $transaction_arr.surcharge_order_percent != 0 }
                    =
                    {if !empty( $order_currency_id )}
                        {displayPrice price=$transaction_arr.surcharge_order_amount+$transaction_arr.surcharge_order_percent currency=$order_currency_id}
                    {else}
                        {$transaction_arr.surcharge_order_amount+$transaction_arr.surcharge_order_percent} {$order_currency_iso}
                    {/if}
                {/if}
            </td>
        </tr>
        {/if}
        <tr>
            <td><strong>{l s='Payment ID' mod='smart2pay'}</strong></td>
            <td>{$transaction_arr.payment_id}</td>
        </tr>

        {if false}
            <!--
                {l s='Account Holder' mod='smart2pay'}
                {l s='Bank Name' mod='smart2pay'}
                {l s='Account Number' mod='smart2pay'}
                {l s='IBAN' mod='smart2pay'}
                {l s='SWIFT / BIC' mod='smart2pay'}
                {l s='Account Currency' mod='smart2pay'}
                {l s='Entity Number' mod='smart2pay'}
                {l s='Reference Number' mod='smart2pay'}
                {l s='Amount To Pay' mod='smart2pay'}
                -->
        {/if}

        {foreach from=$transaction_extra_titles key=key item=val name=transtitles}
            {if empty( $transaction_extra_data[$key] )}
                {continue}
            {/if}
        <tr>
            <td><strong>{l s=$val mod='smart2pay'}</strong></td>
            <td>{$transaction_extra_data[$key]}</td>
        </tr>
        {/foreach}
    </tbody>
    </table>
    </div>
</div>
