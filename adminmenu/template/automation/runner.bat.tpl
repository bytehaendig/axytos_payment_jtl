@echo off
REM Axytos Payment Automation Runner
REM Generated: {$TIMESTAMP}
REM Version: {$PLUGIN_VERSION}

REM ===========================================
REM CONFIGURATION
REM ===========================================

set "SCRIPT_DIR=%~dp0"
set "POWERSHELL_SCRIPT=%SCRIPT_DIR%axytos_export.ps1"
set "LOG_FILE=%SCRIPT_DIR%axytos_automation.log"

REM ===========================================
REM MAIN RUNNER
REM ===========================================

echo [%DATE% %TIME%] === Axytos Automation Runner Started === >> "%LOG_FILE%"
echo [%DATE% %TIME%] === Axytos Automation Runner Started ===

REM Check if PowerShell script exists
if not exist "%POWERSHELL_SCRIPT%" (
    echo [%DATE% %TIME%] ERROR: PowerShell script not found: %POWERSHELL_SCRIPT% >> "%LOG_FILE%"
    echo [%DATE% %TIME%] ERROR: PowerShell script not found: %POWERSHELL_SCRIPT%
    exit /b 1
)

REM Execute PowerShell script with bypass policy and capture output
echo [%DATE% %TIME%] Executing PowerShell automation script... >> "%LOG_FILE%"
echo [%DATE% %TIME%] Executing PowerShell automation script...

REM Run PowerShell and show output in console (also gets logged by PowerShell itself)
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%POWERSHELL_SCRIPT%"
set "PS_EXIT_CODE=%errorlevel%"

REM Log the exit code
if %PS_EXIT_CODE% equ 0 (
    echo [%DATE% %TIME%] PowerShell script completed successfully >> "%LOG_FILE%"
    echo [%DATE% %TIME%] PowerShell script completed successfully
) else (
    echo [%DATE% %TIME%] PowerShell script failed with exit code: %PS_EXIT_CODE% >> "%LOG_FILE%"
    echo [%DATE% %TIME%] PowerShell script failed with exit code: %PS_EXIT_CODE%
)

echo [%DATE% %TIME%] === Axytos Automation Runner Completed === >> "%LOG_FILE%"
echo [%DATE% %TIME%] === Axytos Automation Runner Completed ===

REM Exit with the same code as the PowerShell script
exit /b %PS_EXIT_CODE%
