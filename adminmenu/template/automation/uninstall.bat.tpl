@echo off
REM Axytos Payment Automation Uninstaller
REM Generated: {$TIMESTAMP}
REM Version: {$PLUGIN_VERSION}

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
echo Axytos Payment Automation Uninstaller
echo ===========================================
echo.
echo This will remove the Axytos automation system scheduled task.
echo.
echo The automation files will remain in this folder:
echo %SCRIPT_DIR%
echo.
echo You can safely delete this folder manually if you no longer
echo need the automation system.
echo.
echo WARNING: This action cannot be undone!
echo.

set /p "confirm=Are you sure you want to uninstall? (y/N): "
if /i not "%confirm%"=="y" (
    echo.
    echo Uninstallation cancelled.
    echo.
    pause
    exit /b 0
)

echo.

REM ===========================================
REM STOP RUNNING TASKS
REM ===========================================

echo Stopping any running automation tasks...
schtasks /end /tn "%TASK_NAME%" 2>nul
if !errorlevel! equ 0 (
    echo [OK] Running task stopped
) else (
    echo [OK] No running task found
)

REM ===========================================
REM DELETE SCHEDULED TASK
REM ===========================================

echo Removing scheduled task...
schtasks /delete /tn "%TASK_NAME%" /f 2>nul
if !errorlevel! equ 0 (
    echo [OK] Scheduled task removed: %TASK_NAME%
) else (
    echo [OK] Scheduled task not found (may have been removed already)
)

REM ===========================================
REM UNINSTALLATION COMPLETE
REM ===========================================

echo.
echo ===========================================
echo Uninstallation completed successfully!
echo ===========================================
echo.
echo The scheduled task has been removed.
echo.
echo The automation files are still available in:
echo %SCRIPT_DIR%
echo.
echo You can:
echo - Keep the folder for manual execution
echo - Delete the folder if you no longer need it
echo - Re-run install.bat to recreate the scheduled task
echo.
echo To manually run the automation:
echo automation_runner.bat
echo.

pause
