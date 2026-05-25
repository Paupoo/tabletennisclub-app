<?php

declare(strict_types=1);

use App\Models\AppSetting;
use App\Rules\ValidIban;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // ── Navigation ───────────────────────────────────────────────────────────

    public string $step = '1';

    public int $maxReachable = 1;

    // ── Step 2 — Admin account ───────────────────────────────────────────────

    public string $firstName = '';

    public string $lastName = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    // ── Step 3 — Club licence ────────────────────────────────────────────────

    public string $licence = '';

    // ── Step 4 — Club info ───────────────────────────────────────────────────

    public string $clubName = '';

    public string $clubStreet = '';

    public string $clubCityCode = '';

    public string $clubCityName = '';

    public string $clubBuildingName = '';

    public string $clubEmailContact = '';

    public string $clubPhoneContact = '';

    public string $clubBankAccount = '';

    public string $clubEnterpriseNumber = '';

    public string $clubWebsiteUrl = '';

    // ── Step 5 — Season ──────────────────────────────────────────────────────

    public string $seasonName = '';

    public string $seasonStartAt = '';

    public string $seasonEndAt = '';

    // ── Step 6 — Rooms ───────────────────────────────────────────────────────

    /** @var array<int, array<string, mixed>> */
    public array $rooms = [];

    public string $roomName = '';

    public string $roomStreet = '';

    public string $roomCityCode = '';

    public string $roomCityName = '';

    public string $roomBuildingName = '';

    public int $roomCapacityTraining = 6;

    public int $roomCapacityInterclub = 4;

    public int $roomTotalTables = 4;

    public bool $showRoomForm = false;

    // ── Step 7 — Tables ──────────────────────────────────────────────────────

    /** @var array<int, array<int, array<string, mixed>>> Keyed by room index */
    public array $tables = [];

    public int $activeRoomIndex = 0;

    public string $tableName = '';

    public string $tableBrand = '';

    public bool $tableIsAvailable = true;

    public bool $showTableForm = false;

    // ── Persisted IDs ────────────────────────────────────────────────────────

    public ?int $clubId = null;

    /** @var array<int, int> Room IDs indexed by room array index */
    public array $roomIds = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $year = now()->year;
        $month = now()->month;

        if ($month >= 8) {
            $this->seasonName = "{$year}-" . ($year + 1);
            $this->seasonStartAt = "{$year}-08-01";
            $this->seasonEndAt = ($year + 1) . '-07-31';
        } else {
            $this->seasonName = ($year - 1) . "-{$year}";
            $this->seasonStartAt = ($year - 1) . '-08-01';
            $this->seasonEndAt = "{$year}-07-31";
        }
    }

    // ── Navigation ───────────────────────────────────────────────────────────

    public function startWizard(): void
    {
        $this->maxReachable = max($this->maxReachable, 2);
        $this->step = '2';
    }

    public function goToStep(string $target): void
    {
        if ((int) $target <= $this->maxReachable) {
            $this->step = $target;
        }
    }

    // ── Step 2 — Create admin account ────────────────────────────────────────

    public function completeStep2(): void
    {
        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName'  => 'required|string|max:100',
            'email'     => 'required|email:rfc|max:255|unique:users,email',
            'password'  => 'required|string|min:8|max:255|same:passwordConfirmation',
        ]);

        $user = User::create([
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'email'      => $this->email,
            'password'   => Hash::make($this->password),
            'is_admin'   => true,
            'is_active'  => true,
        ]);

        Auth::login($user);

        $this->password = '';
        $this->passwordConfirmation = '';

        $this->maxReachable = max($this->maxReachable, 3);
        $this->step = '3';
    }

    // ── Step 3 — Club licence ────────────────────────────────────────────────

    public function completeStep3(): void
    {
        $this->validate([
            'licence' => 'required|string|min:3|max:20|alpha_num|unique:clubs,licence',
        ]);

        $club = Club::create([
            'name'    => $this->licence,
            'licence' => $this->licence,
        ]);

        $this->clubId = $club->id;

        if (Auth::check()) {
            Auth::user()->update(['club_id' => $club->id]);
        }

        $this->maxReachable = max($this->maxReachable, 4);
        $this->step = '4';
    }

    // ── Step 4 — Club info ───────────────────────────────────────────────────

    public function completeStep4(): void
    {
        $this->validate([
            'clubName'            => 'required|string|max:100',
            'clubStreet'          => 'required|string|max:255',
            'clubCityCode'        => 'required|integer|between:1000,9999',
            'clubCityName'        => 'required|string|max:100',
            'clubBuildingName'    => 'nullable|string|max:100',
            'clubEmailContact'    => 'nullable|email:rfc|max:100',
            'clubPhoneContact'    => 'nullable|string|max:50',
            'clubBankAccount'     => ['nullable', 'string', new ValidIban],
            'clubEnterpriseNumber' => 'nullable|string|max:20',
            'clubWebsiteUrl'      => 'nullable|url|max:255',
        ]);

        Club::find($this->clubId)?->update([
            'name'              => $this->clubName,
            'street'            => $this->clubStreet,
            'city_code'         => $this->clubCityCode,
            'city_name'         => $this->clubCityName,
            'building_name'     => $this->clubBuildingName ?: null,
            'email_contact'     => $this->clubEmailContact ?: null,
            'phone_contact'     => $this->clubPhoneContact ?: null,
            'bank_account'      => $this->clubBankAccount ?: null,
            'enterprise_number' => $this->clubEnterpriseNumber ?: null,
            'website_url'       => $this->clubWebsiteUrl ?: null,
        ]);

        $this->maxReachable = max($this->maxReachable, 5);
        $this->step = '5';
    }

    // ── Step 5 — Season ──────────────────────────────────────────────────────

    public function completeStep5(): void
    {
        $this->validate([
            'seasonName'    => 'required|string|max:50',
            'seasonStartAt' => 'required|date',
            'seasonEndAt'   => 'required|date|after:seasonStartAt',
        ]);

        $season = Season::create([
            'name'               => $this->seasonName,
            'start_at'           => $this->seasonStartAt,
            'end_at'             => $this->seasonEndAt,
            'is_active'          => false,
            'registrations_open' => false,
        ]);

        $season->activate();

        $this->maxReachable = max($this->maxReachable, 6);
        $this->step = '6';
    }

    // ── Step 6 — Rooms ───────────────────────────────────────────────────────

    public function addRoom(): void
    {
        $this->validate([
            'roomName'              => 'required|string|max:255',
            'roomStreet'            => 'required|string|max:255',
            'roomCityCode'          => 'required|integer|between:1000,9999',
            'roomCityName'          => 'required|string|max:100',
            'roomBuildingName'      => 'nullable|string|max:100',
            'roomCapacityTraining'  => 'required|integer|min:0|max:99',
            'roomCapacityInterclub' => 'required|integer|min:0|max:99',
            'roomTotalTables'       => 'required|integer|min:0|max:99',
        ]);

        $this->rooms[] = [
            'name'                    => $this->roomName,
            'street'                  => $this->roomStreet,
            'city_code'               => $this->roomCityCode,
            'city_name'               => $this->roomCityName,
            'building_name'           => $this->roomBuildingName ?: null,
            'capacity_for_trainings'  => $this->roomCapacityTraining,
            'capacity_for_interclubs' => $this->roomCapacityInterclub,
            'total_tables'            => $this->roomTotalTables,
        ];

        $this->reset(['roomName', 'roomStreet', 'roomCityCode', 'roomCityName', 'roomBuildingName', 'showRoomForm']);
        $this->roomCapacityTraining = 6;
        $this->roomCapacityInterclub = 4;
        $this->roomTotalTables = 4;
        $this->resetValidation(['roomName', 'roomStreet', 'roomCityCode', 'roomCityName', 'roomBuildingName', 'roomCapacityTraining', 'roomCapacityInterclub', 'roomTotalTables']);
    }

    public function removeRoom(int $index): void
    {
        array_splice($this->rooms, $index, 1);
        unset($this->tables[$index]);
    }

    public function completeStep6(): void
    {
        foreach ($this->rooms as $index => $roomData) {
            $room = Room::create($roomData);
            $room->clubs()->attach($this->clubId);
            $this->roomIds[$index] = $room->id;
            $this->tables[$index] ??= [];
        }

        $this->maxReachable = max($this->maxReachable, 7);
        $this->step = count($this->rooms) > 0 ? '7' : '8';

        if (count($this->rooms) === 0) {
            $this->maxReachable = max($this->maxReachable, 8);
        }
    }

    public function skipStep6(): void
    {
        $this->maxReachable = max($this->maxReachable, 7);
        $this->step = '8';
        $this->maxReachable = max($this->maxReachable, 8);
    }

    // ── Step 7 — Tables ──────────────────────────────────────────────────────

    public function openTableForm(int $roomIndex): void
    {
        $this->activeRoomIndex = $roomIndex;
        $this->showTableForm = true;
        $this->reset(['tableName', 'tableBrand']);
        $this->tableIsAvailable = true;
    }

    public function addTable(): void
    {
        $this->validate([
            'tableName'  => 'required|string|max:255',
            'tableBrand' => 'nullable|string|max:100',
        ]);

        $this->tables[$this->activeRoomIndex][] = [
            'name'         => $this->tableName,
            'brand'        => $this->tableBrand ?: null,
            'is_available' => $this->tableIsAvailable,
        ];

        $this->reset(['tableName', 'tableBrand', 'showTableForm']);
        $this->tableIsAvailable = true;
        $this->resetValidation(['tableName', 'tableBrand']);
    }

    public function removeTable(int $roomIndex, int $tableIndex): void
    {
        array_splice($this->tables[$roomIndex], $tableIndex, 1);
    }

    public function completeStep7(): void
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

        $this->maxReachable = max($this->maxReachable, 8);
        $this->step = '8';
    }

    public function skipStep7(): void
    {
        $this->maxReachable = max($this->maxReachable, 8);
        $this->step = '8';
    }

    // ── Step 8 — Complete ────────────────────────────────────────────────────

    public function completeSetup(): void
    {
        AppSetting::set('setup_completed', '1');
        $this->updateEnvFile('APP_CLUB_LICENCE', $this->licence);

        $this->redirectRoute('dashboard', navigate: true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function updateEnvFile(string $key, string $value): void
    {
        $path = base_path('.env');
        $content = file_get_contents($path);

        if (str_contains($content, "{$key}=")) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        file_put_contents($path, $content);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return $this->view()->layout('layouts.setup');
    }
};
