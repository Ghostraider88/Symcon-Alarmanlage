<?php

declare(strict_types=1);

/**
 * Voice announcement dispatch (Alexa / Echo Remote prepared generically).
 *
 * The module never hard-requires Echo Remote: an announcement runs a user
 * script (receiving TEXT + KIND) or writes the text to a variable. If nothing
 * is configured the announcement is skipped and a trouble is logged.
 */
trait AlexaTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function GetVoiceAnnouncements(): array
    {
        $raw = json_decode($this->ReadPropertyString('VoiceAnnouncements'), true);
        return is_array($raw) ? $raw : [];
    }

    private function FireAlexa(string $event, array $ctx): void
    {
        foreach ($this->GetVoiceAnnouncements() as $voice) {
            if (empty($voice['Enabled']) || ($voice['Event'] ?? '') !== $event) {
                continue;
            }
            if (!$this->PassesFilters($voice, $ctx)) {
                continue;
            }
            $kind = (int) ($voice['Kind'] ?? AlarmConstants::VOICE_TTS);
            $text = $this->RenderTemplate((string) ($voice['Template'] ?? ''), $ctx);
            $this->DeliverText(
                (int) ($voice['TargetType'] ?? AlarmConstants::TARGET_SCRIPT),
                (int) ($voice['TargetID'] ?? 0),
                $text,
                ['KIND' => $this->VoiceKindName($kind), 'EVENT' => $event],
                $this->Translate('Voice announcement')
            );
        }
    }

    private function VoiceKindName(int $kind): string
    {
        return match ($kind) {
            AlarmConstants::VOICE_ANNOUNCEMENT => 'Announcement',
            AlarmConstants::VOICE_SSML         => 'SSML',
            default                            => 'TTS',
        };
    }
}
