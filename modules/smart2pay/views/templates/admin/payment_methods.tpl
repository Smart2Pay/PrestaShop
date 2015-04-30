<script type="text/javascript">
{literal}
function s2p_config_js_select_all()
{
    form_obj = document.getElementById( 's2p_payment_methods_configuration' );
    if( form_obj && form_obj.elements && form_obj.elements.length )
    {
        for( i = 0; i < form_obj.elements.length; i++ )
        {
            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 'enabled_methods[]' )
            {
                if( !form_obj.elements[i].checked )
                    form_obj.elements[i].click();
            }
        }
    }
}
function s2p_config_js_deselect_all()
{
    form_obj = document.getElementById( 's2p_payment_methods_configuration' );
    if( form_obj && form_obj.elements && form_obj.elements.length )
    {
        for( i = 0; i < form_obj.elements.length; i++ )
        {
            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 'enabled_methods[]' )
            {
                if( form_obj.elements[i].checked )
                    form_obj.elements[i].click();
            }
        }
    }
}
function s2p_config_js_invert()
{
    form_obj = document.getElementById( 's2p_payment_methods_configuration' );
    if( form_obj && form_obj.elements && form_obj.elements.length )
    {
        for( i = 0; i < form_obj.elements.length; i++ )
        {
            if( form_obj.elements[i].type == 'checkbox' && form_obj.elements[i].name == 'enabled_methods[]' )
            {
                form_obj.elements[i].click();
            }
        }
    }
}
{/literal}
</script>

<div class="panel">
    <div class="panel-heading">Payment Methods</div>
    <div class="smart2pay-admin-payment-method-container">
        {if empty( $payment_methods )}
        <div style="text-align: center">No payment methods defined in database.</div>
        {else}
        <small>Surcharge amount is provided in shop's default currency.<br/>
            If you want to prioritize payment methods when displaying them at checkout, use <em>Order</em> column. Lower values will display payment method higher on the page.</small>
        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall'}" id="s2p_payment_methods_configuration" name="s2p_payment_methods_configuration">
        <table class="table">
            <thead>
            <tr>
                <th>Enabled?</th>
                <th colspan="2">Name</th>
                <th>Surcharge</th>
                <th>Priority</th>
            </tr>
            <tr>
                <td colspan="5">
                    <a href="javascript:void(0);" onclick="s2p_config_js_select_all()">Select all</a>
                    |
                    <a href="javascript:void(0);" onclick="s2p_config_js_invert()">Invert</a>
                    |
                    <a href="javascript:void(0);" onclick="s2p_config_js_deselect_all()">Select none</a>
                </td>
            </tr>
            </thead>
            <tbody>
            {foreach $payment_methods as $payment_method}
                <tr>
                    <td style="width: 50px; text-align: center;"><input type="checkbox" name="enabled_methods[]" id="enabled_methods_{$payment_method.method_id}" value="{$payment_method.method_id}" {if !empty( $payment_method_settings[$payment_method.method_id] )} checked="checked" {/if} /></td>
                    <td style="width: 150px; text-align: center;"><img src="{$logos_path}{$payment_method.logo_url}" style="max-width: 150px;" /></td>
                    <td>
                        <strong>{$payment_method.display_name|escape:'htmlall':'UTF-8'}</strong><br/>
                        {$payment_method.description|escape:'htmlall':'UTF-8'}
                    </td>
                    <td>
                        <input type="number" size="8" step="0.01" style="width: 100px; text-align: right;" name="surcharge_percent[{$payment_method.method_id}]" value="{if !empty( $payment_method_settings[$payment_method.method_id] )}{$payment_method_settings[$payment_method.method_id].surcharge_percent}{else}0{/if}" /> %
                        <br/>
                        <input type="number" size="8" step="0.01" style="width: 100px; text-align: right;" name="surcharge_amount[{$payment_method.method_id}]" value="{if !empty( $payment_method_settings[$payment_method.method_id] )}{$payment_method_settings[$payment_method.method_id].surcharge_amount}{else}0{/if}" /> {$default_currency}
                    </td>
                    <td><input type="number" size="8" style="width: 100px; text-align: right;" name="method_priority[{$payment_method.method_id}]" value="{if !empty( $payment_method_settings[$payment_method.method_id] )}{$payment_method_settings[$payment_method.method_id].priority}{else}0{/if}" /></td>
                </tr>
            {/foreach}

            <tr>
                <td colspan="5">
                    <br/>
                    <input type="submit" value="{l s='Update payment methods' mod='smart2pay'}" name="submit_payment_methods" id="submit_payment_methods" class="button" /><br/>
                    <br/>
                </td>
            </tr>
            </tbody>
        </table>
        </form>
        {/if}
    </div>
</div>
