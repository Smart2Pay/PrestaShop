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

{if $smarty.const._PS_VERSION_ >= 1.6}
<div class="panel">
    <div class="panel-heading">{l s='Payment Methods' mod='smart2pay'}</div>
    <div class="smart2pay-admin-payment-method-container">
{else}
<br/>
<fieldset>
    <legend>{l s='Payment Methods' mod='smart2pay'}</legend>
{/if}

        {if empty( $payment_methods )}
        <div style="text-align: center">{l s='No payment methods defined in database.' mod='smart2pay'}</div>
        {else}
        <small>{l s='Surcharge amount is provided in shop\'s default currency.' mod='smart2pay'}<br/>
            {l s='If you want to prioritize payment methods when displaying them at checkout, use Priority column. Lower values will display payment method higher on the page.' mod='smart2pay'}</small>
        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" id="s2p_payment_methods_configuration" name="s2p_payment_methods_configuration">
        <table class="table" style="{if $smarty.const._PS_VERSION_ >= 1.5}width: 80%;{else}width: 100%;{/if} margin: 0 auto;">
            <thead>
            <tr>
                <th>{l s='Enabled?' mod='smart2pay'}</th>
                <th colspan="2">{l s='Name' mod='smart2pay'}</th>
                <th>{l s='Surcharge' mod='smart2pay'}</th>
                <th>{l s='Priority' mod='smart2pay'}</th>
            </tr>
            <tr>
                <td colspan="5">
                    <a href="javascript:void(0);" onclick="s2p_config_js_select_all()">{l s='Select all' mod='smart2pay'}</a>
                    |
                    <a href="javascript:void(0);" onclick="s2p_config_js_invert()">{l s='Invert' mod='smart2pay'}</a>
                    |
                    <a href="javascript:void(0);" onclick="s2p_config_js_deselect_all()">{l s='Select none' mod='smart2pay'}</a>
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

{if $smarty.const._PS_VERSION_ < 1.6}
</fieldset>
{else}
    </div>
</div>
{/if}
