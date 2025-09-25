@echo off
REM Axytos Payment Automation Uninstaller
REM Generated: {$TIMESTAMP}
REM Shop URL: {$SHOP_URL}

REM ===========================================
REM CONFIGURATION SECTION
REM ===========================================

set "SHOP_URL={$SHOP_URL}"
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
REM MAIN UNINSTALLATION
REM ===========================================

:main
call :log "=== Axytos Payment Automation Uninstaller Started ==="

echo ===========================================
echo Axytos Payment Automation Uninstaller
echo ===========================================
echo.
echo This will completely remove the Axytos automation system including:
echo - Scheduled task: %TASK_NAME%
echo - PowerShell script: %POWERSHELL_SCRIPT%
echo - Runner script: %RUNNER_SCRIPT%
echo - Version file: %VERSION_FILE%
echo - Log files in: %APP_DIR%
echo - Application directory: %APP_DIR% (if empty)
echo.
echo WARNING: This action cannot be undone!
echo.

set /p "confirm=Are you sure you want to uninstall? (y/N): "
if /i not "%confirm%"=="y" (
    echo Uninstallation cancelled.
    call :log "Uninstallation cancelled by user"
    goto :eof
)

REM Stop any running tasks first
call :log "Stopping any running automation tasks..."
schtasks /end /tn "%TASK_NAME%" 2>nul

REM Delete scheduled task
call :log "Removing scheduled task: %TASK_NAME%"
schtasks /delete /tn "%TASK_NAME%" /f 2>nul
if %errorlevel% neq 0 (
    call :log "WARNING: Failed to delete scheduled task (may not exist)"
)

REM Delete automation files
call :log "Removing automation files..."

if exist "%POWERSHELL_SCRIPT%" (
    del "%POWERSHELL_SCRIPT%" 2>nul
    if %errorlevel% equ 0 (
        call :log "Deleted PowerShell script: %POWERSHELL_SCRIPT%"
    ) else (
        call :log "WARNING: Failed to delete PowerShell script"
    )
) else (
    call :log "PowerShell script not found (already removed)"
)

if exist "%RUNNER_SCRIPT%" (
    del "%RUNNER_SCRIPT%" 2>nul
    if %errorlevel% equ 0 (
        call :log "Deleted runner script: %RUNNER_SCRIPT%"
    ) else (
        call :log "WARNING: Failed to delete runner script"
    )
) else (
    call :log "Runner script not found (already removed)"
)

if exist "%VERSION_FILE%" (
    del "%VERSION_FILE%" 2>nul
    if %errorlevel% equ 0 (
        call :log "Deleted version file: %VERSION_FILE%"
    ) else (
        call :log "WARNING: Failed to delete version file"
    )
) else (
    call :log "Version file not found (already removed)"
)

REM Remove log files
if exist "%LOG_FILE%" (
    del "%LOG_FILE%" 2>nul
    if %errorlevel% equ 0 (
        call :log "Deleted main log file: %LOG_FILE%"
    ) else (
        call :log "WARNING: Failed to delete main log file"
    )
)

if exist "%POWERSHELL_LOG%" (
    del "%POWERSHELL_LOG%" 2>nul
    if %errorlevel% equ 0 (
        call :log "Deleted PowerShell log file: %POWERSHELL_LOG%"
    ) else (
        call :log "WARNING: Failed to delete PowerShell log file"
    )
)

REM Remove application directory if empty
if exist "%APP_DIR%" (
    REM Check if directory is empty (only contains directories or nothing)
    dir /b "%APP_DIR%" 2>nul | findstr "." >nul
    if %errorlevel% neq 0 (
        REM Directory is empty, safe to remove
        rmdir "%APP_DIR%" 2>nul
        if %errorlevel% equ 0 (
            call :log "Removed empty application directory: %APP_DIR%"
        ) else (
            call :log "WARNING: Failed to remove application directory"
        )
    ) else (
        call :log "Application directory not empty, keeping it: %APP_DIR%"
        echo Note: Application directory %APP_DIR% was not removed because it contains files.
    )
)

echo.
echo ===========================================
echo Uninstallation completed!
echo ===========================================
echo.
echo The Axytos automation system has been completely removed.
echo You can safely delete this uninstaller script.
echo.

call :log "=== Axytos Payment Automation Uninstaller Completed ==="
pause
goto :eof

REM Execute main function
call :main