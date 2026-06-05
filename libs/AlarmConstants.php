<?php

declare(strict_types=1);

/**
 * Central definition of all constants used by the alarm center.
 *
 * Kept free of any Symcon dependency so the values can also be used inside
 * isolated unit tests (e.g. for the trigger evaluation logic).
 */
abstract class AlarmConstants
{
    // --- Operating modes (user intent, stored in the "Mode" status variable) ---
    public const MODE_DISARMED = 0;
    public const MODE_NIGHT = 1;
    public const MODE_AWAY = 2;
    public const MODE_TEST = 3;

    // --- Internal states of the state machine (stored in the "State" variable) ---
    public const STATE_DISARMED = 0;
    public const STATE_ARMING_EXIT_DELAY = 1;
    public const STATE_ARMED_NIGHT = 2;
    public const STATE_ARMED_AWAY = 3;
    public const STATE_ENTRY_DELAY = 4;
    public const STATE_PRE_ALARM = 5;
    public const STATE_ALARM = 6;
    public const STATE_ALARM_ACKNOWLEDGED = 7;
    public const STATE_TROUBLE = 8;
    public const STATE_TEST = 9;

    // --- Sensor types (display/grouping only, logic always via trigger) ---
    public const SENSOR_DOOR = 0;
    public const SENSOR_WINDOW = 1;
    public const SENSOR_MOTION = 2;
    public const SENSOR_CAMERA = 3;
    public const SENSOR_TAMPER = 4;
    public const SENSOR_SYSTEM = 5;
    public const SENSOR_EXTERNAL = 6;
    public const SENSOR_OTHER = 7;

    // --- Trigger types: how a sensor value is interpreted as "alarm" ---
    public const TRIGGER_BOOL_TRUE = 0;
    public const TRIGGER_BOOL_FALSE = 1;
    public const TRIGGER_INT_EQUALS = 2;
    public const TRIGGER_INT_NOT_EQUALS = 3;
    public const TRIGGER_INT_GREATER_THAN = 4;
    public const TRIGGER_INT_GREATER_EQUAL = 5;
    public const TRIGGER_INT_LESS_THAN = 6;
    public const TRIGGER_INT_LESS_EQUAL = 7;

    // --- Reaction type of a sensor when it triggers ---
    public const REACTION_INSTANT = 0; // immediate alarm
    public const REACTION_PRE_ALARM = 1; // pre-alarm only
    public const REACTION_ENTRY_DELAY = 2; // entry delay before alarm

    // --- Criticality of a sensor (drives pre-arm decisions) ---
    public const CRIT_LOW = 0;
    public const CRIT_NORMAL = 1;
    public const CRIT_HIGH = 2;

    // --- Pre-arm check results ---
    public const PREARM_OK = 0;
    public const PREARM_WARNING = 1;
    public const PREARM_BLOCKED = 2;

    // --- Action target types ---
    public const TARGET_VARIABLE = 0;
    public const TARGET_SCRIPT = 1;

    // --- Voice announcement kinds ---
    public const VOICE_TTS = 0;
    public const VOICE_ANNOUNCEMENT = 1;
    public const VOICE_SSML = 2;

    // --- Event types (used as filters for actions/notifications/voice) ---
    public const EVENT_ARMING_STARTED = 'arming_started';
    public const EVENT_ARMED = 'armed';
    public const EVENT_ARMING_FAILED = 'arming_failed';
    public const EVENT_DISARMED = 'disarmed';
    public const EVENT_ENTRY_DELAY = 'entry_delay';
    public const EVENT_COUNTDOWN = 'countdown';
    public const EVENT_PRE_ALARM = 'pre_alarm';
    public const EVENT_ALARM = 'alarm';
    public const EVENT_ALARM_UNACK = 'alarm_unacknowledged';
    public const EVENT_ACKNOWLEDGED = 'acknowledged';
    public const EVENT_RESET = 'reset';
    public const EVENT_TROUBLE = 'trouble';
    public const EVENT_TEST = 'test';
    public const EVENT_BYPASS_ON = 'bypass_on';
    public const EVENT_BYPASS_OFF = 'bypass_off';
    public const EVENT_PIN_WRONG = 'pin_wrong';
    public const EVENT_PIN_LOCKED = 'pin_locked';

    // --- Restart behaviour options ---
    public const RESTART_DISARMED = 'disarmed';
    public const RESTART_RESTORE = 'restore';
    public const RESTART_RESTORE_PRECHECK = 'restore_precheck';

    /**
     * Maps an operating mode to its target armed state.
     */
    public static function ModeToArmedState(int $mode): int
    {
        return match ($mode) {
            self::MODE_NIGHT => self::STATE_ARMED_NIGHT,
            self::MODE_AWAY  => self::STATE_ARMED_AWAY,
            self::MODE_TEST  => self::STATE_TEST,
            default          => self::STATE_DISARMED,
        };
    }
}
