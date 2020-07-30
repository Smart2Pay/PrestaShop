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
<link href="{$module_dir}views/css/admin.css" rel="stylesheet" type="text/css" media="all"/>
<script type="text/javascript" src="{$module_dir}views/js/admin.js"></script>
<script type="text/javascript" src="{$module_dir}views/js/jquery.toggleinput.js"></script>

{$output}{* HTML, cannot escape*}

<div class="tabbable">
    <ul id="navigation" class="nav nav-tabs">
        <li {if $submit == 'submit_main_data'}class="active"{/if}>
            <a href="#tab-gen" data-panel="tab-general">{l s='General' mod='smart2pay'}</a>
        </li>
        <li {if $submit == 'submit_advanced_data'}class="active"{/if}>
            <a href="#tab-adv" data-panel="tab-advanced">{l s='Advanced' mod='smart2pay'}</a>
        </li>
        <li {if ($submit == 'submit_payment_methods' || $submit == 'submit_syncronize_methods')}class="active"{/if}>
            <a href="#tab-met" data-panel="tab-methods">{l s='Methods' mod='smart2pay'}</a>
        </li>
        <li>
            <a href="#tab-troubleshoot" data-panel="tab-troubleshooting">{l s='Troubleshooting' mod='smart2pay'}</a>
        </li>
    </ul>

    <div class="panel">
        <div class="tab-content">
            <div id="tab-general" {if $submit != 'submit_main_data'}style="display: none;"{/if}>
                {$generalForm}{* HTML, cannot escape*}
            </div>

            <div id="tab-advanced" {if $submit != 'submit_advanced_data'}style="display: none;"{/if}>
                {$advancedForm}{* HTML, cannot escape*}
            </div>

            <div id="tab-methods" {if ($submit != 'submit_payment_methods' && $submit != 'submit_syncronize_methods')}style="display: none;"{/if}>
                {$roundingWarning}{* HTML, cannot escape*}
                {$decimalWarning}{* HTML, cannot escape*}
                {$methods}{* HTML, cannot escape*}
            </div>

            <div id="tab-troubleshooting" style="display: none;">
                {$versions_message}{* HTML, cannot escape*}
                {$logs}{* HTML, cannot escape*}
            </div>
        </div>
    </div>
</div>