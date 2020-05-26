<link href="{$module_dir|escape:'htmlall'}views/css/admin.scss" rel="stylesheet" type="text/css" media="all"/>
<script type="text/javascript" src="{$module_dir|escape:'htmlall'}views/js/admin.js"></script>
<script type="text/javascript" src="{$module_dir|escape:'htmlall'}views/js/jquery.toggleinput.js"></script>

{$output nofilter}

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
                {$generalForm nofilter}
            </div>

            <div id="tab-advanced" {if $submit != 'submit_advanced_data'}style="display: none;"{/if}>
                {$advancedForm nofilter}
            </div>

            <div id="tab-methods" {if ($submit != 'submit_payment_methods' && $submit != 'submit_syncronize_methods')}style="display: none;"{/if}>
                {$roundingWarning nofilter}
                {$decimalWarning nofilter}
                {$methods nofilter}
            </div>

            <div id="tab-troubleshooting" style="display: none;">
                {$versions_message}
                {$logs nofilter}
            </div>
        </div>
    </div>
</div>