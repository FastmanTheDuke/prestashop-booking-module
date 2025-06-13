{if isset($reservation_stats)}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-bar-chart"></i> Statistiques des réservations
    </div>
    <div class="panel-body">
        <div class="row">
            {foreach from=$reservation_stats item=stat}
                {assign var=alert_class value='alert-info'}
                {if $stat.status_id == 0}
                    {assign var=alert_class value='alert-warning'}
                {elseif $stat.status_id == 1}
                    {assign var=alert_class value='alert-info'}
                {elseif $stat.status_id == 2}
                    {assign var=alert_class value='alert-success'}
                {elseif $stat.status_id == 3 || $stat.status_id == 4}
                    {assign var=alert_class value='alert-danger'}
                {/if}
                
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="alert {$alert_class}">
                        <div class="text-center">
                            <div style="font-size: 2em; font-weight: bold;">{$stat.count}</div>
                            <div>{$stat.label}</div>
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>
{/if}