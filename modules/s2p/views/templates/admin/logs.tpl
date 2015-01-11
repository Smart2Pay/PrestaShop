<div class="panel">
    <div class="panel-heading">Logs</div>
    <div class="smart2pay--admin-logs-container">
        <table class="table">
            {foreach $logs as $log}
                <tr>
                    <td>
                        <span class="smart2pay--admin-logs-item-type">[{$log.log_type|escape:'htmlall':'UTF-8'}]</span>
                    </td>
                    <td>
                        <span class="smart2pay--admin-logs-item-date">({$log.log_created|escape:'htmlall':'UTF-8'})</span>
                    </td>
                    <td>
                        <span class="smart2pay--admin-logs-item-text">{$log.log_data|escape:'htmlall':'UTF-8'}</span>
                        <span class="smart2pay--admin-logs-item-file">@{$log.log_source_file|escape:'htmlall':'UTF-8'}</span>
                        <span class="smart2pay--admin-logs-item-line">:{$log.log_source_file_line|escape:'htmlall':'UTF-8'}</span>
                    </td>
                </tr>
            {/foreach}
        </table>
    </div>
</div>