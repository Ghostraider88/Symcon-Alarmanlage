# SymconAlarmPro

Zentrale, state-machine-basierte Alarmanlage für IP-Symcon. Sensoren, Zonen, Aktionen,
Benachrichtigungen, Sprachansagen und Kameras werden vollständig über die
Instanzkonfiguration definiert – es sind **keine** Geräte-, Variablen- oder Skript-IDs im
Code hinterlegt.

## Funktionsumfang

- Betriebsmodi: **Unscharf, Nacht, Abwesend, Test**
- Interne Zustände: DISARMED, ARMING_EXIT_DELAY, ARMED_NIGHT, ARMED_AWAY, ENTRY_DELAY,
  PRE_ALARM, ALARM, ALARM_ACKNOWLEDGED, TROUBLE, TEST
- Frei konfigurierbare Sensoren mit flexibler Wertauswertung (8 Trigger-Typen, Bool/Integer)
- Zonen, Ausgangs-/Eintrittsverzögerungen, Voralarm, Eskalation, Sirenendauer
- PIN-Bedienung (gehasht), Fehlversuchszähler und Sperrzeit
- Bypass je Sensor (optional zeitbegrenzt)
- Pre-Arm-Check (OK / WARNUNG / BLOCKIERT)
- Getrennte Vorgänge: Unscharf, Quittieren, Reset
- Generische Aktionen (Sirene/Licht), Benachrichtigungen, Alexa-Ansagen, Kamera-Hooks
- Störungsverwaltung (TROUBLE) getrennt vom Einbruchalarm
- HTML-SDK Kachel-Frontend mit Live-Aktualisierung und Bedienbuttons
- Historie (begrenzt, persistent)

## Voraussetzungen

- IP-Symcon ab Version 8.1

## Konfiguration (Backend)

Die Konfigurationsseite ist in ExpansionPanels gegliedert: Allgemein, Modi & Verzögerungen,
PIN & Sicherheit, Zonen, Sensoren, Aktionen, Benachrichtigungen, Sprachansagen, Kameras,
Frontend & Historie. Jede Sensor-/Aktions-/Benachrichtigungszeile ist eine eigene
Listenzeile.

### Sensor-Wertauswertung (TriggerType)

| TriggerType | Bedeutung |
|-------------|-----------|
| Bool true   | `true` = Alarm |
| Bool false  | `false` = Alarm |
| Int =       | Wert == TriggerValue |
| Int !=      | Wert != TriggerValue |
| Int >       | Wert > TriggerValue |
| Int >=      | Wert >= TriggerValue |
| Int <       | Wert < TriggerValue |
| Int <=      | Wert <= TriggerValue |

### PIN

Der PIN wird **nicht** als Property gespeichert, sondern gehasht in einem Attribut. Setzen
über den Button „PIN speichern" im Aktionsbereich oder per `Alarm_SetPin`.

## Statusvariablen

`Mode`, `State`, `IsArmed`, `IsAlarmActive`, `IsAcknowledged`, `LastEvent`,
`LastTriggerSensorID/Name/Zone`, `ExitDelayRemaining`, `EntryDelayRemaining`,
`TroubleActive`, `TroubleSummary`, `BypassActive`, `OpenSensorsSummary`, `FrontendHTML`,
`HistoryHTML`.

## Exportierte PHP-Befehle

```php
Alarm_SetMode(int $InstanceID, int $mode): bool
Alarm_ArmNight(int $InstanceID, string $pin = ''): bool
Alarm_ArmAway(int $InstanceID, string $pin = ''): bool
Alarm_Disarm(int $InstanceID, string $pin = ''): bool
Alarm_Acknowledge(int $InstanceID, string $pin = ''): bool
Alarm_Reset(int $InstanceID, string $pin = ''): bool
Alarm_Panic(int $InstanceID): void
Alarm_BypassSensor(int $InstanceID, int $sensorID, int $durationMinutes = 0, string $pin = ''): bool
Alarm_UnbypassSensor(int $InstanceID, int $sensorID, string $pin = ''): bool
Alarm_TestNotification(int $InstanceID): void
Alarm_RunPreArmCheck(int $InstanceID, int $targetMode): string
Alarm_HandleSensorEvent(int $InstanceID, int $variableID, mixed $value): void
Alarm_RebuildFrontend(int $InstanceID): void
Alarm_ClearHistory(int $InstanceID): void
Alarm_SetPin(int $InstanceID, string $pin): void
```

`$mode`: 0 = Unscharf, 1 = Nacht, 2 = Abwesend, 3 = Test.

## Externe Anbindung (Beispiele)

```php
// PIN-Pad-Skript
Alarm_Disarm(12345, '4711');

// Anwesenheits-Automation
if ($allesAbwesend) { Alarm_ArmAway(12345); }

// Externer Sensor ohne eigene Variable
Alarm_HandleSensorEvent(12345, 0, true);
```

## Verhalten nach Neustart

Konfigurierbar: immer unscharf / letzten Modus wiederherstellen / wiederherstellen mit
Pre-Arm-Check. Restzeiten laufender Verzögerungen werden aus einem absoluten Zeitstempel
rekonstruiert.

## Nicht-Ziele (Version 1)

VdS-Zertifizierung, Duress-PIN, Mehrbenutzer-PINs, native Blue-Iris-/Telegram-/Echo-Remote-
Integration, KI-Kameraauswertung. Diese sind generisch vorbereitet (über Skripte/Variablen),
aber nicht nativ implementiert.
