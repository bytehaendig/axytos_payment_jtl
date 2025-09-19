
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

    <!-- Windows Automation Card -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                {__('Windows Automation')}
            </h5>
        </div>
        <small class="form-text text-muted ml-3">{__('Configure webhook key and generate Windows batch scripts for automated invoice data synchronization.')}</small>
        <div class="card-body">
            <div class="form-group">
                <label for="webhook_api_key">{__('Webhook API Key:')}</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="webhook_api_key" name="webhook_api_key" value="{$webhookApiKey}">
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary" id="generate-webhook-key" title="{__('Generate Secure Key')}">
                            <i class="fas fa-key"></i> {__('Generate')}
                        </button>
                    </div>
                </div>
                <small class="form-text text-muted">{__('Your webhook API key will be encrypted before storing in the database.')}</small>
            </div>
            {if !$automationConfig.ready}
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    {$automationConfig.validationMessage}
                </div>
            {else}
                <!-- Automation Configuration -->
                <div class="form-group">
                    <label for="schedule_time">{__('Schedule Time:')}</label>
                    <div class="row">
                        <div class="col-md-2">
                            <select class="form-control" id="schedule_time" name="schedule_time">
                                {foreach $automationConfig.scheduleOptions as $option}
                                    <option value="{$option.value}" {if $option.value == '17:00'}selected{/if}>
                                        {$option.label}
                                    </option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-success" id="generate-automation-script">
                                <i class="fas fa-download"></i> {__('Generate Windows Script')}
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted">{__('Time when the automation should run daily.')}</small>
                </div>

                <!-- Usage Instructions -->
                <div class="mt-4">
                    <h6>{__('How to Use:')}</h6>
                    <ol class="text-muted">
                        <li>{__('Download the generated batch script')}</li>
                        <li>{__('Run the script as Administrator on your Windows system')}</li>
                        <li>{__('The script will install itself and create a scheduled task')}</li>
                        <li>{__('Automation will run daily at the specified time')}</li>
                        <li>{__('Check the log file for execution status')}</li>
                    </ol>
                </div>
            {/if}
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

<!-- Hidden form for automation script generation -->
<form method="post" id="automation-script-form" style="display: none;">
    {$token}
    <input type="hidden" name="generate_script" value="1">
    <input type="hidden" name="schedule_time" id="hidden-schedule-time" value="">
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

// Automation script generation handler
document.getElementById('generate-automation-script').addEventListener('click', function() {
    const button = this;
    const originalHtml = button.innerHTML;
    const scheduleTime = document.getElementById('schedule_time').value;

    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {__('Generating...')}';
    button.disabled = true;

    // Update hidden form with current schedule time
    document.getElementById('hidden-schedule-time').value = scheduleTime;

    // Get form data
    const formData = new FormData(document.getElementById('automation-script-form'));

    // Submit via AJAX
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // The response should trigger a file download
            return response.blob();
        } else {
            // If not a file download, treat as error
            return response.text().then(text => {
                throw new Error(text);
            });
        }
    })
    .then(blob => {
        // Create download link for the blob
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;

        // Generate filename
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, -5);
        a.download = 'axytos_automation_' + timestamp + '.bat';

        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        // Show success state
        button.innerHTML = '<i class="fas fa-check"></i> {__('Downloaded!')}';
        button.classList.remove('btn-success');
        button.classList.add('btn-success');

        // Reset button after 3 seconds
        setTimeout(function() {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-success');
            button.classList.add('btn-success');
            button.disabled = false;
        }, 3000);
    })
    .catch(error => {
        console.error('Error generating automation script:', error);

        // Show error state
        button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> {__('Error')}';
        button.classList.remove('btn-success');
        button.classList.add('btn-danger');

        // Try to show error message if available
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(error.message, 'text/html');
            const alertElement = doc.querySelector('.alert');
            if (alertElement) {
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.innerHTML = alertElement.innerHTML;
                button.parentNode.appendChild(errorDiv);

                // Remove error message after 5 seconds
                setTimeout(function() {
                    if (errorDiv.parentNode) {
                        errorDiv.parentNode.removeChild(errorDiv);
                    }
                }, 5000);
            }
        } catch (e) {
            // Fallback: show generic error
            alert('{__('Error generating automation script. Please check your configuration and try again.')}');
        }

        // Reset button after 3 seconds
        setTimeout(function() {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-danger');
            button.classList.add('btn-success');
            button.disabled = false;
        }, 3000);
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
