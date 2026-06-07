# Symcon Alarm Pro

[![Check Style](https://github.com/ghostraider88/symcon-alarmanlage/actions/workflows/style.yml/badge.svg)](https://github.com/ghostraider88/symcon-alarmanlage/actions/workflows/style.yml)
[![Run Tests](https://github.com/ghostraider88/symcon-alarmanlage/actions/workflows/tests.yml/badge.svg)](https://github.com/ghostraider88/symcon-alarmanlage/actions/workflows/tests.yml)

Flexible, zustandsbasierte Alarmzentrale für IP-Symcon – frei konfigurierbare Sensoren,
Zonen, Modi, Verzögerungen, PIN, Bypass, Aktionen, Sprachansagen, Kamera- und
Benachrichtigungshooks sowie Kachelvisu-Frontend. Ohne hart codierte Geräteabhängigkeiten.

## Enthaltene Module

| Modul | Beschreibung | Doku |
|-------|--------------|------|
| SymconAlarmPro | Zentrale Alarmanlage (State Machine) | [README](SymconAlarmPro/README.md) |
| AlarmKeypad | PIN-Tastenfeld für die Kachelvisu mit Befehlstasten (Nacht / Scharf / Unscharf / Quittieren / Reset) | [README](AlarmKeypad/README.md) |

## Installation

Über das Module Control (Kerninstanz) die Repository-URL hinzufügen:

```
https://github.com/ghostraider88/symcon-alarmanlage
```

## Voraussetzungen

* IP-Symcon ab Version 8.1

## Entwicklung

Alle verbindlichen Struktur- und Codiervorgaben stehen in [`CLAUDE.md`](CLAUDE.md).

## Danksagung

Das Modul **AlarmKeypad** basiert auf dem Pinpad-Modul von **lorbetzki**
([net.lorbetzki.pinpad](https://github.com/lorbetzki/net.lorbetzki.pinpad)).
Dessen Kachel-Layout (Numpad, Theming über `queryParameters`, `handleMessage`/`requestAction`)
diente als Vorlage und wurde mit ausdrücklicher Genehmigung des Autors verwendet und erweitert.
Vielen Dank an lorbetzki für die Freigabe.

## Lizenz

MIT
