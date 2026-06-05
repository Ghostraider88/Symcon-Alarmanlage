<?php

declare(strict_types=1);

/**
 * Generic action engine (siren, light, …).
 *
 * Actions target a variable (switched via RequestAction on the target object)
 * or run a script. Execution supports a start delay and an on-duration via a
 * shared scheduling queue, plus cancellation on disarm/acknowledge.
 */
trait ActionTrait
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function GetActions(): array
    {
        $raw = json_decode($this->ReadPropertyString('Actions'), true);
        return is_array($raw) ? $raw : [];
    }

    private function FireActions(string $event, array $ctx): void
    {
        // No real outputs in test state.
        if ($this->GetValue('State') === AlarmConstants::STATE_TEST) {
            return;
        }

        foreach ($this->GetActions() as $action) {
            if (empty($action['Enabled']) || ($action['Event'] ?? '') !== $event) {
                continue;
            }
            if (!$this->PassesFilters($action, $ctx)) {
                continue;
            }
            $this->ScheduleAction($action);
        }
    }

    private function ScheduleAction(array $action): void
    {
        $delay = (int) ($action['Delay'] ?? 0);
        $startAt = time() + max(0, $delay);

        $queue = $this->GetActionQueue();
        $queue[] = [
            'kind'         => 'on',
            'at'           => $startAt,
            'targetType'   => (int) ($action['TargetType'] ?? AlarmConstants::TARGET_VARIABLE),
            'targetID'     => (int) ($action['TargetID'] ?? 0),
            'value'        => (string) ($action['ValueOn'] ?? '1'),
            'valueOff'     => (string) ($action['ValueOff'] ?? '0'),
            'duration'     => (int) ($action['Duration'] ?? 0),
            'cancelDisarm' => !empty($action['CancelOnDisarm']),
            'cancelAck'    => !empty($action['CancelOnAck']),
            'name'         => (string) ($action['Name'] ?? ''),
        ];
        $this->SaveActionQueue($queue);
        $this->ScheduleActionQueue();
    }

    private function ProcessActionQueue(): void
    {
        $now = time();
        $queue = $this->GetActionQueue();
        $active = $this->GetActiveActions();
        $rest = [];

        foreach ($queue as $op) {
            if ((int) $op['at'] > $now) {
                $rest[] = $op;
                continue;
            }
            if ($op['kind'] === 'on') {
                $this->ExecuteTarget((int) $op['targetType'], (int) $op['targetID'], (string) $op['value']);
                if ((int) $op['duration'] > 0) {
                    $rest[] = [
                        'kind'       => 'off',
                        'at'         => $now + (int) $op['duration'],
                        'targetType' => (int) $op['targetType'],
                        'targetID'   => (int) $op['targetID'],
                        'value'      => (string) $op['valueOff'],
                    ];
                }
                $active[] = [
                    'targetType'   => (int) $op['targetType'],
                    'targetID'     => (int) $op['targetID'],
                    'valueOff'     => (string) $op['valueOff'],
                    'cancelDisarm' => (bool) $op['cancelDisarm'],
                    'cancelAck'    => (bool) $op['cancelAck'],
                ];
            } else { // off
                $this->ExecuteTarget((int) $op['targetType'], (int) $op['targetID'], (string) $op['value']);
                $active = $this->RemoveActive($active, (int) $op['targetID']);
            }
        }

        $this->SaveActionQueue($rest);
        $this->SaveActiveActions($active);
        $this->ScheduleActionQueue();
    }

    /**
     * Turns off running actions. $onDisarm/$onAck restrict to the actions that
     * declared the respective cancel flag; $sirenAll turns all of them off.
     */
    private function CancelActions(bool $onDisarm, bool $onAck, bool $all = false): void
    {
        $active = $this->GetActiveActions();
        $keep = [];
        foreach ($active as $entry) {
            $cancel = $all
                || ($onDisarm && !empty($entry['cancelDisarm']))
                || ($onAck && !empty($entry['cancelAck']));
            if ($cancel) {
                $this->ExecuteTarget((int) $entry['targetType'], (int) $entry['targetID'], (string) $entry['valueOff']);
            } else {
                $keep[] = $entry;
            }
        }
        $this->SaveActiveActions($keep);

        // Drop queued "off" ops only when cancelling everything.
        if ($all) {
            $this->SaveActionQueue([]);
            $this->StopTimer('ActionQueueTimer');
        }
    }

    private function ExecuteTarget(int $targetType, int $targetID, string $value): void
    {
        if ($targetID <= 0) {
            return;
        }
        try {
            if ($targetType === AlarmConstants::TARGET_SCRIPT) {
                if (IPS_ScriptExists($targetID)) {
                    IPS_RunScriptEx($targetID, ['VALUE' => $value, 'SENDER' => 'AlarmCenter']);
                } else {
                    $this->RaiseTrouble(sprintf($this->Translate('Action script %d missing'), $targetID));
                }
                return;
            }
            // Variable target: switch via RequestAction on the target object.
            if (!IPS_VariableExists($targetID)) {
                $this->RaiseTrouble(sprintf($this->Translate('Action variable %d missing'), $targetID));
                return;
            }
            $typed = $this->CastValueForVariable($targetID, $value);
            if (HasAction($targetID)) {
                RequestAction($targetID, $typed);
            } else {
                SetValue($targetID, $typed);
            }
        } catch (Throwable $e) {
            $this->RaiseTrouble($this->Translate('Action failed') . ': ' . $e->getMessage());
        }
    }

    private function CastValueForVariable(int $variableID, string $value): mixed
    {
        $type = IPS_GetVariable($variableID)['VariableType'];
        return match ($type) {
            VARIABLETYPE_BOOLEAN => in_array(strtolower($value), ['1', 'true', 'on'], true),
            VARIABLETYPE_INTEGER => (int) $value,
            VARIABLETYPE_FLOAT   => (float) $value,
            default              => $value,
        };
    }

    // --- queue persistence (buffers) ---

    private function GetActionQueue(): array
    {
        $q = json_decode($this->GetBuffer('ActionQueue') ?: '[]', true);
        return is_array($q) ? $q : [];
    }

    private function SaveActionQueue(array $queue): void
    {
        $this->SetBuffer('ActionQueue', json_encode(array_values($queue)));
    }

    private function GetActiveActions(): array
    {
        $a = json_decode($this->GetBuffer('ActiveActionTimers') ?: '[]', true);
        return is_array($a) ? $a : [];
    }

    private function SaveActiveActions(array $active): void
    {
        $this->SetBuffer('ActiveActionTimers', json_encode(array_values($active)));
    }

    private function RemoveActive(array $active, int $targetID): array
    {
        return array_values(array_filter($active, static fn ($e) => (int) $e['targetID'] !== $targetID));
    }

    private function ScheduleActionQueue(): void
    {
        $next = 0;
        foreach ($this->GetActionQueue() as $op) {
            $at = (int) $op['at'];
            if ($next === 0 || $at < $next) {
                $next = $at;
            }
        }
        if ($next > 0) {
            $this->SetTimerInterval('ActionQueueTimer', max(1, $next - time()) * 1000);
        } else {
            $this->StopTimer('ActionQueueTimer');
        }
    }
}
