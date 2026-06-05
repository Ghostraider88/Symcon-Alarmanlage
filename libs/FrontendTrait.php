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
            'icon'           => $this->GetStateIcon($state),
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
        // Palette aligned with the Central Info Screen dashboard.
        return match ($state) {
            AlarmConstants::STATE_DISARMED           => '#4caf50',
            AlarmConstants::STATE_ARMING_EXIT_DELAY  => '#ff9800',
            AlarmConstants::STATE_ARMED_NIGHT        => '#2196f3',
            AlarmConstants::STATE_ARMED_AWAY         => '#2196f3',
            AlarmConstants::STATE_ENTRY_DELAY        => '#ff9800',
            AlarmConstants::STATE_PRE_ALARM          => '#ff9800',
            AlarmConstants::STATE_ALARM              => '#f44336',
            AlarmConstants::STATE_ALARM_ACKNOWLEDGED => '#ff9800',
            AlarmConstants::STATE_TROUBLE            => '#ff9800',
            AlarmConstants::STATE_TEST               => '#2196f3',
            default                                  => '#4caf50',
        };
    }

    private function GetStateIcon(int $state): string
    {
        // Font Awesome 6 icon classes.
        return match ($state) {
            AlarmConstants::STATE_DISARMED           => 'fa-shield-halved',
            AlarmConstants::STATE_ARMING_EXIT_DELAY  => 'fa-hourglass-half',
            AlarmConstants::STATE_ARMED_NIGHT        => 'fa-moon',
            AlarmConstants::STATE_ARMED_AWAY         => 'fa-lock',
            AlarmConstants::STATE_ENTRY_DELAY        => 'fa-hourglass-half',
            AlarmConstants::STATE_PRE_ALARM          => 'fa-triangle-exclamation',
            AlarmConstants::STATE_ALARM              => 'fa-bell',
            AlarmConstants::STATE_ALARM_ACKNOWLEDGED => 'fa-bell-slash',
            AlarmConstants::STATE_TROUBLE            => 'fa-triangle-exclamation',
            AlarmConstants::STATE_TEST               => 'fa-flask',
            default                                  => 'fa-shield-halved',
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
        $muted = 'color:var(--text-muted,#999)';
        $line = static fn (string $k, string $v): string => $v === '' ? '' :
            '<div style="font-size:12px;line-height:1.6"><span style="' . $muted . '">'
            . htmlspecialchars($k) . ':</span> ' . htmlspecialchars($v) . '</div>';

        // Neutral card with a colored left status border, matching the dashboard.
        $html = '<div style="font-family:\'Poppins\',-apple-system,Segoe UI,Roboto,sans-serif;'
            . 'background:var(--card-color,#fff);color:var(--content-color,#2b2b2b);'
            . 'border:1px solid var(--accent-color,#1abc9c);border-left:3px solid ' . $s['color'] . ';'
            . 'border-radius:6px;padding:10px 12px">';
        $html .= '<div style="' . $muted . ';font-size:12px">' . htmlspecialchars((string) $s['name']) . '</div>';
        $html .= '<div style="font-size:17px;font-weight:600;color:' . $s['color'] . '">' . htmlspecialchars((string) $s['stateName']) . '</div>';
        $html .= $line($this->Translate('Mode'), (string) $s['modeName']);
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
