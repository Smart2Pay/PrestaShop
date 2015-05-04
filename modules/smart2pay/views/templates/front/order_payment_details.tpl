<div class="adresses_bloc">
    <div class="row">
        <div class="col-xs-12 col-sm-12">
            <ul class="address alternate_item box">
                <li><h3 class="page-subheading">{l s='Payment Details' mod='smart2pay'}</h3></li>

                <li><strong class="dark">{l s='Payment Method' mod='smart2pay'}</strong> {$method_details.display_name}</li>

                {if $transaction_arr.surcharge_amount != 0 || $transaction_arr.surcharge_percent != 0}
                <li>
                    <strong class="dark">{l s='Method Surcharge' mod='smart2pay'}</strong>
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
                </li>
                {/if}

                <li><strong class="dark">{l s='Payment ID' mod='smart2pay'}</strong> {$transaction_arr.payment_id}</li>

                {if false}
                <!--
                Hack to put texts in translation interface
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
                    <li>
                        <strong class="dark">{l s=$val mod='smart2pay'}</strong>
                        {$transaction_extra_data[$key]}
                    </li>
                {/foreach}

            </ul>
        </div>
    </div>
</div>

