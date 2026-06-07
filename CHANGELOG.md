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

> **Hinweis:** Das Projekt befindet sich in der **Beta-Phase**. Ein zuvor vorbereitetes
> Release 1.0 wurde **zurückgezogen**; es gibt noch kein finales 1.0-Release und keine
> Einreichung im Symcon Module Store. Versionen unterhalb von `1.0` sind Beta-Stände.

---

## [0.9.0] – 2026-06-07 (Beta)

### Entfernt

- **AlarmKeypad:** Eigenschaft/Checkbox „PIN beim Scharfschalten erforderlich" entfernt.
  Ob eine Aktion eine PIN benötigt, wird ausschließlich zentral in der Alarmzentrale
  (SymconAlarmPro → *PIN & Sicherheit*) festgelegt; das Keypad reicht die PIN nur durch.
- **SymconAlarmPro:** Reste des externen PIN-Pad-Backends vollständig entfernt
  (Eigenschaften `PinInputVariableID`/`ClearPinInput`, `MessageSink`-Verdrahtung,
  `HandlePinInput` in `PinTrait`, Sensor-Watch der Eingabevariable, zugehörige
  Übersetzungen). Die PIN-Eingabe erfolgt nun ausschließlich über das **AlarmKeypad**.

### Vorbereitung (zunächst zurückgestellt)

- `LICENSE` (MIT) ergänzt.
- `url` für beide Module in `module.json` gesetzt.
- Logo `imgs/logo.png` ergänzt und in der README eingebunden.
- `docs/MODULE_STORE.md` mit Einreichungs-Infos angelegt (Einreichung erst nach Beta-Abschluss).
- `CHANGELOG.md` und Versionierungsregeln eingeführt (siehe oben).

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
