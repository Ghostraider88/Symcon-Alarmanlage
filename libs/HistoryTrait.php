<?php

declare(strict_types=1);

/**
 * History log stored as a bounded JSON array in an attribute (survives restart).
 */
trait HistoryTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function GetHistory(): array
    {
        $raw = json_decode($this->ReadAttributeString('History'), true);
        return is_array($raw) ? $raw : [];
    }

    private function AddHistory(string $type, string $text): void
    {
        $history = $this->GetHistory();
        array_unshift($history, [
            'ts'    => time(),
            'time'  => date('Y-m-d H:i:s'),
            'type'  => $type,
            'mode'  => $this->GetModeName($this->GetValue('Mode')),
            'state' => $this->GetStateName($this->GetValue('State')),
            'text'  => $text,
        ]);

        $max = max(1, $this->ReadPropertyInteger('HistoryMaxEntries'));
        if (count($history) > $max) {
            $history = array_slice($history, 0, $max);
        }
        $this->WriteAttributeString('History', json_encode($history));
        $this->SendDebug('History', $type . ': ' . $text, 0);
    }

    private function ClearHistoryInternal(): void
    {
        $this->WriteAttributeString('History', '[]');
        $this->RebuildFrontendInternal();
    }

    private function RenderHistoryHTML(): string
    {
        $rows = '';
        foreach ($this->GetHistory() as $entry) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                htmlspecialchars((string) $entry['time']),
                htmlspecialchars((string) $entry['type']),
                htmlspecialchars((string) $entry['text'])
            );
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="3">' . htmlspecialchars($this->Translate('No history')) . '</td></tr>';
        }
        return '<table class="alarm-history"><thead><tr><th>'
            . htmlspecialchars($this->Translate('Time')) . '</th><th>'
            . htmlspecialchars($this->Translate('Event')) . '</th><th>'
            . htmlspecialchars($this->Translate('Details')) . '</th></tr></thead><tbody>'
            . $rows . '</tbody></table>';
    }
}
