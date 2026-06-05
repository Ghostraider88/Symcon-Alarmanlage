<?php

declare(strict_types=1);

/**
 * Timer registration and the timer callback handlers.
 *
 * The callback methods are public because Symcon timers invoke them as
 * Alarm_<Method>($_IPS['TARGET']). They are not part of the documented API.
 */
trait TimerTrait
{
    public function ExitDelayTick(): void
    {
        $remaining = $this->RemainingDelay();
        $this->SetValue('ExitDelayRemaining', $remaining);
        $this->PushVisualization();
        if ($remaining <= 0) {
            $this->StopTimer('ExitDelayTimer');
            $this->TransitionTo(AlarmConstants::ModeToArmedState($this->GetMode()));
        } else {
            $this->DispatchEvent(AlarmConstants::EVENT_COUNTDOWN, ['remainingSeconds' => $remaining]);
        }
    }

    public function EntryDelayTick(): void
    {
        $remaining = $this->RemainingDelay();
        $this->SetValue('EntryDelayRemaining', $remaining);
        $this->PushVisualization();
        if ($remaining <= 0) {
            $this->StopTimer('EntryDelayTimer');
            $this->AddHistory(AlarmConstants::EVENT_ALARM, $this->Translate('Entry delay expired without disarm'));
            $this->TransitionTo(AlarmConstants::STATE_ALARM);
        } else {
            $this->DispatchEvent(AlarmConstants::EVENT_COUNTDOWN, ['remainingSeconds' => $remaining]);
        }
    }

    public function PreAlarmExpire(): void
    {
        $this->StopTimer('PreAlarmTimer');
        // A pre-alarm does not necessarily escalate; return to the armed state.
        if ($this->GetValue('State') === AlarmConstants::STATE_PRE_ALARM) {
            $this->TransitionTo(AlarmConstants::ModeToArmedState($this->GetMode()));
        }
    }

    public function EscalationTick(): void
    {
        if ($this->GetValue('State') === AlarmConstants::STATE_ALARM) {
            $this->DispatchEvent(AlarmConstants::EVENT_ALARM_UNACK, []);
            $this->AddHistory(AlarmConstants::EVENT_ALARM_UNACK, $this->Translate('Alarm still not acknowledged – escalating'));
        } else {
            $this->StopTimer('EscalationTimer');
        }
    }

    public function SirenExpire(): void
    {
        $this->StopTimer('SirenDurationTimer');
        $this->CancelActions(false, false, true);
    }

    public function BypassExpiryTick(): void
    {
        $this->ProcessBypassExpiry();
    }

    public function FrontendTick(): void
    {
        $this->RebuildFrontendInternal();
    }

    public function ActionQueueTick(): void
    {
        $this->ProcessActionQueue();
    }
    private function RegisterAllTimers(): void
    {
        $this->RegisterTimer('ExitDelayTimer', 0, 'Alarm_ExitDelayTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('EntryDelayTimer', 0, 'Alarm_EntryDelayTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('PreAlarmTimer', 0, 'Alarm_PreAlarmExpire($_IPS[\'TARGET\']);');
        $this->RegisterTimer('EscalationTimer', 0, 'Alarm_EscalationTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('SirenDurationTimer', 0, 'Alarm_SirenExpire($_IPS[\'TARGET\']);');
        $this->RegisterTimer('BypassExpiryTimer', 0, 'Alarm_BypassExpiryTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('FrontendTimer', 0, 'Alarm_FrontendTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('ActionQueueTimer', 0, 'Alarm_ActionQueueTick($_IPS[\'TARGET\']);');
    }

    private function StopTimer(string $name): void
    {
        $this->SetTimerInterval($name, 0);
    }

    private function RemainingDelay(): int
    {
        return max(0, $this->ReadAttributeInteger('DelayEndsAt') - time());
    }
}
