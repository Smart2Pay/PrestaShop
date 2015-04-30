<div style="{if $moduleSettings['s2p_debug_form'] == 1} display: table; {else} display: none; {/if}">
    <p><b>Message to hash</b>: {$messageToHash}</p>
    <p><b>Hash</b>: {$paymentData['Hash']}</p>

    <table>
        {foreach from=$notSetPaymentData key=k item=v}
            <tr>
                <td>
                    {$k}
                </td>
                <td>
                    <input type="text" name="{$k}" value="{$v}"/>
                </td>
            </tr>
        {/foreach}
    </table>

    <form action="{$moduleSettings['postURL']}" id="s2pform" method="POST" {if $moduleSettings['s2p_redirect_in_iframe']} target="merchantIframe" {/if}>
        <table>
            {foreach from=$paymentData key=k item=v}
                <tr>
                    <td>
                        {$k}
                    </td>
                    <td>
                        <input type="text" name="{$k}" value="{$v}"/>
                    </td>
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
                    {if $moduleSettings['s2p_redirect_in_iframe']
                        && $moduleSettings['s2p_skip_payment_page']
                        && ($paymentData['MethodID'] == 1001 || $paymentData['MethodID'] == 1002 || $paymentData['MethodID'] == 76)
                    }
                        <iframe style='border: none; margin: 0px auto; background-color: #ffffff;' id="merchantIframe" name="merchantIframe" src="" width="780" height="500">
                    {else}
                        <iframe style='border: none; margin: 0px auto; background-color: transparent;' id="merchantIframe" name="merchantIframe" src="" width="900" height="800">
                    {/if}

                    </iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    {literal}
        function modalIframe(){
            jQuery("#iframe-container").appendTo('body');
            jQuery("#iframe-container").show();
            jQuery("#iframe-container").css({height: document.getElementsByTagName('html')[0].scrollHeight});
        }
    {/literal}

    jQuery(document).ready(function() {

        jQuery('#s2pform').submit(function(){
            modalIframe();
        });

        /*
         *
         * Auto-send form if not debug form required
         *
         */
        {if !$moduleSettings['s2p_debug_form']}
            jQuery("#s2pform").submit();
        {/if}

        /*
         *
         * Get/Parse smart2pay message
         *
         */
        {literal}
            var onmessage = function(e) {
                if (e.data == 'close_HPP') {
                    setTimeout(function() {jQuery('iframe#merchantIframe').remove()}, 300);
                } else if (e.data.substring(0, 7) == "height=") {
                    var iframe_height = e.data.substring(7);
                    jQuery('iframe#merchantIframe').attr('height', parseInt(iframe_height)+300);
                    console.log("jQuery('iframe#merchantIframe').attr('height'," + (parseInt(iframe_height)+300) + ");");
                } else if (e.data.substring(0, 6) == "width=") {
                    var iframe_width = e.data.substring(6);
                    jQuery('iframe#merchantIframe').attr('width', parseInt(iframe_width)+100);
                    console.log("jQuery('iframe#merchantIframe').attr('width'," + (parseInt(iframe_width)+100) + ");");
                } else if (e.data.substring(0, 12) == "redirectURL="){
                    window.location = e.data.substring(12);
                }
            }
            // set event listener for smart2pay
            if (typeof window.addEventListener != 'undefined') {
                window.addEventListener('message', onmessage, false);
            } else if (typeof window.attachEvent != 'undefined') {
                window.attachEvent('onmessage', onmessage);
            }
        {/literal}
    });
</script>