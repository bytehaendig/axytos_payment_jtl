<div class="card" style="box-shadow: none;">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        {include file="./partials/processing_details.tpl" processingResults=$processingResults}

        <!-- Upload Invoice CSV Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>{__('Upload Invoice CSV')}</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            {$token}
                            <input type="hidden" name="upload_csv" value="1">
                            <input type="hidden" name="tab" value="Invoices">

                            <div class="form-group">
                                <label for="csvFile">{__('Select CSV File')}</label>
                                <div class="d-flex align-items-center">
                                    <div class="custom-file flex-grow-1 mr-3" style="max-width: 400px;">
                                        <input type="file" class="custom-file-input" id="csvFile" name="csv_file" accept="text/csv,.csv" required>
                                        <label class="custom-file-label" for="csvFile">{__('Choose CSV file...')}</label>
                                    </div>
                                    <button type="submit" class="btn btn-primary">{__('Upload CSV')}</button>
                                </div>
                                <small class="form-text text-muted">{__('Select a CSV file containing invoice data')}</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                      <div class="card-header">
                          <h5>{__('Shipped Orders Awaiting Invoice')}</h5>
                      </div>
                     <div class="card-body">
                          {if empty($invoicesData.orders_awaiting_invoice)}
                              <div class="text-center text-muted py-4">
                                  <p>{__('No orders awaiting invoice found')}</p>
                              </div>
                          {else}
                              <div class="table-responsive">
                                  <table class="table">
                                      <thead>
                                          <tr>
                                              <th>{__('Order Number')}</th>
                                              <th>{__('Customer')}</th>
                                              <th>{__('Date')}</th>
                                              <th>{__('Total')}</th>
                                              <th>{__('Status')}</th>
                                          </tr>
                                      </thead>
                                      <tbody>
                                          {foreach $invoicesData.orders_awaiting_invoice as $orderInfo}
                                             <tr class="invoice-row" style="cursor: pointer;" onclick="openInvoiceModal({$orderInfo.order->kBestellung}, '{$orderInfo.order->cBestellNr}')">
                                                 <td>{$orderInfo.order->cBestellNr}</td>
                                                 <td>{$orderInfo.customerName}</td>
                                                 <td>{$orderInfo.order->dErstellt|germanDate:false}</td>
                                                  <td>{$orderInfo.order->fGesamtsumme|number_format:2:',':"."} {if $orderInfo.order->Waehrung && $orderInfo.order->Waehrung->cName}{$orderInfo.order->Waehrung->cName}{else}€{/if}</td>
                                                 <td>{$orderInfo.order->Status}</td>
                                             </tr>
                                         {/foreach}
                                     </tbody>
                                 </table>
                             </div>
                         {/if}
                     </div>
                 </div>
             </div>
         </div>

        <form method="post" class="mt-4">
            {$token}
            <input type="hidden" name="save_invoices" value="1">
            <input type="hidden" name="tab" value="Invoices">

            <div class="form-group">
                <button type="submit" class="btn btn-primary">{__('Refresh Data')}</button>
            </div>
        </form>
    </div>
</div>

<!-- Invoice Number Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">{__('Set Invoice Number')}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" id="invoiceForm">
                {$token}
                <div class="modal-body">
                    <input type="hidden" name="set_invoice_number" value="1">
                    <input type="hidden" name="tab" value="Invoices">
                    <input type="hidden" name="order_id" id="modalOrderId" value="">
                    
                    <div class="form-group">
                        <label for="invoiceNumber">{__('Order')}: <span id="modalOrderNumber"></span></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceNumber">{__('Invoice Number')}</label>
                        <input type="text" class="form-control" id="invoiceNumber" name="invoice_number" required 
                               placeholder="{__('Enter invoice number')}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{__('Cancel')}</button>
                    <button type="submit" class="btn btn-primary">{__('Set Invoice Number')}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openInvoiceModal(orderId, orderNumber) {
    // Set modal data
    document.getElementById('modalOrderId').value = orderId;
    document.getElementById('modalOrderNumber').textContent = orderNumber;
    document.getElementById('invoiceNumber').value = '';

    // Show modal
    $('#invoiceModal').modal('show');
}

// Handle CSV file selection display
document.getElementById('csvFile').addEventListener('change', function(e) {
    var fileName = e.target.files[0] ? e.target.files[0].name : 'Choose CSV file...';
    var label = e.target.nextElementSibling;
    label.textContent = fileName;
});
</script>

<style>
/* Action item styling */
.action-item {
    margin: 4px 0;
    padding: 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
    font-size: 12px;
}

/* Status colors */
.status-pending { color: #fd7e14; }

/* Badge helper classes for consistent styling */
.badge-small {
    font-size: 10px;
}

/* Actions list styling */
.actions-list {
    font-size: 12px;
}

/* Invoice row hover styling */
.invoice-row:hover {
    background-color: #f8f9fa !important;
}

/* Processing Details Card */
.processing-details-card {
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    margin-top: 1rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.processing-details-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1.25rem;
    border-radius: 0.375rem 0.375rem 0 0;
}

.processing-details-card .card-body {
    padding: 1.25rem;
}

/* Processing Items */
.processing-items {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.processing-item {
    padding: 1rem;
    border-radius: 0.5rem;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    transition: all 0.15s ease-in-out;
}

.processing-item:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.processing-item-success {
    border-left: 4px solid #28a745;
    background-color: #f8fff9;
}

.processing-item-info {
    border-left: 4px solid #17a2b8;
    background-color: #f7feff;
}

.processing-item-error {
    border-left: 4px solid #dc3545;
    background-color: #fff8f8;
}

.processing-message {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.5rem;
    line-height: 1.4;
}

/* Bootstrap 4 compatibility for spacing classes */
.me-2 { margin-right: 0.5rem !important; }
.ms-2 { margin-left: 0.5rem !important; }
.mx-2 { margin-left: 0.5rem !important; margin-right: 0.5rem !important; }
</style>
