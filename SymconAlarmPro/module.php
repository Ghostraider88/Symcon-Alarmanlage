<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/AlarmConstants.php';
require_once __DIR__ . '/../libs/ProfileTrait.php';
require_once __DIR__ . '/../libs/StateMachineTrait.php';
require_once __DIR__ . '/../libs/SensorTrait.php';
require_once __DIR__ . '/../libs/TimerTrait.php';
require_once __DIR__ . '/../libs/PinTrait.php';
require_once __DIR__ . '/../libs/BypassTrait.php';
require_once __DIR__ . '/../libs/PreArmTrait.php';
require_once __DIR__ . '/../libs/ActionTrait.php';
require_once __DIR__ . '/../libs/NotificationTrait.php';
require_once __DIR__ . '/../libs/AlexaTrait.php';
require_once __DIR__ . '/../libs/CameraTrait.php';
require_once __DIR__ . '/../libs/FrontendTrait.php';
require_once __DIR__ . '/../libs/HistoryTrait.php';

/**
 * SymconAlarmPro – flexible, state-machine based alarm center.
 *
 * All sensors, zones, actions, notifications, voice announcements and cameras
 * are configured by the user; the module contains no hard coded IDs or devices.
 * Exported functions use the "Alarm" prefix (e.g. Alarm_ArmAway($id)).
 */
class SymconAlarmPro extends IPSModuleStrict
{
    use ProfileTrait;
    use StateMachineTrait;
    use SensorTrait;
    use TimerTrait;
    use PinTrait;
    use BypassTrait;
    use PreArmTrait;
    use ActionTrait;
    use NotificationTrait;
    use AlexaTrait;
    use CameraTrait;
    use FrontendTrait;
    use HistoryTrait;

    public function Create(): void
    {
        parent::Create();

        $this->SetVisualizationType(1);

        // --- General ---
        $this->RegisterPropertyBoolean('Active', true);
        $this->RegisterPropertyString('AlarmName', 'Alarm');
        $this->RegisterPropertyString('RestartBehavior', AlarmConstants::RESTART_DISARMED);
        $this->RegisterPropertyBoolean('Debug', false);

        // --- Modes & delays ---
        $this->RegisterPropertyInteger('ExitDelayNight', 0);
        $this->RegisterPropertyInteger('ExitDelayAway', 60);
        $this->RegisterPropertyInteger('ExitDelayTest', 10);
        $this->RegisterPropertyBoolean('PreAlarmEnabled', false);
        $this->RegisterPropertyInteger('PreAlarmDuration', 30);
        $this->RegisterPropertyBoolean('EscalationEnabled', false);
        $this->RegisterPropertyInteger('EscalationDelay', 60);
        $this->RegisterPropertyBoolean('SirenDurationEnabled', true);
        $this->RegisterPropertyInteger('SirenDuration', 180);
        $this->RegisterPropertyInteger('FrontendRefreshInterval', 0);

        // --- PIN ---
        $this->RegisterPropertyBoolean('PinEnabled', false);
        $this->RegisterPropertyInteger('MaxFailedAttempts', 3);
        $this->RegisterPropertyInteger('LockoutSeconds', 300);
        $this->RegisterPropertyBoolean('RequirePinForArm', false);
        $this->RegisterPropertyBoolean('RequirePinForDisarm', true);
        $this->RegisterPropertyBoolean('RequirePinForBypass', false);
        // External PIN pad: a string variable the keypad writes the entered PIN into.
        $this->RegisterPropertyInteger('PinInputVariableID', 0);
        $this->RegisterPropertyBoolean('ClearPinInput', true);

        // --- History ---
        $this->RegisterPropertyInteger('HistoryMaxEntries', 50);

        // --- Configuration lists (JSON) ---
        $this->RegisterPropertyString('Zones', '[]');
        $this->RegisterPropertyString('Sensors', '[]');
        $this->RegisterPropertyString('Actions', '[]');
        $this->RegisterPropertyString('Notifications', '[]');
        $this->RegisterPropertyString('VoiceAnnouncements', '[]');
        $this->RegisterPropertyString('Cameras', '[]');

        // --- Attributes (persistent runtime state) ---
        $this->RegisterAttributeString('PinHash', '');
        $this->RegisterAttributeInteger('FailedAttempts', 0);
        $this->RegisterAttributeInteger('LockoutUntil', 0);
        $this->RegisterAttributeString('CurrentState', (string) AlarmConstants::STATE_DISARMED);
        $this->RegisterAttributeInteger('CurrentMode', AlarmConstants::MODE_DISARMED);
        $this->RegisterAttributeString('BypassState', '{}');
        $this->RegisterAttributeString('History', '[]');
        $this->RegisterAttributeInteger('StateEnteredAt', 0);
        $this->RegisterAttributeInteger('DelayEndsAt', 0);
        $this->RegisterAttributeInteger('LastTriggerSensorID', 0);

        $this->RegisterAllTimers();
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->CreateProfiles();
        $this->RegisterAllVariables();

        $this->RebuildSensorWatch();
        $this->ScheduleBypassExpiry();

        $interval = $this->ReadPropertyInteger('FrontendRefreshInterval');
        $this->SetTimerInterval('FrontendTimer', $interval > 0 ? $interval * 1000 : 0);

        $this->UpdateDerivedVariables();
        $this->UpdateModuleStatus();
        $this->RebuildFrontendInternal();
    }

    public function Destroy(): void
    {
        $this->DeleteProfiles();
        parent::Destroy();
    }

    public function MessageSink(int $timestamp, int $senderID, int $message, array $data): void
    {
        switch ($message) {
            case VM_UPDATE:
                if ($senderID === $this->ReadPropertyInteger('PinInputVariableID') && $senderID > 0) {
                    $this->HandlePinInput((string) $data[0]);
                } else {
                    $this->HandleSensorEventInternal($senderID, $data[0]);
                }
                break;
            case IPS_KERNELSTARTED:
                $this->ApplyRestartBehavior();
                break;
        }
    }

    /**
     * Builds the configuration form dynamically so every zone selector offers
     * exactly the zones configured in the Zones list (instead of free text).
     */
    public function GetConfigurationForm(): string
    {
        $form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true);
        if (!is_array($form)) {
            return '{}';
        }

        $options = [['caption' => '-', 'value' => '']];
        $zones = json_decode($this->ReadPropertyString('Zones'), true);
        if (is_array($zones)) {
            foreach ($zones as $zone) {
                $name = (string) ($zone['Name'] ?? '');
                if ($name !== '') {
                    $options[] = ['caption' => $name, 'value' => $name];
                }
            }
        }

        if (isset($form['elements']) && is_array($form['elements'])) {
            $this->InjectZoneOptions($form['elements'], $options);
        }
        return json_encode($form);
    }

    public function RequestAction(string $ident, mixed $value): void
    {
        switch ($ident) {
            case 'Mode':
                $this->ChangeMode((int) $value);
                break;
            case 'ArmNight':
                $this->ChangeMode(AlarmConstants::MODE_NIGHT, (string) $value);
                break;
            case 'ArmAway':
                $this->ChangeMode(AlarmConstants::MODE_AWAY, (string) $value);
                break;
            case 'Disarm':
                $this->Disarm((string) $value);
                break;
            case 'Acknowledge':
                $this->Acknowledge((string) $value);
                break;
            case 'Reset':
                $this->Reset((string) $value);
                break;
            case 'Test':
                $this->ChangeMode(AlarmConstants::MODE_TEST, (string) $value);
                break;
            case 'Panic':
                $this->Panic();
                break;
            default:
                throw new Exception('Invalid Ident: ' . $ident);
        }
    }

    // =====================================================================
    //  Public API (exported as Alarm_*)
    // =====================================================================

    public function SetMode(int $mode): bool
    {
        return $this->ChangeMode($mode, '', true);
    }

    public function ArmNight(string $pin = ''): bool
    {
        return $this->ChangeMode(AlarmConstants::MODE_NIGHT, $pin);
    }

    public function ArmAway(string $pin = ''): bool
    {
        return $this->ChangeMode(AlarmConstants::MODE_AWAY, $pin);
    }

    public function Disarm(string $pin = ''): bool
    {
        if ($this->ReadPropertyBoolean('RequirePinForDisarm') && !$this->CheckPinForAction($pin)) {
            return false;
        }
        return $this->DisarmInternal(true);
    }

    public function Acknowledge(string $pin = ''): bool
    {
        if ($this->ReadPropertyBoolean('RequirePinForDisarm') && !$this->CheckPinForAction($pin)) {
            return false;
        }
        $state = $this->GetValue('State');
        if ($state !== AlarmConstants::STATE_ALARM && $state !== AlarmConstants::STATE_PRE_ALARM) {
            return false;
        }
        $this->StopTimer('EscalationTimer');
        $this->CancelActions(false, true);
        $this->AddHistory(AlarmConstants::EVENT_ACKNOWLEDGED, $this->Translate('Alarm acknowledged'));
        $this->TransitionTo(AlarmConstants::STATE_ALARM_ACKNOWLEDGED);
        return true;
    }

    public function Reset(string $pin = ''): bool
    {
        if ($this->ReadPropertyBoolean('RequirePinForDisarm') && !$this->CheckPinForAction($pin)) {
            return false;
        }
        $this->StopTimer('EscalationTimer');
        $this->StopTimer('SirenDurationTimer');
        $this->CancelActions(false, false, true);
        $this->SetValue('IsAlarmActive', false);
        $this->SetValue('IsAcknowledged', false);
        $this->ClearTrouble();
        $this->AddHistory(AlarmConstants::EVENT_RESET, $this->Translate('Alarm reset'));

        $mode = $this->GetValue('Mode');
        $this->TransitionTo(AlarmConstants::ModeToArmedState($mode));
        return true;
    }

    public function Panic(): void
    {
        $this->AddHistory(AlarmConstants::EVENT_ALARM, $this->Translate('Panic alarm triggered'));
        $this->TransitionTo(AlarmConstants::STATE_ALARM, ['eventType' => 'panic']);
    }

    public function BypassSensor(int $sensorID, int $durationMinutes = 0, string $pin = ''): bool
    {
        if ($this->ReadPropertyBoolean('RequirePinForBypass') && !$this->CheckPinForAction($pin)) {
            return false;
        }
        return $this->BypassSensorInternal($sensorID, $durationMinutes);
    }

    public function UnbypassSensor(int $sensorID, string $pin = ''): bool
    {
        if ($this->ReadPropertyBoolean('RequirePinForBypass') && !$this->CheckPinForAction($pin)) {
            return false;
        }
        return $this->UnbypassSensorInternal($sensorID);
    }

    public function TestNotification(): void
    {
        $ctx = ['sensorName' => $this->Translate('Test'), 'zone' => '', 'eventType' => AlarmConstants::EVENT_TEST];
        $this->FireNotifications(AlarmConstants::EVENT_TEST, $ctx);
        $this->FireAlexa(AlarmConstants::EVENT_TEST, $ctx);
        $this->AddHistory(AlarmConstants::EVENT_TEST, $this->Translate('Test notification sent'));
        echo $this->Translate('Test notification sent');
    }

    public function RunPreArmCheck(int $targetMode): string
    {
        $result = $this->RunPreArmCheckInternal($targetMode);
        $text = $this->PreArmResultName($result['result']);
        if ($result['summary'] !== '') {
            $text .= ': ' . $result['summary'];
        }
        $this->AddHistory('prearm', $text);
        return $text;
    }

    public function HandleSensorEvent(int $variableID, mixed $value): void
    {
        $this->HandleSensorEventInternal($variableID, $value);
    }

    public function RebuildFrontend(): void
    {
        $this->RebuildFrontendInternal();
    }

    public function ClearHistory(): void
    {
        $this->ClearHistoryInternal();
    }

    public function SetPin(string $pin): void
    {
        $this->SetPinInternal($pin);
        echo $pin === '' ? $this->Translate('PIN cleared') : $this->Translate('PIN set');
    }

    /**
     * Time seam used by the SymconStubs test framework for timer scheduling.
     * Harmless in production (not called by the real kernel).
     */
    protected function getTime(): int
    {
        return time();
    }

    /**
     * Recursively replaces every "Zone"/"ZoneFilter" list column edit element
     * with a Select offering the configured zones.
     */
    private function InjectZoneOptions(array &$elements, array $options): void
    {
        foreach ($elements as &$element) {
            if (($element['type'] ?? '') === 'List' && isset($element['columns']) && is_array($element['columns'])) {
                foreach ($element['columns'] as &$column) {
                    if (in_array($column['name'] ?? '', ['Zone', 'ZoneFilter'], true)) {
                        $column['edit'] = ['type' => 'Select', 'options' => $options];
                    }
                }
                unset($column);
            }
            if (isset($element['items']) && is_array($element['items'])) {
                $this->InjectZoneOptions($element['items'], $options);
            }
        }
        unset($element);
    }

    // =====================================================================
    //  Internal orchestration helpers
    // =====================================================================

    /**
     * Central event dispatcher: fans an event out to all configured outputs.
     * Outputs are suppressed until the kernel is ready.
     */
    private function DispatchEvent(string $event, array $ctx): void
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $ctx['eventType'] = $event;
        $this->FireActions($event, $ctx);
        $this->FireNotifications($event, $ctx);
        $this->FireAlexa($event, $ctx);
        $this->FireCameras($event, $ctx);
    }

    /**
     * Mode/zone filter shared by actions, notifications and voice items.
     */
    private function PassesFilters(array $item, array $ctx): bool
    {
        $modeFilter = trim((string) ($item['ModeFilter'] ?? ''));
        if ($modeFilter !== '') {
            $modes = array_map('intval', array_filter(array_map('trim', explode(',', $modeFilter)), 'strlen'));
            if ($modes !== [] && !in_array($this->GetValue('Mode'), $modes, true)) {
                return false;
            }
        }
        $zoneFilter = trim((string) ($item['ZoneFilter'] ?? ''));
        if ($zoneFilter !== '') {
            $zone = (string) ($ctx['zone'] ?? '');
            if ($zone !== '' && strcasecmp($zone, $zoneFilter) !== 0) {
                return false;
            }
        }
        return true;
    }

    private function RaiseTrouble(string $text): void
    {
        $this->SetValue('TroubleActive', true);
        $existing = (string) $this->GetValue('TroubleSummary');
        $this->SetValue('TroubleSummary', trim($existing . "\n" . $text));
        $this->AddHistory(AlarmConstants::EVENT_TROUBLE, $text);
        $this->DispatchEvent(AlarmConstants::EVENT_TROUBLE, ['eventType' => AlarmConstants::EVENT_TROUBLE]);
        $this->UpdateModuleStatus();
    }

    private function ClearTrouble(): void
    {
        $this->SetValue('TroubleActive', false);
        $this->SetValue('TroubleSummary', '');
        $this->UpdateModuleStatus();
    }

    private function RegisterAllVariables(): void
    {
        $pos = 0;
        if ($this->RegisterVariableInteger('Mode', $this->Translate('Mode'), $this->ModeProfile(), $pos++)) {
            $this->SetValue('Mode', AlarmConstants::MODE_DISARMED);
        }
        $this->MaintainAction('Mode', true);

        if ($this->RegisterVariableInteger('State', $this->Translate('State'), $this->StateProfile(), $pos++)) {
            $this->SetValue('State', AlarmConstants::STATE_DISARMED);
        }
        $this->RegisterVariableBoolean('IsArmed', $this->Translate('Armed'), '~Switch', $pos++);
        $this->RegisterVariableBoolean('IsAlarmActive', $this->Translate('Alarm active'), '~Alert', $pos++);
        $this->RegisterVariableBoolean('IsAcknowledged', $this->Translate('Acknowledged'), '', $pos++);
        $this->RegisterVariableString('LastEvent', $this->Translate('Last event'), '', $pos++);
        $this->RegisterVariableInteger('LastTriggerSensorID', $this->Translate('Last trigger sensor ID'), '', $pos++);
        $this->RegisterVariableString('LastTriggerSensorName', $this->Translate('Last trigger sensor'), '', $pos++);
        $this->RegisterVariableString('LastTriggerZone', $this->Translate('Last trigger zone'), '', $pos++);
        $this->RegisterVariableInteger('ExitDelayRemaining', $this->Translate('Exit delay remaining'), $this->SecondsProfile(), $pos++);
        $this->RegisterVariableInteger('EntryDelayRemaining', $this->Translate('Entry delay remaining'), $this->SecondsProfile(), $pos++);
        $this->RegisterVariableBoolean('TroubleActive', $this->Translate('Trouble active'), '~Alert', $pos++);
        $this->RegisterVariableString('TroubleSummary', $this->Translate('Trouble summary'), '', $pos++);
        $this->RegisterVariableBoolean('BypassActive', $this->Translate('Bypass active'), '', $pos++);
        $this->RegisterVariableString('OpenSensorsSummary', $this->Translate('Open sensors'), '', $pos++);
        $this->RegisterVariableString('FrontendHTML', $this->Translate('Frontend'), '~HTMLBox', $pos++);
        $this->RegisterVariableString('HistoryHTML', $this->Translate('History'), '~HTMLBox', $pos++);
    }

    private function UpdateModuleStatus(): void
    {
        if (!$this->ReadPropertyBoolean('Active')) {
            $this->SetStatus(104);
            return;
        }
        // Detect configuration errors (enabled sensors with invalid variables).
        foreach ($this->GetSensors() as $sensor) {
            if (!empty($sensor['Enabled'])) {
                $vid = (int) ($sensor['VariableID'] ?? 0);
                if ($vid <= 0 || !IPS_VariableExists($vid)) {
                    $this->SetStatus(201);
                    return;
                }
            }
        }
        if ($this->GetValue('TroubleActive')) {
            $this->SetStatus(202);
            return;
        }
        $this->SetStatus(102);
    }
}
