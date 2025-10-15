# Axytos Payment Automation Export Script v{$PLUGIN_VERSION}
# Generated: {$TIMESTAMP}
# Shop URL: {$SHOP_URL}
# JTL-WaWi Integration: Uses JTL-Ameise for invoice data export

# ===========================================
# READ CONFIGURATION FROM config.ini
# ===========================================

function Read-IniFile {
    param([string]$FilePath)
    
    $ini = @{}
    $section = ""
    
    if (-not (Test-Path $FilePath)) {
        throw "Configuration file not found: $FilePath"
    }
    
    Get-Content $FilePath | ForEach-Object {
        $line = $_.Trim()
        
        # Skip empty lines and comments
        if ($line -eq "" -or $line.StartsWith(";") -or $line.StartsWith("#")) {
            return
        }
        
        # Section header
        if ($line -match '^\[(.+)\]$') {
            $section = $matches[1]
            $ini[$section] = @{}
            return
        }
        
        # Key=Value pair
        if ($line -match '^(.+?)=(.*)$') {
            $key = $matches[1].Trim()
            $value = $matches[2].Trim()
            if ($section -ne "") {
                $ini[$section][$key] = $value
            }
        }
    }
    
    return $ini
}

# Determine script directory and config file location
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$configPath = Join-Path $scriptDir "config.ini"

# Read configuration
try {
    Write-Host "Reading configuration from: $configPath"
    $config = Read-IniFile -FilePath $configPath
    
    # Extract configuration values
    $ShopUrl = $config['Axytos']['ShopUrl']
    $WebhookKey = $config['Axytos']['WebhookKey']
    
    $WawiServer = $config['WaWi']['Server']
    $WawiDatabase = $config['WaWi']['Database']
    $WawiUser = $config['WaWi']['User']
    $WawiPassword = $config['WaWi']['Password']
    $WawiExportTemplate = $config['WaWi']['ExportTemplate']
    $WawiInstallPath = $config['WaWi']['InstallPath']
    
    $MaxRetries = [int]$config['Advanced']['MaxRetries']
    $RetryDelaySeconds = [int]$config['Advanced']['RetryDelaySeconds']
    $LogLevel = $config['Advanced']['LogLevel']
    
    Write-Host "Configuration loaded successfully"
    
} catch {
    Write-Host "ERROR: Failed to read configuration file" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    exit 1
}

# ===========================================
# INTERNAL CONFIGURATION
# ===========================================

# Construct JTL-Ameise executable path from install path
$JtlAmeisePath = Join-Path $WawiInstallPath "JTL-wawi-ameise.exe"

# Set log file location to current script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$LogFile = "$scriptDir\axytos_automation.log"
$TempDir = $env:TEMP

# ===========================================
# LOGGING FUNCTION
# ===========================================

function Write-Log {
    param(
        [string]$Message,
        [string]$Level = "INFO"
    )

    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] [$Level] $Message"

    # Write to console
    switch ($Level) {
        "ERROR" { Write-Host $logMessage -ForegroundColor Red }
        "WARNING" { Write-Host $logMessage -ForegroundColor Yellow }
        "SUCCESS" { Write-Host $logMessage -ForegroundColor Green }
        default { Write-Host $logMessage }
    }

    # Write to log file
    try {
        $logMessage | Out-File -FilePath $LogFile -Append -Encoding UTF8
    } catch {
        Write-Host "Failed to write to log file: $($_.Exception.Message)" -ForegroundColor Red
    }
}

# ===========================================
# VALIDATION FUNCTION
# ===========================================

function Test-Configuration {
    Write-Log "Validating configuration..."

    if ([string]::IsNullOrEmpty($ShopUrl)) {
        throw "ShopUrl is not configured"
    }

    if ([string]::IsNullOrEmpty($WebhookKey)) {
        throw "WebhookKey is not configured"
    }

    if ($WebhookKey.Length -lt 10) {
        throw "WebhookKey appears to be too short (minimum 10 characters expected)"
    }

    if ([string]::IsNullOrEmpty($WawiServer)) {
        throw "WawiServer is not configured"
    }

    if ([string]::IsNullOrEmpty($WawiDatabase)) {
        throw "WawiDatabase is not configured"
    }

    if ([string]::IsNullOrEmpty($WawiUser)) {
        throw "WawiUser is not configured"
    }

    if ([string]::IsNullOrEmpty($WawiPassword)) {
        throw "WawiPassword is not configured"
    }

    if ([string]::IsNullOrEmpty($WawiExportTemplate)) {
        throw "WawiExportTemplate is not configured"
    }

    if (-not (Test-Path $JtlAmeisePath)) {
        throw "JTL-Ameise executable not found at: $JtlAmeisePath (constructed from WawiInstallPath: $WawiInstallPath)"
    }

    if (-not (Test-Path $TempDir)) {
        throw "Temp directory does not exist: $TempDir"
    }

    Write-Log "Configuration validation passed"
}

# ===========================================
# JTL-AMEISE EXPORT FUNCTION
# ===========================================

function Export-InvoiceData {
    param(
        [string]$OutputPath
    )

    Write-Log "Starting JTL-Ameise export..."

    try {
        # Validate JTL-Ameise executable exists
        if (-not (Test-Path $JtlAmeisePath)) {
            throw "JTL-Ameise executable not found at: $JtlAmeisePath"
        }

        # Build JTL-Ameise command arguments
        $arguments = @(
            "--server=$WawiServer",
            "--database=$WawiDatabase",
            "--dbuser=$WawiUser",
            "--dbpass=$WawiPassword",
            "--templateid=$WawiExportTemplate",
            "--outputfile=$OutputPath",
            "--mode=production",
            "--loglevel=3"  # Compact logging
        )

        Write-Log "Executing: $JtlAmeisePath $($arguments -join ' ')"

        # Execute JTL-Ameise
        $process = Start-Process -FilePath $JtlAmeisePath -ArgumentList $arguments -NoNewWindow -Wait -PassThru -RedirectStandardOutput "$TempDir\ameise_stdout.log" -RedirectStandardError "$TempDir\ameise_stderr.log"

        # Check if the output file was created
        if (-not (Test-Path $OutputPath)) {
            # Read error output for debugging
            $errorOutput = ""
            if (Test-Path "$TempDir\ameise_stderr.log") {
                $errorOutput = Get-Content "$TempDir\ameise_stderr.log" -Raw
            }
            throw "JTL-Ameise export failed - output file not created. Error: $errorOutput"
        }

        # Check process exit code
        if ($process.ExitCode -ne 0) {
            $errorOutput = ""
            if (Test-Path "$TempDir\ameise_stderr.log") {
                $errorOutput = Get-Content "$TempDir\ameise_stderr.log" -Raw
            }
            throw "JTL-Ameise exited with code $($process.ExitCode). Error: $errorOutput"
        }

        # Read and log JTL-Ameise output
        if (Test-Path "$TempDir\ameise_stdout.log") {
            $stdout = Get-Content "$TempDir\ameise_stdout.log" -Raw
            Write-Log "JTL-Ameise output: $stdout"
        }

        Write-Log "JTL-Ameise export completed successfully: $OutputPath"
        return $true

    } catch {
        Write-Log "JTL-Ameise export failed: $($_.Exception.Message)" -Level "ERROR"
        return $false
    } finally {
        # Cleanup temporary log files
        $tempLogs = @("$TempDir\ameise_stdout.log", "$TempDir\ameise_stderr.log")
        foreach ($logFile in $tempLogs) {
            if (Test-Path $logFile) {
                try { Remove-Item $logFile -Force } catch { }
            }
        }
    }
}

# ===========================================
# HTTP UPLOAD FUNCTION
# ===========================================

function Send-InvoiceData {
    param(
        [string]$CsvPath,
        [int]$RetryCount = 0
    )

    Write-Log "Starting HTTP upload (attempt $($RetryCount + 1)/$($MaxRetries + 1))..."

    try {
        # Read CSV content
        if (-not (Test-Path $CsvPath)) {
            throw "CSV file not found: $CsvPath"
        }

        try {
            $csvContent = Get-Content -Path $CsvPath -Raw -Encoding UTF8
            Write-Log "CSV file size: $($csvContent.Length) characters"
        } catch {
            throw "Failed to read CSV file content: $($_.Exception.Message)"
        }

        if ([string]::IsNullOrEmpty($csvContent)) {
            throw "CSV file is empty or unreadable"
        }

        # Prepare headers
        $headers = @{
            'X-Axytos-Webhook-Key' = $WebhookKey
        }

        # Prepare URI
        $uri = "$ShopUrl/axytos/v1/invoice-ids"
        Write-Log "Target URI: $uri"

        # Make HTTP request with CSV content type
        $response = Invoke-WebRequest -Uri $uri -Method POST -Body $csvContent -Headers $headers -ContentType "text/csv; charset=utf-8" -UseBasicParsing

        Write-Log "HTTP Response Status: $($response.StatusCode)" -Level "SUCCESS"

        if ($response.Content) {
            Write-Log "Response Content: $($response.Content)"
        }

        return @{
            Success = $true
            StatusCode = $response.StatusCode
            Content = $response.Content
        }

    } catch {
        $errorMessage = $_.Exception.Message
        Write-Log "HTTP request failed: $errorMessage" -Level "ERROR"

        # Check if we should retry
        if ($RetryCount -lt $MaxRetries) {
            $retryDelay = $RetryDelaySeconds * [Math]::Pow(2, $RetryCount)  # Exponential backoff
            Write-Log "Retrying in $retryDelay seconds... (attempt $($RetryCount + 2)/$($MaxRetries + 1))" -Level "WARNING"
            Start-Sleep -Seconds $retryDelay
            return Send-InvoiceData -CsvPath $CsvPath -RetryCount ($RetryCount + 1)
        } else {
            Write-Log "Max retries exceeded" -Level "ERROR"
            return @{
                Success = $false
                Error = $errorMessage
            }
        }
    }
}

# ===========================================
# CLEANUP FUNCTION
# ===========================================

function Remove-TempFiles {
    param(
        [string]$CsvPath
    )

    try {
        if (Test-Path $CsvPath) {
            Remove-Item -Path $CsvPath -Force
            Write-Log "Cleaned up temporary file: $CsvPath"
        }
    } catch {
        Write-Log "Failed to cleanup temporary file: $($_.Exception.Message)" -Level "WARNING"
    }
}

# ===========================================
# MAIN EXECUTION
# ===========================================

function Invoke-Main {
    $exitCode = 0

    try {
        Write-Log "=== Axytos Payment Automation Export Started (JTL-WaWi Integration) ==="

        # Validate configuration
        Test-Configuration

        # Generate unique filename for this run
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $csvPath = Join-Path $TempDir "axytos_invoice_export_$timestamp.csv"

        # Export invoice data from JTL-WaWi
        $exportResult = Export-InvoiceData -OutputPath $csvPath
        if (-not $exportResult) {
            throw "JTL-Ameise export failed"
        }

        # Upload data
        $uploadResult = Send-InvoiceData -CsvPath $csvPath

        if ($uploadResult.Success) {
            Write-Log "Automation completed successfully - invoice data exported and uploaded" -Level "SUCCESS"
        } else {
            throw "Upload failed: $($uploadResult.Error)"
        }

    } catch {
        $errorMessage = $_.Exception.Message
        Write-Log "Automation failed: $errorMessage" -Level "ERROR"
        
        # Keep CSV file on error for debugging
        if ($csvPath -and (Test-Path $csvPath)) {
            Write-Log "CSV file preserved for debugging: $csvPath" -Level "WARNING"
        }
        
        $exitCode = 1
    } finally {
        # Only cleanup temporary files on success
        if ($exitCode -eq 0 -and $csvPath -and (Test-Path $csvPath)) {
            Remove-TempFiles -CsvPath $csvPath
        }

        Write-Log "=== Axytos Payment Automation Export Completed (JTL-WaWi Integration) ==="
    }

    return $exitCode
}

# ===========================================
# SCRIPT ENTRY POINT
# ===========================================

# Execute main function and exit with appropriate code
$exitCode = Invoke-Main
exit $exitCode
