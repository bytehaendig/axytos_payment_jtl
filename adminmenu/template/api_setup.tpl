
<div class="card">
    <div class="card-body">
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

             <div class="form-group">
                 <label for="api_key">API Key:</label>
                 <input type="text" class="form-control" id="api_key" name="api_key" value="{$apiKey}">
                 <small class="form-text text-muted">Your API key will be encrypted before storing in the database.</small>
             </div>

              <div class="form-group">
                  <label for="webhook_api_key">Webhook API Key:</label>
                  <div class="input-group">
                      <input type="text" class="form-control" id="webhook_api_key" name="webhook_api_key" value="{$webhookApiKey}">
                      <div class="input-group-append">
                          <button type="button" class="btn btn-outline-secondary" id="generate-webhook-key" title="Generate Secure Key">
                              <i class="fas fa-key"></i> Generate
                          </button>
                      </div>
                  </div>
                  <small class="form-text text-muted">Your webhook API key will be encrypted before storing in the database.</small>
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

          <!-- Hidden form for key generation -->
          <form method="post" id="key-generation-form" style="display: none;">
              {$token}
              <input type="hidden" name="generate_key" value="1">
          </form>
      </div>
  </div>

  <!-- Webhook Configuration Section -->
  <div class="card mt-4">
      <div class="card-header">
          <h5 class="mb-0">
              <i class="fas fa-webhook"></i> Webhook Configuration
              <button type="button" class="btn btn-sm btn-outline-info float-right" data-toggle="modal" data-target="#webhookInfoModal">
                  <i class="fas fa-info-circle"></i> Webhook Documentation
              </button>
          </h5>
      </div>
      <div class="card-body">
          <!-- Webhook Status -->
          <div class="row mb-3">
              <div class="col-md-8">
                  <div class="form-group">
                      <label>Webhook Endpoint URL:</label>
                      <div class="input-group">
                          <input type="text" class="form-control" value="{$webhookConfig.webhookUrl}" readonly>
                          <div class="input-group-append">
                              <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('{$webhookConfig.webhookUrl}')">
                                  <i class="fas fa-copy"></i> Copy
                              </button>
                          </div>
                      </div>
                  </div>
              </div>
              <div class="col-md-4">
                  <div class="form-group">
                      <label>Webhook Status:</label>
                      <div>
                          {if $webhookConfig.webhookConfigured}
                              <span class="badge badge-success">
                                  <i class="fas fa-check-circle"></i> Configured
                              </span>
                          {else}
                              <span class="badge badge-warning">
                                  <i class="fas fa-exclamation-triangle"></i> Not Configured
                              </span>
                          {/if}
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>

  <!-- Webhook Information Modal -->
  <div class="modal fade" id="webhookInfoModal" tabindex="-1" role="dialog" aria-labelledby="webhookInfoModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="webhookInfoModalLabel">
                      <i class="fas fa-webhook"></i> Webhook Integration Guide
                  </h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <!-- Authentication Section -->
                  <h6><i class="fas fa-key"></i> Authentication</h6>
                  <p>Webhook requests must include the <code>X-Axytos-Webhook-Key</code> header with your configured webhook API key.</p>
                  <div class="alert alert-warning">
                      <strong>Security Note:</strong> Keep your webhook API key secure and never expose it in client-side code or public repositories.
                  </div>

                  <!-- Endpoint Information -->
                  <h6 class="mt-4"><i class="fas fa-link"></i> Endpoint Information</h6>
                  <ul>
                      <li><strong>URL:</strong> <code>{$webhookConfig.webhookUrl}</code></li>
                      <li><strong>Method:</strong> POST</li>
                      <li><strong>Content-Type:</strong> application/json</li>
                      <li><strong>Authentication:</strong> X-Axytos-Webhook-Key header</li>
                  </ul>

                  <!-- Request Format -->
                  <h6 class="mt-4"><i class="fas fa-code"></i> Request Format</h6>
                  <p>The webhook expects a JSON payload with invoice ID updates:</p>
                  <div class="bg-light p-3 rounded">
                      <pre><code>{
  "invoice_ids": {
    "OLD_INVOICE_ID": "NEW_INVOICE_ID",
    "INV001": "NEW001",
    "INV002": "NEW002"
  }
}</code></pre>
                  </div>

                  <!-- Example Requests -->
                  <h6 class="mt-4"><i class="fas fa-terminal"></i> Example Requests</h6>

                  <!-- cURL Example -->
                  <div class="mb-3">
                      <strong>cURL:</strong>
                      <button type="button" class="btn btn-sm btn-outline-secondary float-right" onclick="copyToClipboard('{$webhookConfig.examples.curl|escape:'javascript'}')">
                          <i class="fas fa-copy"></i> Copy
                      </button>
                      <div class="bg-light p-3 rounded mt-2">
                          <pre><code>{$webhookConfig.examples.curl}</code></pre>
                      </div>
                  </div>

                  <!-- PHP Example -->
                  <div class="mb-3">
                      <strong>PHP:</strong>
                      <button type="button" class="btn btn-sm btn-outline-secondary float-right" onclick="copyToClipboard('{$webhookConfig.examples.php|escape:'javascript'}')">
                          <i class="fas fa-copy"></i> Copy
                      </button>
                      <div class="bg-light p-3 rounded mt-2">
                          <pre><code>{$webhookConfig.examples.php}</code></pre>
                      </div>
                  </div>

                  <!-- Python Example -->
                  <div class="mb-3">
                      <strong>Python:</strong>
                      <button type="button" class="btn btn-sm btn-outline-secondary float-right" onclick="copyToClipboard('{$webhookConfig.examples.python|escape:'javascript'}')">
                          <i class="fas fa-copy"></i> Copy
                      </button>
                      <div class="bg-light p-3 rounded mt-2">
                          <pre><code>{$webhookConfig.examples.python}</code></pre>
                      </div>
                  </div>

                  <!-- Response Format -->
                  <h6 class="mt-4"><i class="fas fa-reply"></i> Response Format</h6>
                  <p>The webhook will respond with a JSON object indicating the result:</p>
                  <div class="bg-light p-3 rounded">
                      <pre><code>// Success Response (HTTP 200)
{
  "success": true,
  "data": {
    "updated_count": 2,
    "errors": [],
    "total_processed": 2
  }
}

// Error Response (HTTP 400/500)
{
  "success": false,
  "error": "Error description"
}</code></pre>
                  </div>

                  <!-- Integration Notes -->
                  <h6 class="mt-4"><i class="fas fa-lightbulb"></i> Integration Notes</h6>
                  <ul>
                      <li>Webhook requests are processed asynchronously</li>
                      <li>Failed requests will be retried automatically (up to 3 attempts)</li>
                      <li>All webhook activity is logged for monitoring and debugging</li>
                      <li>Payload size is limited to 1MB</li>
                      <li>Requests without proper authentication will be rejected</li>
                  </ul>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
              </div>
          </div>
      </div>
  </div>

<script>
// Copy to clipboard function
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-check"></i> Copied to clipboard!
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        document.body.appendChild(notification);

        // Auto-remove after 3 seconds
        setTimeout(function() {
            if (notification.parentNode) {
                $(notification).alert('close');
            }
        }, 3000);
    }).catch(function(err) {
        console.error('Failed to copy: ', err);
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);

        const notification = document.createElement('div');
        notification.className = 'alert alert-success alert-dismissible fade show position-fixed';
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-check"></i> Copied to clipboard!
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        document.body.appendChild(notification);

        setTimeout(function() {
            if (notification.parentNode) {
                $(notification).alert('close');
            }
        }, 3000);
    });
}

document.getElementById('generate-webhook-key').addEventListener('click', function() {
    const button = this;
    const originalHtml = button.innerHTML;

    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
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
            button.innerHTML = '<i class="fas fa-check"></i> Generated!';
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
