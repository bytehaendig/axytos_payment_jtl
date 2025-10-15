[Axytos]
; Axytos Payment Plugin Configuration
; Version: {$PLUGIN_VERSION}
; Generated: {$TIMESTAMP}

; Shop Configuration (automatically configured)
ShopUrl={$SHOP_URL}
WebhookKey={$WEBHOOK_KEY_RAW}

; Schedule Configuration
; Time format: HH:MM (24-hour format)
ScheduleTime={$SCHEDULE_TIME}

; Plugin Information
PluginVersion={$PLUGIN_VERSION}

[WaWi]
; JTL-WaWi Database Configuration
; Edit these values to match your JTL-WaWi installation
Server=(local)\JTLWAWI
Database=eazybusiness
User=sa
Password=sa04jT14
ExportTemplate=EXP1
InstallPath=C:\Program Files (x86)\JTL-Software

[Advanced]
; Advanced Settings (optional)
MaxRetries=3
RetryDelaySeconds=5
LogLevel=INFO
