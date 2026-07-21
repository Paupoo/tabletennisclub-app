<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Shared\Rules\ValidIban;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public int $activeRoomIndex = 0;

    // ── Step 3 — Club info ───────────────────────────────────────────────────

    public string $clubBankAccount = '';

    public string $clubBic = '';

    public string $clubBuildingName = '';

    public string $clubCityCode = '';

    public string $clubCityName = '';

    public string $clubEmailContact = '';

    public string $clubEnterpriseNumber = '';

    // ── Persisted IDs ────────────────────────────────────────────────────────

    public ?int $clubId = null;

    public string $clubName = '';

    public string $clubPhoneContact = '';

    public string $clubStreet = '';

    public string $clubWebsiteUrl = '';

    public string $email = '';

    // ── Step 2 — Admin account ───────────────────────────────────────────────

    public string $firstName = '';

    public string $lastName = '';

    public string $licence = '';

    public int $maxReachable = 1;

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $roomBuildingName = '';

    public int $roomCapacityInterclub = 4;

    public int $roomCapacityTraining = 6;

    public string $roomCityCode = '';

    public string $roomCityName = '';

    /** @var array<int, int> Room IDs indexed by room array index */
    public array $roomIds = [];

    public string $roomName = '';

    // ── Step 5 — Rooms ───────────────────────────────────────────────────────

    /** @var array<int, array<string, mixed>> */
    public array $rooms = [];

    public string $roomStreet = '';

    public string $seasonEndAt = '';

    // ── Step 4 — Season ──────────────────────────────────────────────────────

    public string $seasonName = '';

    public string $seasonStartAt = '';

    public bool $showRoomForm = false;

    public bool $showTableForm = false;

    // ── Navigation ───────────────────────────────────────────────────────────

    public string $step = '1';

    public int $submittedStep = 0;

    public string $tableBrand = '';

    public bool $tableIsAvailable = true;

    public string $tableName = '';

    // ── Step 6 — Tables ──────────────────────────────────────────────────────

    /** @var array<int, array<int, array<string, mixed>>> Keyed by room index */
    public array $tables = [];

    // ── Step 5 — Rooms ───────────────────────────────────────────────────────

    public function addRoom(): void
    {
        $this->validate([
            'roomName' => 'required|string|max:255',
            'roomStreet' => 'required|string|max:255',
            'roomCityCode' => 'required|integer|between:1000,9999',
            'roomCityName' => 'required|string|max:100',
            'roomBuildingName' => 'nullable|string|max:100',
            'roomCapacityTraining' => 'required|integer|min:0|max:99',
            'roomCapacityInterclub' => 'required|integer|min:0|max:99',
        ]);

        $this->rooms[] = [
            'name' => $this->roomName,
            'street' => $this->roomStreet,
            'city_code' => $this->roomCityCode,
            'city_name' => $this->roomCityName,
            'building_name' => $this->roomBuildingName ?: null,
            'capacity_for_trainings' => $this->roomCapacityTraining,
            'capacity_for_interclubs' => $this->roomCapacityInterclub,
        ];

        $this->reset(['roomName', 'roomStreet', 'roomCityCode', 'roomCityName', 'roomBuildingName', 'showRoomForm']);
        $this->roomCapacityTraining = 6;
        $this->roomCapacityInterclub = 4;
        $this->resetValidation(['roomName', 'roomStreet', 'roomCityCode', 'roomCityName', 'roomBuildingName', 'roomCapacityTraining', 'roomCapacityInterclub']);
    }

    public function addTable(): void
    {
        $this->validate([
            'tableName' => 'required|string|max:255',
            'tableBrand' => 'nullable|string|max:100',
        ]);

        $this->tables[$this->activeRoomIndex][] = [
            'name' => $this->tableName,
            'brand' => $this->tableBrand ?: null,
            'is_available' => $this->tableIsAvailable,
        ];

        $this->reset(['tableName', 'tableBrand', 'showTableForm']);
        $this->tableIsAvailable = true;
        $this->resetValidation(['tableName', 'tableBrand']);
    }

    // ── Step 7 — Complete ────────────────────────────────────────────────────

    public function completeSetup(): void
    {
        AppSetting::set('setup_completed', '1');

        $this->redirectRoute('dashboard', navigate: true);
    }

    // ── Step 2 — Create admin account ────────────────────────────────────────

    public function completeStep2(): void
    {
        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'email' => 'required|email:rfc|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:255|same:passwordConfirmation',
        ]);

        $user = User::first();

        $attributes = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_active' => true,
        ];

        if ($user) {
            $user->update($attributes);
        } else {
            $user = User::create($attributes);
        }

        $user->assignRole(Role::ADMINISTRATOR->value);

        Auth::login($user);

        $this->password = '';
        $this->passwordConfirmation = '';
        $this->submittedStep = max($this->submittedStep, 2);
        $this->maxReachable = max($this->maxReachable, 3);
        $this->step = '3';
    }

    // ── Step 3 — Club licence + info (merged) ────────────────────────────────

    public function completeStep3(): void
    {
        $this->validate([
            'licence' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9]{3}$/', Rule::unique('clubs', 'licence')->ignore($this->clubId)],
            'clubName' => 'required|string|max:100',
            'clubStreet' => 'required|string|max:255',
            'clubCityCode' => 'required|integer|between:1000,9999',
            'clubCityName' => 'required|string|max:100',
            'clubBuildingName' => 'nullable|string|max:100',
            'clubEmailContact' => 'required|email:rfc|max:100',
            'clubPhoneContact' => 'nullable|string|max:50',
            'clubBic' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'clubBankAccount' => ['nullable', 'string', new ValidIban],
            'clubEnterpriseNumber' => 'nullable|string|max:20',
            'clubWebsiteUrl' => 'nullable|url|max:255',
        ]);

        Club::where('is_own_club', true)->update(['is_own_club' => false]);
        Club::forgetOwnClub();

        $clubData = [
            'licence' => $this->licence,
            'name' => $this->clubName,
            'is_own_club' => true,
            'street' => $this->clubStreet,
            'city_code' => $this->clubCityCode,
            'city_name' => $this->clubCityName,
            'building_name' => $this->clubBuildingName ?: null,
            'email_contact' => $this->clubEmailContact ?: null,
            'phone_contact' => $this->clubPhoneContact ?: null,
            'bic' => $this->clubBic ?: null,
            'bank_account' => $this->clubBankAccount ?: null,
            'enterprise_number' => $this->clubEnterpriseNumber ?: null,
            'website_url' => $this->clubWebsiteUrl ?: null,
        ];

        $club = Club::first();

        if ($club) {
            $club->update($clubData);
        } else {
            $club = Club::create($clubData);
        }

        $this->clubId = $club->id;

        if (Auth::check()) {
            Auth::user()->update(['club_id' => $club->id]);
        }

        $this->submittedStep = max($this->submittedStep, 3);
        $this->maxReachable = max($this->maxReachable, 4);
        $this->step = '4';
    }

    // ── Step 4 — Season ──────────────────────────────────────────────────────

    public function completeStep4(): void
    {
        $this->validate([
            'seasonName' => 'required|string|max:50',
            'seasonStartAt' => 'required|date',
            'seasonEndAt' => 'required|date|after:seasonStartAt',
        ]);

        $season = Season::first();

        if ($season) {
            $season->update([
                'name' => $this->seasonName,
                'start_at' => $this->seasonStartAt,
                'end_at' => $this->seasonEndAt,
            ]);
        } else {
            $season = Season::create([
                'name' => $this->seasonName,
                'start_at' => $this->seasonStartAt,
                'end_at' => $this->seasonEndAt,
                'is_active' => true,
                'registrations_open' => false,
            ]);
        }

        $season->activate();

        $this->submittedStep = max($this->submittedStep, 4);
        $this->maxReachable = max($this->maxReachable, 5);
        $this->step = '5';
    }

    // ── Step 5 — Rooms ───────────────────────────────────────────────────────

    public function completeStep5(): void
    {
        foreach ($this->rooms as $index => $roomData) {
            $room = Room::create($roomData);
            $room->clubs()->attach($this->clubId);
            $this->roomIds[$index] = $room->id;
            $this->tables[$index] ??= [];
        }

        $this->submittedStep = max($this->submittedStep, 5);
        $this->maxReachable = max($this->maxReachable, 6);
        $this->step = count($this->rooms) > 0 ? '6' : '7';

        if (count($this->rooms) === 0) {
            $this->maxReachable = max($this->maxReachable, 7);
        }
    }

    // ── Step 6 — Tables ──────────────────────────────────────────────────────

    public function completeStep6(): void
    {
        foreach ($this->tables as $roomIndex => $roomTables) {
            $roomId = $this->roomIds[$roomIndex] ?? null;
            if (! $roomId) {
                continue;
            }

            foreach ($roomTables as $tableData) {
                Table::create(array_merge($tableData, ['room_id' => $roomId]));
            }
        }

        $this->submittedStep = max($this->submittedStep, 6);
        $this->maxReachable = max($this->maxReachable, 7);
        $this->step = '7';
    }

    public function goToStep(string $target): void
    {
        if ((int) $target <= $this->maxReachable) {
            $this->step = $target;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        if (Auth::check()) {
            $club = Club::own();

            if ($club) {
                $this->clubId = $club->id;
                $this->maxReachable = max($this->maxReachable, 4);
                $this->step = '4';
            } else {
                $this->maxReachable = max($this->maxReachable, 3);
                $this->step = '3';
            }
        }

        $year = now()->year;
        $month = now()->month;

        if ($month >= 8) {
            $this->seasonName = "{$year}-" . ($year + 1);
            $this->seasonStartAt = "{$year}-08-01";
            $this->seasonEndAt = ($year + 1) . '-07-31';
        } else {
            $this->seasonName = ($year - 1) . "-{$year}";
            $this->seasonStartAt = ($year - 1) . '-09-01';
            $this->seasonEndAt = "{$year}-06-30";
        }
    }

    public function openTableForm(int $roomIndex): void
    {
        $this->activeRoomIndex = $roomIndex;
        $this->showTableForm = true;
        $this->reset(['tableName', 'tableBrand']);
        $this->tableIsAvailable = true;
    }

    public function removeRoom(int $index): void
    {
        array_splice($this->rooms, $index, 1);
        unset($this->tables[$index]);
    }

    public function removeTable(int $roomIndex, int $tableIndex): void
    {
        array_splice($this->tables[$roomIndex], $tableIndex, 1);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return $this->view()->layout('layouts.setup');
    }

    public function skipStep5(): void
    {
        $this->submittedStep = max($this->submittedStep, 5);
        $this->maxReachable = max($this->maxReachable, 7);
        $this->step = '7';
    }

    public function skipStep6(): void
    {
        $this->submittedStep = max($this->submittedStep, 6);
        $this->maxReachable = max($this->maxReachable, 7);
        $this->step = '7';
    }

    // ── Navigation ───────────────────────────────────────────────────────────

    public function startWizard(): void
    {
        $this->maxReachable = max($this->maxReachable, 2);
        $this->step = '2';
    }
};
