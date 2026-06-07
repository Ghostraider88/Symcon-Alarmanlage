<?php

declare(strict_types=1);

/**
 * Sensor configuration handling and the sensor event flow.
 */
trait SensorTrait
{
    /**
     * Evaluates whether a value constitutes an alarm for the given trigger.
     *
     * Pure function (no Symcon calls) so it can be unit tested in isolation.
     */
    public static function EvaluateTrigger(mixed $value, int $triggerType, float $triggerValue): bool
    {
        switch ($triggerType) {
            case AlarmConstants::TRIGGER_BOOL_TRUE:
                return (bool) $value === true;
            case AlarmConstants::TRIGGER_BOOL_FALSE:
                return (bool) $value === false;
            case AlarmConstants::TRIGGER_INT_EQUALS:
                return (float) $value === $triggerValue;
            case AlarmConstants::TRIGGER_INT_NOT_EQUALS:
                return (float) $value !== $triggerValue;
            case AlarmConstants::TRIGGER_INT_GREATER_THAN:
                return (float) $value > $triggerValue;
            case AlarmConstants::TRIGGER_INT_GREATER_EQUAL:
                return (float) $value >= $triggerValue;
            case AlarmConstants::TRIGGER_INT_LESS_THAN:
                return (float) $value < $triggerValue;
            case AlarmConstants::TRIGGER_INT_LESS_EQUAL:
                return (float) $value <= $triggerValue;
            default:
                return false;
        }
    }
    /**
     * Decodes the configured sensor list (JSON property) into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function GetSensors(): array
    {
        $raw = json_decode($this->ReadPropertyString('Sensors'), true);
        return is_array($raw) ? $raw : [];
    }

    /**
     * (Re-)registers VM_UPDATE messages for every enabled sensor with a valid
     * variable. Old registrations are cleared first to avoid stale watches.
     */
    private function RebuildSensorWatch(): void
    {
        $previous = json_decode($this->GetBuffer('WatchedVariables') ?: '[]', true);
        if (is_array($previous)) {
            foreach ($previous as $vid) {
                $this->UnregisterMessage((int) $vid, VM_UPDATE);
            }
        }

        $watched = [];
        foreach ($this->GetSensors() as $sensor) {
            if (empty($sensor['Enabled'])) {
                continue;
            }
            $vid = (int) ($sensor['VariableID'] ?? 0);
            if ($vid <= 0 || !IPS_VariableExists($vid)) {
                continue;
            }
            if (!in_array($vid, $watched, true)) {
                $this->RegisterMessage($vid, VM_UPDATE);
                $this->RegisterReference($vid);
                $watched[] = $vid;
            }
        }

        $this->SetBuffer('WatchedVariables', json_encode($watched));
    }

    private function IsSensorActiveInMode(array $sensor, int $mode): bool
    {
        return match ($mode) {
            AlarmConstants::MODE_NIGHT => !empty($sensor['ActiveInNight']),
            AlarmConstants::MODE_AWAY  => !empty($sensor['ActiveInAway']),
            AlarmConstants::MODE_TEST  => !empty($sensor['ActiveInTest']),
            default                    => false,
        };
    }

    /**
     * Core sensor event handler. Reachable internally (MessageSink) and via the
     * public Alarm_HandleSensorEvent for manual triggering and tests.
     */
    private function HandleSensorEventInternal(int $variableID, mixed $value): void
    {
        $state = $this->GetValue('State');
        $mode = $this->GetValue('Mode');

        foreach ($this->GetSensors() as $index => $sensor) {
            if (empty($sensor['Enabled']) || (int) ($sensor['VariableID'] ?? 0) !== $variableID) {
                continue;
            }

            $isAlarm = self::EvaluateTrigger(
                $value,
                (int) ($sensor['TriggerType'] ?? AlarmConstants::TRIGGER_BOOL_TRUE),
                (float) ($sensor['TriggerValue'] ?? 0)
            );
            if (!$isAlarm) {
                continue;
            }

            // Debounce: ignore repeated triggers within the configured window.
            $debounce = (int) ($sensor['Debounce'] ?? 0);
            if ($debounce > 0 && !$this->PassesDebounce($index, $debounce)) {
                continue;
            }

            // While disarmed/test only log; only check trouble-like conditions elsewhere.
            if (!$this->IsArmedState($state)
                && $state !== AlarmConstants::STATE_ENTRY_DELAY
                && $state !== AlarmConstants::STATE_PRE_ALARM
                && $state !== AlarmConstants::STATE_ARMING_EXIT_DELAY
                && $state !== AlarmConstants::STATE_TEST) {
                continue;
            }

            // Mode filter.
            if ($state === AlarmConstants::STATE_TEST) {
                if (!$this->IsSensorActiveInMode($sensor, AlarmConstants::MODE_TEST)) {
                    continue;
                }
            } elseif (!$this->IsSensorActiveInMode($sensor, $mode)) {
                continue;
            }

            // Bypass check.
            if ($this->IsSensorBypassed($index)) {
                $this->AddHistory(AlarmConstants::EVENT_BYPASS_ON, sprintf($this->Translate('Bypassed sensor %s ignored'), (string) ($sensor['Name'] ?? '')));
                continue;
            }

            $this->ProcessSensorTrigger($index, $sensor, $value);
        }
    }

    private function ProcessSensorTrigger(int $index, array $sensor, mixed $value): void
    {
        $state = $this->GetValue('State');
        $context = $this->BuildSensorContext($sensor);

        $this->SetValue('LastTriggerSensorID', (int) ($sensor['VariableID'] ?? 0));
        $this->SetValue('LastTriggerSensorName', (string) ($sensor['Name'] ?? ''));
        $this->SetValue('LastTriggerZone', (string) ($sensor['Zone'] ?? ''));
        $this->WriteAttributeInteger('LastTriggerSensorID', (int) ($sensor['VariableID'] ?? 0));

        // Test mode: only document, never escalate.
        if ($state === AlarmConstants::STATE_TEST) {
            $this->AddHistory(AlarmConstants::EVENT_TEST, sprintf($this->Translate('Test trigger: %s'), (string) ($sensor['Name'] ?? '')));
            $this->RebuildFrontendInternal();
            return;
        }

        // Already in alarm: just document further triggers.
        if ($state === AlarmConstants::STATE_ALARM) {
            $this->AddHistory(AlarmConstants::EVENT_ALARM, sprintf($this->Translate('Additional trigger: %s'), (string) ($sensor['Name'] ?? '')));
            return;
        }

        $reaction = (int) ($sensor['Reaction'] ?? AlarmConstants::REACTION_INSTANT);

        // During exit delay, sensors normally do not fire unless flagged.
        if ($state === AlarmConstants::STATE_ARMING_EXIT_DELAY && empty($sensor['IgnoreExitDelay'])) {
            return;
        }

        switch ($reaction) {
            case AlarmConstants::REACTION_PRE_ALARM:
                $preAlarmDuration = $this->ReadPropertyBoolean('PreAlarmEnabled')
                    ? (int) $this->ReadPropertyInteger('PreAlarmDuration') : 0;
                if ($preAlarmDuration <= 0) {
                    // Pre-alarm not configured → treat as instant alarm.
                    $this->AddHistory(AlarmConstants::EVENT_ALARM, sprintf($this->Translate('Alarm triggered by %s'), (string) ($sensor['Name'] ?? '')));
                    $this->TransitionTo(AlarmConstants::STATE_ALARM, $context);
                } else {
                    $this->AddHistory(AlarmConstants::EVENT_PRE_ALARM, sprintf($this->Translate('Pre-alarm: %s'), (string) ($sensor['Name'] ?? '')));
                    $this->TransitionTo(AlarmConstants::STATE_PRE_ALARM, $context);
                }
                break;

            case AlarmConstants::REACTION_ENTRY_DELAY:
                if ($state === AlarmConstants::STATE_ENTRY_DELAY) {
                    return; // already counting down
                }
                $context['entryDelay'] = (int) ($sensor['EntryDelay'] ?? 30);
                $this->AddHistory(AlarmConstants::EVENT_ENTRY_DELAY, sprintf($this->Translate('Entry delay started by %s'), (string) ($sensor['Name'] ?? '')));
                $this->TransitionTo(AlarmConstants::STATE_ENTRY_DELAY, $context);
                break;

            case AlarmConstants::REACTION_INSTANT:
            default:
                $this->AddHistory(AlarmConstants::EVENT_ALARM, sprintf($this->Translate('Alarm triggered by %s'), (string) ($sensor['Name'] ?? '')));
                $this->TransitionTo(AlarmConstants::STATE_ALARM, $context);
                break;
        }
    }

    private function PassesDebounce(int $index, int $debounceMs): bool
    {
        $map = json_decode($this->GetBuffer('DebounceTimers') ?: '{}', true);
        if (!is_array($map)) {
            $map = [];
        }
        $now = (int) round(microtime(true) * 1000);
        $last = (int) ($map[$index] ?? 0);
        if ($now - $last < $debounceMs) {
            return false;
        }
        $map[$index] = $now;
        $this->SetBuffer('DebounceTimers', json_encode($map));
        return true;
    }

    private function BuildSensorContext(array $sensor): array
    {
        return [
            'sensorName' => (string) ($sensor['Name'] ?? ''),
            'sensorID'   => (int) ($sensor['VariableID'] ?? 0),
            'zone'       => (string) ($sensor['Zone'] ?? ''),
        ];
    }

    /**
     * Returns a human readable summary of all currently "open"/active sensors.
     */
    private function BuildOpenSensorsSummary(): string
    {
        $open = [];
        foreach ($this->GetSensors() as $sensor) {
            if (empty($sensor['Enabled'])) {
                continue;
            }
            $vid = (int) ($sensor['VariableID'] ?? 0);
            if ($vid <= 0 || !IPS_VariableExists($vid)) {
                continue;
            }
            $isAlarm = self::EvaluateTrigger(
                GetValue($vid),
                (int) ($sensor['TriggerType'] ?? AlarmConstants::TRIGGER_BOOL_TRUE),
                (float) ($sensor['TriggerValue'] ?? 0)
            );
            if ($isAlarm) {
                $open[] = (string) ($sensor['Name'] ?? ('#' . $vid));
            }
        }
        return implode(', ', $open);
    }
}
