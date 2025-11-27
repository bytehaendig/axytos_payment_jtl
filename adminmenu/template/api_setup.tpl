
{if !empty($messages)}
    {foreach $messages as $message}
        <div class="alert alert-{$message.type}">
            {$message.text}
        </div>
    {/foreach}
{/if}

<form method="post" id="settings-form">
    {$token}
    <input type="hidden" name="save" value="1">

    <!-- API Configuration Card -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                {__('API Configuration')}
            </h5>
        </div>
        <small class="form-text text-muted ml-3">{__('Used for communication between Axytos Payment plugin and the Axytos API.')}</small>
        <div class="card-body">
            <div class="form-group">
                <label for="api_key">{__('API Key:')}</label>
                <input type="text" class="form-control" id="api_key" name="api_key" value="{$apiKey}">
                <small class="form-text text-muted">{__('Your API key will be encrypted before storing in the database.')}</small>
            </div>

            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="use_sandbox" name="use_sandbox" value="1" {if $useSandbox}checked{/if}>
                    <label class="custom-control-label" for="use_sandbox">{__('Use Sandbox Mode')}</label>
                </div>
                <small class="form-text text-muted">{__('Enable this for testing.')}</small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">{__('Save Settings')}</button>
        </div>
    </div>
</form>



<style>
/* Improve label readability by making them darker */
label {
    color: #495057 !important;
    font-weight: 600 !important;
}

.form-text.text-muted {
    color: #6c757d !important;
}

/* Make card headers more readable */
.card-header h5 {
    color: #495057 !important;
    font-weight: 600 !important;
}


</style>
