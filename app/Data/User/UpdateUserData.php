<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;

readonly class UpdateUserData
{
    /**
     * @param  array<int>  $guardianIds  Guardian ids to sync onto the user.
     * @param  string|null  $password  Plain password; null/empty leaves it unchanged.
     * @param  array<int>  $familyMemberIds  Other user ids to sync into the user's family group.
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public Gender $gender,
        public ?string $phone_number = null,
        public ?string $street = null,
        public ?string $city_code = null,
        public ?string $city_name = null,
        public ?string $birthdate = null,
        public ?string $guardian_phone_number = null,
        public ?string $iban = null,
        public bool $is_committee_member = false,

        public bool $is_admin = false,
        public bool $has_key = false,
        public ?string $licence = null,
        public ?string $ranking = null,
        public ?CommitteeRolesEnum $committee_role = null,
        public ?string $password = null,
        public array $guardianIds = [],
        public array $familyMemberIds = [],
        /**
         * Délégations submitted by the form, as Role values. Null means the caller
         * does not manage duties — the self-service profile screen, typically —
         * and the ones already held must be left alone.
         *
         * @var array<int, string>|null
         */
        public ?array $delegations = null,
    ) {}
}
