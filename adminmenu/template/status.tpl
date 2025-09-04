<div class="card" style="box-shadow: none;">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        <!-- Status Overview Cards -->
        <div class="row mb-4 d-flex">
            <div class="col-md-3 d-flex">
                <div class="card text-white {if $statusOverview.cron_status.has_stuck}bg-danger{else}bg-info{/if} flex-fill">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Cron Status</strong>
                    </div>
                    <div class="card-body" style="padding: 15px;">
                        <div style="font-size: 1.0em; margin-bottom: 8px;">
                            {if $statusOverview.cron_status.has_stuck}
                                <strong>STUCK</strong><br>
                                <span>{$statusOverview.cron_status.stuck_count} job(s)</span>
                            {else}
                                <strong>Last Run:</strong> 
                                {if $statusOverview.last_cron_run}
                                    {$statusOverview.last_cron_run|date_format:"%m/%d %H:%M"}
                                {else}
                                    Never
                                {/if}
                            {/if}
                        </div>
                        <div style="font-size: 1.0em; {if $statusOverview.cron_status.has_stuck}margin-bottom: 8px;{/if}">
                            <strong>Next Run:</strong> 
                            {if $statusOverview.next_cron_run}
                                {$statusOverview.next_cron_run|date_format:"%m/%d %H:%M"}
                            {else}
                                Unknown
                            {/if}
                        </div>
                        {if $statusOverview.cron_status.has_stuck}
                            <form method="post" style="margin-top: auto;">
                                {$token}
                                <input type="hidden" name="reset_stuck_cron" value="1">
                                <button type="submit" class="btn btn-sm btn-warning" 
                                        onclick="return confirm('Are you sure you want to reset stuck cron jobs? This will set their isRunning status to 0.');"
                                        style="font-size: 11px; padding: 4px 8px;">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </form>
                        {/if}
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                <div class="card text-white bg-primary flex-fill">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Total Orders</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center" style="padding: 10px;">
                        <h2 style="margin-bottom: 5px;">{$statusOverview.total_orders}</h2>
                        <small style="margin-bottom: 8px;">Orders with actions</small>
                        <form method="get" style="margin-top: auto;">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="order_search" name="order_search" 
                                       placeholder="Order ID/Number" style="font-size: 11px;" 
                                       value="{if isset($smarty.get.order_search)}{$smarty.get.order_search|escape}{/if}">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-sm btn-light" style="font-size: 11px; padding: 4px 8px;">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                <div class="card text-white flex-fill" style="background-color: {if $statusOverview.pending_orders > 0}#fd7e14{else}#28a745{/if};">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Pending</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center" style="padding: 10px;">
                        <h2 style="margin-bottom: 5px;">{$statusOverview.pending_orders}</h2>
                        <small style="margin-bottom: 8px;">Actions waiting</small>
                        <form method="post" style="margin-top: auto;">
                            {$token}
                            <input type="hidden" name="process_pending" value="1">
                            <button type="submit" class="btn btn-sm btn-light" 
                                    {if $statusOverview.pending_orders == 0}disabled{/if}
                                    style="font-size: 11px; padding: 4px 8px;">
                                <i class="fas fa-play"></i> Process All
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                <div class="card text-white flex-fill" style="background-color: {if $statusOverview.broken_orders > 0}#dc3545{else}#28a745{/if};">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Broken</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <h2 style="margin-bottom: 5px;">{$statusOverview.broken_orders}</h2>
                        <small>Need attention</small>
                    </div>
                </div>
            </div>
        </div>





        <!-- Search Results -->
        {if isset($searchResult) && $searchResult}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card" style="background-color: #f8f9fa;">
                        <div class="card-header" style="background-color: #e9ecef;">
                            <h5>Order Details: {$searchResult.order->cBestellNr}</h5>
                        </div>
                        <div class="card-body" style="padding: 15px;">
                             <div class="mb-3" style="border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                                <p class="mb-1"><strong>Customer:</strong> 
                                    {$searchResult.customerName}
                                    {if $searchResult.customerEmail}
                                        ({$searchResult.customerEmail})
                                    {/if}
                                </p>
                                <p class="mb-1"><strong>Total:</strong> {$searchResult.order->fGesamtsumme|number_format:2:',':"."} EUR</p>
                                <p class="mb-1"><strong>Status:</strong> {$searchResult.order->Status}</p>
                                <p class="mb-0"><strong>Date:</strong> {$searchResult.order->dErstellt}</p>
                            </div>

                            {* Combined Actions List *}
                            {assign var="hasActions" value=false}
                            {if !empty($searchResult.pendingActions) || !empty($searchResult.completedActions)}
                                {assign var="hasActions" value=true}
                                <div class="mb-3" style="border-bottom: 1px solid #dee2e6; padding-bottom: 10px;">
                                    <h6 class="mb-2" style="color: #495057;">Actions</h6>
                                    <div style="font-size: 12px;">
                                         {* Pending and Failed Actions *}
                                         {if !empty($searchResult.pendingActions)}
                                             {foreach $searchResult.pendingActions as $action}
                                                 <div style="margin: 4px 0; padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
                                                     {* Use pre-computed status from backend *}
                                                     <div style="color: {$action.statusColor};">
                                                         <strong>{$action.cAction}</strong>
                                                         <span class="badge {if $action.status == 'completed'}badge-success{elseif $action.status == 'broken'}badge-danger{else}badge-warning{/if}" style="font-size: 10px;">{$action.statusText}</span>
                                                     </div>
                                                     <div style="color: #6c757d; font-size: 11px; margin-top: 2px;">Created: {$action.dCreatedAt}</div>
                                                 </div>
                                             {/foreach}
                                         {/if}
                                        
                                        {* Completed Actions *}
                                        {if !empty($searchResult.completedActions)}
                                            {foreach $searchResult.completedActions as $action}
                                                <div style="margin: 4px 0; padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
                                                    <div style="color: {if $action.statusColor}{$action.statusColor}{else}#28a745{/if};">
                                                        <strong>{$action.cAction}</strong>
                                                        <span class="badge badge-success" style="font-size: 10px;">{if $action.statusText}{$action.statusText}{else}completed{/if}</span>
                                                    </div>
                                                    <div style="color: #6c757d; font-size: 11px; margin-top: 2px;">Completed: {$action.dCreatedAt}</div>
                                                </div>
                                            {/foreach}
                                        {/if}
                                    </div>
                                </div>

                                 {* Order Action Buttons - Only if broken actions exist *}
                                 {assign var="hasBrokenActions" value=false}
                                 {assign var="brokenActionsList" value=[]}
                                 {if !empty($searchResult.pendingActions)}
                                     {foreach $searchResult.pendingActions as $action}
                                         {* Use pre-computed status from backend *}
                                         {if $action.status == 'broken'}
                                             {assign var="hasBrokenActions" value=true}
                                             {assign var="brokenActionsList" value=$brokenActionsList|array_merge:[$action]}
                                         {/if}
                                     {/foreach}
                                 {/if}

                                {if $hasBrokenActions}
                                    <div class="mb-3" style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #dc3545; border-radius: 0 4px 4px 0;">
                                        <h6 class="mb-3" style="color: #495057;">Order Actions</h6>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                            <form method="post" style="display: inline;">
                                                {$token}
                                                <input type="hidden" name="retry_broken" value="1">
                                                <input type="hidden" name="order_id" value="{$searchResult.order->kBestellung}">
                                                 <button type="submit" class="btn btn-sm btn-outline-success"
                                                          onclick="return confirm('Are you sure you want to retry all broken actions for this order? This will attempt to process them immediately.');"
                                                          style="font-size: 11px; padding: 4px 8px;">
                                                     <i class="fas fa-redo"></i> Retry Broken Actions
                                                 </button>
                                            </form>

                                             {foreach $brokenActionsList as $action}
                                                 <form method="post" style="display: inline;">
                                                     {$token}
                                                     <input type="hidden" name="remove_action" value="1">
                                                     <input type="hidden" name="order_id" value="{$searchResult.order->kBestellung}">
                                                     <input type="hidden" name="action_name" value="{$action.cAction}">
                                                      <button type="submit" class="btn btn-sm btn-outline-danger"
                                                               onclick="return confirm('Are you sure you want to remove the failed \'{$action.cAction}\' action? You are responsible for communicating the necessary changes to Axytos to ensure that further processing of the order is guaranteed. This action cannot be undone.');"
                                                               style="font-size: 11px; padding: 4px 8px;">
                                                          <i class="fas fa-trash"></i> Remove Broken Action
                                                      </button>
                                                 </form>
                                             {/foreach}
                                        </div>
                                    </div>
                                {/if}
                            {/if}

                            {if !empty($searchResult.actionLogs)}
                                <div class="mb-0">
                                    <h6 class="mb-2" style="color: #495057;">Action Log</h6>
                                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th style="width: 140px;">Time</th>
                                                    <th>Level</th>
                                                    <th>Action</th>
                                                    <th>Message</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $searchResult.actionLogs as $log}
                                                    <tr>
                                                        <td style="white-space: nowrap;">{$log.processedAt}</td>
                                                        <td>
                                                            <span class="badge badge-{if $log.level == 'error'}danger{elseif $log.level == 'warning'}warning{elseif $log.level == 'info'}info{else}secondary{/if}">
                                                                {$log.level}
                                                            </span>
                                                        </td>
                                                        <td>{$log.action}</td>
                                                        <td>{$log.message}</td>
                                                    </tr>
                                                {/foreach}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            {/if}


                        </div>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Unified Orders Table -->
        {if !empty($ordersData)}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>{if $showActions}Orders with Pending and Broken Actions{else}Recent Orders{/if}</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order Number</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Pending/Broken Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $ordersData as $orderInfo}
                                            <tr style="cursor: pointer;" onclick="searchOrder({$orderInfo.order->kBestellung})">
                                                <td>{$orderInfo.order->cBestellNr}</td>
                                                <td>{$orderInfo.customerName}</td>
                                                <td>{$orderInfo.order->Status}</td>
                                                <td>
                                                    {assign var="hasAnyActions" value=false}
                                                    <div style="font-size: 12px;">
                                                        {* Show pending actions in yellow *}
                                                        {if $orderInfo.has_pending}
                                                            {foreach $orderInfo.pending_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                <div style="margin: 2px 0; color: #fd7e14;">
                                                                    <strong>{$action.action}</strong>
                                                                    <span class="badge badge-warning" style="font-size: 10px;">pending</span>
                                                                </div>
                                                            {/foreach}
                                                        {/if}
                                                        
                                                        {* Show retryable actions in yellow *}
                                                        {if $orderInfo.has_retryable}
                                                            {foreach $orderInfo.retryable_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                <div style="margin: 2px 0; color: #fd7e14;">
                                                                    <strong>{$action.action}</strong>
                                                                    <span class="badge badge-warning" style="font-size: 10px;">retry (failed {$action.failed_count}x)</span>
                                                                </div>
                                                            {/foreach}
                                                        {/if}
                                                        
                                                        {* Show broken actions in red *}
                                                        {if $orderInfo.has_broken}
                                                            {foreach $orderInfo.broken_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                <div style="margin: 2px 0; color: #dc3545;">
                                                                    <strong>{$action.action}</strong>
                                                                    <span class="badge badge-danger" style="font-size: 10px;">broken (failed {$action.failed_count}x)</span>
                                                                </div>
                                                            {/foreach}
                                                        {/if}
                                                    </div>
                                                    
                                                    {if !$hasAnyActions}
                                                        <span class="text-muted">-</span>
                                                    {/if}
                                                </td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {else}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Orders</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">No orders found.</p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}


    </div>
</div>



<script>
function searchOrder(orderId) {
    // Redirect to the same page with GET parameter
    var currentUrl = new URL(window.location);
    currentUrl.searchParams.set('order_search', orderId);
    window.location.href = currentUrl.toString();
}




</script>

<style>

</style>
