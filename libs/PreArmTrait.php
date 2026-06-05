<?php

declare(strict_types=1);

/**
 * Pre-arm check executed before every arming. Returns OK / WARNING / BLOCKED
 * together with a human readable summary.
 */
trait PreArmTrait
{
    /**
     * @return array{result:int, summary:string, details:array<int,string>}
     */
    private function RunPreArmCheckInternal(int $targetMode): array
    {
        $result = AlarmConstants::PREARM_OK;
        $details = [];

        foreach ($this->GetSensors() as $index => $sensor) {
            if (empty($sensor['Enabled'])) {
                continue;
            }
            if (!$this->IsSensorActiveInMode($sensor, $targetMode)) {
                continue;
            }

            $name = (string) ($sensor['Name'] ?? ('#' . $index));
            $vid = (int) ($sensor['VariableID'] ?? 0);

            // Invalid variable -> configuration error -> block.
            if ($vid <= 0 || !IPS_VariableExists($vid)) {
                $details[] = sprintf($this->Translate('Invalid variable for sensor %s'), $name);
                $result = AlarmConstants::PREARM_BLOCKED;
                continue;
            }

            if ($this->IsSensorBypassed($index)) {
                $details[] = sprintf($this->Translate('Sensor %s is bypassed'), $name);
                $result = max($result, AlarmConstants::PREARM_WARNING);
                continue;
            }

            // Open/active sensor.
            $isOpen = self::EvaluateTrigger(
                GetValue($vid),
                (int) ($sensor['TriggerType'] ?? AlarmConstants::TRIGGER_BOOL_TRUE),
                (float) ($sensor['TriggerValue'] ?? 0)
            );
            if ($isOpen) {
                if (!empty($sensor['BlockArmIfActive'])) {
                    $details[] = sprintf($this->Translate('Sensor %s is open'), $name);
                    $result = AlarmConstants::PREARM_BLOCKED;
                } else {
                    $details[] = sprintf($this->Translate('Sensor %s is open (warning)'), $name);
                    $result = max($result, AlarmConstants::PREARM_WARNING);
                }
            }
        }

        if ($this->GetValue('TroubleActive')) {
            $details[] = $this->Translate('Active trouble present');
            $result = max($result, AlarmConstants::PREARM_WARNING);
        }

        return [
            'result'  => $result,
            'summary' => implode('; ', $details),
            'details' => $details,
        ];
    }

    private function PreArmResultName(int $result): string
    {
        return match ($result) {
            AlarmConstants::PREARM_BLOCKED => $this->Translate('BLOCKED'),
            AlarmConstants::PREARM_WARNING => $this->Translate('WARNING'),
            default                        => $this->Translate('OK'),
        };
    }
}
