<?php

declare(strict_types=1);

/**
 * Camera action hooks.
 *
 * Motion detection and camera alarm signals belong in the Sensors list (link the
 * boolean alarm variable). This section handles what happens AFTER an alarm or
 * pre-alarm: a user script is called so you can trigger a snapshot, send a
 * Telegram message with an image, start a recording, etc.
 *
 * The script receives:
 *   CAMERA_NAME  – name configured here
 *   ZONE         – zone this camera is assigned to
 *   EVENT        – 'pre_alarm', 'alarm', or 'test'
 *   SENDER       – always 'AlarmCenter'
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

            // Zone filter: empty = applies to all zones.
            $zone = (string) ($camera['Zone'] ?? '');
            if ($zone !== '' && isset($ctx['zone']) && $ctx['zone'] !== '' && $ctx['zone'] !== $zone) {
                continue;
            }

            if ($isTest && $event !== AlarmConstants::EVENT_TEST) {
                continue;
            }

            $scriptID = (int) ($camera['Script'] ?? 0);
            if ($scriptID <= 0) {
                continue;
            }
            if (!IPS_ScriptExists($scriptID)) {
                $this->RaiseTrouble(sprintf($this->Translate('%s script %d missing'), $this->Translate('Camera'), $scriptID));
                continue;
            }
            try {
                IPS_RunScriptEx($scriptID, [
                    'CAMERA_NAME' => (string) ($camera['Name'] ?? ''),
                    'ZONE'        => $zone,
                    'EVENT'       => $event,
                    'SENDER'      => 'AlarmCenter',
                ]);
            } catch (Throwable $e) {
                $this->RaiseTrouble($this->Translate('Camera') . ' ' . $this->Translate('failed') . ': ' . $e->getMessage());
            }
        }
    }
}
