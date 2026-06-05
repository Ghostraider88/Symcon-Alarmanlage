<?php

declare(strict_types=1);

/**
 * Notification / escalation dispatch. Fully generic: a notification runs a
 * user script (receiving the rendered TEXT) or writes the text to a variable.
 */
trait NotificationTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function GetNotifications(): array
    {
        $raw = json_decode($this->ReadPropertyString('Notifications'), true);
        return is_array($raw) ? $raw : [];
    }

    private function FireNotifications(string $event, array $ctx): void
    {
        foreach ($this->GetNotifications() as $notification) {
            if (empty($notification['Enabled']) || ($notification['Event'] ?? '') !== $event) {
                continue;
            }
            if (!$this->PassesFilters($notification, $ctx)) {
                continue;
            }
            $text = $this->RenderTemplate((string) ($notification['Template'] ?? ''), $ctx);
            $this->DeliverText(
                (int) ($notification['TargetType'] ?? AlarmConstants::TARGET_SCRIPT),
                (int) ($notification['TargetID'] ?? 0),
                $text,
                ['EVENT' => $event],
                $this->Translate('Notification')
            );
        }
    }

    /**
     * Sends rendered text via a script or a variable. Never throws – failures
     * are turned into trouble entries.
     */
    private function DeliverText(int $targetType, int $targetID, string $text, array $extraParams, string $label): void
    {
        if ($targetID <= 0) {
            $this->RaiseTrouble(sprintf($this->Translate('%s has no target configured'), $label));
            return;
        }
        try {
            if ($targetType === AlarmConstants::TARGET_SCRIPT) {
                if (!IPS_ScriptExists($targetID)) {
                    $this->RaiseTrouble(sprintf($this->Translate('%s script %d missing'), $label, $targetID));
                    return;
                }
                IPS_RunScriptEx($targetID, array_merge(['TEXT' => $text, 'SENDER' => 'AlarmCenter'], $extraParams));
            } else {
                if (!IPS_VariableExists($targetID)) {
                    $this->RaiseTrouble(sprintf($this->Translate('%s variable %d missing'), $label, $targetID));
                    return;
                }
                SetValue($targetID, $text);
            }
        } catch (Throwable $e) {
            $this->RaiseTrouble($label . ' ' . $this->Translate('failed') . ': ' . $e->getMessage());
        }
    }
}
