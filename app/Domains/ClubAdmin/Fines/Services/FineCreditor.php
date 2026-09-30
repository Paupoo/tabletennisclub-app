<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Services;

use App\Domains\Shared\Models\AppSetting;
use App\Domains\Shared\Support\IbanNormalizer;

/**
 * Who a fined member pays: the provincial committee, never the club.
 *
 * Its account is typed in once by the treasurer, from the committee's own
 * mail, and changes only when the committee changes bank. The contact is the
 * committee's treasurer, whom the member asks when they dispute a fine.
 */
class FineCreditor
{
    private const string KEY_CONTACT_EMAIL = 'fines.creditor.contact_email';

    private const string KEY_CONTACT_NAME = 'fines.creditor.contact_name';

    private const string KEY_CONTACT_PHONE = 'fines.creditor.contact_phone';

    private const string KEY_IBAN = 'fines.creditor.iban';

    private const string KEY_NAME = 'fines.creditor.name';

    public function contactEmail(): ?string
    {
        return $this->read(self::KEY_CONTACT_EMAIL);
    }

    public function contactName(): ?string
    {
        return $this->read(self::KEY_CONTACT_NAME);
    }

    public function contactPhone(): ?string
    {
        return $this->read(self::KEY_CONTACT_PHONE);
    }

    public function hasContact(): bool
    {
        return $this->contactName() !== null || $this->contactEmail() !== null || $this->contactPhone() !== null;
    }

    public function iban(): ?string
    {
        return IbanNormalizer::normalize($this->read(self::KEY_IBAN));
    }

    public function ibanFormatted(): ?string
    {
        return IbanNormalizer::format($this->iban());
    }

    /**
     * A fine cannot be issued before this holds: its mail would ask the member
     * to pay without saying where.
     */
    public function isConfigured(): bool
    {
        return $this->name() !== null && $this->iban() !== null;
    }

    public function name(): ?string
    {
        return $this->read(self::KEY_NAME);
    }

    public function update(string $name, string $iban, ?string $contactName, ?string $contactEmail, ?string $contactPhone): void
    {
        AppSetting::set(self::KEY_NAME, trim($name));
        AppSetting::set(self::KEY_IBAN, IbanNormalizer::normalize($iban));
        AppSetting::set(self::KEY_CONTACT_NAME, $this->blankToNull($contactName));
        AppSetting::set(self::KEY_CONTACT_EMAIL, $this->blankToNull($contactEmail));
        AppSetting::set(self::KEY_CONTACT_PHONE, $this->blankToNull($contactPhone));
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function read(string $key): ?string
    {
        $value = AppSetting::get($key);

        return filled($value) ? (string) $value : null;
    }
}
