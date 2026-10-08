<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CancelTrainingCampEnrolmentAction;
use App\Actions\ClubAdmin\Subscriptions\CancelTrainingPackEnrolmentAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingPackExit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Sortir un membre d'un pack où sa place était validée.
 *
 * Deux sorties, que rien ne choisit à la place du comité : un départ daté, au
 * pro rata des mois suivis, ou l'annulation d'une inscription posée par erreur,
 * qui efface la ligne. Aucune n'est cochée d'avance : leurs effets sur l'argent
 * n'ont rien à voir, et une option pré-cochée se valide machinalement.
 *
 * Partagée par la liste des participants (Entraînements) et l'écran
 * Affiliations. L'hôte l'ouvre par l'événement `open-training-pack-exit` et se
 * rafraîchit sur `training-pack-exited`.
 */
new class extends Component
{
    use Toast;

    public string $endsOn = '';

    /** `departure`, `error`, ou vide tant que rien n'est choisi. */
    public string $mode = '';

    public bool $modal = false;

    /** Une ligne déjà partie ne peut plus qu'être annulée pour erreur. */
    #[Locked]
    public bool $onlyError = false;

    #[Locked]
    public int $packId = 0;

    #[Locked]
    public int $subscriptionId = 0;

    public function confirm(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = $this->subscription;
        $pack = $this->pack;

        if (! $subscription || ! $pack || ! in_array($this->mode, $this->onlyError ? ['error'] : ['departure', 'error'], true)) {
            return;
        }

        $familyMembersCount = $subscription->has_other_family_members ? 2 : 1;
        $member = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        try {
            $refundable = match (true) {
                // A stage opens its own refund, on its own line: nothing is left
                // for this screen to hand to the treasury.
                $this->mode === 'error' && $this->isCamp => (new CancelTrainingCampEnrolmentAction)($subscription, $pack),
                $this->mode === 'error' => (new CancelTrainingPackEnrolmentAction)($subscription, $pack, $familyMembersCount),
                default => (new LeaveTrainingPackAction)($subscription, $pack, $familyMembersCount, notifyUser: false, endsOn: $this->endsOn !== '' ? $this->endsOn : null),
            };
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        if ($refundable > 0 && ! $this->isCamp) {
            (new RequestSubscriptionRefundAction)($subscription, $refundable, $this->mode === 'error'
                ? __(':member was enrolled in :pack by mistake.', ['member' => $member, 'pack' => $pack->name])
                : __(':member has been removed from :pack after having paid.', ['member' => $member, 'pack' => $pack->name]));
        }

        $this->modal = false;
        $this->dispatch('training-pack-exited');

        $done = $this->mode === 'error'
            ? __('Enrolment of :member in :pack cancelled.', ['member' => $member, 'pack' => $pack->name])
            : __(':member removed from :pack.', ['member' => $member, 'pack' => $pack->name]);

        if ($refundable <= 0) {
            $this->success($done);

            return;
        }

        $iban = $subscription->user->iban;

        $iban
            ? $this->success($done . ' ' . __('Refund of :amount€ to be issued to :iban.', ['amount' => number_format($refundable, 2), 'iban' => $iban]))
            : $this->warning($done . ' ' . __('Refund of :amount€ required — no IBAN on file, please handle manually.', ['amount' => number_format($refundable, 2)]));
    }

    #[Computed]
    public function earliestDeparture(): ?Carbon
    {
        if (! $this->subscription || ! $this->pack) {
            return null;
        }

        return Carbon::instance((new TrainingPackExit)->earliestDeparture($this->subscription, $this->pack));
    }

    /** A stage line: invoiced on its own, owed in full, no prorata. */
    #[Computed]
    public function isCamp(): bool
    {
        if (! $this->subscription || ! $this->pack) {
            return false;
        }

        return (new TrainingCampBilling)->isInvoicedSeparately($this->subscription, $this->pack);
    }

    #[Computed]
    public function markedSessions(): int
    {
        if (! $this->subscription || ! $this->pack) {
            return 0;
        }

        return (new TrainingPackExit)->markedSessionsCount($this->subscription, $this->pack);
    }

    #[On('open-training-pack-exit')]
    public function open(int $subscriptionId, int $packId, bool $onlyError = false): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $this->subscriptionId = $subscriptionId;
        $this->packId = $packId;
        unset($this->subscription, $this->pack, $this->earliestDeparture, $this->markedSessions, $this->preview, $this->isCamp);

        $status = $this->subscription?->trainingPacks->firstWhere('id', $packId)?->pivot->status;

        if (! in_array($status, ['enrolled', 'left'], true)) {
            return;
        }

        $this->onlyError = $onlyError || $status === 'left';
        $this->mode = '';
        $this->endsOn = today()->toDateString();
        $this->modal = true;
    }

    #[Computed]
    public function pack(): ?TrainingPack
    {
        return TrainingPack::find($this->packId);
    }

    /**
     * Ce que l'option choisie produirait : le même calcul que le clic.
     *
     * @return array{line_amount: float, amount_due: float, reduced: float, refund: float, absences: int}|null
     */
    #[Computed]
    public function preview(): ?array
    {
        if ($this->mode === '' || ! $this->subscription || ! $this->pack) {
            return null;
        }

        if ($this->mode === 'error' && $this->markedSessions > 0) {
            return null;
        }

        $endsOn = null;

        if ($this->mode === 'departure') {
            try {
                $endsOn = Carbon::parse($this->endsOn)->toDateString();
            } catch (Throwable) {
                return null;
            }

            if ($endsOn > today()->toDateString() || $endsOn < $this->earliestDeparture?->toDateString()) {
                return null;
            }
        }

        if ($this->isCamp) {
            return $this->campPreview();
        }

        return (new TrainingPackExit)->preview(
            $this->subscription,
            $this->pack,
            $endsOn,
            $this->subscription->has_other_family_members ? 2 : 1,
        );
    }

    #[Computed]
    public function subscription(): ?Subscription
    {
        return Subscription::with(['user', 'season', 'trainingPacks', 'payments'])->find($this->subscriptionId);
    }

    /**
     * A stage's exit, read on its own invoice: a departure changes nothing, an
     * error lowers the unpaid request and refunds what came in.
     *
     * @return array{line_amount: float, amount_due: float|null, reduced: float, refund: float, absences: int}
     */
    private function campPreview(): array
    {
        $billing = new TrainingCampBilling;
        $line = $billing->line($this->subscription, $this->pack);
        $lineAmount = $line?->getAmountDue() ?? 0.0;

        if ($this->mode === 'departure') {
            return ['line_amount' => $lineAmount, 'amount_due' => null, 'reduced' => 0.0, 'refund' => 0.0,
                'absences' => (new TrainingPackExit)->absencesCount($this->subscription, $this->pack, $this->endsOn)];
        }

        $projection = $line ? $billing->project($line, 0.0) : ['reduced' => 0.0, 'refund' => 0.0];

        return [
            'line_amount' => 0.0,
            'amount_due' => null,
            'reduced' => $projection['reduced'],
            'refund' => $projection['refund'],
            'absences' => (new TrainingPackExit)->absencesCount($this->subscription, $this->pack),
        ];
    }
};
?>

<div>
    @can('subscriptions.manage')
    <x-app-modal wire:model="modal" separator :open="$modal"
        :title="__('Remove :member from :pack', [
            'member' => $this->subscription?->user->full_name ?? '',
            'pack' => $this->pack?->name ?? '',
        ])">
        @if ($modal)
            <div class="space-y-3">
                @unless ($onlyError)
                    <label @class([
                        'flex cursor-pointer gap-3 rounded-xl border p-3',
                        'border-primary bg-primary/5' => $mode === 'departure',
                        'border-base-300' => $mode !== 'departure',
                    ])>
                        <input type="radio" class="radio radio-primary radio-sm mt-0.5" value="departure" wire:model.live="mode" />
                        <span class="flex-1 space-y-2">
                            <span class="block font-semibold">{{ __('They stopped coming') }}</span>
                            <span class="block text-sm text-base-content/70">{{ $this->isCamp ? __('The training camp stays billed in full.') : __('The months attended stay billed, up to the departure date.') }}</span>
                            @if ($mode === 'departure')
                                <x-input :label="__('Departure date')" type="date" wire:model.live="endsOn"
                                    :min="$this->earliestDeparture?->toDateString()" :max="today()->toDateString()"
                                    :hint="__('From :date to today', ['date' => $this->earliestDeparture?->format('d/m/Y')])" />
                            @endif
                        </span>
                    </label>
                @endunless

                <label @class([
                    'flex gap-3 rounded-xl border p-3',
                    'cursor-pointer' => $this->markedSessions === 0,
                    'cursor-not-allowed opacity-60' => $this->markedSessions > 0,
                    'border-primary bg-primary/5' => $mode === 'error',
                    'border-base-300' => $mode !== 'error',
                ])>
                    <input type="radio" class="radio radio-primary radio-sm mt-0.5" value="error" wire:model.live="mode"
                        @disabled($this->markedSessions > 0) />
                    <span class="flex-1 space-y-1">
                        <span class="block font-semibold">{{ __('They should never have been enrolled (encoding error)') }}</span>
                        @if ($this->markedSessions > 0)
                            <span class="block text-sm text-warning-content">
                                {{ trans_choice('{1}Marked at one session by the coach: they came.|[2,*]Marked at :count sessions by the coach: they came.', $this->markedSessions, ['count' => $this->markedSessions]) }}
                            </span>
                        @else
                            <span class="block text-sm text-base-content/70">{{ __('The enrolment is erased: nothing is billed for this pack.') }}</span>
                        @endif
                    </span>
                </label>

                @if ($preview = $this->preview)
                    <div class="space-y-1 rounded-lg border border-info/20 bg-info/10 p-3 text-sm" data-testid="exit-preview">
                        <p class="flex justify-between gap-3">
                            <span>{{ __('Billed for this pack') }}</span>
                            <span class="font-semibold">{{ number_format($preview['line_amount'], 2) }} €</span>
                        </p>
                        @if ($preview['amount_due'] !== null)
                            <p class="flex justify-between gap-3">
                                <span>{{ __('New season total') }}</span>
                                <span class="font-semibold">{{ number_format($preview['amount_due'], 2) }} €</span>
                            </p>
                        @endif
                        @if ($preview['reduced'] > 0)
                            <p class="flex justify-between gap-3">
                                <span>{{ __('Payment request lowered by') }}</span>
                                <span class="font-semibold">{{ number_format($preview['reduced'], 2) }} €</span>
                            </p>
                        @endif
                        @if ($preview['refund'] > 0)
                            <p class="flex justify-between gap-3">
                                <span>{{ __('To refund') }}</span>
                                <span class="font-semibold">{{ number_format($preview['refund'], 2) }} €</span>
                            </p>
                        @endif
                        @if ($preview['absences'] > 0)
                            <p class="text-xs text-base-content/70">
                                {{ trans_choice('{1}One absence will be erased.|[2,*]:count absences will be erased.', $preview['absences'], ['count' => $preview['absences']]) }}
                            </p>
                        @endif
                        @if ($mode === 'error')
                            <p class="text-xs text-base-content/70">{{ __('The member is told by email.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('modal', false)" />
            <x-button :label="__('Remove')" class="btn-error" wire:click="confirm" spinner
                :disabled="$this->preview === null" />
        </x-slot:actions>
    </x-app-modal>
    @endcan
</div>
