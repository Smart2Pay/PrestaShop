{*
* 2018 Smart2Pay
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this plugin
* in the future.
*
*  @author Smart2Pay
*  @copyright  2018 Smart2Pay
*}
<div id="s2p_payment_methods_container">
{if $moduleSettings["{$settings_prefix}LOADING_MODAL"]}
<script type="text/javascript">
{literal}
function s2p_display_loading_layer()
{
    var s2p_loading_container_obj = jQuery("#s2p_loader_container");
    if( s2p_loading_container_obj )
    {
        s2p_loading_container_obj.show();
        //s2p_loading_container_obj.css({height: document.getElementsByTagName('html')[0].scrollHeight});

        var s2p_loading_content_obj = jQuery("#s2p_loading_content");
        if( s2p_loading_content_obj )
        {
            $('html, body').animate({
                scrollTop: s2p_loading_content_obj.offset().top
            }, 1000);
        }
    }
}
function s2p_display_loading_layer_close()
{
    var s2p_loading_container_obj = jQuery("#s2p_loader_container");
    if( s2p_loading_container_obj )
        s2p_loading_container_obj.hide();
}
{/literal}
</script>
<div id="s2p_loader_container" style="display: none; position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; z-index: 10000">
    <div style="position: relative; width: 100%; height: 100%;">
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; background: #333; opacity: 0.5; filter:alpha(opacity=50)"></div>
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%;">
            <div id="s2p_loading_content" style="margin: 20% auto 0 auto; width:80%; background-color: white;border: 2px solid lightgrey; text-align: center; padding: 40px;">
                <img src="{$this_path|escape:'html'}views/img/ajax-loader.gif" alt="{l s='Loading...' mod='smart2pay'}" />
                <p style="margin: 20px auto;">{l s='Redirecting. Please wait...' mod='smart2pay'}</p>
                <div style="float:right;"><a href="javascript:s2p_display_loading_layer_close()">{l s='Close' mod='smart2pay'}</a></div>
            </div>
        </div>
    </div>
</div>
{/if}
<form name="s2p_payment_selector" id="s2p_payment_selector" method="post" action="{$s2p_module_obj->get_payment_link( ['link_for_form' => 1] )|escape:'html'}">
{if !empty( $payment_methods )}
    {foreach from=$payment_methods key=method_id item=method_arr name=methodsLoop}
<div class="row">
    <div class="col-xs-12">
        <div class="s2p_payment_module17">
            <label>
            <span class="custom-radio pull-xs-left">
                <input class="ps-shown-by-js custom-radio"
                       id="s2p-payment-option-{$method_arr.method.method_id|escape:'html'}"
                       name="method_id"
                       type="radio"
                       value="{$method_arr.method.method_id|escape:'html'}"/><span></span>
            </span>
                <!--
                <a class="s2ppaymentmethod"
                   href="{$s2p_module_obj->get_payment_link( ['method_id' => {$method_arr.method.method_id|escape:'html'}] )}"
                    {if $moduleSettings["{$settings_prefix}LOADING_MODAL"]} onclick="s2p_display_loading_layer()" {/if}
                   title="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name|escape:'html'}">
                    -->

                <div class="s2p_payment_method_image">
                    <img src="{$method_arr.method.logo_url|escape:'html'}"
                         alt="{l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name|escape:'html'}"
                         class="s2ppaymentlogo"
                         style="position:inherit"/>
                </div>
                {l s='Pay by' mod='smart2pay'} {$method_arr.method.display_name|escape:'html'}
                {if $method_arr.settings.surcharge_percent != 0 || $method_arr.settings.surcharge_amount != 0}
                    {if $config_opt_amount == $display_options.amount_total}
                        ({l s='Total fee amount' mod='smart2pay'}:
                        {if $config_opt_currency == $display_options.from_front}
                            {S2P_displayPrice
                                  price=($method_arr.settings.surcharge_percent_amount + $method_arr.settings.surcharge_amount_converted)
                                  currency=$methods_detected_currency}
                        {else}
                            {S2P_displayPrice
                                  price=($method_arr.settings.surcharge_percent_amount + $method_arr.settings.surcharge_amount)
                                  currency=$method_arr.settings.surcharge_currency_id}
                        {/if})

                    {elseif $config_opt_amount == $display_options.order_total}

                        ({l s='You will pay' mod='smart2pay'}
                        {if $config_opt_currency == $display_options.from_front}
                            {S2P_displayPrice
                                  price=($method_arr.settings.cart_amount + $method_arr.settings.surcharge_percent_amount + $method_arr.settings.surcharge_amount_converted)
                                  currency=$methods_detected_currency}
                        {else}
                            {S2P_displayPrice
                                  price=($method_arr.settings.cart_amount + $method_arr.settings.surcharge_percent_amount + $method_arr.settings.surcharge_amount)
                                  currency=$method_arr.settings.surcharge_currency_id}
                        {/if}
                        {l s='for order including payment processing fee' mod='smart2pay'})
                    {else}
                        ({l s='Fee' mod='smart2pay'}:
                            {if $method_arr.settings.surcharge_percent != 0}{$method_arr.settings.surcharge_percent+1-1}%{/if}{if $method_arr.settings.surcharge_percent != 0 && $method_arr.settings.surcharge_amount > 0} + {/if}{if $method_arr.settings.surcharge_amount != 0}{if $config_opt_currency == $display_options.from_front}{S2P_displayPrice price=$method_arr.settings.surcharge_amount_converted currency=$methods_detected_currency}{else}{S2P_displayPrice price=$method_arr.settings.surcharge_amount currency=$method_arr.settings.surcharge_currency_id}{/if}){/if}
                    {/if}
                {/if}
            <!--</a>-->
            </label>
        </div>
    </div>
</div>
    {/foreach}
{/if}
</form>
</div>
