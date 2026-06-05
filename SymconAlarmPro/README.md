# SymconAlarmPro

Flexible, state-machine-basierte Alarmzentrale für IP-Symcon 8.1+. Keine Geräte-IDs sind fest einprogrammiert – alle Sensoren, Zonen, Ausgänge, Benachrichtigungen und Kamera-Aktionen werden vollständig über die Instanzkonfiguration eingerichtet.

---

## Schnellstart

1. Bibliothek über **Modulverwaltung** → **Hinzufügen** → Repository-URL einfügen
2. Neue Instanz **Symcon Alarm Pro** unter *Sonstige* anlegen
3. Konfiguration öffnen, zunächst **Zonen** anlegen, dann **Sensoren** konfigurieren
4. Erste Tests im **Test**-Modus durchführen, bevor Nacht- oder Abwesend-Modus genutzt wird
5. Bei Bedarf eine PIN im Abschnitt *PIN setzen* am unteren Ende des Formulars setzen

---

## Grundkonzepte

### Modi vs. Zustände

**Modus** ist die Absicht des Nutzers – was die Anlage tun soll:

| Modus | Beschreibung |
|-------|-------------|
| Unscharf | Anlage inaktiv, keine Sensoren überwacht |
| Nacht | Man ist zuhause. Typischerweise nur Perimetersensoren (Türen, Fenster) aktiv. Bewegungsmelder ausgeschaltet. |
| Abwesend | Das Gebäude wurde verlassen. Alle konfigurierten Sensoren sind aktiv. |
| Test | Inbetriebnahme-Modus. Sensoren lösen aus, aber die Anlage eskaliert nie zu einem echten Alarm. |

**Zustand** ist das, was die Anlage intern gerade macht:

| Zustand | Beschreibung |
|---------|-------------|
| Unscharf | Nichts aktiv |
| Scharfschalten (Ausgangsverzögerung) | Countdown vor dem Scharfschalten – jetzt das Gebäude verlassen |
| Scharf (Nacht / Abwesend) | Anlage ist aktiv |
| Eintrittsverzögerung | Sensor mit Reaktion „Eintrittsverzögerung" hat ausgelöst; Anlage innerhalb der konfigurierten Sekunden unscharfschalten |
| Voralarm | Sensor mit Reaktion „Voralarm" hat ausgelöst; stille Warnphase vor dem echten Alarm |
| Alarm | Alarm ist aktiv |
| Alarm quittiert | Alarm wurde bestätigt, aber noch nicht vollständig zurückgesetzt |
| Test | Anlage ist im Test-Modus scharf |

---

## Zonen

Zonen sind logische Bereiche (z. B. *Erdgeschoss*, *Garten*, *Garage*). Sie haben zwei Funktionen:

1. **Organisation** – Jeder Sensor wird einer Zone zugeordnet, um die Sensorliste übersichtlich zu halten
2. **Filterung** – Aktionen, Benachrichtigungen und Kameras können so konfiguriert werden, dass sie nur auslösen, wenn ein Sensor aus einer bestimmten Zone den Alarm ausgelöst hat

Eine Zone hat keine eigene Logik – sie ist nur ein Name, der an anderen Stellen referenziert wird.

**Einrichtung:** Zonen im Bereich **Zonen** anlegen. Der Name ist ein freies Textfeld. Exakt denselben Namen beim Zuordnen von Sensoren und beim Einrichten von Zonenfiltern verwenden.

---

## Sensoren

### Sensor-Typ

Der Sensor-Typ (Türkontakt, Fensterkontakt, Bewegungsmelder, …) dient **ausschließlich der Dokumentation**. Er hat keinen Einfluss auf die Alarmlogik. Das tatsächliche Verhalten wird allein durch die Felder **Auslöser** und **Reaktion** bestimmt.

| Typ | Vorgesehener Einsatz |
|-----|---------------------|
| Türkontakt | Reed-Kontakt oder Magnetkontakt an einer Tür |
| Fensterkontakt | Reed-Kontakt an einem Fenster |
| Bewegungsmelder | PIR- oder Radar-Präsenzsensor |
| Kamera-Alarm | Bool-Alarmausgang einer IP-Kamera |
| Sabotage | Sabotagekontakt am Sensorgehäuse |
| Systemstatus | Systemzustand oder Statussignal |
| Externe Quelle | Beliebiges externes Alarmsignal (z. B. Wasser, Rauch) |
| Sonstiges | Alles weitere |

### Auslöser-Typen

Der Auslöser legt fest, welcher Variablenwert als Alarmsignal gilt. IP-Symcon sendet ein `VM_UPDATE`-Ereignis, sobald eine überwachte Variable ihren Wert ändert; das Modul prüft den neuen Wert gegen den konfigurierten Auslöser.

| Auslöser | Bedeutung |
|----------|-----------|
| Bool wahr = Alarm | Die Boolean-Variable wechselt auf `true` |
| Bool falsch = Alarm | Die Boolean-Variable wechselt auf `false` (z. B. Fensterkontakt: offen = false) |
| Int = Wert | Integer- oder Float-Wert ist gleich dem Schwellwert |
| Int ≠ Wert | Integer- oder Float-Wert ist ungleich dem Schwellwert |
| Int > Wert | Integer- oder Float-Wert ist größer als der Schwellwert |
| Int ≥ Wert | Integer- oder Float-Wert ist größer oder gleich dem Schwellwert |
| Int < Wert | Integer- oder Float-Wert ist kleiner als der Schwellwert |
| Int ≤ Wert | Integer- oder Float-Wert ist kleiner oder gleich dem Schwellwert |

Das Feld **Wert** (Vergleichsschwellwert) wird nur bei Int-Auslösertypen angezeigt.

### Nacht / Abwesend / Test – aktive Modi

Jeder Sensor hat drei Kontrollkästchen: **Nacht**, **Abwesend**, **Test**. Ein Sensor wird nur ausgewertet, wenn das System in einem der aktivierten Modi scharf geschaltet ist.

**Typische Konfiguration:**
- Türkontakt Haustür: ✓ Nacht, ✓ Abwesend → aktiv in beiden Modi
- Bewegungsmelder: ✗ Nacht, ✓ Abwesend → nur aktiv, wenn man das Haus verlassen hat
- Testsensor: ✗ Nacht, ✗ Abwesend, ✓ Test → ausschließlich für die Inbetriebnahme

### Reaktions-Typen

| Reaktion | Verhalten |
|----------|-----------|
| **Sofortalarm** | Der Alarm wird sofort ausgelöst. Für Fenster, Sabotagekontakte und kritische Sensoren. |
| **Voralarm** | Das System wechselt zunächst in eine stille Voralarm-Phase (Dauer unter *Modi & Verzögerungen*). Wer das System nicht innerhalb dieser Zeit unscharfschaltet, löst den vollständigen Alarm aus. Sinnvoll als Gnadenfrist vor der Eskalation. |
| **Eintrittsverzögerung** | Ein sensorindividueller Countdown startet (Feld *Eintrittsverzögerung (s)*). Wird das System nicht in dieser Zeit unscharfgeschaltet, folgt der Alarm. Typisch für die Haustür – man braucht nach Hause kommen einen Moment zum Abschalten. |

### Entprellung

Mindestabstand in Millisekunden zwischen zwei Alarm-Auslösungen desselben Sensors. Verhindert Mehrfachauslösungen durch prellende Kontakte (ein Türkontakt kann beim Öffnen 20–50 ms flattern). Auf 200 ms setzen filtert das zuverlässig heraus. 0 = deaktiviert.

### Bypass

Wenn **Bypass erlaubt** aktiviert ist, kann dieser einzelne Sensor vorübergehend deaktiviert werden, ohne die gesamte Anlage unscharfzuschalten. Anwendungsfall: ein Fenster bleibt offen, während der Rest der Anlage scharf ist.

Bypass per API: `Alarm_BypassSensor($id, $variablenID, $minuten)` oder über einen Button/ein Skript. Der Bypass kann zeitlich begrenzt werden.

### Scharf blockieren wenn aktiv

Ist dieser Sensor im Alarmzustand, wenn ein Scharfschalten versucht wird, wird das Scharfschalten vollständig blockiert statt nur eine Warnung auszugeben. Ohne dieses Flag werden offene Sensoren nur als Warnung angezeigt, das Scharfschalten läuft aber trotzdem durch.

### Kritikalität

Beeinflusst das Ergebnis der Vorab-Scharfschaltprüfung:

| Kritikalität | Auswirkung bei offenem Sensor beim Scharfschalten |
|-------------|--------------------------------------------------|
| Niedrig | Nur Hinweis, Scharfschalten läuft weiter |
| Normal | Warnung wird angezeigt, Scharfschalten läuft weiter |
| Hoch | Scharfschalten wird blockiert (erfordert zusätzlich *Scharf blockieren wenn aktiv*) |

### Ausgangsverzögerung ignorieren

Wenn aktiviert, kann dieser Sensor einen Alarm auch während der Ausgangsverzögerung auslösen. Für Sabotagekontakte oder Panik-Taster, die unabhängig vom Countdown immer aktiv sein müssen.

---

## Aktionen (Sirene / Licht)

Aktionen steuern physische Ausgänge, wenn ein Alarm-Ereignis eintritt: Sirenen-Relais, Warnlichter, Shelly-Schalter, Dimmer oder jede andere IP-Symcon-Variable.

### Zieltypen

| Typ | Beschreibung |
|-----|-------------|
| Variable | Das Modul schreibt *Wert ein* / *Wert aus* in die Variable. Wenn die Variable einen Aktions-Handler hat (z. B. Homematic-Aktor), wird `RequestAction` verwendet, sonst `SetValue`. |
| Skript | Ein IPS-Skript wird mit den Parametern `VALUE` und `SENDER='AlarmCenter'` aufgerufen. |

### Wert ein / Wert aus

Die Werte beim Ein- und Ausschalten:
- Boolean-Variable: `1` / `0` oder `true` / `false`
- Dimmer (0–100): `100` / `0`
- Farbvariable: Zahlendarstellung der Farbe (z. B. `16711680` für Rot / #FF0000)
- Für Skripte: wird als `VALUE`-Parameter übergeben

### Verzögerung und Dauer

- **Verzögerung**: Wartezeit in Sekunden nach dem Ereignis vor dem Einschalten. Für gestaffelte Reaktionen (z. B. 5 s Warnlicht, dann Sirene).
- **Dauer**: Automatisches Ausschalten nach N Sekunden. 0 = unbegrenzt (bis zum Abbruch).

### Blink-Modus

Blinkt den Ausgang N mal mit konfigurierbarem Intervall. Beispiel: Anzahl = 3, Intervall = 1 s → der Ausgang geht ein–aus–ein–aus–ein–aus über 6 Sekunden. Überschreibt **Dauer** wenn aktiviert. Geeignet für Warnlichter.

### Modusfilter

Kommagetrennte Modusnamen. Leer = alle Modi. Beispiel: `night,away` löst im Nacht- und Abwesend-Modus aus, nicht im Test-Modus.

### Zonenfilter

Nur auslösen, wenn der auslösende Sensor zur ausgewählten Zone gehört. Leer = alle Zonen.

### Abbruch bei Unscharf / Abbruch bei Quittierung

Das Modul verfolgt laufende Aktionen und schaltet sie automatisch aus (schreibt *Wert aus*), wenn:
- **Abbruch bei Unscharf**: die Anlage unscharfgeschaltet wird
- **Abbruch bei Quittierung**: der Alarm quittiert wird

Empfehlung: *Abbruch bei Unscharf* für Sirenen; *Abbruch bei Quittierung* für Lichter, die nach der Bestätigung stoppen sollen.

---

## Benachrichtigungen

Benachrichtigungen informieren bei Alarm-Ereignissen. Das Modul unterstützt fünf Liefermethoden.

### Liefermethoden

#### Pushover

Die in IP-Symcon konfigurierte **Pushover**-Instanz auswählen. Das Modul ruft `PushOver_SendNotification()` auf. Das Feld *Betreff/Titel* wird der Titel der Push-Benachrichtigung.

**Einrichtung:** Pushover-Modul aus dem Symcon-Modulkatalog installieren, API-Zugangsdaten eingeben, dann die Instanz hier auswählen.

#### SMTP / E-Mail

Die konfigurierte **SMTP**- oder Mailer-Instanz auswählen. Das Modul ruft `SMTP_SendMail()` auf. *Betreff/Titel* wird zum E-Mail-Betreff. Wenn das Modul eine Empfängeradresse benötigt, diese im Feld **Parameter** eintragen.

#### Telegram

Die konfigurierte **Telegram-Bot**-Instanz auswählen. Die **Chat-ID** im Feld *Parameter* eintragen (Pflicht). Das Modul versucht der Reihe nach `TelegramBot_SendMessage()` und `Telegram_SendMessage()`.

**Einrichtung:** Telegram-Bot-Modul installieren, Bot-Token konfigurieren, Chat-ID des Zielgesprächs ermitteln und hier eintragen.

#### Variable

Der gerenderte Nachrichtentext wird in eine String-Variable geschrieben. Das Modul verwendet `RequestAction`, wenn die Variable einen Aktions-Handler hat (z. B. Echo Remote TTS-Variable), sonst wird der Wert erst geleert und dann neu gesetzt, damit das Änderungs-Ereignis immer ausgelöst wird.

#### Skript

Ein IPS-Skript wird mit folgenden Parametern aufgerufen:
- `TEXT` — gerenderter Nachrichtentext
- `SUBJECT` — Betreff / Titel
- `EVENT` — Ereignisname (z. B. `alarm`, `disarmed`)
- `SENDER` — immer `AlarmCenter`

### Nachrichtenvorlage

Die Vorlage unterstützt folgende Platzhalter:

| Platzhalter | Beschreibung |
|-------------|-------------|
| `{sensorName}` | Name des auslösenden Sensors |
| `{zone}` | Zone des auslösenden Sensors |
| `{mode}` | Aktueller Modus (disarmed/night/away/test) |
| `{state}` | Aktueller Zustand |
| `{timestamp}` | Aktuelles Datum/Uhrzeit |
| `{remainingSeconds}` | Verbleibende Sekunden (bei Eintrittsverzögerung/Ausgangsverzögerung) |

---

## Sprachansagen (Alexa / TTS)

Sprachansagen lassen einen Text vorlesen, wenn ein Alarm-Ereignis eintritt.

### Alexa einrichten (Echo Remote Modul)

1. Die **Echo Remote**-Instanz in IP-Symcon öffnen
2. Die String-Variable **Text zu Sprache** unter der Instanz suchen
3. Variablen-ID notieren
4. In der Sprachansagen-Liste **Zieltyp = Variable (TTS)** auswählen und diese Variable als Ziel eintragen

Das Modul ruft `RequestAction` auf die TTS-Variable auf – dadurch wird der Handler des Echo-Remote-Moduls direkt aufgerufen und der Text wird vorgelesen, auch wenn derselbe Text zuletzt gesprochen wurde.

**Alexa spricht nicht:** Prüfen ob `HasAction()` für die TTS-Variable `true` zurückgibt. Test-Skript: `echo HasAction(<VariablenID>);`. Bei `false` hat die Variable keinen Aktions-Handler – das Modul fällt auf SetValue zurück, und der Text muss sich ändern, damit ein VM_UPDATE ausgelöst wird.

### Art

| Art | Beschreibung |
|-----|-------------|
| TTS | Normales Text-to-Speech |
| Announcement | Nutzt die Alexa-Ankündigungs-API (unterbricht laufende Wiedergabe) |
| SSML | Speech Synthesis Markup Language für individuelle Aussprache-Kontrolle |

Das Feld *Art* wird als `KIND`-Parameter an Skripte übergeben. Bei Variable-Zielen hat es keine direkte Funktion.

---

## Kameras

Der Bereich Kameras steuert, was **nach** einem Alarm passiert: ein Skript aufrufen, das einen Snapshot abruft, ihn per Telegram sendet, eine Aufzeichnung startet o. Ä.

> **Bewegungserkennung → Sensoren verwenden.** Den Bool-Bewegungs- oder Alarmausgang einer Kamera als Sensor in die Sensorliste eintragen. Dieser Kamera-Bereich dient nicht der Bewegungserkennung, sondern der Reaktion auf einen Alarm.

### Skript-Parameter

Wenn ein Ereignis auslöst, ruft das Modul das Skript mit folgenden Parametern auf:

| Parameter | Wert |
|-----------|------|
| `CAMERA_NAME` | Der hier konfigurierte Name |
| `ZONE` | Die Zone, der diese Kamera zugeordnet ist |
| `EVENT` | `pre_alarm`, `alarm` oder `test` |
| `SENDER` | Immer `AlarmCenter` |

### Beispielskript (Telegram-Snapshot)

```php
<?php
$url = 'http://192.168.1.50/snapshot.jpg';
$chatID = '<deine-chat-id>';
$telegramID = 12345; // ID der Telegram-Bot-Instanz in IP-Symcon
$beschriftung = 'Alarm: ' . $_IPS['CAMERA_NAME'] . ' – Zone ' . $_IPS['ZONE'];

$bild = file_get_contents($url);
if ($bild !== false) {
    TelegramBot_SendPhoto($telegramID, $chatID, $bild, $beschriftung);
}
```

---

## PIN-Sicherheit

Die PIN wird als bcrypt-Hash in einem Modul-Attribut gespeichert. Sie wird **niemals im Klartext** gespeichert und erscheint nicht in `settings.json`.

### PIN setzen

Über den Bereich **PIN setzen** im Aktionsbereich der Konfiguration oder per API:
```php
Alarm_SetPin($id, '1234');
```

### Externes PIN-Pad

Ein physisches Tastenfeld anschließen, indem eine String-Variable im Feld *Eingabevariable des PIN-Pads* ausgewählt wird. Das Tastenfeld schreibt die eingegebene PIN in diese Variable. Unterstützte Formate:

| Eingabe | Aktion |
|---------|--------|
| `1234` | Unscharfschalten |
| `DISARM:1234` | Unscharfschalten |
| `ARM_AWAY:1234` | Abwesend scharf |
| `ARM_NIGHT:1234` | Nacht scharf |
| `ACK:1234` | Quittieren |
| `RESET:1234` | Reset |

*PIN-Variable nach dem Lesen leeren* aktivieren, damit die PIN nach der Verarbeitung sofort aus der Variable gelöscht wird.

---

## PHP-API

Alle exportierten Funktionen verwenden das Präfix `Alarm_`.

| Funktion | Beschreibung |
|----------|-------------|
| `Alarm_ArmNight($id, $pin)` | Nacht-Modus scharf |
| `Alarm_ArmAway($id, $pin)` | Abwesend-Modus scharf |
| `Alarm_Disarm($id, $pin)` | Unscharfschalten |
| `Alarm_Acknowledge($id, $pin)` | Alarm quittieren |
| `Alarm_Reset($id, $pin)` | Alarmzustand zurücksetzen |
| `Alarm_SetMode($id, $modus)` | Modus per Ganzzahl setzen (0=Unscharf, 1=Nacht, 2=Abwesend, 3=Test) |
| `Alarm_Panic($id)` | Panik-Alarm sofort auslösen |
| `Alarm_BypassSensor($id, $varID, $minuten, $pin)` | Sensor temporär bypassen |
| `Alarm_UnbypassSensor($id, $varID, $pin)` | Bypass aufheben |
| `Alarm_SetPin($id, $pin)` | PIN setzen oder löschen |
| `Alarm_RunPreArmCheck($id, $modus)` | Vorab-Check durchführen, Ergebnis als Text zurückgeben |
| `Alarm_HandleSensorEvent($id, $varID, $wert)` | Sensor-Ereignis manuell auslösen |
| `Alarm_TestNotification($id)` | Testbenachrichtigung an alle konfigurierten Kanäle senden |
| `Alarm_RebuildFrontend($id)` | Kachel-Visualisierung neu aufbauen |
| `Alarm_ClearHistory($id)` | Ereignisverlauf löschen |

---

## Statusvariablen

Das Modul legt folgende Variablen unter der Instanz an:

| Variable | Beschreibung |
|----------|-------------|
| Modus | Aktueller Betriebsmodus (schaltbar) |
| Zustand | Interner Zustand (nur lesbar) |
| Ist scharf | Boolean: ist die Anlage scharf? |
| Alarm aktiv | Boolean: ist gerade ein Alarm aktiv? |
| Letztes Ereignis | Text des letzten Ereignisses |
| Letzter Auslöser | Name des zuletzt auslösenden Sensors |
| Letzte Zone | Zone des zuletzt auslösenden Sensors |
| Ausgangsverzögerung Rest | Verbleibende Ausgangsverzögerung in Sekunden |
| Eintrittsverzögerung Rest | Verbleibende Eintrittsverzögerung in Sekunden |
| Störung aktiv | Boolean: liegt eine aktive Störung vor? |
| Störungsübersicht | Textbeschreibung aktiver Störungen |
| Bypass aktiv | Boolean: ist ein Sensor gebypastet? |
| Offene Sensoren | Kommagetrennte Liste offener Sensoren |

---

## Kompatibilität

- IP-Symcon 8.1 oder neuer
- PHP 8.0 oder neuer
- Verwendet `IPSModuleStrict` (Pflicht-Typangaben, Read-Only-Variablen)
