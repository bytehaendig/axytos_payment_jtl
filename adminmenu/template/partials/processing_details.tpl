 {* Processing Details Partial Template *}
 {if !empty($processingResults)}
     {assign var="successfulCount" value=0}
     {assign var="skippedCount" value=0}
     {assign var="errorCount" value=0}

      {* Count successful/skipped/error rows *}
      {foreach $processingResults as $result}
          {if $result.type == 'success'}
              {assign var="successfulCount" value=$successfulCount+1}
          {elseif $result.type == 'skipped'}
              {assign var="skippedCount" value=$skippedCount+1}
          {elseif $result.type == 'error'}
              {assign var="errorCount" value=$errorCount+1}
          {/if}
      {/foreach}

    <div class='processing-details-card'>
        <div class='card-header'>
            <h6 class='mb-0'>{'Processing Details'|__}</h6>
        </div>
        <div class='card-body'>
            {* Show successful rows *}
            {if $successfulCount > 0}
                <div class='mb-4'>
                    <h6 class='text-success mb-3'>
                        <i class='fas fa-check-circle me-2'></i>
                        {'Successfully Processed'|__} ({$successfulCount})
                    </h6>
                      <div class='processing-items'>
                          {foreach $processingResults as $row}
                              {if $row.type == 'success'}
                                  {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                  {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                  {assign var="message" value=$row.message|default:'Invoice number added successfully'}

                                  <div class='processing-item processing-item-success'>
                                      <div class='d-flex align-items-center'>
                                          <i class='fas fa-check-circle text-success me-2'></i>
                                          <strong>{'Order'|__}: {$orderNumber}</strong>
                                          <i class='fas fa-arrow-right mx-2 text-muted'></i>
                                          <strong>{'Invoice'|__}: {$invoiceNumber}</strong>
                                          <span class='badge badge-success ms-2'>{'processed'|__}</span>
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
                         {'Skipped'|__} ({$skippedCount})
                     </h6>
                      <div class='processing-items'>
                          {foreach $processingResults as $row}
                              {if $row.type == 'skipped'}
                                  {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                  {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                  {assign var="message" value=$row.message|default:'Order already has invoice number'}

                                  <div class='processing-item processing-item-info'>
                                      <div class='d-flex align-items-center'>
                                          <i class='fas fa-info-circle text-info me-2'></i>
                                          <strong>{'Order'|__}: {$orderNumber}</strong>
                                          <span class='badge badge-info ms-2'>{'skipped'|__}</span>
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
                        {'Failed to Process'|__} ({$errorCount})
                    </h6>
                     <div class='processing-items'>
                         {foreach $processingResults as $row}
                             {if $row.type == 'error'}
                                 {assign var="invoiceNumber" value=$row.invoiceNumber|default:'N/A'}
                                 {assign var="orderNumber" value=$row.orderNumber|default:'N/A'}
                                 {assign var="error" value=$row.error|default:'Unknown error'}

                                 <div class='processing-item processing-item-error'>
                                     <div class='d-flex align-items-center'>
                                         <i class='fas fa-exclamation-circle text-danger me-2'></i>
                                         <strong>{'Order'|__}: {$orderNumber}</strong>
                                         <i class='fas fa-arrow-right mx-2 text-muted'></i>
                                         <strong>{'Invoice'|__}: {$invoiceNumber}</strong>
                                         <span class='badge badge-danger ms-2'>{'failed'|__}</span>
                                     </div>
                                     <div class='processing-message'>{'Error'|__}: {$error}</div>
                                 </div>
                             {/if}
                         {/foreach}
                     </div>
                </div>
            {/if}

            {* If no rows were processed at all *}
            {if $successfulCount == 0 && $errorCount == 0}
                <div class='text-center text-muted py-3'>
                    <em>{'No rows were processed.'|__}</em>
                </div>
            {/if}
        </div>
    </div>
{/if}