
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

    <!-- WaWi Automation Card -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">
                {__('JTL-WaWi Automation')}
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
                     <button type="button" class="btn btn-success" id="generate-automation-script">
                         <i class="fas fa-download"></i> {__('Download Automation Package')}
                     </button>
                     <button type="button" class="btn btn-info ml-2" onclick="showUsageModal()">
                         <i class="fas fa-info-circle"></i> {__('Show Usage Instructions')}
                     </button>
                     <small class="form-text text-muted mt-2">{__('Schedule and additional settings can be configured in config.ini after download.')}</small>
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
</form>

<!-- Usage Instructions Modal -->
<div class="modal-backdrop" id="usage-modal-backdrop" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1040;"></div>

<div class="modal fade" id="usageModal" tabindex="-1" role="dialog" aria-labelledby="usageModalLabel" aria-hidden="true" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; align-items: flex-start; justify-content: center; padding-top: 2rem;">
    <div class="modal-dialog modal-xl" role="document" style="width: 60%; max-width: 60%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usageModalLabel">{__('Usage Instructions')}</h5>
                <button type="button" class="close" onclick="closeUsageModal()" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>{__('Setup Steps:')}</h6>
                <ol class="text-muted">
                    <li>{__('Download the ZIP package')}</li>
                    <li>{__('Extract all files to a folder of your choice')}
                        <br><small class="text-muted">{__('Recommended:')} <code>C:\Tools\AxytosPaymentAutomation\</code></small>
                    </li>
                    <li>{__('Edit the')} <strong>config.ini</strong> {__('and configure:')}
                        <ul class="text-muted">
                            <li>{__('JTL-WaWi database access data (Server, Database, User, Password)')}</li>
                            <li>{__('Schedule (ScheduleTime, Default: 17:00)')}</li>
                            <li>{__('JTL-Ameise Export-Template ID')}</li>
                        </ul>
                    </li>
                    <li>{__('Run')} <strong>install.bat</strong> {__('as administrator')}</li>
                    <li>{__('The automation runs daily at the configured time')}</li>
                    <li>{__('Check the log files in the same folder')} (<code>axytos_automation.log</code>)</li>
                    <li>{__('To uninstall, run')} <strong>uninstall.bat</strong></li>
                </ol>
                <p class="text-muted"><small><strong>{__('Note:')}</strong> {__('All files remain in your chosen folder - nothing is copied to system directories.')}</small></p>
            </div>
        </div>
    </div>
</div>

<script>
function showUsageModal() {
    const modal = document.getElementById('usageModal');
    const backdrop = document.getElementById('usage-modal-backdrop');
    if (modal && backdrop) {
        modal.style.display = 'flex';
        backdrop.style.display = 'block';
        modal.classList.add('show');
    }
}

function closeUsageModal() {
    const modal = document.getElementById('usageModal');
    const backdrop = document.getElementById('usage-modal-backdrop');
    if (modal && backdrop) {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        modal.classList.remove('show');
    }
}

// Add click handler to backdrop to close modal
document.addEventListener('DOMContentLoaded', function() {
    const backdrop = document.getElementById('usage-modal-backdrop');
    if (backdrop) {
        backdrop.addEventListener('click', closeUsageModal);
    }
});

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

    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> {__('Generating...')}';
    button.disabled = true;

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
        a.download = 'axytos_automation_' + timestamp + '.zip';

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

/* Modal styling for usage instructions */
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

.modal-body {
    padding: 20px;
}

.modal-body h6 {
    color: #495057;
    font-weight: 600;
    margin-bottom: 15px;
}

.modal-body ol {
    line-height: 1.8;
}

.modal-body strong {
    color: #495057;
    font-weight: 600;
}
</style>
