{if $smarty.const._PS_VERSION_ >= 1.6}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module">
                <a class="s2ppaymentmethod" href="{$redirect_URL}" title="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}">
                    <img src="{$this_path_bw}logo.png" alt="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}" class="s2ppaymentlogo" style="top: 37px;" />
                    {l s="Pay by {$method_name}" mod="{$module_name}"}
                    <span>({l s="via Smart2Pay" mod="{$module_name}"})</span>
                </a>
            </p>
        </div>
    </div>
{else}
    <p class="payment_module">
        <a class="s2ppaymentmethod" href="{$redirect_URL}" title="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}">
            <img src="{$this_path_bw}logo.png" alt="{l s="Pay by {$method_name} via Smart2Pay" mod="{$module_name}"}" class="s2ppaymentlogo" />
            {l s="Pay by {$method_name}" mod="{$module_name}"}
            <span>({l s="via Smart2Pay" mod="{$module_name}"})</span>
        </a>
    </p>
{/if}