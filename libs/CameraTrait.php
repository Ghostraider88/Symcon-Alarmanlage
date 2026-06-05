<?php

declare(strict_types=1);

/**
 * Camera action hooks. Generic: runs a snapshot and/or alarm script (receiving
 * ZONE, URL and event context) – no native Blue Iris or vendor dependency.
 */
trait CameraTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function GetCameras(): array
    {
        $raw = json_decode($this->ReadPropertyString('Cameras'), true);
        return is_array($raw) ? $raw : [];
    }

    private function FireCameras(string $event, array $ctx): void
    {
        // No real camera output in test state.
        $isTest = $this->GetValue('State') === AlarmConstants::STATE_TEST;

        foreach ($this->GetCameras() as $camera) {
            if (empty($camera['Enabled'])) {
                continue;
            }
            $applies = match ($event) {
                AlarmConstants::EVENT_PRE_ALARM => !empty($camera['OnPreAlarm']),
                AlarmConstants::EVENT_ALARM     => !empty($camera['OnAlarm']),
                AlarmConstants::EVENT_TEST      => !empty($camera['OnTest']),
                default                         => false,
            };
            if (!$applies) {
                continue;
            }
            // Zone filter: empty zone matches any.
            $zone = (string) ($camera['Zone'] ?? '');
            if ($zone !== '' && isset($ctx['zone']) && $ctx['zone'] !== '' && $ctx['zone'] !== $zone) {
                continue;
            }
            if ($isTest && $event !== AlarmConstants::EVENT_TEST) {
                continue;
            }

            $url = $this->RenderTemplate((string) ($camera['URLTemplate'] ?? ''), $ctx);
            $params = ['ZONE' => $zone, 'URL' => $url, 'EVENT' => $event, 'SENDER' => 'AlarmCenter'];

            $this->RunCameraScript((int) ($camera['SnapshotScript'] ?? 0), $params, $this->Translate('Camera snapshot'));
            if ($event === AlarmConstants::EVENT_ALARM) {
                $this->RunCameraScript((int) ($camera['AlarmScript'] ?? 0), $params, $this->Translate('Camera alarm'));
            }
        }
    }

    private function RunCameraScript(int $scriptID, array $params, string $label): void
    {
        if ($scriptID <= 0) {
            return;
        }
        if (!IPS_ScriptExists($scriptID)) {
            $this->RaiseTrouble(sprintf($this->Translate('%s script %d missing'), $label, $scriptID));
            return;
        }
        try {
            IPS_RunScriptEx($scriptID, $params);
        } catch (Throwable $e) {
            $this->RaiseTrouble($label . ' ' . $this->Translate('failed') . ': ' . $e->getMessage());
        }
    }
}
