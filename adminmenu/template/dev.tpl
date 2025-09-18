<div class="card" style="box-shadow: none;">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text|nl2br}
                </div>
            {/foreach}
        {/if}

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>🚀 Development Tools - Add Pending/Failed Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-4">
                            <strong>Purpose:</strong> This tool adds pending actions with different states for testing purposes.<br>
                            <strong>failed_count 0:</strong> Fresh pending action<br>
                            <strong>failed_count 1-2:</strong> Retry-able failed actions<br>
                            <strong>failed_count {$devInfo.max_retries}+:</strong> Broken actions (show retry button)
                        </div>

                        <!-- Add Pending Action Form -->
                        <div class="card bg-light mb-4">
                            <div class="card-header">
                                <h6>Add Test Action to Order</h6>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    {$token}
                                    <input type="hidden" name="add_pending_action" value="1">
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="order_number">Order Number:</label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="order_number" 
                                                       name="order_number" 
                                                       value="{$smarty.post.order_number|default:''}" 
                                                       required 
                                                       placeholder="e.g. 20250101-001">
                                                <small class="form-text text-muted">Enter order number (also accepts order ID)</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="action">Action Name:</label>
                                                <select class="form-control" id="action" name="action" required>
                                                    <option value="">Select action...</option>
                                                    {foreach $actionTypes as $value => $label}
                                                        <option value="{$value}" {if $smarty.post.action == $value}selected{/if}>
                                                            {$label}
                                                        </option>
                                                    {/foreach}
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="failed_count">Failed Count:</label>
                                                <select class="form-control" id="failed_count" name="failed_count" required>
                                                    <option value="">Select failed count...</option>
                                                    <option value="0" {if $smarty.post.failed_count == '0'}selected{/if}>0 - Fresh pending action</option>
                                                    <option value="1" {if $smarty.post.failed_count == '1'}selected{/if}>1 - Failed once (retry-able)</option>
                                                    <option value="2" {if $smarty.post.failed_count == '2'}selected{/if}>2 - Failed twice (retry-able)</option>
                                                    <option value="{$devInfo.max_retries}" {if $smarty.post.failed_count == $devInfo.max_retries}selected{/if}>{$devInfo.max_retries} - Failed {$devInfo.max_retries}x (broken, shows retry button)</option>
                                                    {assign var="max_plus_one" value=$devInfo.max_retries+1}
                                                    {assign var="max_plus_two" value=$devInfo.max_retries+2}
                                                    <option value="{$max_plus_one}" {if $smarty.post.failed_count == $max_plus_one}selected{/if}>{$max_plus_one} - Failed {$max_plus_one}x (broken, shows retry button)</option>
                                                    <option value="{$max_plus_two}" {if $smarty.post.failed_count == $max_plus_two}selected{/if}>{$max_plus_two} - Failed {$max_plus_two}x (broken, shows retry button)</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>&nbsp;</label>
                                                <button type="submit" class="btn btn-primary form-control">
                                                    <i class="fas fa-plus"></i> Add Pending Action
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Development Info -->
                        <div class="card bg-light">
                            <div class="card-header">
                                <h6>Development Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless">
                                        <tbody>
                                            <tr>
                                                <td><strong>Plugin Version:</strong></td>
                                                <td>{$devInfo.plugin_version}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Plugin Path:</strong></td>
                                                <td><code>{$devInfo.plugin_path}</code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Current Time:</strong></td>
                                                <td>{$devInfo.current_time}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>PHP Version:</strong></td>
                                                <td>{$devInfo.php_version}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Max Retries:</strong></td>
                                                <td><span class="badge badge-info">{$devInfo.max_retries}</span></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Database:</strong></td>
                                                <td>
                                                    <span class="badge {if $devInfo.db_connection == 'Connected'}badge-success{else}badge-danger{/if}">
                                                        {$devInfo.db_connection}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <small class="text-muted">
                                <i class="fas fa-exclamation-triangle"></i> 
                                 <strong>Note:</strong> This is a testing tool for development/staging environments only.
                                 Use only with orders that use the Axytos payment method.
                             </small>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     </div>
 </div>

<style>
/* Improve label readability by making them darker */
label {
    color: #495057 !important;
    font-weight: 600 !important;
}

.form-text.text-muted {
    color: #6c757d !important;
}

.card-header h5 {
    color: #495057 !important;
    font-weight: 600 !important;
}

strong {
    color: #495057 !important;
    font-weight: 600 !important;
}
</style>