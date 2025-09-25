@echo off
REM Axytos Payment Automation Installer v{$PLUGIN_VERSION}
REM Generated: {$TIMESTAMP}
REM Shop URL: {$SHOP_URL}

REM ===========================================
REM CONFIGURATION SECTION
REM ===========================================

set "SHOP_URL={$SHOP_URL}"
set "WEBHOOK_KEY={$WEBHOOK_KEY}"
set "SCHEDULE_TIME={$SCHEDULE_TIME}"
set "PLUGIN_VERSION={$PLUGIN_VERSION}"
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

set "LOG_FILE=%APP_DIR%\axytos_automation.log"
set "VERSION_FILE=%APP_DIR%\version.txt"
set "POWERSHELL_LOG=%APP_DIR%\powershell.log"

REM ===========================================
REM LOGGING FUNCTION
REM ===========================================

:log
echo [%DATE% %TIME%] %~1 >> "%LOG_FILE%"
echo %~1
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
REM MAIN INSTALLATION
REM ===========================================

:main
call :log "=== Axytos Payment Automation Installer Started ==="

echo ===========================================
echo Axytos Payment Automation Installer
echo ===========================================
echo Shop URL: %SHOP_URL%
echo Version: %PLUGIN_VERSION%
echo Schedule: Daily at %SCHEDULE_TIME%
echo ===========================================
echo.

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

REM Create PowerShell script
call :create_powershell_script

REM Create runner script  
call :create_runner_script

REM Create uninstall script
call :create_uninstall_script

REM Setup scheduled task
call :setup_task

REM Write version file
call :write_version_file

REM Verify installation
call :verify_installation

echo.
echo ===========================================
echo Installation completed successfully!
echo ===========================================
echo.
echo The automation system has been installed and configured:
echo - PowerShell script: %POWERSHELL_SCRIPT%
echo - Runner script: %RUNNER_SCRIPT%
echo - Uninstall script: %SCRIPT_DIR%uninstall.bat
echo - Application data: %APP_DIR%
echo - Scheduled task: %TASK_NAME%
echo - Log files: %LOG_FILE%, %POWERSHELL_LOG%
echo - Version: %PLUGIN_VERSION%
echo.
echo The system will run daily at %SCHEDULE_TIME%.
echo Check the log files for execution status.
echo To uninstall, run the uninstall.bat script.
echo.

call :log "=== Axytos Payment Automation Installer Completed ==="
pause
goto :eof

REM ===========================================
REM CREATE POWERSHELL SCRIPT
REM ===========================================

:create_powershell_script
call :log "Creating PowerShell export script..."

REM Write the PowerShell script content directly from our template
(
{{POWERSHELL_SCRIPT_CONTENT}}
) > "%POWERSHELL_SCRIPT%"

if %errorlevel% neq 0 (
    call :error "Failed to create PowerShell script file"
    goto :eof
)

call :log "PowerShell script created successfully: %POWERSHELL_SCRIPT%"
goto :eof

REM ===========================================
REM CREATE RUNNER SCRIPT
REM ===========================================

:create_runner_script
call :log "Creating runner script..."

REM Write the runner script content from our template
(
{{RUNNER_SCRIPT_CONTENT}}
) > "%RUNNER_SCRIPT%"

if %errorlevel% neq 0 (
    call :error "Failed to create runner script file"
    goto :eof
)

call :log "Runner script created successfully: %RUNNER_SCRIPT%"
goto :eof

REM ===========================================
REM CREATE UNINSTALL SCRIPT
REM ===========================================

:create_uninstall_script
call :log "Creating uninstall script..."

REM Write the uninstall script content from our template
(
{{UNINSTALL_SCRIPT_CONTENT}}
) > "%SCRIPT_DIR%uninstall.bat"

if %errorlevel% neq 0 (
    call :error "Failed to create uninstall script file"
    goto :eof
)

call :log "Uninstall script created successfully: %SCRIPT_DIR%uninstall.bat"
goto :eof

REM ===========================================
REM SETUP SCHEDULED TASK
REM ===========================================

:setup_task
call :log "Setting up scheduled task..."

REM Delete existing task if it exists
schtasks /delete /tn "%TASK_NAME%" /f 2>nul

REM Create new scheduled task
schtasks /create /tn "%TASK_NAME%" /tr "\"%RUNNER_SCRIPT%\"" /sc daily /st %SCHEDULE_TIME% /f

if %errorlevel% neq 0 (
    call :error "Failed to create scheduled task"
    goto :eof
)

call :log "Scheduled task created successfully: %TASK_NAME%"
goto :eof

REM ===========================================
REM WRITE VERSION FILE
REM ===========================================

:write_version_file
call :log "Writing version file..."

echo Plugin Version: %PLUGIN_VERSION% > "%VERSION_FILE%"
echo Installation Date: %DATE% %TIME% >> "%VERSION_FILE%"
echo Shop URL: %SHOP_URL% >> "%VERSION_FILE%"
echo Schedule: Daily at %SCHEDULE_TIME% >> "%VERSION_FILE%"

if %errorlevel% neq 0 (
    call :error "Failed to create version file"
    goto :eof
)

call :log "Version file created successfully: %VERSION_FILE%"
goto :eof

REM ===========================================
REM VERIFY INSTALLATION
REM ===========================================

:verify_installation
call :log "Verifying installation..."

REM Check if all files were created
if not exist "%POWERSHELL_SCRIPT%" (
    call :error "PowerShell script was not created properly"
    goto :eof
)

if not exist "%RUNNER_SCRIPT%" (
    call :error "Runner script was not created properly"
    goto :eof
)

if not exist "%SCRIPT_DIR%uninstall.bat" (
    call :error "Uninstall script was not created properly"
    goto :eof
)

REM Check if scheduled task exists
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if %errorlevel% neq 0 (
    call :error "Scheduled task was not created properly"
    goto :eof
)

call :log "Installation verification completed successfully"
goto :eof

REM Execute main function
call :main