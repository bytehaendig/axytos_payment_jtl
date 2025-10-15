================================================================================
  Axytos Payment Automation for JTL-WaWi
  Version {$PLUGIN_VERSION}
  Generated: {$TIMESTAMP}
================================================================================

OVERVIEW
--------
This automation system synchronizes invoice data from JTL-WaWi to your
JTL-Shop Axytos Payment plugin. It runs automatically on a daily schedule
using Windows Task Scheduler.

All files are contained in a single folder - no installation to system directories.

SYSTEM REQUIREMENTS
-------------------
- Windows 10/11 (Windows Server 2016+ also supported)
- PowerShell 5.1 or later (included with Windows 10/11)
- JTL-WaWi installation with JTL-Ameise
- Network access to: {$SHOP_URL}

INSTALLATION INSTRUCTIONS
-------------------------
1. Extract all files from this ZIP archive to a folder of your choice
   Recommended: C:\Tools\AxytosPaymentAutomation\
   (You can choose any location you prefer)

2. IMPORTANT: Edit config.ini and update the [WaWi] section with your
   JTL-WaWi database credentials and installation path.

3. Right-click on install.bat and select "Run as administrator"
   (Administrator rights are required for creating scheduled tasks)

4. The automation will run daily at the configured time (default: 17:00)

WHAT GETS INSTALLED
-------------------
- PowerShell export script: axytos_export.ps1
- Task runner wrapper: automation_runner.bat
- Windows scheduled task: "AxytosPaymentAutomation"
- Configuration file: config.ini (in the same folder)
- Log files: axytos_automation.log (in the same folder)
- Documentation: README.txt (this file)

All files remain in your chosen installation folder.

CONFIGURATION
-------------
The config.ini file contains all settings:

[Axytos]
- ShopUrl: Your shop URL (pre-configured)
- WebhookKey: Authentication key (pre-configured, keep secret!)
- ScheduleTime: When to run daily (HH:MM format, default: 17:00)

[WaWi]
- Server: SQL Server instance (e.g., (local)\JTLWAWI)
- Database: JTL-WaWi database name
- User: Database username
- Password: Database password
- ExportTemplate: JTL-Ameise export template ID
- InstallPath: JTL-Software installation directory

[Advanced]
- MaxRetries: HTTP upload retry attempts (default: 3)
- RetryDelaySeconds: Delay between retries (default: 5)
- LogLevel: Logging verbosity (INFO, DEBUG, WARNING, ERROR)

UPDATING CONFIGURATION
----------------------
To change settings:
1. Edit config.ini in this folder
2. Run install.bat again to update the scheduled task

MANUAL TESTING
--------------
To test the automation manually:
1. Open Command Prompt
2. Navigate to this folder
3. Run: automation_runner.bat
4. Check axytos_automation.log for results

LOG FILES
---------
Log files are stored in the same folder as the scripts:
- axytos_automation.log: Main log file with all activity

UNINSTALLATION
--------------
1. Run uninstall.bat from this folder
2. Confirm when prompted
3. This removes only the scheduled task
4. You can manually delete this folder if no longer needed

TROUBLESHOOTING
---------------
Problem: "PowerShell script not found" error
Solution: Make sure all files from the ZIP were extracted to the same folder

Problem: "Access denied" when creating scheduled task
Solution: Run install.bat as administrator (right-click → Run as administrator)

Problem: "JTL-Ameise executable not found"
Solution: Edit config.ini and verify the InstallPath points to your JTL-Software folder

Problem: HTTP connection errors
Solution: Check that your shop URL is accessible and the WebhookKey is correct

Problem: Database connection errors
Solution: Verify the [WaWi] database credentials in config.ini

SUPPORT
-------
For issues with this automation system:
1. Check axytos_automation.log in this folder
2. Verify all configuration settings in config.ini
3. Test manual execution with automation_runner.bat
4. Contact your JTL-Shop administrator or plugin support

SECURITY NOTES
--------------
- Keep config.ini secure - it contains your webhook key and database credentials
- The WebhookKey is used for authentication with your shop
- Never share config.ini or log files publicly
- Use strong database passwords

FOLDER ORGANIZATION
-------------------
We recommend extracting to: C:\Tools\AxytosPaymentAutomation\

Alternative locations you can use:
- C:\Automation\AxytosPaymentAutomation\
- C:\Utils\AxytosPaymentAutomation\
- C:\JTL\AxytosPaymentAutomation\
- Any folder you prefer

VERSION INFORMATION
-------------------
Plugin Version: {$PLUGIN_VERSION}
Generation Date: {$TIMESTAMP}
Shop URL: {$SHOP_URL}

================================================================================
