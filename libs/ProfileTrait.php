<?php

declare(strict_types=1);

/**
 * Creation and cleanup of the instance-bound presentation profiles.
 */
trait ProfileTrait
{
    private function ModeProfile(): string
    {
        return 'Alarm.Mode.' . $this->InstanceID;
    }

    private function StateProfile(): string
    {
        return 'Alarm.State.' . $this->InstanceID;
    }

    private function SecondsProfile(): string
    {
        return 'Alarm.Seconds.' . $this->InstanceID;
    }

    private function CreateProfiles(): void
    {
        // Mode profile (switchable).
        $mode = $this->ModeProfile();
        if (!IPS_VariableProfileExists($mode)) {
            IPS_CreateVariableProfile($mode, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileAssociation($mode, AlarmConstants::MODE_DISARMED, $this->Translate('Disarmed'), 'LockOpen', 0x1F8A4C);
        IPS_SetVariableProfileAssociation($mode, AlarmConstants::MODE_NIGHT, $this->Translate('Night'), 'Moon', 0x1F6FB2);
        IPS_SetVariableProfileAssociation($mode, AlarmConstants::MODE_AWAY, $this->Translate('Away'), 'Lock', 0xD9A300);
        IPS_SetVariableProfileAssociation($mode, AlarmConstants::MODE_TEST, $this->Translate('Test'), 'Tools', 0x5A6B7A);

        // State profile (read only display).
        $state = $this->StateProfile();
        if (!IPS_VariableProfileExists($state)) {
            IPS_CreateVariableProfile($state, VARIABLETYPE_INTEGER);
        }
        $states = [
            [AlarmConstants::STATE_DISARMED, $this->Translate('Disarmed'), 'LockOpen', 0x1F8A4C],
            [AlarmConstants::STATE_ARMING_EXIT_DELAY, $this->Translate('Arming'), 'Clock', 0xD9A300],
            [AlarmConstants::STATE_ARMED_NIGHT, $this->Translate('Armed night'), 'Moon', 0x1F6FB2],
            [AlarmConstants::STATE_ARMED_AWAY, $this->Translate('Armed away'), 'Lock', 0x1F8A4C],
            [AlarmConstants::STATE_ENTRY_DELAY, $this->Translate('Entry delay'), 'Clock', 0xE07B00],
            [AlarmConstants::STATE_PRE_ALARM, $this->Translate('Pre-alarm'), 'Warning', 0xE07B00],
            [AlarmConstants::STATE_ALARM, $this->Translate('Alarm'), 'Alert', 0xC0291F],
            [AlarmConstants::STATE_ALARM_ACKNOWLEDGED, $this->Translate('Alarm acknowledged'), 'Ok', 0x9A3B9A],
            [AlarmConstants::STATE_TROUBLE, $this->Translate('Trouble'), 'Warning', 0x9A3B9A],
            [AlarmConstants::STATE_TEST, $this->Translate('Test'), 'Tools', 0x5A6B7A],
        ];
        foreach ($states as [$value, $name, $icon, $color]) {
            IPS_SetVariableProfileAssociation($state, $value, $name, $icon, $color);
        }

        // Seconds profile for countdowns.
        $seconds = $this->SecondsProfile();
        if (!IPS_VariableProfileExists($seconds)) {
            IPS_CreateVariableProfile($seconds, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileText($seconds, '', ' s');
        IPS_SetVariableProfileIcon($seconds, 'Clock');
    }

    private function DeleteProfiles(): void
    {
        foreach ([$this->ModeProfile(), $this->StateProfile(), $this->SecondsProfile()] as $profile) {
            if (IPS_VariableProfileExists($profile)) {
                IPS_DeleteVariableProfile($profile);
            }
        }
    }
}
