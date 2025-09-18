 {* Processing Details Partial Template *}
 {if !empty($processingResults)}
     {assign var="successfulCount" value=0}
     {assign var="skippedCount" value=0}
     {assign var="errorCount" value=0}

       {* Count successful/skipped/error rows *}
       {foreach $processingResults as $result}
           {if $result.status == 'success'}
               {assign var="successfulCount" value=$successfulCount+1}
           {elseif $result.status == 'skipped'}
               {assign var="skippedCount" value=$skippedCount+1}
           {elseif $result.status == 'error'}
               {assign var="errorCount" value=$errorCount+1}
           {/if}
       {/foreach}

    <div class='processing-details-card'>
        <div class='card-header'>
            <h6 class='mb-0'>{__('Processing Details')}</h6>
        </div>
        <div class='card-body'>
            {* Show successful rows *}
            {if $successfulCount > 0}
                <div class='mb-4'>
                    <h6 class='text-success mb-3'>
                        <i class='fas fa-check-circle me-2'></i>
                        {__('Successfully Processed')} ({$successfulCount})
                    </h6>
                       <div class='processing-items'>
                           {foreach $processingResults as $row}
                               {if $row.status == 'success'}
                                  {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                  {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                   {assign var="message" value=$row.message}

                                   <div class='processing-item processing-item-success'>
                                       <div class='d-flex align-items-center'>
                                           <i class='fas fa-check-circle text-success me-2'></i>
                                           <strong>{__('Order')}: {$orderNumber}</strong>
                                           <i class='fas fa-arrow-right mx-2 text-muted'></i>
                                           <strong>{__('Invoice')}: {$invoiceNumber}</strong>
                                           <span class='badge badge-success ms-2'>{__('processed')}</span>
                                       </div>
                                      <div class='processing-message'>{$message}</div>
                                  </div>
                              {/if}
                          {/foreach}
                      </div>
                </div>
             {/if}

             {* Show skipped rows *}
             {if $skippedCount > 0}
                 <div class='mb-4'>
                     <h6 class='text-info mb-3'>
                         <i class='fas fa-info-circle me-2'></i>
                         {__('Skipped')} ({$skippedCount})
                     </h6>
                       <div class='processing-items'>
                           {foreach $processingResults as $row}
                               {if $row.status == 'skipped'}
                                  {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                  {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                   {assign var="message" value=$row.message}

                                   <div class='processing-item processing-item-info'>
                                       <div class='d-flex align-items-center'>
                                           <i class='fas fa-info-circle text-info me-2'></i>
                                           <strong>{__('Order')}: {$orderNumber}</strong>
                                           <span class='badge badge-info ms-2'>{__('skipped')}</span>
                                       </div>
                                      <div class='processing-message'>{$message}</div>
                                  </div>
                              {/if}
                          {/foreach}
                      </div>
                 </div>
             {/if}

             {* Show failed rows *}
            {if $errorCount > 0}
                <div class='mb-4'>
                    <h6 class='text-danger mb-3'>
                        <i class='fas fa-exclamation-triangle me-2'></i>
                        {__('Failed to Process')} ({$errorCount})
                    </h6>
                      <div class='processing-items'>
                          {foreach $processingResults as $row}
                              {if $row.status == 'error'}
                                 {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                 {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                 {assign var="error" value=$row.error|default:'Unknown error'}

                                  <div class='processing-item processing-item-error'>
                                      <div class='d-flex align-items-center'>
                                          <i class='fas fa-exclamation-circle text-danger me-2'></i>
                                          <strong>{__('Order')}: {$orderNumber}</strong>
                                          <i class='fas fa-arrow-right mx-2 text-muted'></i>
                                          <strong>{__('Invoice')}: {$invoiceNumber}</strong>
                                          <span class='badge badge-danger ms-2'>{__('failed')}</span>
                                      </div>
                                      <div class='processing-message'>{__('Error')}: {$error}</div>
                                  </div>
                             {/if}
                         {/foreach}
                     </div>
                </div>
            {/if}

              {* If no rows were processed at all *}
              {if $successfulCount == 0 && $skippedCount == 0 && $errorCount == 0}
                  <div class='text-center text-muted py-3'>
                      <em>{__('No rows were processed.')}</em>
                  </div>
              {/if}
         </div>
     </div>
{/if}

<style>
/* Improve label readability by making them darker */
strong {
    color: #495057 !important;
    font-weight: 600 !important;
}

.card-header h6 {
    color: #495057 !important;
    font-weight: 600 !important;
}
</style>