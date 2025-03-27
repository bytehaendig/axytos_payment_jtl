
<div class="card">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        <form method="post">
            {$token}
            <input type="hidden" name="save" value="1">

            <div class="form-group">
                <label for="api_key">API Key:</label>
                <input type="text" class="form-control" id="api_key" name="api_key" value="{$apiKey}">
                <small class="form-text text-muted">Your API key will be encrypted before storing in the database.</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="use_sandbox" name="use_sandbox" value="1" {if $useSandbox}checked{/if}>
                    <label class="custom-control-label" for="use_sandbox">Use Sandbox Mode</label>
                </div>
                <small class="form-text text-muted">Enable this for testing.</small>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
