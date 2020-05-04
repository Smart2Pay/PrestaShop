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

{extends file='page.tpl'}

{block name="content"}
<div style="{if $moduleSettings["{$settings_prefix}DEBUG_FORM"] == 1} display: table; {else} display: none; {/if}">
    <p><b>Message to hash</b>: {$messageToHash|escape:'html'}</p>
    <p><b>Hash</b>: {$paymentData['Hash']|escape:'html'}</p>

    <table>
        {foreach from=$notSetPaymentData key=k item=v}
        <tr>
            <td>{$k|escape:'html'}</td>
            <td><input type="text" name="{$k|escape:'html'}" value="{$v|escape:'html'}" /></td>
        </tr>
        {/foreach}
    </table>

    <form action="{$moduleSettings['posturl']|escape:'html'}" id="s2pform" method="post" {if $moduleSettings["{$settings_prefix}REDIRECT_IN_IFRAME"]} target="merchantIframe" {/if}>
    <table>
        {foreach from=$paymentData key=k item=v}
        <tr>
            <td>{$k|escape:'html'}</td>
            <td><input type="text" name="{$k|escape:'html'}" value="{$v|escape:'html'}"/></td>
        </tr>
        {/foreach}
    </table>
    <input type="submit">
    </form>
</div>

<div id="iframe-container" style="display: none; position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; z-index: 10000">
    <div style="position: relative; width: 100%; height: 100%;">
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%; background: #333; opacity: 0.5; filter:alpha(opacity=50)"></div>
        <div style="position: absolute; top: 0px; left: 0px; width: 100%; height: 100%;">
            <div id="iframe-wrapper" style="position: fixed; display: table; margin: 0px auto; margin-top: 50px; width: 100%">
                <div style="margin: 0px auto; display: table;">
                {if $moduleSettings["{$settings_prefix}REDIRECT_IN_IFRAME"] == 0 && $moduleSettings["{$settings_prefix}LOADING_MODAL"]} == 1}
                    <div id="s2p_loading_content" style="margin: 20% auto 0 auto; width:80%; background-color: white;border: 2px solid lightgrey; text-align: center; padding: 40px;">
                        <img src="{$this_path|escape:'html'}views/img/ajax-loader.gif" alt="{l s='Loading...' mod='smart2pay'}" />
                        <p style="margin: 20px auto;">{l s='Redirecting. Please wait...' mod='smart2pay'}</p>
                    </div>
                {/if}
                {if $moduleSettings["{$settings_prefix}REDIRECT_IN_IFRAME"] && $moduleSettings["{$settings_prefix}SKIP_PAYMENT_PAGE"] && ($paymentData['MethodID'] == 1001 || $paymentData['MethodID'] == 1002 || $paymentData['MethodID'] == 76)}
                    <iframe style="border: none; margin: 0px auto; background-color: #ffffff;" id="merchantIframe" name="merchantIframe" src="" width="780" height="500"></iframe>
                {else}
                    <iframe style="border: none; margin: 0px auto; background-color: transparent;" id="merchantIframe" name="merchantIframe" src="" width="900" height="800"></iframe>
                {/if}
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
{literal}
function modalIframe()
{
    var iframe_container_obj = jQuery("#iframe-container");
    if( iframe_container_obj )
    {
        iframe_container_obj.appendTo('body');
        iframe_container_obj.show();
        iframe_container_obj.css({height: document.getElementsByTagName('html')[0].scrollHeight});
    }
}

jQuery(document).ready(function()
{
    jQuery('#s2pform').submit(function(){
        modalIframe();
    });

    /*
     *
     * Auto-send form if not debug form required
     *
     */
    {/literal}
    {if !$moduleSettings["{$settings_prefix}DEBUG_FORM"]}
        jQuery("#s2pform").submit();
    {/if}
    {literal}

    /*
     *
     * Get/Parse smart2pay message
     *
     */
    var onmessage = function(e)
    {
        if (e.data == 'close_HPP')
        {
            setTimeout(function() {jQuery('iframe#merchantIframe').remove()}, 300);
        } else if (e.data.substring(0, 7) == "height=")
        {
            var iframe_height = e.data.substring( 7 );
            jQuery('iframe#merchantIframe').attr('height', parseInt(iframe_height)+300);
            console.log("jQuery('iframe#merchantIframe').attr('height'," + (parseInt(iframe_height)+300) + ");");
        } else if (e.data.substring(0, 6) == "width=")
        {
            var iframe_width = e.data.substring( 6 );
            jQuery('iframe#merchantIframe').attr('width', parseInt(iframe_width)+100);
            console.log("jQuery('iframe#merchantIframe').attr('width'," + (parseInt(iframe_width)+100) + ");");
        } else if (e.data.substring(0, 12) == "redirectURL=")
        {
            window.location = e.data.substring( 12 );
        }
    };

    // set event listener for smart2pay
    if ( typeof window.addEventListener != 'undefined' )
    {
        window.addEventListener( 'message', onmessage, false );
    } else if ( typeof window.attachEvent != 'undefined' )
    {
        window.attachEvent( 'onmessage', onmessage );
    }
});
</script>
{/literal}
{/block}
