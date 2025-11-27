<div class="widget-custom-data">
    {if $notInstalled}
        <div class="text-center text-muted">
            <i class="fas fa-info-circle fa-2x mb-2"></i>
            <p class="mb-0">{__("Payment method not yet installed")}</p>
        </div>
    {elseif $setupRequired}
        <div class="text-center">
            <i class="fas fa-exclamation-triangle fa-3x mb-2" style="color: #ffc107;"></i>
            <p class="mb-3"><strong>{__("Setup Required")}</strong></p>
            <p class="text-muted mb-3">{__("API key not configured")}</p>
            <a href="{$setupUrl}" class="btn btn-sm btn-warning">
                <i class="fas fa-cog"></i> {__("Configure API Key")}
            </a>
        </div>
    {elseif $hasIssues}
        {* Sandbox mode warning *}
        {if $useSandbox}
            <div class="alert alert-info mb-3" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                <i class="fas fa-flask"></i> <strong>{__("Sandbox Mode Active")}</strong>
            </div>
        {/if}
        
        {* Informational entries first *}
        {if $pendingOrders > 0}
            <div class="mb-2" style="color: #17a2b8;">
                <i class="fas fa-clock" style="width: 20px; text-align: center;"></i>
                <strong>{$pendingOrders}</strong> {if $pendingOrders == 1}{__("pending action")}{else}{__("pending actions")}{/if}
            </div>
        {/if}
        
        {* Attention required section *}
        <div class="alert alert-warning mb-3 mt-3" style="padding: 0.5rem 0.75rem;">
            <strong><i class="fas fa-exclamation-triangle"></i> {__("Attention Required")}</strong>
        </div>
        
        {if $brokenOrders > 0}
            <div class="mb-2">
                <i class="fas fa-exclamation-circle text-danger" style="width: 20px; text-align: center;"></i>
                <strong>{$brokenOrders}</strong> {if $brokenOrders == 1}{__("broken action")}{else}{__("broken actions")}{/if}
            </div>
        {/if}
        
        {if $cronStatus.has_stuck}
            <div class="mb-2">
                <i class="fas fa-times-circle text-danger" style="width: 20px; text-align: center;"></i>
                <strong>{__("Cron job stuck")}</strong>
            </div>
        {elseif $cronStatus.is_overdue}
            <div class="mb-2">
                <i class="fas fa-exclamation-triangle text-warning" style="width: 20px; text-align: center;"></i>
                <strong>{__("Cron job overdue")}</strong>
            </div>
        {/if}
        
        <div class="mt-3">
            <a href="{$statusUrl}" class="btn btn-sm btn-primary">
                <i class="fas fa-list"></i> {__("View Status")}
            </a>
        </div>
    {else}
        {* Sandbox mode warning *}
        {if $useSandbox}
            <div class="alert alert-info mb-3" style="padding: 0.5rem 0.75rem; font-size: 0.9rem;">
                <i class="fas fa-flask"></i> <strong>{__("Sandbox Mode Active")}</strong>
            </div>
        {/if}
        
        <div class="text-center text-success">
            <i class="fas fa-check-circle fa-3x mb-2"></i>
            <p class="mb-0"><strong>{__("All systems operational")}</strong></p>
            {if $hasPending}
                <div class="mt-2" style="font-size: 0.9rem; color: #17a2b8;">
                    <p class="mb-1">
                        <i class="fas fa-clock"></i> {$pendingOrders} {if $pendingOrders == 1}{__("pending action")}{else}{__("pending actions")}{/if}
                    </p>
                </div>
            {/if}
        </div>
        <div class="mt-3 text-center">
            <a href="{$statusUrl}" class="btn btn-sm btn-primary">
                <i class="fas fa-list"></i> {__("View Status")}
            </a>
        </div>
    {/if}
</div>
