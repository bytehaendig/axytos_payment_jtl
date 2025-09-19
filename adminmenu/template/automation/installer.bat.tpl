@echo off
REM Axytos Payment Automation Installer v{{PLUGIN_VERSION}}
REM Generated: {{TIMESTAMP}}
REM Shop URL: {{SHOP_URL}}

REM ===========================================
REM CONFIGURATION SECTION
REM ===========================================

set "SHOP_URL={{SHOP_URL}}"
set "WEBHOOK_KEY={{WEBHOOK_KEY}}"
set "SCHEDULE_TIME={{SCHEDULE_TIME}}"
set "PLUGIN_VERSION={{PLUGIN_VERSION}}"
set "SCRIPT_DIR=%~dp0"
set "TASK_NAME=AxytosPaymentAutomation"
set "RUNNER_SCRIPT=%SCRIPT_DIR%automation_runner.bat"
set "POWERSHELL_SCRIPT=%SCRIPT_DIR%axytos_export.ps1"

REM Choose data directory based on installation type
REM For user-specific installation (default):
if defined APPDATA (
    set "APP_DIR=%APPDATA%\AxytosPaymentJTL"
) else (
    set "APP_DIR=%USERPROFILE%\AppData\Roaming\AxytosPaymentJTL"
)

REM For system-wide installation (uncomment and modify if needed):
REM if defined PROGRAMDATA (
REM     set "APP_DIR=%PROGRAMDATA%\AxytosPaymentJTL"
REM ) else (
REM     set "APP_DIR=%ALLUSERSPROFILE%\AxytosPaymentJTL"
REM )

set "LOG_FILE=%APP_DIR%\axytos_automation.log"
set "VERSION_FILE=%APP_DIR%\version.txt"
set "POWERSHELL_LOG=%APP_DIR%\powershell.log"

REM Create application directory if it doesn't exist
if not exist "%APP_DIR%" (
    mkdir "%APP_DIR%" 2>nul
    if %errorlevel% neq 0 (
        call :log "WARNING: Could not create application directory: %APP_DIR%"
        call :log "Falling back to script directory for data files"
        set "APP_DIR=%SCRIPT_DIR%"
        set "LOG_FILE=%APP_DIR%\axytos_automation.log"
        set "VERSION_FILE=%APP_DIR%\version.txt"
        set "POWERSHELL_LOG=%APP_DIR%\powershell.log"
    ) else (
        call :log "Created application directory: %APP_DIR%"
    )
)

REM ===========================================
REM LOGGING FUNCTION
REM ===========================================

:log
echo [%DATE% %TIME%] %~1 >> "%LOG_FILE%"
goto :eof

REM ===========================================
REM ERROR HANDLING
REM ===========================================

:error
call :log "ERROR: %~1"
echo ERROR: %~1
echo Check log file: %LOG_FILE%
pause
exit /b 1

REM ===========================================
REM VERSION DETECTION
REM ===========================================

:get_installed_version
if exist "%VERSION_FILE%" (
    set /p INSTALLED_VERSION=<"%VERSION_FILE%"
    call :log "Found installed version: %INSTALLED_VERSION%"
) else (
    set "INSTALLED_VERSION="
    call :log "No previous installation detected"
)
goto :eof

:write_version_file
echo %PLUGIN_VERSION% > "%VERSION_FILE%"
call :log "Version file updated to: %PLUGIN_VERSION%"
goto :eof

REM ===========================================
REM PRE-INSTALLATION CHECKS
REM ===========================================

:check_admin
net session >nul 2>&1
if %errorLevel% == 0 (
    call :log "Running with administrator privileges"
) else (
    call :log "WARNING: Not running with administrator privileges"
    echo WARNING: Some operations may require administrator privileges
)
goto :eof

:check_existing_installation
call :log "Checking for existing installation..."

REM Check for existing task
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %errorlevel% == 0 (
    set "EXISTING_TASK=1"
    call :log "Found existing scheduled task: %TASK_NAME%"
) else (
    set "EXISTING_TASK=0"
    call :log "No existing scheduled task found"
)

REM Check for existing PowerShell script
if exist "%SCRIPT_DIR%axytos_export.ps1" (
    set "EXISTING_POWERSHELL=1"
    call :log "Found existing PowerShell script: %SCRIPT_DIR%axytos_export.ps1"
) else (
    set "EXISTING_POWERSHELL=0"
    call :log "No existing PowerShell script found"
)

REM Check for existing runner script
if exist "%SCRIPT_DIR%automation_runner.bat" (
    set "EXISTING_RUNNER=1"
    call :log "Found existing runner script: %SCRIPT_DIR%automation_runner.bat"
) else (
    set "EXISTING_RUNNER=0"
    call :log "No existing runner script found"
)

REM Check for existing version file
call :get_installed_version

goto :eof

:validate_configuration
call :log "Validating configuration..."

if "%SHOP_URL%"=="" (
    call :error "SHOP_URL is not configured"
    goto :eof
)

if "%WEBHOOK_KEY%"=="" (
    call :error "WEBHOOK_KEY is not configured"
    goto :eof
)

if "%SCHEDULE_TIME%"=="" (
    call :error "SCHEDULE_TIME is not configured"
    goto :eof
)

REM Validate schedule time format (HH:MM)
echo %SCHEDULE_TIME% | findstr /r "^[0-9][0-9]:[0-9][0-9]$" >nul
if %errorlevel% neq 0 (
    call :error "SCHEDULE_TIME must be in HH:MM format (e.g., 09:00)"
    goto :eof
)

call :log "Configuration validation passed"
goto :eof



REM ===========================================
REM SETUP WINDOWS TASK SCHEDULER
REM ===========================================

:setup_task
call :log "Setting up Windows Task Scheduler..."

REM Delete existing task if it exists
schtasks /delete /tn "%TASK_NAME%" /f 2>nul

REM Create new scheduled task to run PowerShell script directly
schtasks /create /tn "%TASK_NAME%" /tr "powershell -ExecutionPolicy Bypass -File \"%POWERSHELL_SCRIPT%\" -LogFile \"%LOG_FILE%\"" /sc daily /st %SCHEDULE_TIME% /ru "%USERNAME%" /rl highest /f

if %errorlevel% neq 0 (
    call :error "Failed to create scheduled task"
    goto :eof
)

call :log "Scheduled task created successfully: %TASK_NAME%"
goto :eof

REM ===========================================
REM CREATE RUNNER SCRIPT
REM ===========================================

:create_runner
call :log "Creating runner script..."

echo @echo off > "%RUNNER_SCRIPT%"
echo REM Axytos Payment Automation Runner >> "%RUNNER_SCRIPT%"
echo REM Version: %PLUGIN_VERSION% >> "%RUNNER_SCRIPT%"
echo REM Generated: %DATE% %TIME% >> "%RUNNER_SCRIPT%"
echo REM Shop URL: %SHOP_URL% >> "%RUNNER_SCRIPT%"
echo. >> "%RUNNER_SCRIPT%"
echo REM Call the main installer script in run_once mode >> "%RUNNER_SCRIPT%"
echo call "%SCRIPT_DIR%installer.bat" run_once >> "%RUNNER_SCRIPT%"

call :log "Runner script created: %RUNNER_SCRIPT% (Version: %PLUGIN_VERSION%)"
goto :eof

REM ===========================================
REM CREATE POWERSHELL SCRIPT
REM ===========================================

:create_powershell_script
call :log "Creating PowerShell export script..."

REM Create the PowerShell script file with embedded content
(
echo # Axytos Payment Automation Export Script
echo # Generated: {{TIMESTAMP}}
echo # Shop URL: {{SHOP_URL}}
echo # Webhook Key: [CONFIGURED]
echo.
echo # ===========================================
echo # CONFIGURATION SECTION
echo # ===========================================
echo.
echo param^(
echo     [string]$ShopUrl = "%SHOP_URL%",
echo     [string]$WebhookKey = "%WEBHOOK_KEY%",
echo     [string]$LogFile,
echo     [string]$TempDir = "$env:TEMP",
echo     [int]$MaxRetries = 3,
echo     [int]$RetryDelaySeconds = 5
echo ^)
echo.
echo # ===========================================
echo # LOGGING FUNCTION
echo # ===========================================
echo.
echo function Write-Log {
echo     param^(
echo         [string]$Message,
echo         [string]$Level = "INFO"
echo     ^)
echo.
echo     $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
echo     $logMessage = "[$timestamp] [$Level] $Message"
echo.
echo     # Write to console
echo     switch ^($Level^) {
echo         "ERROR" { Write-Host $logMessage -ForegroundColor Red }
echo         "WARNING" { Write-Host $logMessage -ForegroundColor Yellow }
echo         "SUCCESS" { Write-Host $logMessage -ForegroundColor Green }
echo         default { Write-Host $logMessage }
echo     }
echo.
echo     # Write to log file
echo     try {
echo         $logMessage ^| Out-File -FilePath $LogFile -Append -Encoding UTF8
echo     } catch {
echo         Write-Host "Failed to write to log file: $($_.Exception.Message)" -ForegroundColor Red
echo     }
echo }
echo.
echo # ===========================================
echo # VALIDATION FUNCTION
echo # ===========================================
echo.
echo function Test-Configuration {
echo     Write-Log "Validating configuration..."
echo.
echo     if ^([string]::IsNullOrEmpty^($ShopUrl^)^) {
echo         throw "ShopUrl is not configured"
echo     }
echo.
echo     if ^([string]::IsNullOrEmpty^($WebhookKey^)^) {
echo         throw "WebhookKey is not configured"
echo     }
echo.
echo     if ^($WebhookKey.Length -lt 10^) {
echo         throw "WebhookKey appears to be too short ^(minimum 10 characters expected^)"
echo     }
echo.
echo     if ^(-not ^(Test-Path $TempDir^)^) {
echo         throw "Temp directory does not exist: $TempDir"
echo     }
echo.
echo     Write-Log "Configuration validation passed"
echo }
echo.
echo # ===========================================
echo # CSV GENERATION FUNCTION ^(DUMMY DATA^)
echo # ===========================================
echo.
echo function New-InvoiceCsv {
echo     param^(
echo         [string]$OutputPath
echo     ^)
echo.
echo     Write-Log "Generating invoice CSV data..."
echo.
echo     try {
echo         # Create dummy invoice data ^(placeholder for JTL-WaWi integration^)
echo         # This represents typical invoice data that would be exported from JTL-WaWi
echo         $csvData = @^(
echo             [PSCustomObject]@{
echo                 'Rechnungsnummer' = 'INV-1234'
echo                 'Externe Bestellnummer' = 'RE-10018'
echo                 'Name' = 'Bla'
echo             },
echo             [PSCustomObject]@{
echo                 'Rechnungsnummer' = 'INV-1235'
echo                 'Externe Bestellnummer' = 'RE-10019'
echo                 'Name' = 'Foo'
echo             }
echo         ^)
echo.
echo         # Export to CSV with semicolon delimiter ^(standard German CSV format^)
echo         $csvData ^| Export-Csv -Path $OutputPath -NoTypeInformation -Encoding UTF8 -Delimiter ';'
echo.
echo         Write-Log "CSV file created successfully: $OutputPath"
echo         return $true
echo.
echo     } catch {
echo         Write-Log "Failed to create CSV file: $($_.Exception.Message)" -Level "ERROR"
echo         return $false
echo     }
echo }
echo.
echo # ===========================================
echo # HTTP UPLOAD FUNCTION
echo # ===========================================
echo.
echo function Send-InvoiceData {
echo     param^(
echo         [string]$CsvPath,
echo         [int]$RetryCount = 0
echo     ^)
echo.
echo     Write-Log "Starting HTTP upload ^(attempt $($RetryCount + 1)/$($MaxRetries + 1)^)..."
echo.
echo     try {
echo         # Read CSV content
echo         if ^(-not ^(Test-Path $CsvPath^)^) {
echo             throw "CSV file not found: $CsvPath"
echo         }
echo.
echo         try {
echo             $csvContent = Get-Content -Path $CsvPath -Raw -Encoding UTF8
echo             Write-Log "CSV file size: $($csvContent.Length) characters"
echo         } catch {
echo             throw "Failed to read CSV file content: $($_.Exception.Message)"
echo         }
echo.
echo         if ^([string]::IsNullOrEmpty^($csvContent^)^) {
echo             throw "CSV file is empty or unreadable"
echo         }
echo.
echo         # Prepare multipart form data
echo         $boundary = [System.Guid]::NewGuid^(^).ToString^(^)
echo         $LF = "`r`n"
echo.
echo         # Build multipart body
echo         $bodyLines = @^(
echo             "--$boundary",
echo             'Content-Disposition: form-data; name="invoice_ids_file"; filename="invoice_data.csv"',
echo             'Content-Type: text/csv',
echo             '',
echo             $csvContent,
echo             "--$boundary--",
echo             ''
echo         ^)
echo.
echo         $body = $bodyLines -join $LF
echo.
echo         # Prepare headers
echo         $headers = @{
echo             'X-Webhook-Key' = $WebhookKey
echo         }
echo.
echo         # Prepare URI
echo         $uri = "$ShopUrl/axytos/v1/invoice-ids"
echo         Write-Log "Target URI: $uri"
echo.
echo         # Make HTTP request
echo         $response = Invoke-WebRequest -Uri $uri -Method POST -Body $body -Headers $headers -ContentType "multipart/form-data; boundary=$boundary" -UseBasicParsing
echo.
echo         Write-Log "HTTP Response Status: $($response.StatusCode)" -Level "SUCCESS"
echo.
echo         if ^($response.Content^) {
echo             Write-Log "Response Content: $($response.Content)"
echo         }
echo.
echo         return @{
echo             Success = $true
echo             StatusCode = $response.StatusCode
echo             Content = $response.Content
echo         }
echo.
echo     } catch {
echo         $errorMessage = $_.Exception.Message
echo         Write-Log "HTTP request failed: $errorMessage" -Level "ERROR"
echo.
echo         # Check if we should retry
echo         if ^($RetryCount -lt $MaxRetries^) {
echo             $retryDelay = $RetryDelaySeconds * [Math]::Pow^(2, $RetryCount^)  # Exponential backoff
echo             Write-Log "Retrying in $retryDelay seconds... ^(attempt $($RetryCount + 2)/$($MaxRetries + 1)^)" -Level "WARNING"
echo             Start-Sleep -Seconds $retryDelay
echo             return Send-InvoiceData -CsvPath $CsvPath -RetryCount ^($RetryCount + 1^)
echo         } else {
echo             Write-Log "Max retries exceeded" -Level "ERROR"
echo             return @{
echo                 Success = $false
echo                 Error = $errorMessage
echo             }
echo         }
echo     }
echo }
echo.
echo # ===========================================
echo # CLEANUP FUNCTION
echo # ===========================================
echo.
echo function Remove-TempFiles {
echo     param^(
echo         [string]$CsvPath
echo     ^)
echo.
echo     try {
echo         if ^(Test-Path $CsvPath^) {
echo             Remove-Item -Path $CsvPath -Force
echo             Write-Log "Cleaned up temporary file: $CsvPath"
echo         }
echo     } catch {
echo         Write-Log "Failed to cleanup temporary file: $($_.Exception.Message)" -Level "WARNING"
echo     }
echo }
echo.
echo # ===========================================
echo # MAIN EXECUTION
echo # ===========================================
echo.
echo function Invoke-Main {
echo     $exitCode = 0
echo.
echo     try {
echo         Write-Log "=== Axytos Payment Automation Export Started ==="
echo.
echo         # Validate configuration
echo         Test-Configuration
echo.
echo         # Generate unique filename for this run
echo         $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
echo         $csvPath = Join-Path $TempDir "axytos_invoice_export_$timestamp.csv"
echo.
echo         # Generate CSV data
echo         $csvResult = New-InvoiceCsv -OutputPath $csvPath
echo         if ^(-not $csvResult^) {
echo             throw "CSV generation failed"
echo         }
echo.
echo         # Upload data
echo         $uploadResult = Send-InvoiceData -CsvPath $csvPath
echo.
echo         if ^($uploadResult.Success^) {
echo             Write-Log "Automation completed successfully" -Level "SUCCESS"
echo         } else {
echo             throw "Upload failed: $($uploadResult.Error)"
echo         }
echo.
echo     } catch {
echo         $errorMessage = $_.Exception.Message
echo         Write-Log "Automation failed: $errorMessage" -Level "ERROR"
echo         $exitCode = 1
echo     } finally {
echo         # Always cleanup temporary files
echo         if ^($csvPath -and ^(Test-Path $csvPath^)^) {
echo             Remove-TempFiles -CsvPath $csvPath
echo         }
echo.
echo         Write-Log "=== Axytos Payment Automation Export Completed ==="
echo     }
echo.
echo     return $exitCode
echo }
echo.
echo # ===========================================
echo # SCRIPT ENTRY POINT
echo # ===========================================
echo.
echo # Execute main function and exit with appropriate code
echo $exitCode = Invoke-Main
echo exit $exitCode
) > "%POWERSHELL_SCRIPT%"

if %errorlevel% neq 0 (
    call :error "Failed to create PowerShell script file"
    goto :eof
)

call :log "PowerShell script created successfully: %POWERSHELL_SCRIPT%"
goto :eof

REM ===========================================
REM VERIFY POWERSHELL EXECUTION POLICY
REM ===========================================

:check_powershell_policy
call :log "Checking PowerShell execution policy..."

powershell -Command "Get-ExecutionPolicy" >nul 2>&1
if %errorlevel% neq 0 (
    call :log "WARNING: PowerShell execution policy may restrict script execution"
    echo WARNING: PowerShell execution policy may prevent script execution
    echo You may need to run: Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
    echo.
)

call :log "PowerShell execution policy check completed"
goto :eof

REM ===========================================
REM VERIFY INSTALLATION
REM ===========================================

:verify_installation
call :log "Verifying installation..."

REM Check if PowerShell script exists
if not exist "%POWERSHELL_SCRIPT%" (
    call :error "PowerShell script not found: %POWERSHELL_SCRIPT%"
    goto :eof
)

REM Check if scheduled task exists
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %errorlevel% neq 0 (
    call :error "Scheduled task not found: %TASK_NAME%"
    goto :eof
)

REM Check if runner script exists
if not exist "%RUNNER_SCRIPT%" (
    call :error "Runner script not found: %RUNNER_SCRIPT%"
    goto :eof
)

call :log "Installation verification completed successfully"
goto :eof

REM ===========================================
REM UNINSTALLATION COMMANDS (COMMENTED OUT)
REM ===========================================

:uninstall
call :log "Starting uninstallation process..."

REM Uncomment the following lines to enable uninstallation:
REM echo This will remove the Axytos automation system.
REM echo Press Ctrl+C to cancel or Enter to continue...
REM pause >nul

REM schtasks /delete /tn "%TASK_NAME%" /f 2>nul
REM if exist "%POWERSHELL_SCRIPT%" del "%POWERSHELL_SCRIPT%" 2>nul
REM if exist "%RUNNER_SCRIPT%" del "%RUNNER_SCRIPT%" 2>nul
REM if exist "%VERSION_FILE%" del "%VERSION_FILE%" 2>nul

REM call :log "Uninstallation completed"
goto :eof

REM ===========================================
REM VERSION CHECK FOR UPDATES
REM ===========================================

:check_for_updates
call :log "Checking for available updates..."

REM This is a placeholder for future update checking
REM In a real implementation, this could check a remote endpoint
REM for the latest version and compare with current version

if defined INSTALLED_VERSION (
    if "%INSTALLED_VERSION%" neq "%PLUGIN_VERSION%" (
        call :log "Version mismatch detected - Installed: %INSTALLED_VERSION%, Current: %PLUGIN_VERSION%"
        echo Version update available: %INSTALLED_VERSION% -^> %PLUGIN_VERSION%
    ) else (
        call :log "Versions match - no update needed"
    )
) else (
    call :log "No installed version found - fresh installation"
)

goto :eof

REM ===========================================
REM RUN ONCE MODE (FOR SCHEDULED EXECUTION)
REM ===========================================

:run_once
call :log "Starting automation run..."

REM Verify PowerShell script exists
if not exist "%POWERSHELL_SCRIPT%" (
    call :error "PowerShell script not found: %POWERSHELL_SCRIPT%"
    goto :eof
)

REM Execute PowerShell script with bypass execution policy
call :log "Executing PowerShell automation script..."
powershell -ExecutionPolicy Bypass -File "%POWERSHELL_SCRIPT%" -LogFile "%LOG_FILE%"

if %errorlevel% neq 0 (
    call :error "PowerShell automation script failed with exit code: %errorlevel%"
    goto :eof
)

call :log "Automation run completed successfully"
goto :eof

REM ===========================================
REM INSTALLATION MODE
REM ===========================================

:install
call :log "Starting installation process..."

REM Run pre-installation checks
call :check_existing_installation
call :validate_configuration

if %errorlevel% neq 0 (
    call :error "Pre-installation checks failed"
    goto :eof
)

echo ===========================================
echo Axytos Payment Automation Installer
echo ===========================================
echo.
echo This will install the Axytos payment automation system.
echo.

REM Show existing installation info
if defined INSTALLED_VERSION (
    echo Existing installation detected:
    echo - Version: %INSTALLED_VERSION%
    echo - New version: %PLUGIN_VERSION%
    echo.
)

if "%EXISTING_TASK%"=="1" (
    echo - Existing scheduled task will be updated
)
if "%EXISTING_POWERSHELL%"=="1" (
    echo - Existing PowerShell script will be updated
)
if "%EXISTING_RUNNER%"=="1" (
    echo - Existing runner script will be updated
)

echo.
echo Configuration:
echo - Shop URL: %SHOP_URL%
echo - Schedule: Daily at %SCHEDULE_TIME%
echo - Log file: %LOG_FILE%
echo - Version: %PLUGIN_VERSION%
echo.

set /p "confirm=Continue with installation? (y/N): "
if /i not "%confirm%"=="y" (
    echo Installation cancelled.
    call :log "Installation cancelled by user"
    goto :eof
)

REM Perform installation steps
call :check_admin
call :check_powershell_policy

REM Create required directories
if not exist "%APP_DIR%" (
    mkdir "%APP_DIR%" 2>nul
    if %errorlevel% neq 0 (
        call :log "WARNING: Could not create application directory: %APP_DIR%"
        call :log "Falling back to script directory for data files"
        set "APP_DIR=%SCRIPT_DIR%"
        set "LOG_FILE=%APP_DIR%\axytos_automation.log"
        set "VERSION_FILE=%APP_DIR%\version.txt"
        set "POWERSHELL_LOG=%APP_DIR%\powershell.log"
    ) else (
        call :log "Created application directory: %APP_DIR%"
    )
)

REM Remove existing task if it exists
if "%EXISTING_TASK%"=="1" (
    call :log "Removing existing scheduled task..."
    schtasks /delete /tn "%TASK_NAME%" /f 2>nul
)

REM Remove existing PowerShell script if it exists
if "%EXISTING_POWERSHELL%"=="1" (
    call :log "Removing existing PowerShell script..."
    if exist "%POWERSHELL_SCRIPT%" del "%POWERSHELL_SCRIPT%" 2>nul
)

call :create_powershell_script
call :create_runner
call :setup_task
call :write_version_file
call :verify_installation

echo.
echo ===========================================
echo Installation completed successfully!
echo ===========================================
echo.
echo The automation system has been installed and configured:
echo - PowerShell script: %POWERSHELL_SCRIPT%
echo - Application data: %APP_DIR%
echo - Runner script: %SCRIPT_DIR%automation_runner.bat
echo - Scheduled task: %TASK_NAME%
echo - Log files: %LOG_FILE%, %POWERSHELL_LOG%
echo - Version: %PLUGIN_VERSION%
echo.
echo The system will run automatically every day at %SCHEDULE_TIME%.
echo.
echo IMPORTANT: Before the automation can work, you need to configure the JTL-WaWi connection:
echo 1. Open the PowerShell script: %POWERSHELL_SCRIPT%
echo 2. Edit the WAWI Configuration section at the top of the file
echo 3. Update the following parameters with your JTL-WaWi settings:
echo    - WawiServer (your SQL Server instance)
echo    - WawiDatabase (your JTL-WaWi database name)
echo    - WawiUser (database username)
echo    - WawiPassword (database password)
echo    - WawiExportTemplate (export template ID, e.g., EXP1)
echo    - WawiInstallPath (path to JTL-Software installation directory)
echo.
echo Test the configuration by running the PowerShell script manually first.
echo.

call :log "Installation completed successfully - Version: %PLUGIN_VERSION%"
pause
goto :eof

REM ===========================================
REM DISPLAY VERSION INFO
REM ===========================================

:show_version
echo Axytos Payment Automation Script
echo Version: %PLUGIN_VERSION%
echo Generated: {{TIMESTAMP}}
echo PowerShell Script: %POWERSHELL_SCRIPT%
echo Application Data: %APP_DIR%
if defined INSTALLED_VERSION (
    echo Installed Version: %INSTALLED_VERSION%
) else (
    echo Installed Version: Not installed
)
echo.
goto :eof

REM ===========================================
REM MAIN EXECUTION
REM ===========================================

:main
call :log "Script started with arguments: %*"
call :get_installed_version

if "%1"=="run_once" (
    call :run_once
) elseif "%1"=="version" (
    call :show_version
) elseif "%1"=="uninstall" (
    call :uninstall
) elseif "%1"=="check_updates" (
    call :check_for_updates
) else (
    call :install
)

call :log "Script execution completed"
goto :eof

REM Execute main function
call :main %*