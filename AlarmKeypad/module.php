<?php

declare(strict_types=1);

class AlarmKeypad extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('AlarmInstanceID', 0);
        $this->RegisterPropertyBoolean('RequirePinForArm', false);

        $this->SetVisualizationType(1);
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $alarmID = $this->ReadPropertyInteger('AlarmInstanceID');
        if ($alarmID > 0) {
            $this->RegisterReference($alarmID);
        }

        if ($alarmID > 0 && IPS_InstanceExists($alarmID)) {
            $this->SetStatus(102);
        } else {
            $this->SetStatus(104);
        }
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        $pin = (string) $value;
        $alarmID = $this->ReadPropertyInteger('AlarmInstanceID');

        if ($alarmID <= 0 || !IPS_InstanceExists($alarmID)) {
            $this->PushFeedback($this->Translate('No alarm instance configured'), false, 3000);
            return;
        }

        $result = false;
        $msg    = '';

        switch ($ident) {
            case 'ArmNight':
                $result = Alarm_ArmNight($alarmID, $pin);
                $msg    = $result ? $this->Translate('Armed Night') : $this->Translate('Error');
                break;

            case 'ArmAway':
                $result = Alarm_ArmAway($alarmID, $pin);
                $msg    = $result ? $this->Translate('Armed') : $this->Translate('Error');
                break;

            case 'Disarm':
                $result = Alarm_Disarm($alarmID, $pin);
                $msg    = $result ? $this->Translate('Disarmed') : $this->Translate('Error');
                break;

            case 'Acknowledge':
                $result = Alarm_Acknowledge($alarmID, $pin);
                $msg    = $result ? $this->Translate('Acknowledged') : $this->Translate('Error');
                break;

            case 'Reset':
                $result = Alarm_Reset($alarmID, $pin);
                $msg    = $result ? $this->Translate('Reset') : $this->Translate('Error');
                break;

            default:
                $this->SendDebug('RequestAction', 'Unknown ident: ' . $ident, 0);
                return;
        }

        $this->PushFeedback($msg, $result, 2500);
    }

    public function GetVisualizationTile(): string
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    private function PushFeedback(string $text, bool $ok, int $ms): void
    {
        $payload = [
            'sendText' => $text,
            'timeOut'  => $ms,
            'ok'       => $ok,
        ];
        $this->UpdateVisualizationValue(json_encode($payload));
    }
}
