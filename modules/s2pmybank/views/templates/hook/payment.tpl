{if $smarty.const._PS_VERSION_ >= 1.6}
    <div class="row">
        <div class="col-xs-12 col-md-6">
            <p class="payment_module">
                <a href="{$redirect_URL}" title="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}">
                    <img src="{$this_path_bw}logo.png" alt="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}" width="86" height="49"/>
                    {l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}
                </a>
            </p>
        </div>
    </div>
{else}
    <p class="payment_module">
        <a href="{$redirect_URL}", 'payment')|escape:'html'}" title="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}">
            <img src="{$this_path_bw}logo.png" alt="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}" width="86" height="49"/>
            {l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}
        </a>
    </p>
{/if}