<?php

declare(strict_types=1);

/**
 * Notification dispatch: Pushover, SMTP/Email, Telegram, Variable, Script.
 *
 * Each notification entry selects a Symcon module instance (or variable/script)
 * as the delivery target. The message text is rendered from a configurable
 * template with alarm context placeholders.
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
            $subject = (string) ($notification['Subject'] ?? $this->Translate('Alarm'));
            $this->DeliverNotification(
                (int) ($notification['NotifyType'] ?? AlarmConstants::NOTIFY_SCRIPT),
                (int) ($notification['TargetID'] ?? 0),
                (string) ($notification['Parameter'] ?? ''),
                $subject,
                $text,
                $event
            );
        }
    }

    /**
     * Dispatches a rendered notification to the configured delivery channel.
     *
     * Pushover   – calls PushOver_SendNotification on the selected instance.
     * SMTP/Email – calls SMTP_SendMail on the selected instance.
     * Telegram   – calls TelegramBot_SendMessage or Telegram_SendMessage;
     *              Parameter holds the chat ID.
     * Variable   – writes text to a string variable; uses RequestAction when
     *              the variable has an action handler (e.g. Echo Remote TTS).
     * Script     – calls IPS_RunScriptEx with TEXT, SUBJECT, EVENT, SENDER.
     */
    private function DeliverNotification(int $type, int $targetID, string $parameter, string $subject, string $text, string $event): void
    {
        if ($targetID <= 0) {
            $this->RaiseTrouble($this->Translate('Notification') . ': ' . $this->Translate('no target configured'));
            return;
        }
        try {
            switch ($type) {
                case AlarmConstants::NOTIFY_PUSHOVER:
                    if (!IPS_InstanceExists($targetID)) {
                        $this->RaiseTrouble(sprintf($this->Translate('Notification: Pushover instance %d not found'), $targetID));
                        return;
                    }
                    if (function_exists('PushOver_SendNotification')) {
                        PushOver_SendNotification($targetID, $subject, $text, '', '', '', 0, '');
                    } elseif (function_exists('PushOver_SendPush')) {
                        PushOver_SendPush($targetID, $subject, $text);
                    } else {
                        $this->RaiseTrouble($this->Translate('Notification: Pushover module function not found. Check module installation.'));
                    }
                    break;

                case AlarmConstants::NOTIFY_SMTP:
                    if (!IPS_InstanceExists($targetID)) {
                        $this->RaiseTrouble(sprintf($this->Translate('Notification: SMTP instance %d not found'), $targetID));
                        return;
                    }
                    if (function_exists('SMTP_SendMail')) {
                        SMTP_SendMail($targetID, $subject, $text);
                    } elseif (function_exists('SMTP_SendMailEx')) {
                        SMTP_SendMailEx($targetID, '', $parameter, $subject, $text);
                    } else {
                        $this->RaiseTrouble($this->Translate('Notification: SMTP module function not found. Check module installation.'));
                    }
                    break;

                case AlarmConstants::NOTIFY_TELEGRAM:
                    if (!IPS_InstanceExists($targetID)) {
                        $this->RaiseTrouble(sprintf($this->Translate('Notification: Telegram instance %d not found'), $targetID));
                        return;
                    }
                    if (function_exists('TelegramBot_SendMessage')) {
                        TelegramBot_SendMessage($targetID, $parameter, $text);
                    } elseif (function_exists('Telegram_SendMessage')) {
                        Telegram_SendMessage($targetID, $parameter, $text);
                    } elseif (function_exists('Telegram_SendText')) {
                        Telegram_SendText($targetID, $text);
                    } else {
                        $this->RaiseTrouble($this->Translate('Notification: Telegram module function not found. Check module installation and enter function name in docs.'));
                    }
                    break;

                case AlarmConstants::NOTIFY_VARIABLE:
                    if (!IPS_VariableExists($targetID)) {
                        $this->RaiseTrouble(sprintf($this->Translate('%s variable %d missing'), $this->Translate('Notification'), $targetID));
                        return;
                    }
                    $this->DeliverTextToVariable($targetID, $text);
                    break;

                case AlarmConstants::NOTIFY_SCRIPT:
                default:
                    if (!IPS_ScriptExists($targetID)) {
                        $this->RaiseTrouble(sprintf($this->Translate('%s script %d missing'), $this->Translate('Notification'), $targetID));
                        return;
                    }
                    IPS_RunScriptEx($targetID, [
                        'TEXT'    => $text,
                        'SUBJECT' => $subject,
                        'EVENT'   => $event,
                        'SENDER'  => 'AlarmCenter',
                    ]);
                    break;
            }
        } catch (Throwable $e) {
            $this->RaiseTrouble($this->Translate('Notification') . ' ' . $this->Translate('failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * Delivers text to a string variable. Uses RequestAction when the variable
     * has an action handler (e.g. Echo Remote TTS variable) so the module's
     * handler fires even if the value has not changed. Falls back to a
     * clear-then-set pattern so VM_UPDATE always fires.
     */
    private function DeliverTextToVariable(int $targetID, string $text): void
    {
        if (HasAction($targetID)) {
            RequestAction($targetID, $text);
        } else {
            @SetValue($targetID, '');
            SetValue($targetID, $text);
        }
    }

    /**
     * Generic text delivery used by AlexaTrait (variable or script target).
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
                $this->DeliverTextToVariable($targetID, $text);
            }
        } catch (Throwable $e) {
            $this->RaiseTrouble($label . ' ' . $this->Translate('failed') . ': ' . $e->getMessage());
        }
    }
}
