<div class="card">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        <!-- Status Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-info" style="height: 120px;">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Cron Status</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center" style="padding: 10px;">
                        <div style="font-size: 0.9em;">
                            {if $statusOverview.last_cron_run}
                                {$statusOverview.last_cron_run|date_format:"%m/%d %H:%M"}
                            {else}
                                Never
                            {/if}
                        </div>
                        <hr style="margin: 5px 0; border-color: rgba(255,255,255,0.3);">
                        <div style="font-size: 0.8em;">
                            <strong>Next:</strong>
                            {if $statusOverview.next_cron_run}
                                {$statusOverview.next_cron_run|date_format:"%m/%d %H:%M"}
                            {else}
                                Unknown
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-primary" style="height: 120px;">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Total Orders</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <h2 style="margin-bottom: 5px;">{$statusOverview.total_orders}</h2>
                        <small>Orders with actions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background-color: {if $statusOverview.pending_orders > 0}#fd7e14{else}#28a745{/if}; height: 120px;">
                    <div class="card-header text-center" style="padding: 8px; background-color: rgba(0,0,0,0.1);">
                        <strong>Pending</strong>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <h2 style="margin-bottom: 5px;">{$statusOverview.pending_orders}</h2>
                        <small>Actions waiting</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background-color: {if $statusOverview.broken_orders > 0}#dc3545{else}#28a745{/if}; height: 120px;">
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

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form method="post" class="mb-2">
                    {$token}
                    <input type="hidden" name="process_pending" value="1">
                    <button type="submit" class="btn btn-success" 
                            {if $statusOverview.pending_orders == 0}disabled{/if}>
                        <i class="fas fa-play"></i> Process All Pending Actions
                    </button>
                </form>
            </div>
        </div>

        <!-- Order Search -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Search Order</h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            {$token}
                            <input type="hidden" name="search_order" value="1">
                            <div class="form-group">
                                <label for="order_search">Order ID or Number:</label>
                                <input type="text" class="form-control" id="order_search" name="order_search" 
                                       placeholder="Enter order ID or order number">
                            </div>
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Results -->
        {if isset($searchResult) && $searchResult}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Order Details: #{$searchResult.order->kBestellung} ({$searchResult.order->cBestellNr})</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Customer:</strong> {$searchResult.order->cVorname} {$searchResult.order->cNachname}</p>
                                    <p><strong>Total:</strong> {$searchResult.order->fGesamtsumme|number_format:2:',':"."} EUR</p>
                                    <p><strong>Status:</strong> {$searchResult.order->cStatus}</p>
                                    <p><strong>Date:</strong> {$searchResult.order->dErstellt}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Pending Actions:</strong> {count($searchResult.pendingActions)}</p>
                                    <p><strong>Completed Actions:</strong> {count($searchResult.completedActions)}</p>
                                </div>
                            </div>

                            {if !empty($searchResult.pendingActions)}
                                <div class="mt-3">
                                    <h6>Pending Actions</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Created</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $searchResult.pendingActions as $action}
                                                    <tr>
                                                        <td>{$action.cAction}</td>
                                                        <td>{$action.dCreatedAt}</td>
                                                        <td>
                                                            {if empty($action.dFailedAt)}
                                                                <span class="badge badge-warning">Pending</span>
                                                            {else}
                                                                <span class="badge badge-danger">
                                                                    Failed {$action.nFailedCount}x
                                                                </span>
                                                            {/if}
                                                        </td>
                                                        <td>
                                                            {if !empty($action.dFailedAt) && $action.nFailedCount >= 3}
                                                                <form method="post" style="display:inline">
                                                                    {$token}
                                                                    <input type="hidden" name="retry_broken" value="1">
                                                                    <input type="hidden" name="order_id" value="{$searchResult.order->kBestellung}">
                                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Retry</button>
                                                                </form>
                                                            {/if}
                                                        </td>
                                                    </tr>
                                                {/foreach}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            {/if}

                            {if !empty($searchResult.completedActions)}
                                <div class="mt-3">
                                    <h6>Completed Actions</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $searchResult.completedActions as $action}
                                                    <tr>
                                                        <td>{$action.cAction}</td>
                                                        <td>{$action.dProcessedAt}</td>
                                                    </tr>
                                                {/foreach}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            {/if}

                            {if !empty($searchResult.actionLogs)}
                                <div class="mt-3">
                                    <h6>Action Log</h6>
                                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Time</th>
                                                    <th>Action</th>
                                                    <th>Level</th>
                                                    <th>Message</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {foreach $searchResult.actionLogs as $log}
                                                    <tr>
                                                        <td>{$log.processedAt}</td>
                                                        <td>{$log.action}</td>
                                                        <td>
                                                            <span class="badge badge-{if $log.level == 'error'}danger{elseif $log.level == 'warning'}warning{elseif $log.level == 'info'}info{else}secondary{/if}">
                                                                {$log.level}
                                                            </span>
                                                        </td>
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

        <!-- Recent Orders -->
        {if !empty($recentOrders)}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Recent Orders with Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Order Number</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Pending</th>
                                            <th>Completed</th>
                                            <th>Issues</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $recentOrders as $orderInfo}
                                            <tr>
                                                <td>#{$orderInfo.order->kBestellung}</td>
                                                <td>{$orderInfo.order->cBestellNr}</td>
                                                <td>{$orderInfo.order->cVorname} {$orderInfo.order->cNachname}</td>
                                                <td>{$orderInfo.order->fGesamtsumme|number_format:2:',':"."} EUR</td>
                                                <td>{$orderInfo.order->cStatus}</td>
                                                <td>
                                                    {if $orderInfo.pending_count > 0}
                                                        <span class="badge badge-warning">{$orderInfo.pending_count}</span>
                                                    {else}
                                                        <span class="badge badge-success">0</span>
                                                    {/if}
                                                </td>
                                                <td>
                                                    <span class="badge badge-success">{$orderInfo.completed_count}</span>
                                                </td>
                                                <td>
                                                    {if $orderInfo.has_broken}
                                                        <span class="badge badge-danger">BROKEN</span>
                                                    {else}
                                                        <span class="badge badge-success">OK</span>
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
        {/if}

        <!-- Broken Orders -->
        {if !empty($brokenOrders)}
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5>Orders Requiring Attention</h5>
                        </div>
                        <div class="card-body">
                            {foreach $brokenOrders as $orderInfo}
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>Order #{$orderInfo.order->kBestellung} ({$orderInfo.order->cBestellNr})</strong>
                                        - {$orderInfo.order->cVorname} {$orderInfo.order->cNachname}
                                        <form method="post" style="display:inline; float:right;">
                                            {$token}
                                            <input type="hidden" name="retry_broken" value="1">
                                            <input type="hidden" name="order_id" value="{$orderInfo.order->kBestellung}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Retry All</button>
                                        </form>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Action</th>
                                                        <th>Failed Count</th>
                                                        <th>Last Failed</th>
                                                        <th>Reason</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {foreach $orderInfo.broken_actions as $action}
                                                        <tr>
                                                            <td>{$action->cAction}</td>
                                                            <td><span class="badge badge-danger">{$action->nFailedCount}</span></td>
                                                            <td>{$action->dFailedAt}</td>
                                                            <td>
                                                                {if !empty($action->cFailReason)}
                                                                    <small>{$action->cFailReason|truncate:100}</small>
                                                                {else}
                                                                    <small class="text-muted">No specific reason</small>
                                                                {/if}
                                                            </td>
                                                        </tr>
                                                    {/foreach}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>