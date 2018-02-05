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

function s2p_config_js_select_all_countries( method_id )
{
    $("input[id^='checked_method_countries_"+method_id+"_'").each( function( el )
    {
        var jquery_obj = $(this);
        if( jquery_obj && !jquery_obj.is(':checked') )
            jquery_obj.prop('checked', true );
    });
}
function s2p_config_js_deselect_all_countries( method_id )
{
    $("input[id^='checked_method_countries_"+method_id+"_'").each( function( el )
    {
        var jquery_obj = $(this);
        if( jquery_obj && jquery_obj.is(':checked') )
            jquery_obj.prop('checked', false );
    });
}
function s2p_config_js_invert_countries( method_id )
{
    $("input[id^='checked_method_countries_"+method_id+"_'").each( function( el )
    {
        var jquery_obj = $(this);
        if( jquery_obj )
        {
            if( jquery_obj.is(':checked') )
                jquery_obj.prop( 'checked', false );
            else
                jquery_obj.prop( 'checked', true );
        }
    });
}

function update_countries_availability()
{
    $("input[id^='enabled_method_countries_'").each( function( el )
    {
        var method_id = this.id.replace( 'enabled_method_countries_', '' );
        update_method_countries_availability( method_id );
    });
}

function update_method_countries_availability( method_id )
{
    var method_countries_obj = $('#enabled_method_countries_'+method_id);
    if( !method_countries_obj )
        return;

    var countries_list = method_countries_obj.val();
    var countries_arr = [];

    $("i[id^='pmc_check_"+method_id+"_'").each( function ( i )
    {
        $(this).html('');
    });

    if( countries_list.length )
    {
        countries_arr = countries_list.split(',');
        for( var i = 0; i < countries_arr.length; i++ )
        {
            country_id = parseInt( countries_arr[i] );
            if( !country_id )
                continue;

            var country_check_obj = $('#pmc_check_'+method_id+'_'+country_id);
            if( !country_check_obj )
                continue;

            // country_check_obj.addClass( 'icon-wrench' );
            country_check_obj.html( '✓' );
        }
    }
}

function update_method_countries_checkboxes( method_id )
{
    var method_countries_obj = $('#enabled_method_countries_'+method_id);
    if( !method_countries_obj )
        return;

    s2p_config_js_deselect_all_countries( method_id );

    var countries_list = method_countries_obj.val();
    var countries_arr = [];

    if( countries_list.length )
    {
        countries_arr = countries_list.split(',');
        for( var i = 0; i < countries_arr.length; i++ )
        {
            country_id = parseInt( countries_arr[i] );
            if( !country_id )
                continue;

            var country_check_obj = $('#checked_method_countries_'+method_id+'_'+country_id);
            if( !country_check_obj )
                continue;

            country_check_obj.prop( 'checked', true );
        }
    }
}

function update_method_selected_countries_checkboxes( method_id )
{
    var method_countries_obj = $('#enabled_method_countries_'+method_id);
    if( !method_countries_obj )
        return;

    var new_countries_str = '';
    $("input[id^='checked_method_countries_"+method_id+"_'").each( function( el )
    {
        var jquery_obj = $(this);
        if( jquery_obj )
        {
            if( jquery_obj.is(':checked') )
            {
                if( new_countries_str != '' )
                    new_countries_str = new_countries_str + ',';

                new_countries_str = new_countries_str + jquery_obj.val();
            }
        }
    });

    method_countries_obj.val( new_countries_str );

    show_all_countries_for_method( method_id, 'show' );
    update_method_countries_availability( method_id );
}

function customize_method_countries( method_id )
{
    var customize_obj = $('#s2p_meth_custom_countries_'+method_id);
    var countries_obj = $('#s2p_meth_countries_'+method_id);
    if( !customize_obj || !countries_obj )
        return;

    if( customize_obj.css('display') == 'none' )
    {
        customize_obj.slideDown();
        countries_obj.slideUp();
        update_method_countries_checkboxes( method_id );
    } else
    {
        update_method_selected_countries_checkboxes( method_id );
        customize_obj.slideUp();
        countries_obj.slideDown();

        var scrollto_obj = $('#method_anchor_'+method_id);
        if( scrollto_obj )
        {
            $('html, body').animate({
                scrollTop: scrollto_obj.offset().top - 150
            }, 1000);
        }
    }
}
function show_all_countries_for_method( method_id, action )
{
    if( typeof action == 'undefined' )
        action = 'noforce';

    var div_obj = $('#s2p_meth_countries_'+method_id);

    if( (action == 'show' || div_obj.css('height') == '30px')
     && action != 'hide' )
    {
        div_obj.css('overflow', 'visible').css('height', 'auto');
    } else if( action == 'hide' || action == 'noforce' )
    {
        div_obj.css('overflow', 'hidden').css('height', '30px');
    }
}

$(document).ready(function(){
    update_countries_availability();
});
{/literal}
</script>

<a name="smart2pay_methods"></a>
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
        <div style="text-align: center">
            {l s='No payment methods defined in database for %s environment.' sprintf=[$plugin_environment] mod='smart2pay'}
            {l s='In order to update payment methods, please select desired environment from Environment drop-down option and then save settings.' mod='smart2pay'}<br/>
            <br/>
            {l s='Last syncronization' mod='smart2pay'}: {if empty( $last_sync_date )} {l s='Never' mod='smart2pay'} {else} {$last_sync_date} {/if}<br/>
            <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" id="s2p_payment_methods_syncronization" name="s2p_payment_methods_syncronization">
                <input type="submit" value="{l s='Syncronize Now' mod='smart2pay'}" name="submit_syncronize_methods" id="submit_syncronize_methods" class="button" />
            </form>
        </div>

        {else}
        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" id="s2p_payment_methods_configuration" name="s2p_payment_methods_configuration">
        <div style="text-align: center">
            {l s='Displaying payment methods for %s environment.' sprintf=[$plugin_environment] mod='smart2pay'}
            {l s='In order to update payment methods for other environments please select desired environment from Environment drop-down option and then save settings.' mod='smart2pay'}<br/>

            {l s='Last syncronization' mod='smart2pay'}: {if empty( $last_sync_date )} {l s='Never' mod='smart2pay'} {else} {$last_sync_date} {/if}<br/>
            {if empty( $time_to_launch_sync )}
            <input type="submit" value="{l s='Syncronize Now' mod='smart2pay'}" name="submit_syncronize_methods" id="submit_syncronize_methods" class="button" />
            {else}
            {l s='Time untill syncronization is available' mod='smart2pay'}: {$time_to_launch_sync}
            {/if}
            <br/><br/>
        </div>
        <small>
            {l s='If you want to prioritize payment methods when displaying them at checkout, use Priority column. Lower values will display payment method higher on the page.' mod='smart2pay'}<br/>
            {l s='NOTE: Payment method settings apply for all stores.' mod='smart2pay'}
        </small>
        <table class="table" style="width: 100%; margin: 0 auto;">
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
            <tr>
                <td colspan="5">
                    <br/>
                    <input type="submit" value="{l s='Update payment methods' mod='smart2pay'}" name="submit_payment_methods_2" id="submit_payment_methods_2" class="button" /><br/>
                    <br/>
                </td>
            </tr>
            {foreach $payment_methods as $payment_method}
                <tr>
                    <td style="width: 50px; text-align: center;">
                        <input type="hidden" name="enabled_method_countries[{$payment_method.method_id}]" id="enabled_method_countries_{$payment_method.method_id}" value="{if !empty( $comma_countries_methods[$payment_method.method_id] )}{$comma_countries_methods[$payment_method.method_id]}{/if}" />
                        <input type="checkbox" name="enabled_methods[]" id="enabled_methods_{$payment_method.method_id}" value="{$payment_method.method_id}" {if !empty( $payment_method_settings[$payment_method.method_id] )} checked="checked" {/if} />
                    </td>
                    <td style="width: 150px; text-align: center;"><label for="enabled_methods_{$payment_method.method_id}" style="text-align:center !important;"><img src="{$payment_method.logo_url}" style="max-width: 150px;" /></label></td>
                    <td>
                        <strong id="method_anchor_{$payment_method.method_id}">{$payment_method.display_name|escape:'htmlall':'UTF-8'}</strong><br/>
                        <a href="javascript:void(0);" style="text-decoration: underline;" onclick="customize_method_countries( {$payment_method.method_id} )">{l s='Customize countries' mod='smart2pay'}</a>
                        <div id="s2p_meth_custom_countries_{$payment_method.method_id}" style="display:none;">
                            {l s='Method active for following countries' mod='smart2pay'}:<br/>
                            {if !empty( $method_countries[$payment_method.method_id] )}
                            <table style="width:100%;">
                            {assign var=knti value=0}
                            {assign var=per_line value=3}
                            <tr>
                                <td colspan="{$per_line*2}" style="text-align: left;">
                                    <a href="javascript:void(0);" onclick="s2p_config_js_select_all_countries( '{$payment_method.method_id}' )">{l s='Select all' mod='smart2pay'}</a>
                                    |
                                    <a href="javascript:void(0);" onclick="s2p_config_js_invert_countries( '{$payment_method.method_id}' )">{l s='Invert' mod='smart2pay'}</a>
                                    |
                                    <a href="javascript:void(0);" onclick="s2p_config_js_deselect_all_countries( '{$payment_method.method_id}' )">{l s='Select none' mod='smart2pay'}</a>
                                </td>
                            </tr>
                            {foreach $method_countries[$payment_method.method_id] as $ccountry}
                                {if !empty( $countries_by_id[$ccountry.country_id] )}
                                    {if !$knti}<tr>{/if}
                                        <td style="width:3%;"><input type="checkbox" id="checked_method_countries_{$payment_method.method_id}_{$ccountry.country_id}" value="{$ccountry.country_id}" /></td>
                                        <td style="width:33%;"><label style="font-weight: normal;text-align:left !important;" for="checked_method_countries_{$payment_method.method_id}_{$ccountry.country_id}">{$countries_by_id[$ccountry.country_id].name}</label></td>
                                    {if $knti == $per_line-1}</tr>{$knti=-1}{/if}
                                    {$knti=$knti+1}
                                {/if}
                            {/foreach}
                            {if $knti && $knti <= $per_line-1}{for $i=$knti to $per_line-1}<td colspan="2">&nbsp;</td>{/for}{/if}
                            <tr>
                                <td colspan="{$per_line*2}" style="text-align: center;">
                                    <input type="button" value="{l s='Update method' mod='smart2pay'}" onclick="customize_method_countries( '{$payment_method.method_id}' )" /><br/>
                                    <strong>{l s='NOTE: Changes are saved when you submit the form using button under table of methods' mod='smart2pay'} (<em>{l s='Update payment methods' mod='smart2pay'}</em>).</strong>
                                </td>
                            </tr>
                            </table>
                            {/if}
                        </div>
                        <div id="s2p_meth_countries_{$payment_method.method_id}" style="height: 30px; overflow: hidden;text-overflow: ellipsis;display:block;">
                        <strong>{l s='Available in following countries' mod='smart2pay'}</strong> (<a href="javascript:void(0);" style="text-decoration: underline;" onclick="show_all_countries_for_method( '{$payment_method.method_id}' )">toggle</a>):
                        {if !empty( $method_countries[$payment_method.method_id] )}{$already_displayed = false}
                            {foreach $method_countries[$payment_method.method_id] as $ccountry}{if !empty( $countries_by_id[$ccountry.country_id] )}{if $already_displayed}, {/if}<span class="s2p_method_country"><i id="pmc_check_{$payment_method.method_id}_{$ccountry.country_id}" style="color:green; padding: 0 3px 0 0;"></i>{$countries_by_id[$ccountry.country_id].name}</span>{$already_displayed = true}{/if}{/foreach}
                        {/if}
                        </div>
                        <!--
                        <br/>
                        {$payment_method.description|escape:'htmlall':'UTF-8'}
                        -->
                    </td>
                    <td style="white-space: nowrap;">
                        <input type="number" size="8" step="0.01" style="width: 100px; text-align: right;" name="surcharge_percent[{$payment_method.method_id}]" value="{if !empty( $payment_method_settings[$payment_method.method_id] )}{$payment_method_settings[$payment_method.method_id].surcharge_percent}{else}0{/if}" /> %
                        <br/>
                        <input type="number" size="8" step="0.01" style="width: 100px; text-align: right;" name="surcharge_amount[{$payment_method.method_id}]" value="{if !empty( $payment_method_settings[$payment_method.method_id] )}{$payment_method_settings[$payment_method.method_id].surcharge_amount}{else}0{/if}" />
                        {if empty( $all_currencies )}
                            Please activate currencies first!
                        {else}
                            <select name="surcharge_currency[{$payment_method.method_id}]" style="margin: 0 5px; max-height: 30px; padding: 2px 4px; width: 80px; display: inherit;">
                            {foreach $all_currencies as $ccurrency}
                            <option value="{$ccurrency['iso_code']}"
                                    {if ((!empty( $payment_method_settings[$payment_method.method_id] ) && $ccurrency['iso_code'] == $payment_method_settings[$payment_method.method_id]['surcharge_currency'])
                                         || (empty( $payment_method_settings[$payment_method.method_id] ) && $ccurrency['id_currency'] == $default_currency_id) )} selected="selected" {/if}>{$ccurrency['iso_code']}{if $ccurrency['id_currency'] == $default_currency_id} ({l s='default' mod='smart2pay'}){/if}</option>
                            {/foreach}
                            </select>
                        {/if}
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
