<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\Gender;

readonly class CreateUserData
{
    /**
     * @param  string|null  $email  Identifies a login, so it is null for every member the club
     *                              cannot hand one to — a child, a member reached through their
     *                              guardian. That is a normal account, not an incomplete one.
     * @param  string|null  $password  Plain password set by an admin; when null/empty the
     *                                 user is created password-less and an invitation is sent.
     * @param  array<int>  $guardianIds  Guardian ids to link to the new user.
     * @param  array<int>  $familyMemberIds  Other user ids to sync into the new user's family group.
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public ?string $email,
        public Gender $gender,
        public ?string $phone_number = null,
        public ?string $street = null,
        public ?string $city_code = null,
        public ?string $city_name = null,
        public ?string $birthdate = null,
        public bool $has_key = false,
        public ?string $licence = null,
        public ?string $ranking = null,
        public ?string $password = null,
        public array $guardianIds = [],
        public array $familyMemberIds = [],
        /**
         * The rights layer — délégations, committee seat, statutory title. Null
         * means this caller does not manage rights, and the new member is created
         * without any: only a screen that holds `access.manage` fills it in.
         */
        public ?AccessData $access = null,
    ) {}
}
