<?php

declare(strict_types=1);

/**
 * Frontend: shared template renderer, HTML-SDK tile visualization and the
 * optional classic HTMLBox status/history variables.
 */
trait FrontendTrait
{
    /**
     * HTML-SDK entry point: returns the tile markup with the initial state
     * injected. Called once when the tile is displayed.
     */
    public function GetVisualizationTile(): string
    {
        $html = @file_get_contents(__DIR__ . '/../SymconAlarmPro/module.html');
        if ($html === false) {
            return '<div>module.html missing</div>';
        }
        $initial = json_encode($this->BuildVisualizationState());
        return str_replace('/*INITIAL_STATE*/null', $initial, $html);
    }
    /**
     * Replaces {placeholders} in a template with values from the global state
     * and the given event context.
     */
    private function RenderTemplate(string $template, array $ctx = []): string
    {
        $mode = $this->GetValue('Mode');
        $state = $this->GetValue('State');
        $map = [
            '{mode}'             => $this->GetModeName($mode),
            '{state}'            => $this->GetStateName($state),
            '{zone}'             => (string) ($ctx['zone'] ?? $this->GetValue('LastTriggerZone')),
            '{sensorName}'       => (string) ($ctx['sensorName'] ?? $this->GetValue('LastTriggerSensorName')),
            '{sensorID}'         => (string) ($ctx['sensorID'] ?? $this->GetValue('LastTriggerSensorID')),
            '{remainingSeconds}' => (string) ($ctx['remainingSeconds'] ?? 0),
            '{timestamp}'        => date('Y-m-d H:i:s'),
            '{eventType}'        => (string) ($ctx['eventType'] ?? ''),
            '{alarmID}'          => (string) ($ctx['alarmID'] ?? ''),
        ];
        return strtr($template, $map);
    }

    /**
     * Builds the state object consumed by the HTML-SDK tile (module.html).
     */
    private function BuildVisualizationState(): array
    {
        $state = $this->GetValue('State');
        return [
            'mode'           => $this->GetValue('Mode'),
            'modeName'       => $this->GetModeName($this->GetValue('Mode')),
            'state'          => $state,
            'stateName'      => $this->GetStateName($state),
            'color'          => $this->GetStateColor($state),
            'isArmed'        => $this->GetValue('IsArmed'),
            'alarmActive'    => $this->GetValue('IsAlarmActive'),
            'acknowledged'   => $this->GetValue('IsAcknowledged'),
            'lastTrigger'    => $this->GetValue('LastTriggerSensorName'),
            'lastZone'       => $this->GetValue('LastTriggerZone'),
            'openSensors'    => $this->GetValue('OpenSensorsSummary'),
            'exitRemaining'  => $this->GetValue('ExitDelayRemaining'),
            'entryRemaining' => $this->GetValue('EntryDelayRemaining'),
            'trouble'        => $this->GetValue('TroubleActive'),
            'troubleText'    => $this->GetValue('TroubleSummary'),
            'bypass'         => $this->GetBypassSummary(),
            'pinRequired'    => $this->ReadPropertyBoolean('PinEnabled') && $state === AlarmConstants::STATE_ENTRY_DELAY,
            'history'        => array_slice($this->GetHistory(), 0, 20),
            'name'           => $this->ReadPropertyString('AlarmName'),
        ];
    }

    private function GetStateColor(int $state): string
    {
        return match ($state) {
            AlarmConstants::STATE_DISARMED           => '#3a3a3a',
            AlarmConstants::STATE_ARMING_EXIT_DELAY  => '#d9a300',
            AlarmConstants::STATE_ARMED_NIGHT        => '#1f6fb2',
            AlarmConstants::STATE_ARMED_AWAY         => '#1f8a4c',
            AlarmConstants::STATE_ENTRY_DELAY        => '#e07b00',
            AlarmConstants::STATE_PRE_ALARM          => '#e07b00',
            AlarmConstants::STATE_ALARM              => '#c0291f',
            AlarmConstants::STATE_ALARM_ACKNOWLEDGED => '#9a3b9a',
            AlarmConstants::STATE_TROUBLE            => '#9a3b9a',
            AlarmConstants::STATE_TEST               => '#5a6b7a',
            default                                  => '#3a3a3a',
        };
    }

    /**
     * Pushes the current state to the HTML-SDK tile (live update).
     */
    private function PushVisualization(): void
    {
        if (method_exists($this, 'UpdateVisualizationValue')) {
            $this->UpdateVisualizationValue(json_encode($this->BuildVisualizationState()));
        }
    }

    /**
     * Rebuilds the frontend: pushes to the tile and updates the optional
     * classic HTMLBox variables.
     */
    private function RebuildFrontendInternal(): void
    {
        $this->PushVisualization();

        if (@$this->GetIDForIdent('HistoryHTML')) {
            $this->SetValue('HistoryHTML', $this->RenderHistoryHTML());
        }
        if (@$this->GetIDForIdent('FrontendHTML')) {
            $this->SetValue('FrontendHTML', $this->RenderStatusHTML());
        }
    }

    private function RenderStatusHTML(): string
    {
        $s = $this->BuildVisualizationState();
        $line = static fn (string $k, string $v): string => $v === '' ? '' :
            '<div class="row"><span class="k">' . htmlspecialchars($k) . '</span><span class="v">' . htmlspecialchars($v) . '</span></div>';

        $html = '<div class="alarm-tile" style="border-left:6px solid ' . $s['color'] . ';padding:8px">';
        $html .= '<div class="title" style="font-weight:bold">' . htmlspecialchars((string) $s['name']) . '</div>';
        $html .= $line($this->Translate('Mode'), (string) $s['modeName']);
        $html .= $line($this->Translate('State'), (string) $s['stateName']);
        if ($s['exitRemaining'] > 0) {
            $html .= $line($this->Translate('Exit delay'), $s['exitRemaining'] . ' s');
        }
        if ($s['entryRemaining'] > 0) {
            $html .= $line($this->Translate('Entry delay'), $s['entryRemaining'] . ' s');
        }
        $html .= $line($this->Translate('Open sensors'), (string) $s['openSensors']);
        $html .= $line($this->Translate('Bypass'), (string) $s['bypass']);
        if ($s['trouble']) {
            $html .= $line($this->Translate('Trouble'), (string) $s['troubleText']);
        }
        if ($s['lastTrigger'] !== '') {
            $html .= $line($this->Translate('Last trigger'), (string) $s['lastTrigger']);
        }
        $html .= '</div>';
        return $html;
    }
}
