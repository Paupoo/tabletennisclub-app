<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

@php
    $subscription = $this->currentSubscription;
    $delegations = $this->heldDelegations;
    $guardians = $user->guardians;
    $family = $user->familyMembers();
@endphp

<div>
    <x-header :title="$user->full_name" separator progress-indicator>
        <x-slot:actions>
            @if ($this->mayEdit)
                <x-button :label="__('Edit')" icon="o-pencil" class="btn-primary btn-sm"
                    link="{{ route('admin.users.edit', $user) }}" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- Identity --}}
    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-base-300 bg-base-100 p-4">
        <x-avatar class="h-16 w-16 shrink-0" image="{{ $user->photo ?? '/images/empty-user.jpg' }}" />
        <div class="min-w-0 flex-1">
            <p class="text-lg font-bold break-words text-base-content">{{ $user->full_name }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-content/70">
                @if ($user->birthdate)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="o-cake" class="h-4 w-4 shrink-0" />
                        {{ $user->birthdate->isoFormat('LL') }}
                    </span>
                @endif
                @if ($user->licence)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="o-identification" class="h-4 w-4 shrink-0" />
                        {{ $user->licence }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1">
                    <x-icon name="o-scale" class="h-4 w-4 shrink-0" />
                    {{ $user->ranking->getLabel() }}
                </span>
                @if ($user->committee_role)
                    <span class="inline-flex items-center gap-1">
                        <x-icon name="o-briefcase" class="h-4 w-4 shrink-0" />
                        {{ $user->committee_role->label() }}
                    </span>
                @endif
            </div>
        </div>
        <x-admin.users.account-status-badge :user="$user" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card class="shadow-sm" :title="__('Contact')">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-base-content/60">{{ __('Email') }}</dt>
                        <dd class="mt-1 break-words text-sm">{{ $user->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-base-content/60">{{ __('Phone Number') }}</dt>
                        <dd class="mt-1 text-sm">{{ $user->phone_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-base-content/60">{{ __('IBAN') }}</dt>
                        <dd class="mt-1 font-mono text-sm">{{ $this->displayedIban ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wider text-base-content/60">{{ __('Address') }}</dt>
                        <dd class="mt-1 text-sm">
                            @if (filled($user->street))
                                {{ $user->street }}<br>{{ $user->city_code }} {{ $user->city_name }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            @if ($guardians->isNotEmpty() || $user->isMinor())
                <x-card class="shadow-sm" :title="__('Responsible adults')">
                    @if ($guardians->isEmpty())
                        <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft">
                            <span class="text-sm">{{ __('This member is a minor without a legal guardian.') }}</span>
                        </x-alert>
                    @else
                        <ul class="space-y-2">
                            @foreach ($guardians as $guardian)
                                <li class="flex items-center gap-3 rounded-lg border border-base-300 p-3">
                                    <x-icon name="o-user" class="h-5 w-5 shrink-0 text-primary" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold">{{ $guardian->first_name }} {{ $guardian->last_name }}</p>
                                        <p class="truncate text-xs text-base-content/70">
                                            {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endif

            @if ($family->isNotEmpty())
                <x-card class="shadow-sm" :title="__('Family')">
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($family as $relative)
                            <li>
                                <a href="{{ route('admin.users.show', $relative) }}"
                                    class="inline-flex items-center gap-1 rounded-full border border-base-300 px-3 py-1 text-sm transition-colors duration-150 hover:border-primary">
                                    {{ $relative->full_name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($user->medical_certificate_path || $user->parental_consent_path)
                <x-card class="shadow-sm" :title="__('Documents')">
                    <div class="space-y-3">
                        @foreach (array_filter([
                            'medical' => $user->medical_certificate_path ? __('Medical Certificate') : null,
                            'parental_consent' => $user->parental_consent_path ? __('Parental Consent') : null,
                        ]) as $type => $label)
                            <div class="flex items-center gap-3 rounded-lg border border-base-300 p-3">
                                <x-icon name="o-document-check" class="h-5 w-5 shrink-0 text-success" />
                                <p class="min-w-0 flex-1 text-sm font-semibold">{{ $label }}</p>
                                <a href="{{ route('admin.user.documents.download', [$user, $type]) }}" target="_blank"
                                    class="btn btn-ghost btn-sm gap-1">
                                    <x-icon name="o-arrow-down-tray" class="h-4 w-4" />
                                    {{ __('Download') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card class="shadow-sm" :title="__('Affiliation')">
                @if ($subscription === null)
                    <p class="text-sm text-base-content/70">{{ __('No affiliation for the current season.') }}</p>
                @else
                    @php
                        $status = match ($subscription->status) {
                            'pending' => ['class' => 'badge-warning badge-soft', 'label' => __('To process')],
                            'confirmed' => ['class' => 'badge-info badge-soft', 'label' => __('Confirmed')],
                            'paid' => ['class' => 'badge-success badge-soft', 'label' => __('Paid')],
                            'refunded' => ['class' => 'badge-error badge-soft', 'label' => __('Refunded')],
                            'cancelled' => ['class' => 'badge-ghost', 'label' => __('Cancelled')],
                            default => ['class' => 'badge-ghost', 'label' => $subscription->status],
                        };
                    @endphp
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :value="$status['label']" class="{{ $status['class'] }} badge-sm" />
                        <x-badge :value="$subscription->is_competitive ? __('Competitive') : __('Recreational')"
                            class="{{ $subscription->is_competitive ? 'badge-primary badge-soft' : 'badge-ghost' }} badge-sm" />
                    </div>
                @endif
            </x-card>

            <x-card class="shadow-sm" :title="__('Delegations')">
                @if ($delegations === [])
                    <p class="text-sm text-base-content/70">{{ __('No delegation: this member holds no management right.') }}</p>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($delegations as $held)
                            <span class="inline-flex items-center rounded-full border border-primary/40 bg-primary/10 px-3 py-1 text-xs font-semibold text-base-content">
                                {{ $held->label() }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <x-card class="shadow-sm" :title="__('Entrusted equipment')">
                @if ($user->keyRings->isEmpty() && $user->heldCashRegisters->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('No key ring entrusted.') }}</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($user->keyRings as $keyRing)
                            <li class="flex items-center gap-2 text-sm">
                                <x-icon name="o-key" class="h-4 w-4 text-base-content/60" />
                                {{ $keyRing->label() }}
                            </li>
                        @endforeach
                        @foreach ($user->heldCashRegisters as $register)
                            <li class="flex items-center gap-2 text-sm">
                                <x-icon name="o-banknotes" class="h-4 w-4 text-base-content/60" />
                                {{ $register->name }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</div>
