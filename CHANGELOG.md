# Changelog

Alle nennenswerten Änderungen an dieser Bibliothek werden hier dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).
Die Versionierung folgt dem Schema `MAJOR.MINOR.PATCH` (`version` in `library.json`),
zusätzlich wird bei jedem Release die ganzzahlige `build`-Nummer erhöht.

## Versionierungsregeln

- Jede veröffentlichte Änderung erhöht die **Patch-Version** (z. B. `1.0` → `1.0.1` → `1.0.2`).
- Funktionserweiterungen erhöhen die **Minor-Version** (`1.1`, `1.2`, …),
  grundlegende/inkompatible Änderungen die **Major-Version** (`2.0`).
- Bei **jedem** Release werden in `library.json` `version`, `build` (+1) und `date`
  (Unix-Zeitstempel) aktualisiert und hier ein Eintrag ergänzt.

---

## [1.0] – 2026-06-07

Erstes Release.

### Module

- **SymconAlarmPro** – zustandsbasierte Alarmzentrale (IP-Symcon 8.1+):
  Modi (Unscharf / Nacht / Scharf / Test), interne State Machine, frei
  konfigurierbare Sensoren, Zonen, Aus-/Eintrittsverzögerungen, Vor- und
  Voll­alarm, Eskalation, PIN-Schutz mit Sperrzeit, Bypass, Vorab-Scharf­prüfung,
  Aktionen (Sirene/Licht), Benachrichtigungen (Pushover, SMTP, Telegram,
  Variable, Skript), Sprachansagen (Alexa/TTS), Kamera-Hooks, Ereignisverlauf
  und Kachelvisualisierung (HTML-SDK) mit Live-Sensorstatus.
- **AlarmKeypad** – PIN-Tastenfeld für die Kachelvisu mit eigenen Befehlstasten
  (Nacht / Scharf / Unscharf / Quittieren / Reset), das PIN und Befehl gemeinsam
  an die Alarmzentrale sendet. Basiert auf dem Pinpad von
  [lorbetzki](https://github.com/lorbetzki/net.lorbetzki.pinpad) (mit Genehmigung).

### Kachelvisualisierung

- Live-Sensorstatus in zwei Spalten (Tür-/Fensterkontakte „auf/zu",
  Bewegungsmelder „Bewegung/frei").
- Bedien-Buttons werden abhängig vom PIN-Schutz ein-/ausgeblendet:
  Bei aktivem PIN für Scharf- bzw. Unscharf/Quittieren/Reset erscheinen die
  entsprechenden Buttons nicht mehr in der Kachel – die Bedienung erfolgt dann
  über das AlarmKeypad.
- „Letzter Auslöser" wird beim Quittieren und Zurücksetzen gelöscht.
