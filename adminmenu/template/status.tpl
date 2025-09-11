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
                      <div class="card-header text-center status-card-header">
                          <strong>{__("Cron Status")}</strong>
                      </div>
                     <div class="card-body status-card-body">
                        <div class="status-info">
                             {if $statusOverview.cron_status.has_stuck}
                                 <strong>STUCK</strong><br>
                                 <span>{$statusOverview.cron_status.stuck_count} job(s)</span>
                             {else}
                                 <strong>{__("Last Run:")}</strong>
                                 {if $statusOverview.last_cron_run}
                                     {$statusOverview.last_cron_run|date_format:"%m/%d %H:%M"}
                                 {else}
                                     {__("Never")}
                                 {/if}
                             {/if}
                        </div>
                         <div class="status-info {if $statusOverview.cron_status.has_stuck}mb-2{/if}">
                             <strong>{__("Next Run:")}</strong>
                             {if $statusOverview.next_cron_run}
                                 {$statusOverview.next_cron_run|date_format:"%m/%d %H:%M"}
                             {else}
                                 {__("Unknown")}
                             {/if}
                         </div>
                        {if $statusOverview.cron_status.has_stuck}
                            <form method="post" style="margin-top: auto;">
                                {$token}
                                <input type="hidden" name="reset_stuck_cron" value="1">
                                  <button type="submit" class="btn btn-sm btn-warning btn-status"
                                          data-confirm-message="{__("Are you sure you want to reset stuck cron jobs? This will set their isRunning status to 0.")}"
                                          onclick="return confirmAction(this);">
                                     <i class="fas fa-redo"></i> {__("Reset")}
                                 </button>
                            </form>
                        {/if}
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                 <div class="card text-white bg-primary flex-fill">
                      <div class="card-header text-center status-card-header">
                          <strong>{__("Total Orders")}</strong>
                      </div>
                      <div class="card-body text-center d-flex flex-column justify-content-center status-card-body-compact">
                         <h2 style="margin-bottom: 5px;">{$statusOverview.total_orders}</h2>
                         <small style="margin-bottom: 8px;">{__("Orders with actions")}</small>
                        <form method="get" style="margin-top: auto;">
                            <div class="input-group input-group-sm">
                                  <input type="text" class="form-control form-control-sm" id="order_search" name="order_search"
                                         placeholder="{__("Order ID/Number")}"
                                         value="{if isset($smarty.get.order_search)}{$smarty.get.order_search|escape}{/if}">
                                <div class="input-group-append">
                                     <button type="submit" class="btn btn-sm btn-light btn-status">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                 <div class="card text-white flex-fill {if $statusOverview.pending_orders > 0}card-bg-warning{else}card-bg-success{/if}">
                      <div class="card-header text-center status-card-header">
                          <strong>{__("Pending")}</strong>
                      </div>
                      <div class="card-body text-center d-flex flex-column justify-content-center status-card-body-compact">
                         <h2 style="margin-bottom: 5px;">{$statusOverview.pending_orders}</h2>
                         <small style="margin-bottom: 8px;">{__("Actions waiting")}</small>
                        <form method="post" style="margin-top: auto;">
                            {$token}
                            <input type="hidden" name="process_pending" value="1">
                              <button type="submit" class="btn btn-sm btn-light btn-status"
                                      {if $statusOverview.pending_orders == 0}disabled{/if}
                                      data-confirm-message="{__("Are you sure you want to process all pending actions?")}"
                                      onclick="return confirmAction(this);">
                                 <i class="fas fa-play"></i> {__("Process All")}
                             </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-3 d-flex">
                 <div class="card text-white flex-fill {if $statusOverview.broken_orders > 0}card-bg-danger{else}card-bg-success{/if}">
                      <div class="card-header text-center status-card-header">
                          <strong>{__("Broken")}</strong>
                      </div>
                      <div class="card-body text-center d-flex flex-column justify-content-center status-card-body-compact">
                         <h2 style="margin-bottom: 5px;">{$statusOverview.broken_orders}</h2>
                         <small>{__("Need attention")}</small>
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
                             <h5>{__("Order Details:")} {$searchResult.order->cBestellNr}</h5>
                         </div>
                         <div class="card-body status-card-body">
                             <div class="mb-3 section-divider">
                                 <p class="mb-1"><strong>{__("Customer:")}</strong>
                                     {$searchResult.customerName}
                                     {if $searchResult.customerEmail}
                                         ({$searchResult.customerEmail})
                                     {/if}
                                 </p>
                                 <p class="mb-1"><strong>{__("Total:")}</strong> {$searchResult.order->fGesamtsumme|number_format:2:',':"."} EUR</p>
                                 <p class="mb-1"><strong>{__("Status:")}</strong> {$searchResult.order->Status}</p>
                                 <p class="mb-0"><strong>{__("Date:")}</strong> {$searchResult.order->dErstellt}</p>
                             </div>

                            {* Combined Actions List *}
                            {assign var="hasActions" value=false}
                            {if !empty($searchResult.pendingActions) || !empty($searchResult.completedActions)}
                                {assign var="hasActions" value=true}
                              <div class="mb-3 section-divider">
                                     <h6 class="mb-2" style="color: #495057;">{__("Actions")}</h6>
                                                     <div class="actions-list">
                                         {* Pending and Failed Actions *}
                                         {if !empty($searchResult.pendingActions)}
                                             {foreach $searchResult.pendingActions as $action}
                                                  <div class="action-item">
                                                      {* Use pre-computed status from backend *}
                                                      <div class="status-{$action.status}">
                                                          <strong>{$action.cAction}</strong>
                                                          <span class="badge {if $action.status == 'completed'}badge-success{elseif $action.status == 'broken'}badge-danger{else}badge-warning{/if} badge-small">{$action.statusText}</span>
                                                      </div>
                                                       <div class="action-timestamp">{__("Created:")} {$action.dCreatedAt}</div>
                                                  </div>
                                             {/foreach}
                                         {/if}
                                        
                                        {* Completed Actions *}
                                        {if !empty($searchResult.completedActions)}
                                            {foreach $searchResult.completedActions as $action}
                                                 <div class="action-item">
                                                     <div class="status-completed">
                                                         <strong>{$action.cAction}</strong>
                                                          <span class="badge badge-success badge-small">{if $action.statusText}{$action.statusText}{else}{__("completed")}{/if}</span>
                                                     </div>
                                                     <div class="action-timestamp">Completed: {$action.dCreatedAt}</div>
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
                                     <div class="mb-3 action-section">
                                         <h6 class="mb-3" style="color: #495057;">{__("Order Actions")}</h6>
                                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                            <form method="post" style="display: inline;">
                                                {$token}
                                                <input type="hidden" name="retry_broken" value="1">
                                                <input type="hidden" name="order_id" value="{$searchResult.order->kBestellung}">
                                                   <button type="submit" class="btn btn-sm btn-outline-success btn-status"
                                                            data-confirm-message="{__("Are you sure you want to retry all broken actions for this order? This will attempt to process them immediately.")}"
                                                            onclick="return confirmAction(this);">
                                                      <i class="fas fa-redo"></i> {__("Retry Broken Actions")}
                                                  </button>
                                            </form>

                                             {foreach $brokenActionsList as $action}
                                                 <form method="post" style="display: inline;">
                                                     {$token}
                                                     <input type="hidden" name="remove_action" value="1">
                                                     <input type="hidden" name="order_id" value="{$searchResult.order->kBestellung}">
                                                     <input type="hidden" name="action_name" value="{$action.cAction}">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-status"
                                                                 data-confirm-message="{sprintf(__("Are you sure you want to remove the failed '%s' action? You are responsible for communicating the necessary changes to Axytos to ensure that further processing of the order is guaranteed. This action cannot be undone."), $action.cAction)}"
                                                                 onclick="return confirmAction(this);">
                                                           <i class="fas fa-trash"></i> {__("Remove Broken Action")}
                                                       </button>
                                                 </form>
                                             {/foreach}
                                        </div>
                                    </div>
                                {/if}
                            {/if}

                            {if !empty($searchResult.actionLogs)}
                                <div class="mb-0">
                                     <h6 class="mb-2" style="color: #495057;">{__("Action Log")}</h6>
                                     <div class="table-responsive action-log-container">
                                        <table class="table table-sm">
                                            <thead>
                                                 <tr>
                                                     <th class="time-column">{__("Time")}</th>
                                                     <th>{__("Level")}</th>
                                                     <th>{__("Action")}</th>
                                                     <th>{__("Message")}</th>
                                                 </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $searchResult.actionLogs as $log}
                                                    <tr>
                                                         <td class="text-nowrap">{$log.processedAt}</td>
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
                             <h5>{if $showActions}{__("Orders with Pending and Broken Actions")}{else}{__("Recent Orders")}{/if}</h5>
                         </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                     <thead>
                                         <tr>
                                             <th>{__("Order Number")}</th>
                                             <th>{__("Customer")}</th>
                                             <th>{__("Status")}</th>
                                             <th>{__("Pending/Broken Actions")}</th>
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
                                                    <div class="actions-list">
                                                        {* Show pending actions in yellow *}
                                                        {if $orderInfo.has_pending}
                                                            {foreach $orderInfo.pending_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                  <div class="action-item status-pending">
                                                                      <strong>{$action.action}</strong>
                                                                      <span class="badge badge-warning badge-small">{__("pending")}</span>
                                                                  </div>
                                                            {/foreach}
                                                        {/if}
                                                        
                                                        {* Show retryable actions in yellow *}
                                                        {if $orderInfo.has_retryable}
                                                            {foreach $orderInfo.retryable_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                  <div class="action-item status-retryable">
                                                                      <strong>{$action.action}</strong>
                                                                      <span class="badge badge-warning badge-small">{sprintf(__("retry (failed %dx)"), $action.failed_count)}</span>
                                                                  </div>
                                                            {/foreach}
                                                        {/if}
                                                        
                                                        {* Show broken actions in red *}
                                                        {if $orderInfo.has_broken}
                                                            {foreach $orderInfo.broken_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                  <div class="action-item status-broken">
                                                                      <strong>{$action.action}</strong>
                                                                      <span class="badge badge-danger badge-small">{sprintf(__("broken (failed %dx)"), $action.failed_count)}</span>
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
                             <h5>{__("Orders")}</h5>
                         </div>
                         <div class="card-body">
                             <p class="text-muted">{__("No orders found.")}</p>
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

function confirmAction(button) {
    var message = button.getAttribute('data-confirm-message');
    return confirm(message);
}




</script>

<style>
/* Status card styling */
.status-card-header {
    padding: 8px;
    background-color: rgba(0,0,0,0.1);
}

.status-card-body {
    padding: 15px;
}

.status-card-body-compact {
    padding: 10px;
}

/* Button styling */
.btn-status {
    font-size: 11px;
    padding: 4px 8px;
}

/* Action item styling */
.action-item {
    margin: 4px 0;
    padding: 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
}

.action-timestamp {
    color: #6c757d;
    font-size: 11px;
    margin-top: 2px;
}

/* Status colors */
.status-pending { color: #fd7e14; }
.status-retryable { color: #fd7e14; }
.status-broken { color: #dc3545; }
.status-completed { color: #28a745; }

/* Card background colors */
.card-bg-warning { background-color: #fd7e14; }
.card-bg-success { background-color: #28a745; }
.card-bg-danger { background-color: #dc3545; }

/* Section styling */
.section-divider {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.action-section {
    padding: 15px;
    background-color: #f8f9fa;
    border-left: 4px solid #dc3545;
    border-radius: 0 4px 4px 0;
}

/* Badge helper classes for consistent styling */
.badge-small {
    font-size: 10px;
}

/* Status info styling */
.status-info {
    font-size: 1.0em;
    margin-bottom: 8px;
}

/* Table column styling */
.time-column {
    width: 140px;
}

/* Action log container */
.action-log-container {
    max-height: 300px;
    overflow-y: auto;
}

/* Actions list styling */
.actions-list {
    font-size: 12px;
}
</style>
