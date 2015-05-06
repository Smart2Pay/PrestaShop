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
<div class="panel">
    <div class="panel-heading">{l s='Logs' mod='smart2pay'}</div>
    <div class="smart2pay-admin-logs-container">
        {if empty( $logs )}
        <div style="text-align: center">{l s='No logs available at the moment' mod='smart2pay'}</div>
        {else}
        <table class="table">
            <thead>
            <tr>
                <th>{l s='Type' mod='smart2pay'}</th>
                <th>{l s='Date' mod='smart2pay'}</th>
                <th>{l s='Message' mod='smart2pay'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach $logs as $log}
                <tr>
                    <td class="smart2pay-admin-logs-item-type">[{$log.log_type|escape:'htmlall':'UTF-8'}]</td>
                    <td class="smart2pay-admin-logs-item-date">{$log.log_created|escape:'htmlall':'UTF-8'}</td>
                    <td>
                        <span class="smart2pay-admin-logs-item-text">{$log.log_data|escape:'htmlall':'UTF-8'}</span><br/>
                        <span class="smart2pay-admin-logs-item-file">@{$log.log_source_file|escape:'htmlall':'UTF-8'}</span>
                        <span class="smart2pay-admin-logs-item-line">:{$log.log_source_file_line|escape:'htmlall':'UTF-8'}</span>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
        {/if}
    </div>
</div>
