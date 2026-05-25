<div>
    @php
        $currentStep = (int) $step;

        $wizardSteps = [
            1 => ['label' => __('Bienvenue'),    'icon' => 'o-hand-raised'],
            2 => ['label' => __('Administrateur'), 'icon' => 'o-user-circle'],
            3 => ['label' => __('Matricule'),     'icon' => 'o-identification'],
            4 => ['label' => __('Club'),          'icon' => 'o-building-office'],
            5 => ['label' => __('Saison'),        'icon' => 'o-calendar'],
            6 => ['label' => __('Salles'),        'icon' => 'o-map-pin'],
            7 => ['label' => __('Tables'),        'icon' => 'o-table-cells'],
            8 => ['label' => __('Terminé'),       'icon' => 'o-check-badge'],
        ];
    @endphp

    {{-- ── Metro-line navigation ────────────────────────────────────────────── --}}
    <div class="flex items-center gap-0 mb-8 overflow-x-auto pb-2">
        @foreach ($wizardSteps as $num => $info)
            @php $reachable = $num <= $maxReachable; @endphp
            <button
                wire:click="{{ $reachable ? "\$set('step', '{$num}')" : 'null' }}"
                @class([
                    'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                    'bg-primary text-primary-content shadow'                                          => $num === $currentStep,
                    'text-base-content/70 hover:bg-base-200 hover:text-base-content cursor-pointer'  => $reachable && $num !== $currentStep,
                    'text-base-content/30 cursor-not-allowed'                                         => !$reachable,
                ])
            >
                <x-icon name="{{ $info['icon'] }}" class="w-4 h-4 shrink-0" />
                <span class="hidden sm:inline">{{ $info['label'] }}</span>
                @if ($reachable && $num < $currentStep)
                    <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success shrink-0" />
                @endif
            </button>
            @if ($num < 8)
                <x-icon name="o-chevron-right" class="w-4 h-4 text-base-content/15 shrink-0 mx-0.5" />
            @endif
        @endforeach
    </div>

    {{-- ── Step content ────────────────────────────────────────────────────── --}}

    {{-- STEP 1 — Bienvenue --}}
    @if ($step == '1')
        <div class="animate-in fade-in duration-500 text-center py-4">
            <div class="mb-6">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <x-icon name="o-rocket-launch" class="w-8 h-8 text-primary" />
                </div>
                <h2 class="text-2xl font-bold text-base-content">{{ __('Bienvenue dans l\'assistant d\'installation') }}</h2>
                <p class="mt-3 text-base-content/60 max-w-lg mx-auto">
                    {{ __('Cet assistant va vous guider à travers la configuration initiale de votre application de gestion de club de tennis de table.') }}
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-8 text-left max-w-lg mx-auto">
                @foreach ([
                    ['icon' => 'o-user-circle',     'label' => __('Compte administrateur')],
                    ['icon' => 'o-identification',  'label' => __('Identifiant du club')],
                    ['icon' => 'o-building-office', 'label' => __('Informations du club')],
                    ['icon' => 'o-calendar',        'label' => __('Première saison')],
                ] as $item)
                    <div class="flex items-center gap-2.5 p-3 bg-base-200/50 rounded-lg">
                        <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 text-primary shrink-0" />
                        <span class="text-sm text-base-content/80">{{ $item['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <x-button
                :label="__('Commencer l\'installation')"
                icon="o-arrow-right"
                class="btn-primary btn-lg"
                wire:click="startWizard"
            />
        </div>
    @endif

    {{-- STEP 2 — Compte administrateur --}}
    @if ($step == '2')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Compte administrateur') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('Ce compte aura accès à toutes les fonctionnalités de l\'application.') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input
                    label="{{ __('Prénom') }}"
                    wire:model="firstName"
                    placeholder="Jean"
                    required
                />
                <x-input
                    label="{{ __('Nom') }}"
                    wire:model="lastName"
                    placeholder="Dupont"
                    required
                />
                <x-input
                    label="{{ __('Adresse email') }}"
                    wire:model="email"
                    type="email"
                    placeholder="admin@monclub.be"
                    class="sm:col-span-2"
                    required
                />
                <x-input
                    label="{{ __('Mot de passe') }}"
                    wire:model="password"
                    type="password"
                    required
                />
                <x-input
                    label="{{ __('Confirmer le mot de passe') }}"
                    wire:model="passwordConfirmation"
                    type="password"
                    required
                />
            </div>

            <div class="flex justify-end mt-6">
                <x-button
                    label="{{ __('Suivant') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep2"
                    spinner="completeStep2"
                />
            </div>
        </div>
    @endif

    {{-- STEP 3 — Matricule du club --}}
    @if ($step == '3')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Matricule fédéral du club') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('Ce numéro d\'affiliation identifie votre club auprès de la fédération et dans toute l\'application.') }}</p>

            <div class="max-w-xs">
                <x-input
                    label="{{ __('Matricule') }}"
                    wire:model="licence"
                    placeholder="BBW214"
                    hint="{{ __('Ex : BBW214, HEW058…') }}"
                    required
                />
            </div>

            <x-alert
                title="{{ __('Information') }}"
                :description="__('Cette valeur sera enregistrée dans votre configuration système. Elle ne peut être modifiée qu\'en accédant directement au fichier .env.')"
                icon="o-information-circle"
                class="alert-info alert-soft mt-4"
            />

            <div class="flex justify-end mt-6">
                <x-button
                    label="{{ __('Suivant') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep3"
                    spinner="completeStep3"
                />
            </div>
        </div>
    @endif

    {{-- STEP 4 — Informations du club --}}
    @if ($step == '4')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Informations du club') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('Ces informations seront affichées sur le site public et utilisées dans les communications officielles.') }}</p>

            <div class="space-y-6">
                {{-- Identity --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Identité') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            label="{{ __('Nom du club') }}"
                            wire:model="clubName"
                            placeholder="CTT Ottignies-Blocry"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            label="{{ __('Numéro de compte IBAN') }}"
                            wire:model="clubBankAccount"
                            placeholder="BE00 0000 0000 0000"
                        />
                        <x-input
                            label="{{ __('Numéro d\'entreprise') }}"
                            wire:model="clubEnterpriseNumber"
                            placeholder="0000.000.000"
                        />
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Adresse') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            label="{{ __('Nom du bâtiment') }}"
                            wire:model="clubBuildingName"
                            placeholder="Centre Sportif"
                            class="sm:col-span-2"
                        />
                        <x-input
                            label="{{ __('Rue') }}"
                            wire:model="clubStreet"
                            placeholder="Rue de la Station 1"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            label="{{ __('Code postal') }}"
                            wire:model="clubCityCode"
                            placeholder="1340"
                            required
                        />
                        <x-input
                            label="{{ __('Ville') }}"
                            wire:model="clubCityName"
                            placeholder="Ottignies"
                            required
                        />
                    </div>
                </div>

                {{-- Contact --}}
                <div>
                    <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-3">{{ __('Contact') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-input
                            label="{{ __('Email de contact') }}"
                            wire:model="clubEmailContact"
                            type="email"
                            placeholder="contact@monclub.be"
                        />
                        <x-input
                            label="{{ __('Téléphone') }}"
                            wire:model="clubPhoneContact"
                            placeholder="+32 10 00 00 00"
                        />
                        <x-input
                            label="{{ __('Site web') }}"
                            wire:model="clubWebsiteUrl"
                            placeholder="https://www.monclub.be"
                            class="sm:col-span-2"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end mt-6">
                <x-button
                    label="{{ __('Suivant') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep4"
                    spinner="completeStep4"
                />
            </div>
        </div>
    @endif

    {{-- STEP 5 — Saison --}}
    @if ($step == '5')
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Première saison') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('La saison est la période de référence pour les affiliations, les entraînements et les interclubs. Elle sera marquée comme saison active.') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-lg">
                <x-input
                    label="{{ __('Nom de la saison') }}"
                    wire:model="seasonName"
                    placeholder="2025-2026"
                    class="sm:col-span-3"
                    required
                />
                <x-input
                    label="{{ __('Début') }}"
                    wire:model="seasonStartAt"
                    type="date"
                    required
                />
                <x-input
                    label="{{ __('Fin') }}"
                    wire:model="seasonEndAt"
                    type="date"
                    required
                />
            </div>

            <div class="flex justify-end mt-6">
                <x-button
                    label="{{ __('Créer la saison') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep5"
                    spinner="completeStep5"
                />
            </div>
        </div>
    @endif

    {{-- STEP 6 — Salles --}}
    @if ($step == '6')
        <div class="animate-in fade-in duration-500">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">{{ __('Salles d\'entraînement') }}</h2>
                    <p class="text-sm text-base-content/60 mt-0.5">{{ __('Encodez les salles où se déroulent vos entraînements et interclubs.') }}</p>
                </div>
                <span class="badge badge-soft badge-ghost text-xs">{{ __('Optionnel') }}</span>
            </div>

            {{-- Room list --}}
            @if (count($rooms) > 0)
                <div class="mt-4 space-y-2 mb-4">
                    @foreach ($rooms as $i => $room)
                        <div class="flex items-center justify-between p-3 bg-base-200/60 rounded-lg">
                            <div>
                                <p class="font-medium text-sm text-base-content">{{ $room['name'] }}</p>
                                <p class="text-xs text-base-content/50">{{ $room['city_code'] }} {{ $room['city_name'] }} — {{ $room['capacity_for_trainings'] }} pl. entraînement</p>
                            </div>
                            <x-button
                                icon="o-trash"
                                class="btn-ghost btn-sm text-error"
                                wire:click="removeRoom({{ $i }})"
                            />
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 mb-4 p-4 bg-base-200/30 rounded-lg text-center">
                    <x-icon name="o-map-pin" class="w-8 h-8 text-base-content/20 mx-auto mb-2" />
                    <p class="text-sm text-base-content/40">{{ __('Aucune salle ajoutée') }}</p>
                </div>
            @endif

            {{-- Add room form --}}
            @if ($showRoomForm)
                <div class="border border-base-300 rounded-xl p-4 mt-2">
                    <p class="text-sm font-semibold text-base-content mb-4">{{ __('Nouvelle salle') }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <x-input
                            label="{{ __('Nom de la salle') }}"
                            wire:model="roomName"
                            placeholder="Salle omnisports"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            label="{{ __('Bâtiment') }}"
                            wire:model="roomBuildingName"
                            placeholder="Centre Sportif"
                            class="sm:col-span-2"
                        />
                        <x-input
                            label="{{ __('Rue') }}"
                            wire:model="roomStreet"
                            placeholder="Rue de la Station 1"
                            class="sm:col-span-2"
                            required
                        />
                        <x-input
                            label="{{ __('Code postal') }}"
                            wire:model="roomCityCode"
                            type="number"
                            placeholder="1340"
                            required
                        />
                        <x-input
                            label="{{ __('Ville') }}"
                            wire:model="roomCityName"
                            placeholder="Ottignies"
                            required
                        />
                        <x-input
                            label="{{ __('Capacité entraînement') }}"
                            wire:model="roomCapacityTraining"
                            type="number"
                            hint="{{ __('Nombre de joueurs max') }}"
                        />
                        <x-input
                            label="{{ __('Capacité interclub') }}"
                            wire:model="roomCapacityInterclub"
                            type="number"
                            hint="{{ __('Nombre de tables pour les matchs') }}"
                        />
                        <x-input
                            label="{{ __('Nombre total de tables') }}"
                            wire:model="roomTotalTables"
                            type="number"
                        />
                    </div>
                    <div class="flex justify-end gap-2 mt-4">
                        <x-button
                            label="{{ __('Annuler') }}"
                            class="btn-ghost btn-sm"
                            wire:click="$set('showRoomForm', false)"
                        />
                        <x-button
                            label="{{ __('Ajouter') }}"
                            icon="o-plus"
                            class="btn-primary btn-sm"
                            wire:click="addRoom"
                            spinner="addRoom"
                        />
                    </div>
                </div>
            @else
                <x-button
                    label="{{ __('+ Ajouter une salle') }}"
                    class="btn-ghost btn-sm border border-dashed border-base-300 w-full mt-2"
                    wire:click="$set('showRoomForm', true)"
                />
            @endif

            <div class="flex items-center justify-between mt-6">
                <x-button
                    label="{{ __('Passer cette étape') }}"
                    class="btn-ghost btn-sm"
                    wire:click="skipStep6"
                />
                <x-button
                    label="{{ __('Suivant') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep6"
                    spinner="completeStep6"
                />
            </div>
        </div>
    @endif

    {{-- STEP 7 — Tables --}}
    @if ($step == '7')
        <div class="animate-in fade-in duration-500">
            <div class="flex items-start justify-between mb-1">
                <div>
                    <h2 class="text-lg font-semibold text-base-content">{{ __('Tables de ping-pong') }}</h2>
                    <p class="text-sm text-base-content/60 mt-0.5">{{ __('Encodez les tables disponibles dans chaque salle.') }}</p>
                </div>
                <span class="badge badge-soft badge-ghost text-xs">{{ __('Optionnel') }}</span>
            </div>

            <div class="mt-4 space-y-6">
                @foreach ($rooms as $i => $room)
                    <div class="border border-base-200 rounded-xl overflow-hidden">
                        <div class="bg-base-200/40 px-4 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-map-pin" class="w-4 h-4 text-primary" />
                                <span class="font-medium text-sm">{{ $room['name'] }}</span>
                            </div>
                            <span class="badge badge-sm badge-soft badge-primary">
                                {{ count($tables[$i] ?? []) }} {{ __('table(s)') }}
                            </span>
                        </div>

                        <div class="p-4 space-y-2">
                            @foreach ($tables[$i] ?? [] as $t => $table)
                                <div class="flex items-center justify-between py-1.5 px-2 bg-base-200/30 rounded-lg">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="o-check-circle" class="w-4 h-4 text-success shrink-0" />
                                        <span class="text-sm">{{ $table['name'] }}</span>
                                        @if ($table['brand'])
                                            <span class="text-xs text-base-content/40">{{ $table['brand'] }}</span>
                                        @endif
                                    </div>
                                    <x-button
                                        icon="o-trash"
                                        class="btn-ghost btn-xs text-error"
                                        wire:click="removeTable({{ $i }}, {{ $t }})"
                                    />
                                </div>
                            @endforeach

                            @if ($showTableForm && $activeRoomIndex === $i)
                                <div class="border border-base-300 rounded-lg p-3 mt-2">
                                    <div class="grid grid-cols-2 gap-3">
                                        <x-input
                                            label="{{ __('Nom / numéro') }}"
                                            wire:model="tableName"
                                            placeholder="Table 1"
                                            required
                                        />
                                        <x-input
                                            label="{{ __('Marque') }}"
                                            wire:model="tableBrand"
                                            placeholder="Butterfly"
                                        />
                                    </div>
                                    <x-toggle
                                        label="{{ __('Disponible') }}"
                                        wire:model="tableIsAvailable"
                                        class="mt-3"
                                    />
                                    <div class="flex justify-end gap-2 mt-3">
                                        <x-button
                                            label="{{ __('Annuler') }}"
                                            class="btn-ghost btn-xs"
                                            wire:click="$set('showTableForm', false)"
                                        />
                                        <x-button
                                            label="{{ __('Ajouter') }}"
                                            icon="o-plus"
                                            class="btn-primary btn-xs"
                                            wire:click="addTable"
                                            spinner="addTable"
                                        />
                                    </div>
                                </div>
                            @else
                                <button
                                    class="w-full text-center text-xs text-base-content/40 hover:text-base-content/60 py-1.5 border border-dashed border-base-300 rounded-lg transition-colors"
                                    wire:click="openTableForm({{ $i }})"
                                >
                                    + {{ __('Ajouter une table') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between mt-6">
                <x-button
                    label="{{ __('Passer cette étape') }}"
                    class="btn-ghost btn-sm"
                    wire:click="skipStep7"
                />
                <x-button
                    label="{{ __('Suivant') }}"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeStep7"
                    spinner="completeStep7"
                />
            </div>
        </div>
    @endif

    {{-- STEP 8 — Terminé --}}
    @if ($step == '8')
        <div class="animate-in fade-in duration-500 text-center py-4">
            <div class="w-16 h-16 bg-success/15 rounded-full flex items-center justify-center mx-auto mb-4">
                <x-icon name="o-check-badge" class="w-8 h-8 text-success" />
            </div>

            <h2 class="text-2xl font-bold text-base-content">{{ __('Installation terminée !') }}</h2>
            <p class="mt-2 text-base-content/60">{{ __('Votre application est prête. Voici un résumé de ce qui a été configuré :') }}</p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 mb-8">
                @php
                    $totalTables = collect($tables)->flatten(1)->count();
                @endphp

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-user-circle" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Admin') }}</p>
                    <p class="text-xs text-base-content/60 truncate">{{ $email }}</p>
                </div>

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-building-office" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Club') }}</p>
                    <p class="text-xs text-base-content/60 truncate">{{ $clubName ?: $licence }}</p>
                </div>

                <div class="p-3 bg-success/10 border border-success/20 rounded-xl">
                    <x-icon name="o-calendar" class="w-6 h-6 text-success mx-auto mb-1" />
                    <p class="text-xs font-semibold text-success">{{ __('Saison') }}</p>
                    <p class="text-xs text-base-content/60">{{ $seasonName }}</p>
                </div>

                <div class="p-3 bg-{{ count($rooms) > 0 ? 'success' : 'base-200' }}/10 border border-{{ count($rooms) > 0 ? 'success' : 'base-300' }}/20 rounded-xl">
                    <x-icon name="o-map-pin" class="w-6 h-6 text-{{ count($rooms) > 0 ? 'success' : 'base-content/30' }} mx-auto mb-1" />
                    <p class="text-xs font-semibold text-{{ count($rooms) > 0 ? 'success' : 'base-content/40' }}">{{ count($rooms) }} {{ __('salle(s)') }}</p>
                    <p class="text-xs text-base-content/60">{{ $totalTables }} {{ __('table(s)') }}</p>
                </div>
            </div>

            <x-alert
                title="{{ __('Configuration email (SMTP)') }}"
                description="{{ __('La configuration du serveur d\'envoi d\'email n\'est pas couverte par cet assistant. Modifiez les variables MAIL_* dans votre fichier .env.') }}"
                icon="o-envelope"
                class="alert-warning alert-soft text-left mb-6"
            />

            <x-button
                label="{{ __('Accéder au tableau de bord') }}"
                icon-right="o-arrow-right"
                class="btn-primary btn-lg"
                wire:click="completeSetup"
                spinner="completeSetup"
            />
        </div>
    @endif
</div>
