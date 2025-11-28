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

Damit Axytos die Bezahlungsverarbeitung durchführen kann, müssen die Rechnungsnummern an Axytos übermittelt werden. Dies geschieht **vollautomatisch** durch die Integration mit JTL-WaWi.

**Einrichtung der Automatisierung:**
Damit die automatische Übermittlung funktioniert, müssen in der JTL-WaWi Workflows angelegt werden. Die Dokumentation dazu erfolgt separat.

**Rechnungsnummer einsehen:**
Im Admin-Bereich unter **Axytos Payment > Status** wird die Rechnungsnummer in der Bestellübersicht angezeigt. Sie können dort prüfen, ob die Synchronisation erfolgreich war.

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

Darunter finden Sie eine Übersicht aller Bestellungen mit ihren zugehörigen Rechnungsnummern. Die Tabelle zeigt:

- **Bestellnummer**: Ihre Shop-Bestellnummer
- **Kunde**: Name des Kunden
- **Datum**: Bestelldatum
- **Gesamt**: Bestellsumme
- **Status**: Aktueller Bestellstatus
- **Rechnungsnummer**: Automatisch synchronisiert aus JTL-WaWi
- **Ausstehende/Kaputte Aktionen**: Status der Axytos-Kommunikation

In der Detailansicht einer Bestellung (per Klick auf die Bestellung oder Suche) sehen Sie ebenfalls die Rechnungsnummer zusammen mit allen anderen Bestelldetails.

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
- **Direkt-Links**: Schnellzugriff auf die Plugin-Tabs für detaillierte Aktionen

Das Widget ermöglicht es Ihnen, den Zustand des Axytos-Systems im Blick zu behalten, ohne die Plugin-Tabs öffnen zu müssen. Nur bei Problemen oder für detaillierte Aktionen wechseln Sie in die jeweiligen Plugin-Bereiche.
