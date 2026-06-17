<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;

readonly class CreateUserData
{
    /**
     * @param  string|null  $password  Plain password set by an admin; when null/empty the
     *                                 user is created password-less and an invitation is sent.
     * @param  array<int>  $guardianIds  Guardian ids to link to the new user.
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
        public bool $is_committee_member = false,
        public bool $is_admin = false,
        public bool $is_coach = false,
        public bool $has_key = false,
        public ?string $licence = null,
        public ?string $ranking = null,
        public ?CommitteeRolesEnum $committee_role = null,
        public ?string $password = null,
        public array $guardianIds = [],
    ) {}
}
