@echo off
REM Axytos Payment Automation Installer v{$PLUGIN_VERSION}
REM Generated: {$TIMESTAMP}
REM Shop URL: {$SHOP_URL}

setlocal enabledelayedexpansion

REM ===========================================
REM CONFIGURATION
REM ===========================================

set "SCRIPT_DIR=%~dp0"
set "TASK_NAME=AxytosPaymentAutomation"

REM ===========================================
REM DISPLAY BANNER
REM ===========================================

echo ===========================================
echo Axytos Payment Automation Installer
echo Version: {$PLUGIN_VERSION}
echo ===========================================
echo.

REM ===========================================
REM CHECK PREREQUISITES
REM ===========================================

echo Checking prerequisites...

REM Check if config.ini exists
if not exist "%SCRIPT_DIR%config.ini" (
    echo ERROR: config.ini not found!
    echo.
    echo Please make sure all files from the ZIP archive are extracted
    echo to the same folder.
    echo.
    pause
    exit /b 1
)

REM Check if PowerShell script exists
if not exist "%SCRIPT_DIR%axytos_export.ps1" (
    echo ERROR: axytos_export.ps1 not found!
    echo.
    echo Please make sure all files from the ZIP archive are extracted
    echo to the same folder.
    echo.
    pause
    exit /b 1
)

REM Check if runner script exists
if not exist "%SCRIPT_DIR%automation_runner.bat" (
    echo ERROR: automation_runner.bat not found!
    echo.
    echo Please make sure all files from the ZIP archive are extracted
    echo to the same folder.
    echo.
    pause
    exit /b 1
)

echo [OK] All required files found
echo.

REM ===========================================
REM READ CONFIGURATION
REM ===========================================

echo Reading configuration...

REM Use PowerShell to read INI file
for /f "usebackq tokens=*" %%a in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-Content '%SCRIPT_DIR%config.ini' | Select-String -Pattern '^ScheduleTime=' | ForEach-Object { $_.Line.Split('=')[1].Trim() }"`) do set "SCHEDULE_TIME=%%a"

if "!SCHEDULE_TIME!"=="" (
    echo WARNING: Could not read ScheduleTime from config.ini
    echo Using default: 17:00
    set "SCHEDULE_TIME=17:00"
)

echo [OK] Schedule time: !SCHEDULE_TIME!
echo.

REM ===========================================
REM CREATE SCHEDULED TASK
REM ===========================================

echo Setting up scheduled task...

REM Delete existing task if it exists
schtasks /query /tn "%TASK_NAME%" >nul 2>&1
if !errorlevel! equ 0 (
    echo Removing existing task...
    schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1
)

REM Create new scheduled task pointing to current folder
schtasks /create /tn "%TASK_NAME%" /tr "\"%SCRIPT_DIR%automation_runner.bat\"" /sc daily /st !SCHEDULE_TIME! /f >nul 2>&1

if !errorlevel! neq 0 (
    echo ERROR: Failed to create scheduled task
    echo.
    echo This usually means you need administrator privileges.
    echo Please try running this installer as administrator:
    echo - Right-click install.bat
    echo - Select "Run as administrator"
    echo.
    pause
    exit /b 1
)

echo [OK] Scheduled task created: %TASK_NAME%
echo     Schedule: Daily at !SCHEDULE_TIME!
echo     Task runs: "%SCRIPT_DIR%automation_runner.bat"
echo.

REM ===========================================
REM INSTALLATION COMPLETE
REM ===========================================

echo ===========================================
echo Installation completed successfully!
echo ===========================================
echo.
echo Installation folder: %SCRIPT_DIR%
echo.
echo Files in this folder:
echo - axytos_export.ps1 (main automation script)
echo - automation_runner.bat (task scheduler wrapper)
echo - config.ini (configuration file)
echo - uninstall.bat (removal script)
echo - README.txt (documentation)
echo.
echo Scheduled task: %TASK_NAME%
echo Schedule: Daily at !SCHEDULE_TIME!
echo.
echo Log files will be created in this folder:
echo - axytos_automation.log
echo.
echo IMPORTANT: Before first run, edit config.ini and configure
echo your JTL-WaWi database credentials in the [WaWi] section.
echo.
echo To test manually, run:
echo automation_runner.bat
echo.
echo To uninstall, run:
echo uninstall.bat
echo.
echo For detailed help, see README.txt
echo.

pause
