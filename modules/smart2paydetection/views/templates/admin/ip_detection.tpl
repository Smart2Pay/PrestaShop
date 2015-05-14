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
{if $smarty.const._PS_VERSION_ >= 1.6}
<div class="panel">
    <div class="panel-heading">{l s='IP Detection Demo' mod='smart2paydetection'}</div>
    <div class="smart2paydetection-admin-ip-detection-container">
{else}
<br/>
<fieldset>
    <legend>{l s='IP Detection Demo' mod='smart2paydetection'}</legend>
{/if}

        <div style="text-align: center; padding: 5px;">

            This product includes GeoLite2 data created by MaxMind, available from <a href="http://www.maxmind.com">http://www.maxmind.com</a>.<br/>

            <strong>{l s='Please note that in order to have higher rates of detection you should update database file as often as possible.' mod='smart2paydetection'}</strong><br/>

            {l s='You can download database file from following location: ' mod='smart2paydetection'} <a href="http://dev.maxmind.com/geoip/geoip2/geolite2/" target="_blank">http://dev.maxmind.com/geoip/geoip2/geolite2/</a>.

        </div>

        {if empty( $db_file_installed )}

        <div style="text-align: center; color: red;">{l s='Database file GeoLite2-Country.mmdb not found. Please download and copy database file in plugin\'s directory.' mod='smart2paydetection'}</div>
        <div style="text-align: center; color: red;">{l s='Desired location' mod='smart2paydetection'}: <strong>{$db_file_location}</strong>.</div>

        {else}

        <table class="table" style="width: 80%; margin: 0 auto;">
        <tbody>
        <tr>
            <td><strong>{l s='DB File Location' mod='smart2paydetection'}</strong></td>
            <td>{$db_file_location}</td>
        </tr>
        <tr>
            <td><strong>{l s='DB File Date' mod='smart2paydetection'}</strong></td>
            <td>{$db_file_time}</td>
        </tr>
        <tr>
            <td><strong>{l s='DB Size' mod='smart2paydetection'}</strong></td>
            <td>{$db_file_size_human} ({$db_file_size} {l s='bytes' mod='smart2paydetection'}), {$db_file_records} {l s='records' mod='smart2paydetection'}</td>
        </tr>
        <tr>
            <td><strong>{l s='DB Version' mod='smart2paydetection'}</strong></td>
            <td>{$db_file_version}</td>
        </tr>
        <tr>
            <td><strong>{l s='Description' mod='smart2paydetection'}</strong></td>
            <td>{$db_file_description}</td>
        </tr>
        </tbody>
        </table>

        <div style="text-align: center; padding: 10px;">

            {l s='To test plugin detection you can use the form below.' mod='smart2paydetection'}<br/>

        </div>


        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" id="s2pd_ip_detection_test" name="s2pd_ip_detection_test" class="defaultForm form-horizontal smart2paydetection">
        <div class="form-wrapper">

            <div class="form-group">
                <label class="control-label col-lg-3 required" for="s2p_test_ip">{l s='Try Detection on IP' mod='smart2paydetection'}</label>
                <div class="col-lg-9"><input id="s2p_test_ip" name="s2p_test_ip" class="" type="text" required="required" value="{$s2p_test_ip|escape:'htmlall':'UTF-8'}" /></div>
            </div>

            <div class="form-group">
                <label class="control-label col-lg-3"></label>
                <div class="col-lg-9"><input type="submit" value="{l s='Check IP detection' mod='smart2paydetection'}" name="submit_test_detection" id="submit_test_detection" class="button" /></div>
            </div>
        </div>
        </form>

        {if !empty( $detection_result ) }
        <table class="table" style="width: 80%; margin: 0 auto;">
            <tbody>
            <tr>
                <td><strong>{l s='Detected Country' mod='smart2paydetection'}</strong></td>
                <td>{$detection_result['country']['name']} ({$detection_result['country']['code']})</td>
            </tr>
            <tr>
                <td><strong>{l s='Detected Continent' mod='smart2paydetection'}</strong></td>
                <td>{$detection_result['continent']['name']} ({$detection_result['continent']['code']})</td>
            </tr>
            </tbody>
        </table>
        {/if}

        {/if}

{if $smarty.const._PS_VERSION_ < 1.6}
</fieldset>
{else}
    </div>
</div>
{/if}

{if $smarty.const._PS_VERSION_ >= 1.6}
<div class="panel">
    <div class="panel-heading">{l s='Detection Logs' mod='smart2paydetection'}</div>
    <div class="smart2paydetection-admin-ip-detection-container">
{else}
<br/>
<fieldset>
    <legend>{l s='Detection Logs' mod='smart2paydetection'}</legend>
{/if}

        <div style="text-align: center; padding: 5px;">

            <strong>{l s='Below you have last 20 logs about IP detection.' mod='smart2paydetection'}</strong><br/>

        </div>

        <table class="table s2p-payment-logs row-margin-bottom" {if $smarty.const._PS_VERSION_ < 1.6}style="width: 100%"{/if}>
            <thead>
            <tr>
                <td style="width:180px; text-align: center;">{l s='Date' mod='smart2paydetection'}</td>
                <td>{l s='Log' mod='smart2paydetection'}</td>
            </tr>
            </thead>
            <tbody>
            {if empty( $detection_logs )}
                <tr>
                    <td colspan="2" style="text-align: center;"><p>{l s='No detection logs in database.' mod='smart2paydetection'}</p></td>
                </tr>
            {else}
                {foreach from=$detection_logs key=key item=log_item name=s2p_detection_logs}
                    <tr>
                        <td style="white-space: nowrap;">{if $smarty.const._PS_VERSION_ >= 1.5}
                            {Tools::displayDate( $log_item.log_created, null, true )}
                        {else}
                            {Tools::displayDate( $log_item.log_created, $language_id, true )}
                        {/if}</td>
                        <td>{$log_item.log_data}</td>
                    </tr>
                {/foreach}
            {/if}
            </tbody>
        </table>

{if $smarty.const._PS_VERSION_ < 1.6}
</fieldset>
{else}
    </div>
</div>
{/if}
