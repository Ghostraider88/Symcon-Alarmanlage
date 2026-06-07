# Module Store – Einreichungs-Infos (Release 1.0)

Dieses Dokument bündelt alle Angaben, die für die Einreichung im **IP-Symcon Module Store**
benötigt werden. Inhalte können direkt in das Einreichungsformular auf der Symcon-Website
kopiert werden: **Entwicklerbereich → Module Store → Bibliothek einreichen**.

---

## Eckdaten

| Feld | Wert |
|------|------|
| Bibliotheksname | **Symcon Alarm Pro** |
| Autor | Torsten Wolf |
| Repository-URL | https://github.com/ghostraider88/symcon-alarmanlage |
| Standard-Branch | `main` |
| Version / Build | `1.0` / `1` |
| Lizenz | MIT |
| Mindest-Kernel | IP-Symcon **8.1** |
| Bibliotheks-GUID | `{583DA8D0-D8D7-45B1-805E-DD873461DCD5}` |
| Logo | `imgs/logo.png` (1254×1254 PNG) |

### Enthaltene Module

| Modul | GUID | Typ | Prefix |
|-------|------|-----|--------|
| SymconAlarmPro | `{91723F1C-05F2-4CDD-A2AC-A309AB3E335C}` | 3 (Gerät) | `ALARM` |
| AlarmKeypad | `{D5FB72C3-7FDA-69C4-4DEB-E16515F84F31}` | 3 (Gerät) | `ALARMKP` |

---

## Kategorie & Tags

- **Empfohlene Kategorie:** Sicherheit / Alarmanlage
- **Tags / Suchbegriffe:** Alarmanlage, Alarmzentrale, Sicherheit, PIN, Keypad, Sensoren,
  Bewegungsmelder, Türkontakt, Fensterkontakt, Sirene, Benachrichtigung, Pushover, Telegram,
  Kachelvisu

---

## Kurzbeschreibung (eine Zeile)

> Flexible, zustandsbasierte Alarmzentrale für IP-Symcon mit Sensoren, Zonen, Modi, PIN-Schutz,
> Benachrichtigungen und Kachelvisu – komplett ohne hart codierte Geräteabhängigkeiten.

---

## Beschreibung (lang)

**Symcon Alarm Pro** verwandelt IP-Symcon in eine vollwertige Alarmzentrale. Alle Sensoren,
Zonen, Ausgänge, Benachrichtigungen und Kamera-Aktionen werden vollständig über die
Instanzkonfiguration eingerichtet – es sind keine Geräte-IDs fest einprogrammiert.

**Funktionen:**

- Betriebsmodi **Unscharf / Nacht / Scharf / Test** mit interner State Machine
- Frei konfigurierbare **Sensoren** mit 8 Auslöser-Typen, Modusfiltern, Entprellung,
  Reaktionstypen (Sofort-, Vor-, verzögerter Alarm) und Kritikalität
- **Zonen** zur Organisation und Filterung von Aktionen/Benachrichtigungen
- **Aus- und Eintrittsverzögerungen**, Voralarm und Alarm-Eskalation
- **PIN-Schutz** mit bcrypt-Hash, Fehlversuchs-Sperrzeit und feingranularer Pflicht
  (Scharf / Unscharf / Quittieren / Reset / Bypass)
- **Bypass** einzelner Sensoren (zeitlich begrenzbar) und **Vorab-Scharfprüfung**
- **Aktionen** für Sirene/Licht (Variable oder Skript, mit Verzögerung, Dauer, Blink-Modus)
- **Benachrichtigungen** über Pushover, SMTP/E-Mail, Telegram, Variable oder Skript
- **Sprachansagen** (Alexa/TTS) und **Kamera-Hooks** (Snapshot/Aufzeichnung per Skript)
- **Ereignisverlauf** und moderne **Kachelvisualisierung** (HTML-SDK) mit Live-Sensorstatus
- Mitgeliefertes **AlarmKeypad**: PIN-Tastenfeld für die Kachelvisu mit eigenen Befehlstasten
  (Nacht / Scharf / Unscharf / Quittieren / Reset), das PIN und Befehl gemeinsam sendet

**Voraussetzungen:** IP-Symcon ab 8.1.

---

## Checkliste vor dem Einreichen

- [ ] GitHub-Actions **Check Style** und **Run Tests** sind auf `main` grün
- [ ] `library.json`: `version`, `build`, `date` aktuell (aktuell `1.0` / `1`)
- [ ] `LICENSE` vorhanden (MIT) ✔
- [ ] Logo `imgs/logo.png` vorhanden ✔
- [ ] READMEs für Bibliothek und beide Module aktuell ✔
- [ ] `CHANGELOG.md` enthält den aktuellen Release ✔
- [ ] Optional: Screenshots der Kachelvisu für die Store-Galerie bereitlegen

---

## Update-Prozess für künftige Releases

1. `library.json`: `version` (z. B. `1.0` → `1.0.1`), `build` (+1) und `date` (`date +%s`) anpassen
2. `CHANGELOG.md`: neuen Versionsabschnitt ganz oben ergänzen
3. Betroffene READMEs aktualisieren
4. Commit (`release 1.0.1: …`) und nach `main` pushen

Der Module Store erkennt das Update automatisch anhand der geänderten `version`/`build`.
