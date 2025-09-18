
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
    </div>

    <!-- Webhook Configuration Card -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-webhook"></i> {__('Webhook Configuration')}
            </h5>
        </div>
        <small class="form-text text-muted ml-3">{__('Used for automated data transfer between JTL WaWi and the Axytos Payment plugin.')}</small>
        <div class="card-body">
            <div class="form-group">
                <label for="webhook_api_key">{__('Webhook API Key:')}</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="webhook_api_key" name="webhook_api_key" value="{$webhookApiKey}">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="generate-webhook-key" title="Generate Secure Key">
                            <i class="fas fa-key"></i> {__('Generate')}
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">{__('Your webhook API key will be encrypted before storing in the database.')}</small>
            </div>
        </div>
    </div>

    <!-- Shared Save Button -->
    <div class="form-group mt-4">
        <button type="submit" class="btn btn-primary">{__('Save Settings')}</button>
    </div>
</form>

<!-- Hidden form for key generation -->
<form method="post" id="key-generation-form" style="display: none;">
    {$token}
    <input type="hidden" name="generate_key" value="1">
</form>




<script>
document.getElementById('generate-webhook-key').addEventListener('click', function() {
    const button = this;
    const originalHtml = button.innerHTML;

    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {__('Generating...')}';
    button.disabled = true;

    // Get form data
    const formData = new FormData(document.getElementById('key-generation-form'));

    // Submit via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Parse the response to extract the generated key
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Find the webhook key input in the response
        const generatedKeyInput = doc.querySelector('#webhook_api_key');
        if (generatedKeyInput) {
            // Update the current page's input field
            document.getElementById('webhook_api_key').value = generatedKeyInput.value;

            // Show success state
            button.innerHTML = '<i class="fas fa-check"></i> {__('Generated!')}';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');

            // Reset button after 2 seconds
            setTimeout(function() {
                button.innerHTML = originalHtml;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
                button.disabled = false;
            }, 2000);
        } else {
            // Fallback: reload the page if we can't parse the response
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error generating key:', error);
        // Fallback: reload the page
        window.location.reload();
    });
});
</script>

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
