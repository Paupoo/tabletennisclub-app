<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Domains\Shared\Enums\Gender;

readonly class UpdateUserData
{
    /**
     * @param  array<int>  $guardianIds  Guardian ids to sync onto the user.
     * @param  string|null  $email  Identifies a login, so it is null for every member the club
     *                              cannot hand one to — a child, a member reached through their
     *                              guardian. That is a normal account, not an incomplete one.
     * @param  string|null  $password  Plain password; null/empty leaves it unchanged.
     * @param  array<int>  $familyMemberIds  Other user ids to sync into the user's family group.
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
        public ?string $guardian_phone_number = null,
        public ?string $iban = null,
        public bool $has_key = false,
        public ?string $licence = null,
        public ?string $ranking = null,
        public ?string $password = null,
        public array $guardianIds = [],
        public array $familyMemberIds = [],
        /**
         * The rights layer — délégations, committee seat, statutory title. Null
         * means this caller does not manage rights, and every one of them is left
         * exactly as it stands: the self-service profile screen, typically, which
         * used to re-read them from the model only to hand them straight back.
         */
        public ?AccessData $access = null,
    ) {}
}
