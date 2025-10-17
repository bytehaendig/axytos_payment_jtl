# Axytos Payment-Plugin für JTL-Shop

Dieses Plugin integriert Axytos als Zahlungsmethode in Ihren JTL-Shop und ermöglicht "Kauf auf Rechnung" (Buy Now, Pay Later) mit automatischer Bonitätsprüfung.

## Systemvoraussetzungen

- JTL-Shop Version 5.0.0 oder höher
- PHP 7.4 oder höher
- HTTPS-fähiger Webserver

## Installation

1. Plugin als ZIP-Datei im JTL-Shop Admin-Bereich hochladen (**Plugin Manager > Upload > Datei auswählen**)
2. Plugin aktivieren (**Plugin Manager > Vorhanden**)
3. Nach der Aktivierung erscheint:
   - Im Admin-Menü unter **Installierte Plugins** der Eintrag **Axytos Payment**
   - Auf dem Dashboard das Widget **Axytos Overview**

## Einrichtung

### API-Schlüssel konfigurieren

1. Sie erhalten den API-Schlüssel von einem Axytos-Mitarbeiter
2. Öffnen Sie im Shop-Admin den Bereich **Axytos Payment > API Setup**
3. Geben Sie den API-Schlüssel ein
4. Wählen Sie den Betriebsmodus:
   - **Sandbox**: Haken bei "Sandbox-Modus" setzen (verwendet Test-Server von Axytos)
   - **Produktion**: Haken bei "Sandbox-Modus" nicht setzen (verwendet Live-System)

### Bezahlart in JTL-WaWi hinzufügen

- im JTL-WaWi das Menü **Zahlungen > Zahlungsarten** auswählen
- via "Anlegen" eine neue Zahlungsart erzeugen - diese muss den Namen "Bezahlen auf Rechnung" haben und das Häkchen "Auslieferung vor Zahlungseingang möglich" gesetzt haben
- für englischsprachige Bestellungen eine weitere Zahlungsart mit dem Namen "Pay Later" anlegen (gleiches Häkchen setzen)

## Rechnungsnummern übermitteln

Damit Axytos die Bezahlungsverarbeitung durchführen kann, müssen die Rechnungsnummern an Axytos übermittelt werden. Da Rechnungsnummern nicht automatisch beim Datenabgleich zwischen JTL-Shop und JTL-WaWi übertragen werden, gibt es drei Möglichkeiten zur Übermittlung:

### 1. Manuell einzeln

**Empfohlene Häufigkeit:** Regelmäßig (z.B. 1× pro Woche)

1. Öffnen Sie **Axytos Payment > Rechnungen**
2. Es wird eine Liste der Bestellungen angezeigt, für die noch keine Rechnungsnummer übermittelt wurde
3. Klicken Sie auf eine Bestellung und geben Sie die zugehörige Rechnungsnummer ein

### 2. Manuell via CSV-Upload

**Empfohlene Häufigkeit:** Regelmäßig (z.B. 1× pro Woche)

1. Erstellen Sie eine CSV-Datei mit der **JTL-Ameise Exportvorlage** (siehe unten):
   - Öffnen Sie JTL-WaWi und starten Sie die JTL-Ameise (**Start > JTL-Ameise**)
   - Wählen Sie unter **Zuletzt bearbeitete Exportvorlagen** die Vorlage "Rechnungsnummern"
   - Klicken Sie auf **Export starten**, bestätigen Sie im Dialog und wählen Sie den Speicherort
2. Laden Sie die erzeugte CSV-Datei hoch: **Axytos Payment > Rechnungen > CSV-Datei auswählen**

### 3. Automatisch via Windows Task Scheduler

**Empfohlene Häufigkeit:** Einmalige Einrichtung (danach täglich automatisch)

**Voraussetzungen:**
- Windows 10 oder höher
- JTL-WaWi mit Kommandozeilen-Version der JTL-Ameise

**Einrichtung:**

1. Erstellen Sie eine JTL-Ameise Exportvorlage (siehe unten) und notieren Sie deren ID (z.B. `EXP1`)
2. Öffnen Sie **Axytos Payment > API Setup > JTL-WaWi Automatisierung**
3. Generieren und speichern Sie einen **Webhook-API-Schlüssel**
4. Klicken Sie auf **Automatisierungs-Paket herunterladen** (ZIP-Datei) auf dem Computer, auf dem JTL-WaWi installiert ist
5. Entpacken Sie die ZIP-Datei in einen geeigneten Ordner (z.B. `C:\Tools\AxytosPaymentAutomation`)
6. Bearbeiten Sie die Datei `config.ini` und passen Sie die Werte im Abschnitt `[WaWi]` an Ihre JTL-WaWi-Installation an:
   - **Server**: SQL-Server-Instanz (Standard: `(local)\JTLWAWI`)
   - **Database**: Name der WaWi-Datenbank (Standard: `eazybusiness`)
   - **User**: SQL-Server-Benutzername
   - **Password**: SQL-Server-Passwort
   - **ExportTemplate**: ID der JTL-Ameise Exportvorlage (z.B. `EXP1`)
   - **InstallPath**: Installationspfad von JTL-WaWi (Standard: `C:\Program Files (x86)\JTL-Software`)
7. Optional können Sie im Abschnitt `[Axytos]` die **ScheduleTime** anpassen (Uhrzeit im Format HH:MM, Standard: 02:00)
8. Führen Sie das Skript `install.bat` im entpackten Ordner aus

Das Installationsskript richtet im Windows Task Scheduler einen täglichen Task ein, der automatisch Rechnungsnummern an das Axytos-Plugin übermittelt.

**Deinstallation:**
Um den automatischen Task wieder zu entfernen, führen Sie das Skript `uninstall.bat` im Installationsordner aus. Dies entfernt den Task aus dem Windows Task Scheduler.

### JTL-Ameise Exportvorlage anlegen

So erstellen Sie eine Exportvorlage für Rechnungsnummern:

1. Öffnen Sie JTL-WaWi und starten Sie die JTL-Ameise (**Start > JTL-Ameise**)
2. Klicken Sie auf **Export**
3. Wählen Sie links **Buchungsdaten > Rechnungen**
4. Wählen Sie bei **Datenbankfeld** folgende Felder aus:
   - Rechnungsnummer
   - Externe Bestellnummer
5. Lassen Sie **Exportdatei** unverändert
6. Setzen Sie bei **Exportfilter wählen**:
   - Haken bei **offene Rechnungen**
   - Zeitraum: **aktueller Monat**
7. Klicken Sie auf **Vorlage speichern**
8. Geben Sie einen **Vorlagennamen** ein (z.B. "Rechnungsnummern")
9. Klicken Sie auf **Neue Vorlage speichern**
10. Notieren Sie sich die angezeigte **Vorlagen-ID** (z.B. `EXP1`) für die Automatisierung

## Überwachung

### Wie funktioniert die Kommunikation mit Axytos?

Wenn ein Kunde mit Axytos bezahlt, kommuniziert das Plugin automatisch mit dem Axytos-System. Diese Kommunikation erfolgt in mehreren Schritten:

1. **Vorprüfung**: Prüfung der Bonität während der Bestellung
2. **Bestätigung**: Benachrichtigung an Axytos nach Bestellabschluss
3. **Rechnung**: Übermittlung der Rechnungsnummer nach Erstellung
4. **Versand**: Benachrichtigung bei Versand der Ware

Jeder dieser Schritte wird als "Aktion" im System gespeichert. Falls eine Kommunikation fehlschlägt (z.B. bei Netzwerkproblemen), versucht das System automatisch, die Aktion erneut durchzuführen.

**Cron-Job:** Ein automatischer Hintergrundprozess verarbeitet diese Aktionen regelmäßig und wiederholt fehlgeschlagene Kommunikationen. Er läuft ohne Ihr Zutun - der Status ist im Admin-Bereich einsehbar.

### Status-Übersicht

Im Admin-Bereich unter **Axytos Payment > Status** sehen Sie den Gesundheitszustand des Systems:

**Systemstatus-Karten:**
- **Cron-Job Status**: Zeigt, ob die automatische Verarbeitung aktiv ist
- **Ausstehende Aktionen**: Anzahl der Aktionen, die noch verarbeitet werden
- **Kaputte Aktionen**: Aktionen, die mehrfach fehlgeschlagen sind und Ihre Aufmerksamkeit benötigen
- **Bestellungen gesamt**: Gesamtzahl der Axytos-Bestellungen

**Aktions-Status verstehen:**

- **Ausstehend**: Normale Aktionen, die auf Verarbeitung warten
- **Erneut versuchen**: Aktionen, die einmal fehlgeschlagen sind und automatisch wiederholt werden
- **Kaputt**: Aktionen, die mehrfach fehlgeschlagen sind - **diese benötigen Ihre Aufmerksamkeit**

### Fehler beheben

**Bei "kaputten" Aktionen:**

1. Klicken Sie auf **Bestellungen mit Aktionen anzeigen**
2. Suchen Sie Bestellungen mit "kaputten" Aktionen (rote Kennzeichnung)
3. Überprüfen Sie die Fehlerursache in der Detailansicht
4. Optionen:
   - **Erneut verarbeiten**: Wenn das Problem behoben ist (z.B. nach Netzwerkausfall)
   - **Aktion entfernen**: ⚠️ **ACHTUNG** - Wenn Sie eine Aktion entfernen, müssen Sie die Information **manuell an Axytos übermitteln** (z.B. über das Axytos Customer Portal) oder die Bestellung in Ihrem System entsprechend korrigieren. Das Plugin wird diese Aktion danach nicht mehr automatisch verarbeiten!

**Button "Alle verarbeiten":**
- Startet die manuelle Verarbeitung aller ausstehenden Aktionen
- Nützlich, wenn Sie nicht auf die automatische Verarbeitung warten möchten

### Dashboard-Widget

Auf dem Shop-Dashboard finden Sie das **Axytos Overview Widget** mit den wichtigsten Informationen auf einen Blick:

- **Systemstatus**: Schnelle Übersicht über Cron-Job, ausstehende und kaputte Aktionen
- **Bestellungen ohne Rechnung**: Zeigt Bestellungen, für die noch keine Rechnungsnummer übermittelt wurde
- **Direkt-Links**: Schnellzugriff auf die Plugin-Tabs für detaillierte Aktionen

Das Widget ermöglicht es Ihnen, den Zustand des Axytos-Systems im Blick zu behalten, ohne die Plugin-Tabs öffnen zu müssen. Nur bei Problemen oder für detaillierte Aktionen wechseln Sie in die jeweiligen Plugin-Bereiche.

