<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\ExternalParticipants\AdjustExternalRegistrationAction;
use App\Actions\ClubAdmin\ExternalParticipants\CancelExternalRegistrationAction;
use App\Actions\ClubAdmin\ExternalParticipants\EnrollExternalInCampAction;
use App\Actions\ClubAdmin\ExternalParticipants\ResendExternalConfirmationAction;
use App\Actions\ClubAdmin\ExternalParticipants\UpdateExternalIdentityAction;
use App\Actions\ClubAdmin\ExternalParticipants\WithdrawExternalRegistrationAction;
use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Support\LocaleSort;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Les participants externes d'un stage : ni membres, ni comptes.
 *
 * Seul le club les encode — pas de formulaire public —, et seulement à partir
 * du jour où le stage s'ouvre aux non-membres : avant, les membres ont la
 * priorité. Stage complet = complet, ils n'ont pas de liste d'attente.
 *
 * Tout le monde qui lit la fiche voit leurs coordonnées — le coach doit pouvoir
 * appeler un parent —, seul `trainings.manage` les change. L'hôte se rafraîchit
 * sur `external-registrations-changed` : les places sont communes.
 */
new class extends Component
{
    use Toast;

    public bool $addPriceForced = false;

    public string $addPriceAmount = '';

    public string $addPriceReason = '';

    public string $email = '';

    #[Locked]
    public ?int $editingId = null;

    public bool $exitModal = false;

    /** `withdrawal`, `error`, or empty until chosen: their effects on money differ too much to pre-tick one. */
    public string $exitMode = '';

    #[Locked]
    public ?int $exitingId = null;

    public string $firstName = '';

    public string $guardianFirstName = '';

    public string $guardianLastName = '';

    public string $guardianPhone = '';

    public bool $identityModal = false;

    public bool $isMinor = false;

    public string $lastName = '';

    #[Locked]
    public int $packId = 0;

    public string $phone = '';

    public string $priceAmount = '';

    #[Locked]
    public ?int $pricingId = null;

    public bool $priceModal = false;

    public string $priceReason = '';

    public bool $sendConfirmation = true;

    /**
     * Why a non-member cannot be added right now, or null when they can.
     */
    #[Computed]
    public function closedReason(): ?string
    {
        $camp = $this->camp;

        return match (true) {
            $camp->externals_open_on === null => null,
            $camp->externals_open_on->isAfter(today()) => __('Open to non-members on :date', ['date' => $camp->externals_open_on->format('d/m/Y')]),
            ! $camp->hasAvailableSpot() => __('Training camp full'),
            default => null,
        };
    }

    #[Computed]
    public function camp(): TrainingPack
    {
        return TrainingPack::findOrFail($this->packId);
    }

    public function confirmExit(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $registration = $this->registration($this->exitingId);

        if (! $registration || ! in_array($this->exitMode, ['withdrawal', 'error'], true)) {
            return;
        }

        try {
            if ($this->exitMode === 'withdrawal') {
                (new WithdrawExternalRegistrationAction)($registration);
                $this->success(__(':name has withdrawn: the price stays owed.', ['name' => $registration->displayName()]));
            } else {
                $refunded = (new CancelExternalRegistrationAction)($registration);
                $this->success($refunded > 0
                    ? __('Registration cancelled. :amount € to refund, sent to the treasurer.', ['amount' => number_format($refunded, 2, ',', ' ')])
                    : __('Registration cancelled.'));
            }
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->exitModal = false;
        $this->changed();
    }

    public function openAdd(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if ($this->closedReason !== null) {
            $this->error($this->closedReason);

            return;
        }

        $this->resetIdentity();
        $this->editingId = null;
        $this->identityModal = true;
    }

    public function openEdit(int $registrationId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $registration = $this->registration($registrationId);

        if (! $registration || $registration->isAnonymized()) {
            return;
        }

        $this->resetIdentity();
        $this->editingId = $registration->id;
        $this->firstName = (string) $registration->first_name;
        $this->lastName = (string) $registration->last_name;
        $this->email = (string) $registration->email;
        $this->phone = (string) $registration->phone;
        $this->isMinor = $registration->is_minor;
        $this->guardianFirstName = (string) $registration->guardian_first_name;
        $this->guardianLastName = (string) $registration->guardian_last_name;
        $this->guardianPhone = (string) $registration->guardian_phone;
        $this->identityModal = true;
    }

    public function openExit(int $registrationId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->exitingId = $this->registration($registrationId)?->id;
        $this->exitMode = '';
        $this->exitModal = $this->exitingId !== null;
    }

    public function openPrice(int $registrationId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $registration = $this->registration($registrationId);

        if (! $registration) {
            return;
        }

        $this->pricingId = $registration->id;
        $this->priceAmount = $registration->override_amount !== null ? number_format($registration->override_amount / 100, 2, '.', '') : '';
        $this->priceReason = (string) $registration->override_reason;
        $this->priceModal = true;
    }

    /**
     * The non-members of the stage, enrolled first, then those who left.
     *
     * @return array{enrolled: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    #[Computed]
    public function participants(): array
    {
        $registrations = $this->camp->externalRegistrations()->get();

        if ($registrations->isEmpty()) {
            return ['enrolled' => [], 'past' => []];
        }

        // What each registration still asks, read in one query.
        $balances = Payment::query()
            ->where('payable_type', ExternalRegistration::class)
            ->whereIn('payable_id', $registrations->pluck('id'))
            ->where('status', 'pending')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->get()
            ->groupBy('payable_id')
            ->map(fn ($claims): float => round((float) $claims->sum(fn (Payment $claim): float => $claim->balance()), 2));

        $counted = Training::query()
            ->where('training_pack_id', $this->packId)
            ->where('status', 'scheduled')
            ->whereNotNull('attendance_taken_at')
            ->pluck('id');

        $present = $counted->isEmpty() ? collect() : DB::table('external_registration_training')
            ->whereIn('training_id', $counted)
            ->where('status', 'present')
            ->groupBy('external_registration_id')
            ->pluck(DB::raw('COUNT(*)'), 'external_registration_id');

        $rows = $registrations->map(fn (ExternalRegistration $registration): array => [
            'id' => $registration->id,
            'name' => $registration->isAnonymized() ? $registration->displayName() : $registration->last_name . ' ' . $registration->first_name,
            'anonymized' => $registration->isAnonymized(),
            'isMinor' => $registration->is_minor,
            'guardian' => trim($registration->guardian_first_name . ' ' . $registration->guardian_last_name),
            'email' => $registration->email,
            'phone' => $registration->is_minor ? $registration->guardian_phone : $registration->phone,
            'status' => $registration->status,
            'balance' => (float) ($balances[$registration->id] ?? 0.0),
            'overrideAmount' => $registration->override_amount !== null ? $registration->override_amount / 100 : null,
            'overrideReason' => $registration->override_reason,
            'rate' => $counted->isEmpty() ? null : (int) round(((int) ($present[$registration->id] ?? 0) / $counted->count()) * 100),
        ]);

        return [
            'enrolled' => LocaleSort::byKey($rows->where('status', 'enrolled'), 'name')->all(),
            'past' => LocaleSort::byKey($rows->whereIn('status', ['left', 'cancelled']), 'name')->all(),
        ];
    }

    public function resendConfirmation(int $registrationId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $registration = $this->registration($registrationId);

        if ($registration && (new ResendExternalConfirmationAction)($registration)) {
            $this->success(__('Confirmation sent to :email.', ['email' => $registration->email]));

            return;
        }

        $this->warning(__('Nothing left to pay: no confirmation to send.'));
    }

    public function saveIdentity(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'guardianFirstName' => 'required_if:isMinor,true|nullable|string|max:255',
            'guardianLastName' => 'required_if:isMinor,true|nullable|string|max:255',
            'guardianPhone' => 'required_if:isMinor,true|nullable|string|max:50',
            'addPriceAmount' => 'nullable|numeric|min:0',
            'addPriceReason' => 'required_if:addPriceForced,true|nullable|string|max:255',
        ]);

        $identity = new ExternalIdentity(
            firstName: $this->firstName,
            lastName: $this->lastName,
            email: $this->email,
            phone: $this->phone,
            isMinor: $this->isMinor,
            guardianFirstName: $this->guardianFirstName,
            guardianLastName: $this->guardianLastName,
            guardianPhone: $this->guardianPhone,
        );

        try {
            if ($this->editingId !== null) {
                $registration = $this->registration($this->editingId);

                if ($registration) {
                    (new UpdateExternalIdentityAction)($registration, $identity);
                }

                $this->success(__('Participant updated.'));
            } else {
                $forced = $this->addPriceForced && $this->addPriceAmount !== '';

                (new EnrollExternalInCampAction)(
                    $this->camp,
                    $identity,
                    $forced ? (float) $this->addPriceAmount : null,
                    $forced ? $this->addPriceReason : null,
                    $this->sendConfirmation,
                );

                $this->success(__(':name added to :pack.', [
                    'name' => trim($this->firstName . ' ' . $this->lastName),
                    'pack' => $this->camp->name,
                ]), icon: 'o-user-plus');
            }
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->identityModal = false;
        $this->changed();
    }

    public function savePrice(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $registration = $this->registration($this->pricingId);

        if (! $registration) {
            return;
        }

        try {
            $refunded = (new AdjustExternalRegistrationAction)(
                $registration,
                $this->priceAmount !== '' ? (float) $this->priceAmount : null,
                $this->priceReason,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->priceModal = false;
        $this->changed();

        $this->success($refunded > 0
            ? __('Price saved. :amount € to refund, sent to the treasurer.', ['amount' => number_format($refunded, 2, ',', ' ')])
            : __('Price saved.'));
    }

    private function changed(): void
    {
        unset($this->participants, $this->camp, $this->closedReason);

        $this->dispatch('external-registrations-changed');
    }

    /** One of this stage's registrations, never another stage's. */
    private function registration(?int $id): ?ExternalRegistration
    {
        return $id === null ? null : $this->camp->externalRegistrations()->find($id);
    }

    private function resetIdentity(): void
    {
        $this->resetValidation();
        $this->firstName = '';
        $this->lastName = '';
        $this->email = '';
        $this->phone = '';
        $this->isMinor = false;
        $this->guardianFirstName = '';
        $this->guardianLastName = '';
        $this->guardianPhone = '';
        $this->addPriceForced = false;
        $this->addPriceAmount = '';
        $this->addPriceReason = '';
        $this->sendConfirmation = true;
    }
};
?>

<div>
    @if ($this->camp->externals_open_on !== null)
        @php $participants = $this->participants; @endphp

        <div class="mt-8">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Non-members') }}</p>
                <x-badge :value="count($participants['enrolled'])" class="badge-accent badge-soft badge-sm" />

                @can('trainings.manage')
                    <div class="ml-auto">
                        @if ($this->closedReason)
                            <span class="text-xs text-base-content/60">{{ $this->closedReason }}</span>
                        @else
                            <x-button class="btn-ghost btn-sm" icon="o-user-plus" :label="__('Add a non-member')" wire:click="openAdd" />
                        @endif
                    </div>
                @endcan
            </div>

            @if (empty($participants['enrolled']) && empty($participants['past']))
                <div class="rounded-xl border border-dashed border-base-300 py-6 text-center text-sm text-base-content/50">
                    {{ __('No non-member on this training camp yet.') }}
                </div>
            @else
                {{-- Même enveloppe que la liste des membres : `lg:overflow-x-visible`
                     laisse le menu de ligne sortir du tableau sur grand écran. --}}
                <div class="overflow-x-auto rounded-xl border border-base-300 bg-base-100 lg:overflow-x-visible">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Participant') }}</th>
                                <th>{{ __('Contact') }}</th>
                                <th class="text-center">{{ __('Attendance') }}</th>
                                @can('trainings.manage')
                                    <th class="w-px text-right">{{ __('Actions') }}</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([...$participants['enrolled'], ...$participants['past']] as $row)
                                <tr wire:key="external-{{ $row['id'] }}" @class(['opacity-60' => $row['status'] !== 'enrolled'])>
                                    <td>
                                        <span class="whitespace-nowrap">{{ $row['name'] }}</span>
                                        @if ($row['isMinor'])
                                            <x-badge class="badge-ghost badge-xs ml-1" :value="__('minor')" />
                                        @endif
                                        @if ($row['status'] === 'left')
                                            <x-badge class="badge-neutral badge-soft badge-xs ml-1" :value="__('withdrawn')" />
                                        @elseif ($row['status'] === 'cancelled')
                                            <x-badge class="badge-neutral badge-soft badge-xs ml-1" :value="__('cancelled')" />
                                        @endif
                                        @if ($row['status'] !== 'cancelled')
                                            @if ($row['balance'] > 0)
                                                <x-badge class="badge-warning badge-soft badge-xs ml-1"
                                                    :value="__('to pay: :amount €', ['amount' => number_format($row['balance'], 2, ',', ' ')])" />
                                            @else
                                                <x-badge class="badge-success badge-soft badge-xs ml-1" :value="__('paid')" />
                                            @endif
                                        @endif
                                        @if ($row['overrideAmount'] !== null)
                                            <x-badge class="badge-ghost badge-xs ml-1"
                                                :value="__('price set by hand: :amount €', ['amount' => number_format($row['overrideAmount'], 2, ',', ' ')])"
                                                :title="$row['overrideReason']" />
                                        @endif
                                    </td>
                                    <td class="text-sm">
                                        @if (! $row['anonymized'])
                                            @if ($row['isMinor'] && $row['guardian'] !== '')
                                                <div class="text-base-content/70">{{ $row['guardian'] }}</div>
                                            @endif
                                            @if ($row['phone'])
                                                <a class="link link-hover whitespace-nowrap" href="tel:{{ preg_replace('/[^0-9+]/', '', $row['phone']) }}">{{ $row['phone'] }}</a>
                                            @endif
                                            <div class="break-all text-xs text-base-content/60">{{ $row['email'] }}</div>
                                        @else
                                            <span class="text-base-content/40">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-sm font-bold tabular-nums">
                                        {{ $row['rate'] !== null ? $row['rate'] . '%' : '—' }}
                                    </td>
                                    @can('trainings.manage')
                                        <td class="w-px">
                                            @if ($row['status'] !== 'cancelled' && ! $row['anonymized'])
                                                <x-admin.shared.row-menu :label="__('Edit')" icon="o-pencil" :wire-click="'openEdit(' . $row['id'] . ')'">
                                                    <li>
                                                        <button type="button" class="w-full justify-start gap-2 text-start" wire:click="openPrice({{ $row['id'] }})">
                                                            <x-icon name="o-currency-euro" class="h-4 w-4" />
                                                            {{ __('Price') }}
                                                        </button>
                                                    </li>
                                                    @if ($row['status'] === 'enrolled' && $row['balance'] > 0)
                                                        <li>
                                                            <button type="button" class="w-full justify-start gap-2 text-start" wire:click="resendConfirmation({{ $row['id'] }})">
                                                                <x-icon name="o-envelope" class="h-4 w-4" />
                                                                {{ __('Send the confirmation again') }}
                                                            </button>
                                                        </li>
                                                    @endif
                                                    <li>
                                                        <button type="button" class="w-full justify-start gap-2 text-start text-error" wire:click="openExit({{ $row['id'] }})">
                                                            <x-icon name="o-user-minus" class="h-4 w-4" />
                                                            {{ __('Remove from the training camp') }}
                                                        </button>
                                                    </li>
                                                </x-admin.shared.row-menu>
                                            @endif
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @can('trainings.manage')
            {{-- ── Ajouter ou corriger un participant externe ──────────────────── --}}
            <x-app-modal wire:model="identityModal" separator :open="$identityModal"
                :title="$editingId ? __('Edit the participant') : __('Add a non-member')">
                <div class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-input :label="__('First name')" wire:model="firstName" />
                        <x-input :label="__('Last name')" wire:model="lastName" />
                    </div>

                    <x-toggle :label="__('Minor')" wire:model.live="isMinor"
                        :hint="__('The club then writes to and calls the responsible adult.')" />

                    @if ($isMinor)
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input :label="__('First name of the responsible adult')" wire:model="guardianFirstName" />
                            <x-input :label="__('Last name of the responsible adult')" wire:model="guardianLastName" />
                        </div>
                        <x-input :label="__('Mobile of the responsible adult')" wire:model="guardianPhone" type="tel" />
                        <x-input :label="__('Email of the responsible adult')" wire:model="email" type="email" />
                    @else
                        <x-input :label="__('Email')" wire:model="email" type="email" />
                        <x-input :label="__('Mobile (optional)')" wire:model="phone" type="tel" />
                    @endif

                    @unless ($editingId)
                        <x-toggle :label="__('Force a price for this participant')" wire:model.live="addPriceForced" />
                        @if ($addPriceForced)
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-input type="number" min="0" step="0.50" wire:model="addPriceAmount"
                                    :label="__('Price (€)')"
                                    :placeholder="number_format((float) ($this->camp->external_price ?? $this->camp->price), 2, '.', '')" />
                                <x-input wire:model="addPriceReason" :label="__('Reason')" :placeholder="__('E.g. one day only')" />
                            </div>
                        @endif

                        <x-toggle :label="__('Send the confirmation and the invitation to pay now')" wire:model="sendConfirmation"
                            :hint="__('Untick when the participant pays cash on the spot.')" />
                    @endunless
                </div>

                <x-slot:actions>
                    <x-button :label="__('Cancel')" wire:click="$set('identityModal', false)" />
                    <x-button :label="__('Save')" class="btn-primary" wire:click="saveIdentity" spinner />
                </x-slot:actions>
            </x-app-modal>

            {{-- ── Prix de ce participant ───────────────────────────────────────── --}}
            <x-app-modal :title="__('Price for this participant')" wire:model="priceModal" separator :open="$priceModal">
                <p class="text-sm text-base-content/70">
                    {{ __('The training camp invoice follows: an unpaid request is adjusted, money already paid beyond the new price is refunded.') }}
                </p>
                <x-input class="mt-3" type="number" min="0" step="0.50" wire:model="priceAmount"
                    :label="__('Price for this participant (€)')"
                    :placeholder="number_format((float) ($this->camp->external_price ?? $this->camp->price), 2, '.', '')"
                    :hint="__('Leave empty for the training camp price.')" />
                <x-input class="mt-3" wire:model="priceReason" :label="__('Reason')" :placeholder="__('E.g. one day only')" />
                <x-slot:actions>
                    <x-button :label="__('Cancel')" wire:click="$set('priceModal', false)" />
                    <x-button :label="__('Save')" class="btn-primary" wire:click="savePrice" spinner />
                </x-slot:actions>
            </x-app-modal>

            {{-- ── Retirer un participant : désistement ou erreur ──────────────── --}}
            <x-app-modal :title="__('Remove from the training camp')" wire:model="exitModal" separator :open="$exitModal">
                <div class="space-y-3">
                    <label @class([
                        'flex cursor-pointer gap-3 rounded-xl border p-3',
                        'border-primary bg-primary/5' => $exitMode === 'withdrawal',
                        'border-base-300' => $exitMode !== 'withdrawal',
                    ])>
                        <input type="radio" class="radio radio-primary radio-sm mt-0.5" value="withdrawal" wire:model.live="exitMode" />
                        <span>
                            <span class="block font-medium">{{ __('The participant withdraws') }}</span>
                            <span class="block text-sm text-base-content/70">{{ __('The place frees up; the price stays owed in full.') }}</span>
                        </span>
                    </label>
                    <label @class([
                        'flex cursor-pointer gap-3 rounded-xl border p-3',
                        'border-primary bg-primary/5' => $exitMode === 'error',
                        'border-base-300' => $exitMode !== 'error',
                    ])>
                        <input type="radio" class="radio radio-primary radio-sm mt-0.5" value="error" wire:model.live="exitMode" />
                        <span>
                            <span class="block font-medium">{{ __('Encoding error') }}</span>
                            <span class="block text-sm text-base-content/70">{{ __('Nothing is owed: the unpaid invoice is cancelled, money already paid goes to refund.') }}</span>
                        </span>
                    </label>
                </div>
                <x-slot:actions>
                    <x-button :label="__('Cancel')" wire:click="$set('exitModal', false)" />
                    <x-button :label="__('Confirm')" class="btn-error" wire:click="confirmExit" :disabled="$exitMode === ''" spinner />
                </x-slot:actions>
            </x-app-modal>
        @endcan
    @endif
</div>
