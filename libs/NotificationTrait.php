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
            $notification['RenderedText'] = $this->RenderTemplate((string) ($notification['Template'] ?? ''), $ctx);
            $notification['RenderedSubject'] = $this->RenderTemplate((string) ($notification['Subject'] ?? $this->Translate('Alarm')), $ctx);
            $this->DeliverNotification($notification, $event);
        }
    }

    /**
     * Dispatches a rendered notification to the configured delivery channel.
     *
     * Pushover   – calls TUPO_SendMessageComplete on the selected instance,
     *              supporting priority, sound, HTML and (for emergency priority)
     *              retry/expire. Falls back to TUPO_SendMessage / PushOver_*.
     * SMTP/Email – calls SMTP_SendMail on the selected instance.
     * Telegram   – calls TelegramBot_SendMessage or Telegram_SendMessage;
     *              Parameter holds the chat ID.
     * Variable   – writes text to a string variable; uses RequestAction when
     *              the variable has an action handler (e.g. Echo Remote TTS).
     * Script     – calls IPS_RunScriptEx with TEXT, SUBJECT, EVENT, SENDER.
     *
     * @param array<string, mixed> $notification
     */
    private function DeliverNotification(array $notification, string $event): void
    {
        $type = (int) ($notification['NotifyType'] ?? AlarmConstants::NOTIFY_SCRIPT);
        $targetID = (int) ($notification['TargetID'] ?? 0);
        $parameter = (string) ($notification['Parameter'] ?? '');
        $subject = (string) ($notification['RenderedSubject'] ?? $this->Translate('Alarm'));
        $text = (string) ($notification['RenderedText'] ?? '');

        if ($targetID <= 0) {
            $this->RaiseTrouble($this->Translate('Notification') . ': ' . $this->Translate('no target configured'));
            return;
        }
        try {
            switch ($type) {
                case AlarmConstants::NOTIFY_PUSHOVER:
                    $this->DeliverPushover($targetID, $subject, $text, $notification);
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
     * Pushover delivery via the timo-u Symcon_Pushover module (TUPO_ prefix).
     *
     * Priority levels follow the Pushover API: -2 lowest, -1 low, 0 normal,
     * 1 high, 2 emergency. For emergency priority Pushover requires retry
     * (>= 30 s) and expire (<= 10800 s); the notification repeats until the
     * user acknowledges it.
     *
     * @param array<string, mixed> $notification
     */
    private function DeliverPushover(int $targetID, string $subject, string $text, array $notification): void
    {
        if (!IPS_InstanceExists($targetID)) {
            $this->RaiseTrouble(sprintf($this->Translate('Notification: Pushover instance %d not found'), $targetID));
            return;
        }

        $priority = (int) ($notification['Priority'] ?? 0);
        $priority = max(-2, min(2, $priority));
        $sound = (string) ($notification['Sound'] ?? '');
        $html = !empty($notification['HTML']) ? 1 : 0;
        // Emergency priority needs sane retry/expire bounds (Pushover limits).
        $retry = max(30, (int) ($notification['Retry'] ?? 60));
        $expire = min(10800, max($retry, (int) ($notification['Expire'] ?? 3600)));

        if (function_exists('TUPO_SendMessageComplete')) {
            // (id, title, text, url, urlTitle, priority, html, retry, expire, sound)
            TUPO_SendMessageComplete($targetID, $subject, $text, '', '', $priority, $html, $retry, $expire, $sound);
        } elseif (function_exists('TUPO_SendMessage')) {
            TUPO_SendMessage($targetID, $subject, $text, $priority);
        } elseif (function_exists('PushOver_SendNotification')) {
            PushOver_SendNotification($targetID, $subject, $text, $sound, '', '', $priority, '');
        } else {
            $this->RaiseTrouble($this->Translate('Notification: Pushover module function not found. Check module installation.'));
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
