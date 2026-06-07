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
            // A lockout is transient (it expires on its own), so only log it –
            // do not raise a persistent trouble that would have to be cleared.
            $this->AddHistory(AlarmConstants::EVENT_PIN_LOCKED, sprintf($this->Translate('PIN locked for %d seconds'), $lockout));
            $this->DispatchEvent(AlarmConstants::EVENT_PIN_LOCKED, ['lockout' => $lockout]);
        }
        $this->RebuildFrontendInternal();
    }

    private function ResetAttempts(): void
    {
        $this->WriteAttributeInteger('FailedAttempts', 0);
        $this->WriteAttributeInteger('LockoutUntil', 0);
    }
}
