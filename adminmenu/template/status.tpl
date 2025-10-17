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
                 <div class="card text-white {if $statusOverview.cron_status.has_stuck}bg-danger{elseif $statusOverview.cron_status.is_overdue}card-bg-warning{else}bg-info{/if} flex-fill">
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
                                       {$statusOverview.last_cron_run|germanDate}
                                   {else}
                                      {__("Never")}
                                  {/if}
                             {/if}
                        </div>
                         <div class="status-info {if $statusOverview.cron_status.has_stuck}mb-2{/if}">
                             <strong>{__("Next Run:")}</strong>
                               {if $statusOverview.next_cron_run}
                                   {$statusOverview.next_cron_run|germanDate}
                                   {if $statusOverview.cron_status.is_overdue}
                                       <span style="color: #fff; font-weight: bold;"> ⚠ {__("OVERDUE")}</span>
                                   {/if}
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
                          <small style="margin-bottom: 8px;">{__("Need attention")}</small>
                        <div style="margin-top: auto; min-height: 31px;">
                            {* Placeholder for consistent spacing with other cards *}
                        </div>
                    </div>
                </div>
            </div>
        </div>





        <!-- Search Results Modal -->
        {if isset($searchResult) && $searchResult}
            <!-- Modal Backdrop -->
            <div class="modal-backdrop show" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1040;"></div>

            <div class="modal fade show" id="orderModal" tabindex="-1" role="dialog" aria-labelledby="orderModalLabel" aria-hidden="false" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; display: flex; align-items: flex-start; justify-content: center; padding-top: 2rem;">
                <div class="modal-dialog modal-xl" role="document" style="width: 60%; max-width: 60%;">
                    <div class="modal-content">
                         <div class="modal-header">
                             <h5 class="modal-title" id="orderModalLabel">{__("Order Details:")} {$searchResult.order->cBestellNr}</h5>
                             <button type="button" class="close" onclick="closeOrderModal({$menuID})" aria-label="Close">
                                 <span aria-hidden="true">&times;</span>
                             </button>
                         </div>
                         <div class="card-body status-card-body">
                             <div class="mb-3 section-divider">
                                 <p class="mb-1"><strong>{__("Customer:")}</strong>
                                     {$searchResult.customerName}
                                     {if $searchResult.customerEmail}
                                         ({$searchResult.customerEmail})
                                     {/if}
                                 </p>
                                  <p class="mb-1"><strong>{__("Total:")}</strong> {$searchResult.order->fGesamtsumme|number_format:2:',':"."} {if $searchResult.order->Waehrung && $searchResult.order->Waehrung->cName}{$searchResult.order->Waehrung->cName}{else}€{/if}</p>
                                 <p class="mb-1"><strong>{__("Status:")}</strong> {$searchResult.order->Status}</p>
                                   <p class="mb-0"><strong>{__("Date:")}</strong> {$searchResult.order->dErstellt|germanDate}</p>
                             </div>

                             {* Compact Timeline *}
                             <div class="mb-3 section-divider">
                                 <h6 class="mb-2" style="color: #495057;">{__("Actions Overview")}</h6>
                                 {if !empty($searchResult.timeline)}
                                     <div class="compact-timeline-container" style="max-height: calc(100vh - 20rem); overflow-y: auto;">
                                        {foreach $searchResult.timeline as $index => $action}
                                            <div class="compact-timeline-entry status-{$action.status}">
                                                <div class="timeline-action-header" data-toggle="collapse" data-target="#action-logs-{$index}" style="cursor: pointer;">
                                                    <div class="action-summary">
                                                        <div class="action-info">
                                                            <span class="action-icon">
                                                                 <i class="fas fa-circle action-status-icon action-status-{$action.status}"></i>
                                                            </span>
                                                            <span class="action-name">{$action.action}</span>
                                                              <span class="action-status badge badge-small log-level-{$action.level}">
                                                                  {$action.statusText}
                                                             </span>
                                                        </div>
                                                        <div class="action-meta">
                                                            <div class="action-timestamp-line">
                                                                <span class="action-timestamp-prominent">{$action.latest_timestamp|germanDate}</span>
                                                            </div>
                                                            {if $action.log_count > 0}
                                                                <div class="log-count-line">
                                                                    <i class="fas fa-chevron-down expand-icon-prominent"></i>
                                                                    <span class="log-count-text">{$action.log_count} {if $action.log_count == 1}{__("log entry")}{else}{__("log entries")}{/if}</span>
                                                                </div>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                    <div class="action-summary-message">
                                                        {$action.summary}
                                                    </div>
                                                </div>
                                                
                                                {if $action.log_count > 0}
                                                    <div class="collapse action-logs-section" id="action-logs-{$index}">
                                                        <div class="logs-container">
                                                            <div class="logs-header">
                                                                <small class="text-muted">{__("Detailed log entries for")} {$action.action}:</small>
                                                            </div>
                                                            {foreach $action.logs as $log}
                                                                <div class="log-entry level-{$log.level}">
                                                                    <div class="log-header">
                                                                         <span class="log-level badge badge-small log-level-{$log.level}">
                                                                            {$log.level}
                                                                        </span>
                                                                          <span class="log-timestamp">{$log.timestamp|germanDate:true:true}</span>
                                                                    </div>
                                                                    <div class="log-message">
                                                                        {$log.message}
                                                                    </div>
                                                                </div>
                                                            {/foreach}
                                                        </div>
                                                    </div>
                                                {/if}
                                            </div>
                                        {/foreach}
                                    </div>
                                {else}
                                    <div class="text-center text-muted py-3">
                                        <p>{__("No actions found for this order")}</p>
                                    </div>
                                {/if}
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
                                                 <th>{__("Date")}</th>
                                                 <th>{__("Total")}</th>
                                                 <th>{__("Status")}</th>
                                                 <th>{__("Pending/Broken Actions")}</th>
                                             </tr>
                                         </thead>
                                    <tbody>
                                        {foreach $ordersData as $orderInfo}
                                            <tr class="status-row" style="cursor: pointer;" onclick="searchOrder({$orderInfo.order->kBestellung})">
                                                <td>{$orderInfo.order->cBestellNr}</td>
                                                <td>{$orderInfo.customerName}</td>
                                                 <td data-order="{$orderInfo.order->dErstellt|strtotime}">{$orderInfo.order->dErstellt|germanDate:false}</td>
                                                 <td>{$orderInfo.order->fGesamtsumme|number_format:2:',':"."} {if $orderInfo.order->Waehrung && $orderInfo.order->Waehrung->cName}{$orderInfo.order->Waehrung->cName}{else}€{/if}</td>
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
                                                                        <span class="badge badge-warning badge-small">{$action.status_text}</span>
                                                                  </div>
                                                            {/foreach}
                                                        {/if}
                                                        
                                                        {* Show broken actions in red *}
                                                        {if $orderInfo.has_broken}
                                                            {foreach $orderInfo.broken_actions as $action}
                                                                {assign var="hasAnyActions" value=true}
                                                                  <div class="action-item status-broken">
                                                                      <strong>{$action.action}</strong>
                                                                        <span class="badge badge-danger badge-small">{$action.status_text}</span>
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

        <form method="post" class="mt-4">
            {$token}
            <input type="hidden" name="save_status" value="1">
            <input type="hidden" name="tab" value="Status">
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">{__('Refresh Data')}</button>
            </div>
        </form>

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

function closeOrderModal(menuID) {
    // Set order_search to empty to stay on Status tab without showing modal
    var currentUrl = new URL(window.location);
    currentUrl.searchParams.set('order_search', '');
    window.location.href = currentUrl.toString();
}

// Add click handler to backdrop to close modal
document.addEventListener('DOMContentLoaded', function() {
    var backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeOrderModal({$menuID});
        });
    }
});

// Handle expand/collapse for action logs
$(document).ready(function() {
    $('.timeline-action-header').on('click', function() {
        var $this = $(this);
        var target = $this.attr('data-target');
        var $icon = $this.find('.expand-icon-prominent, .expand-icon');
        var $collapse = $(target);
        
        // Toggle collapse
        $collapse.collapse('toggle');
        
        // Update icon
        $collapse.on('shown.bs.collapse', function() {
            $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        });
        
        $collapse.on('hidden.bs.collapse', function() {
            $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        });
    });
});




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

/* Status row hover styling */
.status-row:hover {
    background-color: #f8f9fa !important;
}

/* Compact Timeline styling */
.compact-timeline-container {
    border-left: 3px solid #dee2e6;
    padding-left: 15px;
    max-height: calc(100vh - 20rem);
    overflow-y: auto;
}

.compact-timeline-entry {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.compact-timeline-entry:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.timeline-action-header {
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    user-select: none;
}

.timeline-action-header:hover {
    background: #e9ecef;
}

.action-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.action-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-icon {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.action-name {
    font-weight: bold;
    color: #495057;
    text-transform: capitalize;
}

.action-meta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12px;
    color: #6c757d;
}

.action-timestamp-line {
    display: flex;
    align-items: center;
}

.action-timestamp-prominent {
    font-family: monospace;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
}

.log-count-line {
    display: flex;
    align-items: center;
    gap: 6px;
}

.expand-icon-prominent {
    font-size: 14px;
    font-weight: bold;
    color: #495057;
    transition: transform 0.2s ease;
}

.log-count-text {
    font-weight: 500;
    color: #6c757d;
}

.expand-icon {
    transition: transform 0.2s ease;
}

.action-summary-message {
    font-size: 13px;
    color: #495057;
    margin: 0;
}

/* Action Logs Section */
.action-logs-section {
    border-top: 1px solid #dee2e6;
}

.logs-container {
    padding: 0;
}

.logs-header {
    padding: 10px 15px 5px;
    background: #f1f3f4;
}

.log-entry {
    padding: 8px 15px;
    border-bottom: 1px solid #eee;
    font-size: 12px;
}

.log-entry:last-child {
    border-bottom: none;
}

.log-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.log-timestamp {
    margin-left: auto;
    font-family: monospace;
    color: #6c757d;
}

.log-message {
    color: #495057;
    line-height: 1.4;
}

/* Status-based styling for action entries */
.compact-timeline-entry.status-completed {
    border-left: 4px solid #28a745;
}

.compact-timeline-entry.status-broken {
    border-left: 4px solid #dc3545;
}

.compact-timeline-entry.status-retryable {
    border-left: 4px solid #ffc107;
}

.compact-timeline-entry.status-pending {
    border-left: 4px solid #17a2b8;
}

/* Modal styling for order details */
.modal-backdrop {
    backdrop-filter: blur(2px);
}

.modal-content {
    border: none;
    border-radius: 8px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
}

.modal-title {
    font-weight: 600;
    color: #495057;
}

.modal .close {
    padding: 0;
    margin: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s ease;
}

.modal .close:hover {
    background-color: rgba(0, 0, 0, 0.1);
}

.modal .close span {
    font-size: 24px;
    line-height: 1;
}

/* Log level badge colors */
.log-level-error { background-color: #dc3545; color: white; }
.log-level-warning { background-color: #ffc107; color: black; }
.log-level-info { background-color: #17a2b8; color: white; }
.log-level-debug { background-color: #6c757d; color: white; }

/* Action status icon colors */
.action-status-completed::before { content: "\f058"; color: #28a745; } /* check-circle */
.action-status-broken::before { content: "\f06a"; color: #dc3545; } /* exclamation-circle */
.action-status-retryable::before { content: "\f071"; color: #ffc107; } /* exclamation-triangle */
.action-status-pending::before { content: "\f017"; color: #17a2b8; } /* clock */

/* Improve label readability by making them darker */
strong {
    color: #495057 !important;
    font-weight: 600 !important;
}

.card-header h5 {
    color: #495057 !important;
    font-weight: 600 !important;
}

.modal-title {
    color: #495057 !important;
    font-weight: 600 !important;
}

</style>
