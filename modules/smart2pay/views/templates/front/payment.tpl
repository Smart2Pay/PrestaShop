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
{if !empty( $payment_methods )}
    {foreach from=$payment_methods key=method_id item=method_arr name=methodsLoop}
        {if $smarty.const._PS_VERSION_ >= 1.6}
<div class="row">
    <div class="col-xs-12">
        <p class="payment_module">
            <a class="s2ppaymentmethod" href="{$s2p_module_obj->get_payment_link( ['method_id' => {$method_arr.method.method_id}] )}" title="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}">
                <img src="{$this_path}views/img/logos/{$method_arr.method.logo_url}" alt="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}" class="s2ppaymentlogo" />
                {l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}
                {if $method_arr.settings.surcharge_percent != 0 || $method_arr.settings.surcharge_amount != 0 }
                    ({l s='Fee' mod='smart2pay'}:
                        {if $method_arr.settings.surcharge_percent != 0}{$method_arr.settings.surcharge_percent_format}%{/if}{if $method_arr.settings.surcharge_percent != 0 && $method_arr.settings.surcharge_amount > 0} + {/if}{if $method_arr.settings.surcharge_amount != 0}{displayPrice price=$method_arr.settings.surcharge_amount_converted currency=$methods_detected_currency}{/if})
                {/if}
            </a>
        </p>
    </div>
</div>
        {else}
<p class="payment_module">
    <a href="{$s2p_module_obj->get_payment_link( ['method_id' => {$method_arr.method.method_id}] )}" title="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}" {if $smarty.const._PS_VERSION_ < 1.5}style="min-height: 50px"{/if}>
        <span style="width: 86px; height: 49px;"><img src="{$this_path}views/img/logos/{$method_arr.method.logo_url}" alt="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}" style="max-width: 86px; max-height: 49px;" /></span>
        {l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name}
        {if $method_arr.settings.surcharge_percent != 0 || $method_arr.settings.surcharge_amount != 0 }
            ({l s='Fee' mod='smart2pay'}:
                {if $method_arr.settings.surcharge_percent != 0}{$method_arr.settings.surcharge_percent_format}%{/if}{if $method_arr.settings.surcharge_percent != 0 && $method_arr.settings.surcharge_amount > 0} + {/if}{if $method_arr.settings.surcharge_amount != 0}{displayPrice price=$method_arr.settings.surcharge_amount_converted currency=$methods_detected_currency}{/if})
        {/if}
    </a>
</p>
        {/if}
    {/foreach}
{/if}