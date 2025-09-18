<div class="card" style="box-shadow: none;">
    <div class="card-body">
        {if !empty($messages)}
            {foreach $messages as $message}
                <div class="alert alert-{$message.type}">
                    {$message.text}
                </div>
            {/foreach}
        {/if}

        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                     <div class="card-header">
                         <h5>{'Shipped Orders Awaiting Invoice'|__}</h5>
                     </div>
                    <div class="card-body">
                        {if empty($invoicesData.recent_invoices)}
                            <div class="text-center text-muted py-4">
                                <p>{'No orders awaiting invoice found'|__}</p>
                            </div>
                        {else}
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{'Order Number'|__}</th>
                                            <th>{'Customer'|__}</th>
                                            <th>{'Date'|__}</th>
                                            <th>{'Total'|__}</th>
                                            <th>{'Status'|__}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $invoicesData.recent_invoices as $orderInfo}
                                            <tr class="invoice-row" style="cursor: pointer;" onclick="openInvoiceModal({$orderInfo.order->kBestellung}, '{$orderInfo.order->cBestellNr}')">
                                                <td>{$orderInfo.order->cBestellNr}</td>
                                                <td>{$orderInfo.customerName}</td>
                                                <td>{$orderInfo.order->dErstellt|germanDate:false}</td>
                                                <td>{$orderInfo.order->fGesamtsumme|number_format:2:',':"."} {if $orderInfo.order->Waehrung}{$orderInfo.order->Waehrung->cName}{else}EUR{/if}</td>
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
                <button type="submit" class="btn btn-primary">{'Refresh Data'|__}</button>
            </div>
        </form>
    </div>
</div>

<!-- Invoice Number Modal -->
<div class="modal fade" id="invoiceModal" tabindex="-1" role="dialog" aria-labelledby="invoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">{'Set Invoice Number'|__}</h5>
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
                        <label for="invoiceNumber">{'Order'|__}: <span id="modalOrderNumber"></span></label>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceNumber">{'Invoice Number'|__}</label>
                        <input type="text" class="form-control" id="invoiceNumber" name="invoice_number" required 
                               placeholder="{'Enter invoice number'|__}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{'Cancel'|__}</button>
                    <button type="submit" class="btn btn-primary">{'Set Invoice Number'|__}</button>
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
</style>