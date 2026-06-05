<?php

declare(strict_types=1);

/**
 * Core of the alarm state machine.
 *
 * Strictly separates the user-facing operating Mode (DISARMED/NIGHT/AWAY/TEST)
 * from the internal State of the machine. The Mode is a trigger; the State is
 * always a result of mode changes, sensor events and timers.
 */
trait StateMachineTrait
{
    private function GetState(): int
    {
        return $this->GetValue('State');
    }

    private function GetMode(): int
    {
        return $this->GetValue('Mode');
    }

    /**
     * Performs a state transition: exit hooks -> persist -> enter hooks ->
     * refresh derived variables and frontend.
     */
    private function TransitionTo(int $newState, array $context = []): void
    {
        $current = $this->GetValue('State');
        $this->OnExitState($current, $newState);

        $this->SetValue('State', $newState);
        $this->WriteAttributeString('CurrentState', (string) $newState);
        $this->WriteAttributeInteger('StateEnteredAt', time());

        $this->OnEnterState($newState, $context);
        $this->UpdateDerivedVariables();
        $this->RebuildFrontendInternal();

        $this->SendDebug(__FUNCTION__, $this->GetStateName($current) . ' -> ' . $this->GetStateName($newState), 0);
    }

    private function OnExitState(int $state, int $newState): void
    {
        // Stop the phase timer of the state we are leaving.
        switch ($state) {
            case AlarmConstants::STATE_ARMING_EXIT_DELAY:
                $this->StopTimer('ExitDelayTimer');
                $this->SetValue('ExitDelayRemaining', 0);
                break;
            case AlarmConstants::STATE_ENTRY_DELAY:
                $this->StopTimer('EntryDelayTimer');
                $this->SetValue('EntryDelayRemaining', 0);
                break;
            case AlarmConstants::STATE_PRE_ALARM:
                $this->StopTimer('PreAlarmTimer');
                break;
            case AlarmConstants::STATE_ALARM:
            case AlarmConstants::STATE_ALARM_ACKNOWLEDGED:
                // Alarm timers are only stopped explicitly on reset/disarm,
                // not on every transition between alarm sub-states.
                if ($newState === AlarmConstants::STATE_DISARMED
                    || $newState >= AlarmConstants::STATE_ARMED_NIGHT && $newState <= AlarmConstants::STATE_ARMED_AWAY) {
                    $this->StopTimer('EscalationTimer');
                    $this->StopTimer('SirenDurationTimer');
                }
                break;
        }
    }

    private function OnEnterState(int $state, array $context): void
    {
        switch ($state) {
            case AlarmConstants::STATE_DISARMED:
                $this->SetValue('IsArmed', false);
                $this->SetValue('IsAlarmActive', false);
                $this->SetValue('IsAcknowledged', false);
                break;

            case AlarmConstants::STATE_ARMING_EXIT_DELAY:
                $this->SetValue('IsArmed', true);
                $delay = $this->GetExitDelayForMode($this->GetMode());
                $this->WriteAttributeInteger('DelayEndsAt', time() + $delay);
                $this->SetValue('ExitDelayRemaining', $delay);
                $this->SetTimerInterval('ExitDelayTimer', 1000);
                $this->DispatchEvent(AlarmConstants::EVENT_ARMING_STARTED, $context);
                break;

            case AlarmConstants::STATE_ARMED_NIGHT:
            case AlarmConstants::STATE_ARMED_AWAY:
                $this->SetValue('IsArmed', true);
                $this->SetValue('IsAlarmActive', false);
                $this->SetValue('IsAcknowledged', false);
                $this->DispatchEvent(AlarmConstants::EVENT_ARMED, $context);
                break;

            case AlarmConstants::STATE_ENTRY_DELAY:
                $delay = (int) ($context['entryDelay'] ?? 30);
                $this->WriteAttributeInteger('DelayEndsAt', time() + $delay);
                $this->SetValue('EntryDelayRemaining', $delay);
                $this->SetTimerInterval('EntryDelayTimer', 1000);
                $this->DispatchEvent(AlarmConstants::EVENT_ENTRY_DELAY, $context);
                break;

            case AlarmConstants::STATE_PRE_ALARM:
                $duration = $this->ReadPropertyBoolean('PreAlarmEnabled')
                    ? $this->ReadPropertyInteger('PreAlarmDuration') : 0;
                if ($duration > 0) {
                    $this->SetTimerInterval('PreAlarmTimer', $duration * 1000);
                }
                $this->DispatchEvent(AlarmConstants::EVENT_PRE_ALARM, $context);
                break;

            case AlarmConstants::STATE_ALARM:
                $this->SetValue('IsAlarmActive', true);
                $this->SetValue('IsAcknowledged', false);
                if ($this->ReadPropertyBoolean('EscalationEnabled')) {
                    $this->SetTimerInterval('EscalationTimer', max(1, $this->ReadPropertyInteger('EscalationDelay')) * 1000);
                }
                if ($this->ReadPropertyBoolean('SirenDurationEnabled')) {
                    $this->SetTimerInterval('SirenDurationTimer', max(1, $this->ReadPropertyInteger('SirenDuration')) * 1000);
                }
                $this->DispatchEvent(AlarmConstants::EVENT_ALARM, $context);
                break;

            case AlarmConstants::STATE_ALARM_ACKNOWLEDGED:
                $this->SetValue('IsAcknowledged', true);
                $this->DispatchEvent(AlarmConstants::EVENT_ACKNOWLEDGED, $context);
                break;

            case AlarmConstants::STATE_TEST:
                $this->SetValue('IsArmed', true);
                $this->DispatchEvent(AlarmConstants::EVENT_TEST, $context);
                break;
        }
    }

    /**
     * Handles a mode change requested by the user (variable, API or visu).
     */
    private function ChangeMode(int $mode, string $pin = '', bool $pinChecked = false): bool
    {
        if (!$pinChecked) {
            $needPin = ($mode === AlarmConstants::MODE_DISARMED)
                ? $this->ReadPropertyBoolean('RequirePinForDisarm')
                : $this->ReadPropertyBoolean('RequirePinForArm');
            if ($needPin && !$this->CheckPinForAction($pin)) {
                return false;
            }
        }

        if ($mode === AlarmConstants::MODE_DISARMED) {
            return $this->DisarmInternal(true);
        }

        // Arming a mode -> run pre-arm check first.
        $result = $this->RunPreArmCheckInternal($mode);
        if ($result['result'] === AlarmConstants::PREARM_BLOCKED) {
            $this->AddHistory(AlarmConstants::EVENT_ARMING_FAILED, $this->Translate('Arming blocked') . ': ' . $result['summary']);
            $this->DispatchEvent(AlarmConstants::EVENT_ARMING_FAILED, ['summary' => $result['summary']]);
            $this->RebuildFrontendInternal();
            return false;
        }

        $this->SetValue('Mode', $mode);
        $this->WriteAttributeInteger('CurrentMode', $mode);

        if ($mode === AlarmConstants::MODE_TEST) {
            $this->TransitionTo(AlarmConstants::STATE_TEST);
            return true;
        }

        $delay = $this->GetExitDelayForMode($mode);
        if ($delay > 0) {
            $this->TransitionTo(AlarmConstants::STATE_ARMING_EXIT_DELAY);
        } else {
            $this->TransitionTo(AlarmConstants::ModeToArmedState($mode));
        }
        return true;
    }

    private function DisarmInternal(bool $clearMode): bool
    {
        if ($clearMode) {
            $this->SetValue('Mode', AlarmConstants::MODE_DISARMED);
            $this->WriteAttributeInteger('CurrentMode', AlarmConstants::MODE_DISARMED);
        }
        $this->StopTimer('ExitDelayTimer');
        $this->StopTimer('EntryDelayTimer');
        $this->StopTimer('PreAlarmTimer');
        $this->StopTimer('EscalationTimer');
        $this->StopTimer('SirenDurationTimer');
        $this->CancelActions(true, false);
        $this->TransitionTo(AlarmConstants::STATE_DISARMED);
        $this->DispatchEvent(AlarmConstants::EVENT_DISARMED, []);
        $this->ResetAttempts();
        return true;
    }

    private function GetExitDelayForMode(int $mode): int
    {
        return match ($mode) {
            AlarmConstants::MODE_NIGHT => $this->ReadPropertyInteger('ExitDelayNight'),
            AlarmConstants::MODE_AWAY  => $this->ReadPropertyInteger('ExitDelayAway'),
            AlarmConstants::MODE_TEST  => $this->ReadPropertyInteger('ExitDelayTest'),
            default                    => 0,
        };
    }

    private function IsArmedState(int $state): bool
    {
        return in_array($state, [
            AlarmConstants::STATE_ARMED_NIGHT,
            AlarmConstants::STATE_ARMED_AWAY,
        ], true);
    }

    /**
     * Restores state after a Symcon restart (only called once at KR_READY).
     */
    private function ApplyRestartBehavior(): void
    {
        $behavior = $this->ReadPropertyString('RestartBehavior');
        if ($behavior === AlarmConstants::RESTART_DISARMED) {
            $this->DisarmInternal(true);
            return;
        }

        $mode = $this->ReadAttributeInteger('CurrentMode');
        if ($mode === AlarmConstants::MODE_DISARMED) {
            $this->TransitionTo(AlarmConstants::STATE_DISARMED);
            return;
        }

        if ($behavior === AlarmConstants::RESTART_RESTORE_PRECHECK) {
            $result = $this->RunPreArmCheckInternal($mode);
            if ($result['result'] === AlarmConstants::PREARM_BLOCKED) {
                $this->RaiseTrouble($this->Translate('Restore blocked by pre-arm check') . ': ' . $result['summary']);
                $this->DisarmInternal(true);
                return;
            }
        }

        $this->SetValue('Mode', $mode);
        $this->TransitionTo(AlarmConstants::ModeToArmedState($mode));
    }

    private function GetStateName(int $state): string
    {
        return match ($state) {
            AlarmConstants::STATE_DISARMED           => 'DISARMED',
            AlarmConstants::STATE_ARMING_EXIT_DELAY  => 'ARMING_EXIT_DELAY',
            AlarmConstants::STATE_ARMED_NIGHT        => 'ARMED_NIGHT',
            AlarmConstants::STATE_ARMED_AWAY         => 'ARMED_AWAY',
            AlarmConstants::STATE_ENTRY_DELAY        => 'ENTRY_DELAY',
            AlarmConstants::STATE_PRE_ALARM          => 'PRE_ALARM',
            AlarmConstants::STATE_ALARM              => 'ALARM',
            AlarmConstants::STATE_ALARM_ACKNOWLEDGED => 'ALARM_ACKNOWLEDGED',
            AlarmConstants::STATE_TROUBLE            => 'TROUBLE',
            AlarmConstants::STATE_TEST               => 'TEST',
            default                                  => 'UNKNOWN',
        };
    }

    private function GetModeName(int $mode): string
    {
        return match ($mode) {
            AlarmConstants::MODE_NIGHT => $this->Translate('Night'),
            AlarmConstants::MODE_AWAY  => $this->Translate('Away'),
            AlarmConstants::MODE_TEST  => $this->Translate('Test'),
            default                    => $this->Translate('Disarmed'),
        };
    }

    /**
     * Updates the convenience/summary variables that depend on the state.
     */
    private function UpdateDerivedVariables(): void
    {
        $state = $this->GetValue('State');
        $this->SetValue('LastEvent', date('Y-m-d H:i:s') . ' – ' . $this->GetStateName($state));
        $this->SetValue('OpenSensorsSummary', $this->BuildOpenSensorsSummary());
        $this->SetValue('BypassActive', $this->GetBypassSummary() !== '');
    }
}
