<?php

declare(strict_types=1);

/**
 * PIN handling: hashed storage, verification, failed-attempt counting and
 * lockout. The PIN is never stored in clear text or in a property.
 */
trait PinTrait
{
    private function SetPinInternal(string $pin): void
    {
        if ($pin === '') {
            $this->WriteAttributeString('PinHash', '');
            $this->ResetAttempts();
            return;
        }
        $this->WriteAttributeString('PinHash', password_hash($pin, PASSWORD_DEFAULT));
        $this->ResetAttempts();
    }

    private function HasPin(): bool
    {
        return $this->ReadAttributeString('PinHash') !== '';
    }

    private function VerifyPin(string $pin): bool
    {
        $hash = $this->ReadAttributeString('PinHash');
        if ($hash === '') {
            return false;
        }
        return password_verify($pin, $hash);
    }

    private function IsLockedOut(): bool
    {
        return time() < $this->ReadAttributeInteger('LockoutUntil');
    }

    /**
     * Checks a PIN for an action that requires one. Handles lockout, wrong PIN
     * counting and the resulting trouble/history entries.
     */
    private function CheckPinForAction(string $pin): bool
    {
        if (!$this->ReadPropertyBoolean('PinEnabled') || !$this->HasPin()) {
            // PIN feature off or no PIN configured -> allow (user decision).
            return true;
        }

        if ($this->IsLockedOut()) {
            $this->AddHistory(AlarmConstants::EVENT_PIN_LOCKED, $this->Translate('PIN entry locked'));
            $this->DispatchEvent(AlarmConstants::EVENT_PIN_LOCKED, []);
            return false;
        }

        if ($this->VerifyPin($pin)) {
            $this->ResetAttempts();
            return true;
        }

        $this->RegisterFailedAttempt();
        return false;
    }

    private function RegisterFailedAttempt(): void
    {
        $attempts = $this->ReadAttributeInteger('FailedAttempts') + 1;
        $this->WriteAttributeInteger('FailedAttempts', $attempts);
        $max = max(1, $this->ReadPropertyInteger('MaxFailedAttempts'));

        $this->AddHistory(AlarmConstants::EVENT_PIN_WRONG, sprintf($this->Translate('Wrong PIN (%d/%d)'), $attempts, $max));
        $this->DispatchEvent(AlarmConstants::EVENT_PIN_WRONG, ['attempts' => $attempts]);

        if ($attempts >= $max) {
            $lockout = max(1, $this->ReadPropertyInteger('LockoutSeconds'));
            $this->WriteAttributeInteger('LockoutUntil', time() + $lockout);
            $this->RaiseTrouble(sprintf($this->Translate('PIN locked for %d seconds'), $lockout));
            $this->DispatchEvent(AlarmConstants::EVENT_PIN_LOCKED, ['lockout' => $lockout]);
        }
        $this->RebuildFrontendInternal();
    }

    private function ResetAttempts(): void
    {
        $this->WriteAttributeInteger('FailedAttempts', 0);
        $this->WriteAttributeInteger('LockoutUntil', 0);
    }

    /**
     * Processes a value written by an external PIN pad into the configured input
     * variable. The value is either just the PIN (-> disarm) or a command with
     * the PIN, e.g. "ARM_AWAY:1234", "ARM_NIGHT:1234", "DISARM:1234",
     * "ACK:1234", "RESET:1234". The input variable is cleared afterwards.
     */
    private function HandlePinInput(string $raw): void
    {
        $raw = trim($raw);
        if ($raw === '') {
            return;
        }

        $command = 'DISARM';
        $pin = $raw;
        if (strpos($raw, ':') !== false) {
            [$prefix, $rest] = explode(':', $raw, 2);
            $prefix = strtoupper(trim($prefix));
            if (in_array($prefix, ['ARM_AWAY', 'ARM_NIGHT', 'DISARM', 'ACK', 'RESET'], true)) {
                $command = $prefix;
                $pin = trim($rest);
            }
        }

        switch ($command) {
            case 'ARM_AWAY':
                $this->ArmAway($pin);
                break;
            case 'ARM_NIGHT':
                $this->ArmNight($pin);
                break;
            case 'ACK':
                $this->Acknowledge($pin);
                break;
            case 'RESET':
                $this->Reset($pin);
                break;
            default:
                $this->Disarm($pin);
                break;
        }

        // Clear the input variable so the PIN is not left in clear text.
        $vid = $this->ReadPropertyInteger('PinInputVariableID');
        if ($this->ReadPropertyBoolean('ClearPinInput') && $vid > 0 && IPS_VariableExists($vid)) {
            @SetValue($vid, '');
        }
    }
}
