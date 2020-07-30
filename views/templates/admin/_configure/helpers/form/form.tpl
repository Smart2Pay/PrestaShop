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
{extends file="helpers/form/form.tpl"}

{block name="input_row"}
    {if (isset($input.s2p_start_env_test) && $input.s2p_start_env_test)}
        <div class="env-test">
    {elseif (isset($input.s2p_start_env_live) && $input.s2p_start_env_live)}
        <div class="env-live">
    {elseif (isset($input.s2p_return_url) && $input.s2p_return_url)}
        <div class="return-url">
    {elseif (isset($input.s2p_start_section) && $input.s2p_start_section)}
        <div class="section">
            <legend>{$input.s2p_section_legend}</legend>
    {/if}

    {$smarty.block.parent}

    {if (isset($input.s2p_end_env_test) && $input.s2p_end_env_test) || (isset($input.s2p_end_env_live) && $input.s2p_end_env_live) || (isset($input.s2p_return_url) && $input.s2p_return_url) || (isset($input.s2p_end_section) && $input.s2p_end_section)}
        </div>
    {/if}
{/block}


{block name="input"}
    {if (isset($input.s2p_radio_to_switch) && $input.s2p_radio_to_switch)}
        <div class="div-inline-flex">
    {/if}

    {$smarty.block.parent}

    {if (isset($input.s2p_radio_to_switch) && $input.s2p_radio_to_switch)}
        </div>
        {$input.create_account_message}{* HTML, cannot escape*}
        {$input.change_env_message}{* HTML, cannot escape*}
        {$input.kyc_info_message}{* HTML, cannot escape*}
    {/if}
{/block}