<?php

declare(strict_types=1);

namespace App\Data\ExternalParticipant;

/**
 * Who a non-member is, as the club encodes them for one event.
 *
 * The strict minimum: a name, an address to send the invoice to and, for a
 * child, the adult a coach calls. For a minor the address and the number are
 * the adult's — the child has none of their own here.
 */
readonly class ExternalIdentity
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phone = null,
        public bool $isMinor = false,
        public ?string $guardianFirstName = null,
        public ?string $guardianLastName = null,
        public ?string $guardianPhone = null,
    ) {}

    /**
     * @throws \DomainException when something the club needs is missing
     */
    public function assertComplete(): void
    {
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new \DomainException(__('The first and last name are required.'));
        }

        if (filter_var(trim($this->email), FILTER_VALIDATE_EMAIL) === false) {
            throw new \DomainException(__('A valid email address is required.'));
        }

        if (! $this->isMinor) {
            return;
        }

        if ($this->blankToNull($this->guardianFirstName) === null || $this->blankToNull($this->guardianLastName) === null) {
            throw new \DomainException(__('The name of the responsible adult is required for a minor.'));
        }

        // The number a coach calls when the child is hurt or did not turn up.
        if ($this->blankToNull($this->guardianPhone) === null) {
            throw new \DomainException(__('The phone number of the responsible adult is required for a minor.'));
        }
    }

    /**
     * The registration's columns: a field that means nothing for an adult is
     * never written, so a box unticked after typing leaves no stray name.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'first_name' => trim($this->firstName),
            'last_name' => trim($this->lastName),
            'email' => mb_strtolower(trim($this->email)),
            'phone' => $this->isMinor ? null : $this->blankToNull($this->phone),
            'is_minor' => $this->isMinor,
            'guardian_first_name' => $this->isMinor ? $this->blankToNull($this->guardianFirstName) : null,
            'guardian_last_name' => $this->isMinor ? $this->blankToNull($this->guardianLastName) : null,
            'guardian_phone' => $this->isMinor ? $this->blankToNull($this->guardianPhone) : null,
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
