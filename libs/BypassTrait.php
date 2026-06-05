<?php

declare(strict_types=1);

/**
 * Bypass handling: a sensor can be temporarily or permanently ignored.
 *
 * The bypass state is persisted as JSON in an attribute, keyed by the sensor's
 * index in the configured sensor list: { "<index>": { "until": <ts|0> } }.
 */
trait BypassTrait
{
    private function GetBypassState(): array
    {
        $state = json_decode($this->ReadAttributeString('BypassState'), true);
        return is_array($state) ? $state : [];
    }

    private function SaveBypassState(array $state): void
    {
        $this->WriteAttributeString('BypassState', json_encode($state));
    }

    private function IsSensorBypassed(int $index): bool
    {
        $state = $this->GetBypassState();
        if (!isset($state[$index])) {
            return false;
        }
        $until = (int) ($state[$index]['until'] ?? 0);
        if ($until !== 0 && time() >= $until) {
            // Expired -> clean up.
            unset($state[$index]);
            $this->SaveBypassState($state);
            return false;
        }
        return true;
    }

    /**
     * Resolves a sensor reference (variable id, or list index) to list indices.
     *
     * @return int[]
     */
    private function ResolveSensorIndices(int $sensorRef): array
    {
        $indices = [];
        foreach ($this->GetSensors() as $index => $sensor) {
            if ((int) ($sensor['VariableID'] ?? 0) === $sensorRef) {
                $indices[] = $index;
            }
        }
        if ($indices === [] && isset($this->GetSensors()[$sensorRef])) {
            $indices[] = $sensorRef;
        }
        return $indices;
    }

    private function BypassSensorInternal(int $sensorRef, int $durationMinutes): bool
    {
        $indices = $this->ResolveSensorIndices($sensorRef);
        if ($indices === []) {
            return false;
        }
        $state = $this->GetBypassState();
        foreach ($indices as $index) {
            $sensor = $this->GetSensors()[$index];
            if (empty($sensor['BypassAllowed'])) {
                continue;
            }
            $state[$index] = ['until' => $durationMinutes > 0 ? time() + $durationMinutes * 60 : 0];
            $this->AddHistory(AlarmConstants::EVENT_BYPASS_ON, sprintf($this->Translate('Bypass enabled for %s'), (string) ($sensor['Name'] ?? '')));
        }
        $this->SaveBypassState($state);
        $this->ScheduleBypassExpiry();
        $this->SetValue('BypassActive', $this->GetBypassSummary() !== '');
        $this->DispatchEvent(AlarmConstants::EVENT_BYPASS_ON, []);
        $this->RebuildFrontendInternal();
        return true;
    }

    private function UnbypassSensorInternal(int $sensorRef): bool
    {
        $indices = $this->ResolveSensorIndices($sensorRef);
        $state = $this->GetBypassState();
        foreach ($indices as $index) {
            if (isset($state[$index])) {
                unset($state[$index]);
                $sensor = $this->GetSensors()[$index] ?? [];
                $this->AddHistory(AlarmConstants::EVENT_BYPASS_OFF, sprintf($this->Translate('Bypass disabled for %s'), (string) ($sensor['Name'] ?? '')));
            }
        }
        $this->SaveBypassState($state);
        $this->ScheduleBypassExpiry();
        $this->SetValue('BypassActive', $this->GetBypassSummary() !== '');
        $this->DispatchEvent(AlarmConstants::EVENT_BYPASS_OFF, []);
        $this->RebuildFrontendInternal();
        return true;
    }

    private function ProcessBypassExpiry(): void
    {
        $state = $this->GetBypassState();
        $changed = false;
        foreach ($state as $index => $entry) {
            $until = (int) ($entry['until'] ?? 0);
            if ($until !== 0 && time() >= $until) {
                unset($state[$index]);
                $changed = true;
                $sensor = $this->GetSensors()[$index] ?? [];
                $this->AddHistory(AlarmConstants::EVENT_BYPASS_OFF, sprintf($this->Translate('Bypass expired for %s'), (string) ($sensor['Name'] ?? '')));
            }
        }
        if ($changed) {
            $this->SaveBypassState($state);
            $this->SetValue('BypassActive', $this->GetBypassSummary() !== '');
            $this->RebuildFrontendInternal();
        }
        $this->ScheduleBypassExpiry();
    }

    /**
     * Sets the next bypass expiry timer to the closest upcoming expiry.
     */
    private function ScheduleBypassExpiry(): void
    {
        $next = 0;
        foreach ($this->GetBypassState() as $entry) {
            $until = (int) ($entry['until'] ?? 0);
            if ($until > 0 && ($next === 0 || $until < $next)) {
                $next = $until;
            }
        }
        if ($next > 0) {
            $this->SetTimerInterval('BypassExpiryTimer', max(1, $next - time()) * 1000);
        } else {
            $this->StopTimer('BypassExpiryTimer');
        }
    }

    private function GetBypassSummary(): string
    {
        $names = [];
        $sensors = $this->GetSensors();
        foreach ($this->GetBypassState() as $index => $entry) {
            if ($this->IsSensorBypassed((int) $index)) {
                $names[] = (string) ($sensors[$index]['Name'] ?? ('#' . $index));
            }
        }
        return implode(', ', $names);
    }
}
