@echo off
REM Axytos Payment Automation Runner
REM Generated: {$TIMESTAMP}
REM Shop URL: {$SHOP_URL}

REM ===========================================
REM CONFIGURATION SECTION
REM ===========================================

set "SCRIPT_DIR=%~dp0"
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

REM ===========================================
REM LOGGING FUNCTION
REM ===========================================

:log
echo [%DATE% %TIME%] %~1 >> "%LOG_FILE%"
goto :eof

REM ===========================================
REM MAIN RUNNER
REM ===========================================

call :log "=== Axytos Automation Runner Started ==="

REM Check if PowerShell script exists
if not exist "%POWERSHELL_SCRIPT%" (
    call :log "ERROR: PowerShell script not found: %POWERSHELL_SCRIPT%"
    exit /b 1
)

REM Execute PowerShell script with bypass policy
call :log "Executing PowerShell automation script..."
powershell.exe -ExecutionPolicy Bypass -File "%POWERSHELL_SCRIPT%"

REM Log the exit code
set "PS_EXIT_CODE=%errorlevel%"
if %PS_EXIT_CODE% equ 0 (
    call :log "PowerShell script completed successfully (exit code: %PS_EXIT_CODE%)"
) else (
    call :log "PowerShell script failed with exit code: %PS_EXIT_CODE%"
)

call :log "=== Axytos Automation Runner Completed ==="

REM Exit with the same code as the PowerShell script
exit /b %PS_EXIT_CODE%