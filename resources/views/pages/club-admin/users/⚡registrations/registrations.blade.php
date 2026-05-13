<div>
    <x-header title="{{ __('Registrations') }}" subtitle="{{ __('Applications awaiting affiliation') }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                placeholder="{{ __('Search a family...') }}"
                wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass"
                class="border-none bg-base-200" />
        </x-slot:middle>
        <x-slot:actions>
            @if(!$this->registrationClosed)
            <x-button
                label="{{ __('Register a member') }}"
                icon="o-user-plus"
                class="btn-primary btn-sm"
                @click="$wire.memberDrawer = true" />
            @endif
            <x-button :label="$this->registrationClosed ? __('Open Registrations') : __('Close Registrations')"
                :icon="$this->registrationClosed ? 'o-lock-open' : 'o-lock-closed'"
                class="btn-outline btn-sm"
                wire:click="toggleRegistrations" />

            <x-button
                label="{{ __('Bulk actions') }}"
                icon="o-funnel"
                class="btn-ghost btn-sm"
                @click="$wire.bulkDrawer = true"
                ::class="{ 'btn-disabled opacity-50': $wire.selectedPeople.length === 0 }" />
        </x-slot:actions>
    </x-header>

    <x-card class=" bg-base-100 border-none shadow-sm">
        <x-table :headers="$headers" :rows="$registrations" hover>

            @scope('cell_name', $req)
            <span class="font-bold text-base-content italic">{{ $req->first_name }} {{ $req->last_name }}</span>
            @endscope

            @scope('cell_type', $req)
            <span class="text-xs uppercase tracking-widest opacity-60">{{ __($req->type) }}</span>
            @endscope

            @scope('cell_members_count', $req)
            <span class="text-sm font-medium">{{ count($req->members)}}</span>
            @endscope

            @scope('cell_trainings_count', $req)
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium">{{ collect($req->members)->sum(fn($m) => count($m['trainings'])) }}</span>
            </div>
            @endscope

            @scope('cell_status', $req)
            <x-badge :value="__($req->status)" class="badge-primary badge-outline text-[10px] uppercase font-bold" />
            @endscope

            @scope('actions', $req)
            <x-button label="{{ __('Process') }}" wire:click="review({{ $req->id }})" class="btn-xs btn-neutral" />
            @endscope

        </x-table>
    </x-card>

    {{-- Modal de traitement --}}
    <x-modal wire:model="reviewModal" title="{{ __('Family File') }} {{ $currentRequest->name ?? '' }}" separator class="backdrop-blur-sm">

        {{-- État 1 : Dossier à traiter (avant validation) --}}
        @if(!$paymentGenerated && $currentRequest)
        <div class="space-y-6">
            <div>
                <h3 class="text-xs font-bold opacity-40 uppercase tracking-widest mb-4">{{ __('Members & Trainings') }}</h3>
                <div class="space-y-3">
                    @foreach($currentRequest->members as $member)
                    <div class="bg-base-200 p-4 rounded-xl border border-base-300/50">
                        <div class="font-bold text-base-content">{{ $member['first_name'] }} {{ $member['last_name'] }}</div>
                        <div class="text-sm opacity-60 mt-1">
                            {{ implode(' • ', array_map(fn($t) => __($t), $member['trainings'])) }}
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- État 2 : Paiement généré — QR + infos à envoyer --}}
        @if($paymentGenerated && !empty($paymentData))
        <div class="space-y-6">

            {{-- QR Code --}}
            <div class="flex flex-col items-center gap-3">
                <img src="{{ $paymentData['qr_code'] }}" alt="QR Code" class="w-48 h-48 rounded-xl border border-base-200 shadow" />
                <p class="text-xs opacity-50 text-center">{{ __('Scan this QR code with your banking app') }}</p>
            </div>

            <x-menu-separator />

            {{-- Détails bancaires --}}
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Beneficiary') }}</span>
                    <span class="font-semibold">{{ $paymentData['beneficiary'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('IBAN') }}</span>
                    <span class="font-mono font-semibold tracking-wide">{{ $paymentData['iban'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('BIC') }}</span>
                    <span class="font-mono font-semibold">{{ $paymentData['bic'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="opacity-60">{{ __('Structured reference') }}</span>
                    <span class="font-mono font-bold text-primary">{{ $paymentData['reference'] }}</span>
                </div>
                <div class="flex justify-between items-center pt-1 border-t border-base-200">
                    <span class="font-bold">{{ __('Amount') }}</span>
                    <span class="text-lg font-black text-primary">{{ $paymentData['amount_due'] }} €</span>
                </div>
            </div>

            {{-- Avertissement référence --}}
            <div class="flex gap-2 p-3 rounded-lg bg-warning/10 border border-warning/20 text-xs">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning shrink-0 mt-0.5" />
                <span class="opacity-80">{{ __('Always include the structured reference when making your transfer so your payment is automatically matched.') }}</span>
            </div>

            {{-- Destinataire --}}
            <div class="flex items-center gap-3 p-3 rounded-xl bg-base-200/50 border border-base-300 text-sm">
                <x-icon name="o-envelope" class="w-4 h-4 opacity-50 shrink-0" />
                <span class="flex-1 opacity-70">{{ $paymentData['member_name'] }} &lt;{{ $paymentData['member_email'] }}&gt;</span>
                @if($paymentData['invitation_counter'] > 0)
                <span class="text-xs opacity-50 italic shrink-0">
                    {{ __('Sent :n×', ['n' => $paymentData['invitation_counter']]) }}
                </span>
                @endif
            </div>

        </div>
        @endif

        <x-slot:actions>
            @if(!$paymentGenerated)
            <x-button label="{{ __('Reject') }}" wire:click="reject" class="btn-ghost text-error" />
            <x-button label="{{ __('Approve and Invoice') }}" wire:click="approve" class="btn-primary shadow-lg" />
            @else
            <x-button label="{{ __('Close') }}" @click="$wire.reviewModal = false" class="btn-ghost" />
            <x-button label="{{ __('Send by email') }}" icon="o-paper-airplane" class="btn-primary" wire:click="sendPaymentEmail" spinner />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- Drawer pour l'inscription/renouvellement --}}
    <x-drawer wire:model="memberDrawer" title="{{ __('Family Registration') }}" right separator with-close-button class="w-11/12 md:w-5/12">

        <div class="space-y-6">
            <div class="bg-base-200 p-4 rounded-xl">
                <x-input
                    placeholder="{{ __('Search for a member to add to the group...') }}"
                    wire:model.live.debounce.300ms="searchMember"
                    icon="o-magnifying-glass"
                    hint="{{ __('Add all family members here') }}" />

                @if(strlen($searchMember) > 2)
                <div class="mt-2 shadow-lg bg-base-100 rounded-lg border border-base-300">
                    @foreach($membersFound as $m)
                    <div class="p-3 flex justify-between items-center hover:bg-base-200 cursor-pointer border-b last:border-none"
                        wire:click="addToBasket({{ $m->id }})">
                        <span class="text-sm font-bold">{{ $m->first_name }} {{ $m->last_name }}</span>
                        <x-icon name="o-plus-circle" class="w-5 h-5 text-primary" />
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="space-y-4">
                @forelse($familyBasket as $userId => $config)
                <div class="border-2 border-base-300 rounded-2xl p-4 relative bg-base-100 shadow-sm">
                    <button wire:click="removeFromBasket({{ $userId }})" class="absolute top-2 right-2 text-error hover:scale-110 transition-transform">
                        <x-icon name="o-trash" class="w-4 h-4" />
                    </button>

                    <h3 class="font-black text-primary uppercase text-xs mb-4 flex items-center gap-2">
                        <x-icon name="o-user" class="w-4 h-4" />
                        {{ $config['name'] }}
                    </h3>

                    <div class="grid grid-cols-1 gap-4">
                        <x-radio
                            label="{{ __('Licence type') }}"
                            wire:model="familyBasket.{{ $userId }}.licence_type"
                            :options="[['id' => 'competitive', 'name' => __('Competitive')], ['id' => 'recreative', 'name' => __('Recreational')]]"
                            class="radio-sm" />

                        <x-choices
                            label="{{ __('Trainings') }}"
                            wire:model="familyBasket.{{ $userId }}.trainings"
                            :options="$this->trainingOptions()"
                            compact
                            allow-all />
                    </div>
                </div>
                @empty
                <div class="text-center py-10 opacity-40 italic border-2 border-dashed rounded-2xl">
                    {{ __('No member selected. Use the search above.') }}
                </div>
                @endforelse
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.memberDrawer = false" />
            @if(count($familyBasket) > 0)
            <x-button
                label="{{ __('Validate group registration') }} ({{ count($familyBasket) }})"
                icon="o-check"
                class="btn-primary"
                wire:click="saveFamilyRegistration" />
            @endif
        </x-slot:actions>
    </x-drawer>
</div>