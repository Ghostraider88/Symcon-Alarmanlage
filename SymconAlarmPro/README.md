# SymconAlarmPro

A flexible, state-machine-based alarm center for IP-Symcon 8.1+. No device IDs are hardcoded — every sensor, zone, output, notification, and camera action is configured through the instance form.

---

## Quick-start checklist

1. Add the library via **Module Control** → **Add** → paste the repository URL
2. Create an instance of **Symcon Alarm Pro** under *Other*
3. Open the instance configuration, configure **Zones** first, then **Sensors**
4. Test with the **Test** mode before using Night / Away mode
5. Set a PIN if required (via the *Set PIN* section at the bottom of the form)

---

## Concepts

### Modes vs. States

**Mode** is the user's intent (what you want the system to do):

| Mode | Description |
|------|-------------|
| Disarmed | System is off, no sensors are active |
| Night | You are home. Typically only perimeter sensors (doors/windows) are active. Motion detectors are off. |
| Away | You have left the building. All configured sensors are active. |
| Test | Commissioning mode. Sensors fire but the system never escalates to a real alarm. Use for first-time setup. |

**State** is what the system is currently doing internally:

| State | Description |
|-------|-------------|
| Disarmed | Nothing active |
| Arming (exit delay) | Countdown before arming; leave the building now |
| Armed Night / Armed Away | System is live |
| Entry delay | A sensor with reaction "Entry delay" triggered; disarm within the configured seconds or the alarm fires |
| Pre-alarm | A sensor with reaction "Pre-alarm" triggered; a warning period before full alarm |
| Alarm | Alarm is active |
| Alarm acknowledged | Alarm was confirmed but not fully reset |
| Test | System is armed in test mode |

---

## Zones

Zones are logical areas (e.g. *Ground floor*, *Garden*, *Garage*). They serve two purposes:

1. **Organisation** – label which area a sensor belongs to
2. **Filtering** – actions, notifications, and cameras can be limited to fire only when a sensor from a specific zone triggers

A zone has no logic of its own; it is purely a label that you define and then reference in other places.

**Setup:** Add zones in the **Zones** panel. The name is a free text field. Use exactly the same name when assigning sensors and configuring zone filters.

---

## Sensors

### Sensor type

The sensor type (Door contact, Window contact, Motion detector, …) is **documentary only**. It does not affect alarm logic. Use it to label and organise your list. The actual alarm behaviour is entirely determined by the **Trigger** and **Reaction** fields.

| Type | Intended use |
|------|-------------|
| Door contact | Reed switch or magnetic contact on a door |
| Window contact | Reed switch on a window |
| Motion detector | PIR or radar presence sensor |
| Camera alarm | Boolean alarm output of an IP camera |
| Tamper | Anti-tamper switch on a sensor housing |
| System status | System health or status signal |
| External source | Any external alarm signal (e.g. flood, smoke) |
| Other | Anything else |

### Trigger types

The trigger defines which variable value counts as "alarm". IP-Symcon sends a `VM_UPDATE` event whenever a watched variable changes; the module evaluates the new value against the configured trigger.

| Trigger | Meaning |
|---------|---------|
| Bool true = alarm | The Boolean variable changes to `true` |
| Bool false = alarm | The Boolean variable changes to `false` (e.g. a window contact where *open* = `false`) |
| Int = value | Integer/float value equals the threshold |
| Int ≠ value | Integer/float value does not equal the threshold |
| Int > value | Integer/float value is greater than the threshold |
| Int ≥ value | Integer/float value is greater than or equal to the threshold |
| Int < value | Integer/float value is less than the threshold |
| Int ≤ value | Integer/float value is less than or equal to the threshold |

The **Value** field (comparison threshold) is only relevant for Int trigger types.

### Night / Away / Test — active modes

Each sensor has three checkboxes: **Night**, **Away**, **Test**. A sensor is only evaluated when the system is armed in one of the checked modes.

**Typical configuration:**
- Door contact on front door: ✓ Night, ✓ Away → active in both modes
- Motion detector: ✗ Night, ✓ Away → active only when you are away (not when sleeping)
- Test-only sensor: ✗ Night, ✗ Away, ✓ Test → only for commissioning

### Reaction types

| Reaction | Behaviour |
|----------|-----------|
| **Instant alarm** | The alarm fires immediately when the sensor triggers. Use for windows, tamper contacts, and critical sensors. |
| **Pre-alarm** | The system enters a silent pre-alarm phase first. If the alarm is not cleared or disarmed within the pre-alarm duration (configured in Modes & Delays), the full alarm fires. Useful as a grace period before escalation. |
| **Entry delay** | A per-sensor countdown starts (the *Entry delay (s)* field). If you do not disarm within that time, the alarm fires. Use for the front door — you need a moment to disarm the system when you come home. |

### Debounce

Minimum milliseconds between two alarm triggers from the same sensor. Prevents bouncing contacts from generating multiple alarms. A door contact may flutter for 20–50 ms when it opens; setting Debounce to 200 ms filters that out. Set to 0 to disable.

### Bypass

When **Bypass allowed** is checked, this specific sensor can be temporarily disabled without disarming the whole system. Use case: you want to leave a window open while the rest of the system is armed.

Bypass a sensor via the `Alarm_BypassSensor($id, $variableID)` API call or from a script/button. The bypass can be time-limited (duration in minutes).

### Block arm if active

If this sensor is in alarm state when you try to arm the system, arming is blocked with an error. Without this flag, open sensors generate only a warning and arming proceeds anyway.

### Criticality

Affects the pre-arm check result:

| Criticality | Effect when sensor is open during arm attempt |
|-------------|----------------------------------------------|
| Low | Notice only, arming proceeds |
| Normal | Warning shown, arming proceeds |
| High | Blocks arming (requires *Block arm if active* to also be checked) |

### Exit delay ignore

If checked, this sensor can fire an alarm even during the exit delay phase. Use for tamper contacts or a panic button that must always be active regardless of the countdown.

---

## Actions (siren / lights)

Actions control physical outputs when an alarm event occurs: siren relays, warning lights, Shelly switches, dimmers, or any other IP-Symcon variable.

### Target types

| Type | Description |
|------|-------------|
| Variable | The module writes `Value on` / `Value off` to the variable. If the variable has an action handler (e.g. it belongs to a Homematic actor), `RequestAction` is used; otherwise `SetValue`. |
| Script | An IPS script is called with `VALUE` and `SENDER='AlarmCenter'` parameters. |

### Value on / Value off

The values written when the action switches on and off:
- Boolean variable: `1` / `0` or `true` / `false`
- Dimmer (0–100): `100` / `0`
- Colour variable: integer representation of the colour (e.g. `16711680` for red)
- For scripts: passed as the `VALUE` parameter

### Delay and Duration

- **Delay**: Wait N seconds after the event before switching on. Useful for staged responses (pre-warning light 5 s before siren).
- **Duration**: Keep the output on for N seconds, then switch off automatically. Set to 0 for indefinite (until cancelled).

### Blink mode

Flashes the output N times with a configurable interval. Example: `Blink count = 3`, `Blink interval = 1 s` → the output goes on–off–on–off–on–off over 6 seconds. Overrides **Duration** when enabled. Useful for warning lights.

### Mode filter

Comma-separated mode names. Leave empty to fire in all modes. Example: `night,away` fires in Night and Away modes but not in Test mode.

### Zone filter

Select a zone from the dropdown. Leave empty to fire regardless of which zone triggered.

### Cancel on disarm / Cancel on ack

The module tracks running actions and automatically turns them off (writes `Value off`) when:
- **Cancel on disarm**: the system is disarmed
- **Cancel on ack**: the alarm is acknowledged

Recommendation: enable *Cancel on disarm* for sirens. Enable *Cancel on ack* for lights that should stop when the alarm is confirmed.

---

## Notifications

Notifications send a message when an alarm event occurs. The module supports five delivery types.

### Delivery types

#### Pushover

Select the **Pushover** module instance configured in IP-Symcon. The module calls `PushOver_SendNotification()`. The *Subject / Title* field becomes the notification title.

**Setup:** Install the Pushover module from the Symcon module store, enter your API credentials, then select the instance here.

#### SMTP / Email

Select the **SMTP** (Mailer) module instance. The module calls `SMTP_SendMail()`. Subject becomes the email subject line. If the module requires a recipient address, enter it in the **Parameter** field.

#### Telegram

Select the **Telegram Bot** module instance. Enter the **Chat ID** in the *Parameter* field. The module tries `TelegramBot_SendMessage()` and `Telegram_SendMessage()` in order.

**Setup:** Install a Telegram Bot module, configure the bot token, then enter the Chat ID of the target conversation.

#### Variable

Writes the rendered message text into a String variable. The module uses `RequestAction` when the variable has an action handler (e.g. Echo Remote TTS), otherwise clears and re-sets the value to ensure the change event fires.

#### Script

Calls an IPS script with these parameters:
- `TEXT` — rendered message
- `SUBJECT` — subject / title
- `EVENT` — event name (e.g. `alarm`, `disarmed`)
- `SENDER` — always `AlarmCenter`

### Message template

The template supports these placeholders:

| Placeholder | Description |
|-------------|-------------|
| `{sensorName}` | Name of the triggering sensor |
| `{zone}` | Zone of the triggering sensor |
| `{mode}` | Current mode (disarmed/night/away/test) |
| `{state}` | Current state |
| `{timestamp}` | Current date/time |
| `{remainingSeconds}` | Remaining delay seconds (during entry/exit delay) |

---

## Voice announcements (Alexa / TTS)

Voice announcements send spoken text when an alarm event occurs.

### Alexa setup (Echo Remote module)

1. Open the **Echo Remote** instance in IP-Symcon
2. Find the **Text to speech** String variable under the instance
3. Note the variable ID
4. In the Voice announcements list, set **Target type = Variable (TTS)** and select this variable as target

The module uses `RequestAction` on the TTS variable, which triggers the Echo Remote module's handler directly. This ensures speech fires even if the same text was spoken recently.

**If Alexa does not speak:** verify that `HasAction()` returns true for the TTS variable. Open a test script and run `echo HasAction(<variableID>);`. If it returns `false`, the variable has no action handler and the module falls back to `SetValue` — which requires the text to change to fire a VM_UPDATE.

### Kind

| Kind | Description |
|------|-------------|
| TTS | Plain text-to-speech |
| Announcement | Uses the Alexa announcement API (interrupts whatever is playing) |
| SSML | Speech Synthesis Markup Language for advanced pronunciation |

The Kind field is passed as `KIND` to scripts. For variable targets it is informational only.

---

## Cameras

The Cameras section handles what happens **after** an alarm: call a script so you can grab a snapshot, send it via Telegram, start a recording, etc.

> **Motion detection → use Sensors.** Add a camera's Boolean motion/alarm output variable as a Sensor. The Camera section is not for detecting motion; it is for reacting to an alarm with camera-related actions.

### Script parameters

When an event fires, the module calls your script with:

| Parameter | Value |
|-----------|-------|
| `CAMERA_NAME` | Name configured in the list |
| `ZONE` | Zone the camera is assigned to |
| `EVENT` | `pre_alarm`, `alarm`, or `test` |
| `SENDER` | `AlarmCenter` |

### Example script (Telegram snapshot)

```php
<?php
$url = 'http://192.168.1.50/snapshot.jpg';
$chatID = '<your-chat-id>';
$telegramID = 12345; // ID of Telegram Bot instance in IP-Symcon
$caption = 'Alarm: ' . $_IPS['CAMERA_NAME'] . ' – ' . $_IPS['ZONE'];

$image = file_get_contents($url);
if ($image !== false) {
    TelegramBot_SendPhoto($telegramID, $chatID, $image, $caption);
}
```

---

## PIN security

The PIN is stored as a bcrypt hash in a module attribute. It is **never** stored in clear text or in a Symcon property (which would appear in settings.json).

### Setting a PIN

Use the **Set PIN** section in the actions area of the configuration form, or call:
```php
Alarm_SetPin($id, '1234');
```

### External PIN pad

Connect a physical keypad by selecting a String variable in the *External PIN pad input variable* field. The keypad writes the PIN into this variable. Supported formats:

| Input | Action |
|-------|--------|
| `1234` | Disarm |
| `DISARM:1234` | Disarm |
| `ARM_AWAY:1234` | Arm Away |
| `ARM_NIGHT:1234` | Arm Night |
| `ACK:1234` | Acknowledge |
| `RESET:1234` | Reset |

Enable *Clear PIN input variable after reading* to erase the PIN from the variable immediately after processing.

---

## PHP API

All exported functions use the `Alarm_` prefix.

| Function | Description |
|----------|-------------|
| `Alarm_ArmNight($id, $pin)` | Arm in Night mode |
| `Alarm_ArmAway($id, $pin)` | Arm in Away mode |
| `Alarm_Disarm($id, $pin)` | Disarm |
| `Alarm_Acknowledge($id, $pin)` | Acknowledge alarm |
| `Alarm_Reset($id, $pin)` | Reset alarm state |
| `Alarm_SetMode($id, $mode)` | Set mode by integer (0=Disarmed, 1=Night, 2=Away, 3=Test) |
| `Alarm_Panic($id)` | Trigger panic alarm immediately |
| `Alarm_BypassSensor($id, $varID, $minutes, $pin)` | Bypass a sensor temporarily |
| `Alarm_UnbypassSensor($id, $varID, $pin)` | Remove bypass |
| `Alarm_SetPin($id, $pin)` | Set or clear the PIN |
| `Alarm_RunPreArmCheck($id, $mode)` | Run pre-arm check and return result string |
| `Alarm_HandleSensorEvent($id, $varID, $value)` | Manually trigger a sensor event |
| `Alarm_TestNotification($id)` | Send a test notification to all configured channels |
| `Alarm_RebuildFrontend($id)` | Force-refresh the tile visualization |
| `Alarm_ClearHistory($id)` | Clear the event history |

---

## Status variables

The module creates these variables under the instance:

| Variable | Description |
|----------|-------------|
| Mode | Current operating mode (switchable) |
| State | Internal state (read-only) |
| IsArmed | Boolean: is the system armed? |
| IsAlarmActive | Boolean: is an alarm currently active? |
| LastEvent | Text of the last event |
| LastTriggerSensorName | Name of the last sensor that triggered |
| LastTriggerZone | Zone of the last triggering sensor |
| ExitDelayRemaining | Remaining exit delay in seconds |
| EntryDelayRemaining | Remaining entry delay in seconds |
| TroubleActive | Boolean: is there an active trouble? |
| TroubleSummary | Text description of active troubles |
| BypassActive | Boolean: is any sensor bypassed? |
| OpenSensorsSummary | Comma-separated list of open sensors |

---

## Compatibility

- IP-Symcon 8.1 or newer
- PHP 8.0 or newer
- Uses `IPSModuleStrict` (mandatory type hints, read-only variables)
