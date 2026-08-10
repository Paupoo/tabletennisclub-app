<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @if ($plan === null)
        {{-- ── Plan management ─────────────────────────────────────────── --}}
        <x-header progress-indicator separator :title="__('Planning board')"
            :subtitle="$season?->name" />

        <div class="mx-auto w-full max-w-3xl space-y-6">
            @if ($season === null)
                <x-admin.shared.missing-season-state
                    :message="__('A plan is composed from an active season. Open one to start composing training groups.')" />
            @elseif ($canManage)
                <x-card :title="__('Create a new plan')"
                    :subtitle="__('Starts a draft from the active season — packs and current enrolments are copied.')"
                    separator>
                    <form wire:submit="createPlan" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <x-input class="flex-1" :label="__('New plan name')"
                            wire:model="newPlanName"
                            :placeholder="__('e.g. Scenario A')" />
                        <x-button type="submit" class="btn-primary w-full sm:w-auto" icon="o-plus"
                            :label="__('Create from season')" spinner="createPlan" />
                    </form>
                </x-card>
            @endif

            <x-card>
                @if ($plans->isEmpty())
                    <x-empty-state icon="o-rectangle-stack"
                        :heading="__('No plans yet')"
                        :message="__('Create a plan to start composing training groups.')" />
                @else
                    <div class="flex flex-col divide-y divide-base-200">
                        @foreach ($plans as $p)
                            <div wire:key="plan-{{ $p->id }}"
                                class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate font-medium">{{ $p->name }}</span>
                                        @if ($p->status->value === 'archived')
                                            <x-badge :value="$p->status->getLabel()" class="badge-ghost badge-sm" />
                                        @else
                                            <x-badge :value="$p->status->getLabel()" class="badge-primary badge-soft badge-sm" />
                                        @endif
                                    </div>
                                    <p class="text-xs text-base-content/60">
                                        {{ $p->packs_count }} {{ __('packs') }} ·
                                        {{ $p->assignments_count }} {{ __('members') }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 flex-wrap items-center gap-1">
                                    <x-button class="btn-sm btn-primary btn-soft" icon="o-arrow-right"
                                        :label="__('Open')"
                                        wire:click="selectPlan({{ $p->id }})" />
                                    @if ($canManage && $p->status->value !== 'archived')
                                        <x-button class="btn-sm btn-ghost" icon="o-archive-box"
                                            :tooltip="__('Archive')"
                                            wire:click="confirmArchivePlan({{ $p->id }})" :aria-label="__('Archive')" />
                                    @endif
                                    @if ($canManage)
                                        <x-button class="btn-sm btn-ghost text-error" icon="o-trash"
                                            :tooltip="__('Delete')"
                                            wire:click="confirmDeletePlan({{ $p->id }})" :aria-label="__('Delete')" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        {{-- Confirm modals (project modals, no native JS confirm) --}}
        <x-confirm-modal model="confirmArchiveModal" :title="__('Archive this plan?')"
            :confirmLabel="__('Archive')" confirmClass="btn-warning" confirmAction="archivePlan" :open="$confirmArchiveModal">
            <p>{{ __('The plan is hidden from the active list. You can still delete it later.') }}</p>
        </x-confirm-modal>

        <x-confirm-modal model="confirmDeleteModal" :title="__('Delete this plan permanently?')"
            :subtitle="__('Warning!')" :confirmLabel="__('Delete')" confirmAction="deletePlan" :open="$confirmDeleteModal">
            <p>{{ __('This permanently deletes the plan and its layout. This action is irreversible.') }}</p>
        </x-confirm-modal>
    @else
        {{-- ── Board ───────────────────────────────────────────────────── --}}
        <x-header progress-indicator separator :title="$plan->name"
            :subtitle="$season?->name">
            <x-slot:actions>
                <div class="flex w-full flex-wrap items-center justify-end gap-2">
                    <x-button class="btn-ghost btn-sm mr-auto" icon="o-arrow-left"
                        :label="__('Back to plans')" wire:click="closePlan" />
                    @if ($overCapacityCount > 0)
                        <x-badge class="badge-error gap-1"
                            :value="$overCapacityCount . ' ' . __('over capacity')" />
                    @endif
                    <x-dropdown :label="__('More actions')" icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                        @if ($canManage)
                            <x-menu-item icon="o-sparkles" :title="__('Suggest a layout')"
                                wire:click="optimize" spinner="optimize" />
                        @endif
                        <x-menu-item icon="o-arrow-down-tray" :title="__('Export CSV')" wire:click="export('csv')" />
                        <x-menu-item icon="o-arrow-down-tray" :title="__('Export ODS')" wire:click="export('ods')" />
                        <x-menu-item icon="o-arrow-down-tray" :title="__('Export XLSX')" wire:click="export('xlsx')" />
                        @if ($canManage)
                            <x-menu-item icon="o-arrow-up-tray" :title="__('Import CSV')" wire:click="openImport" />
                        @endif
                    </x-dropdown>
                    @if ($canManage)
                        <x-button class="btn-primary btn-sm" icon="o-plus"
                            :label="__('Add a group')" wire:click="openAddPack" />
                    @endif
                </div>
            </x-slot:actions>
        </x-header>

        {{-- Responsive grid of columns: wraps instead of scrolling horizontally,
             each column is capped and scrolls vertically inside (no endless columns). --}}
        <div class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($columns as $column)
                <div wire:key="col-{{ $column['id'] }}"
                    class="flex max-h-[28rem] flex-col rounded-xl border bg-base-100
                        {{ $column['over_capacity'] ? 'border-error/40' : 'border-base-300' }}">
                    {{-- Column header (stays visible above the scroll) with capacity tension --}}
                    <div class="flex shrink-0 items-center justify-between gap-2 rounded-t-xl border-b border-base-300 px-3 py-2
                        {{ $column['over_capacity'] ? 'bg-error/10' : 'bg-base-200/40' }}">
                        <span class="truncate text-sm font-semibold">{{ $column['name'] }}</span>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($column['is_pool'])
                                @php $poolFiltered = $poolAgeFilter !== '' || $poolSeriesFilter !== ''; @endphp
                                <x-badge
                                    :value="$poolFiltered ? $column['current_count'] . ' / ' . ($column['total_count'] ?? $column['current_count']) : (string) $column['current_count']"
                                    class="badge-ghost badge-sm" />
                            @elseif ($column['max_participants'] === null)
                                <x-badge :value="$column['current_count'] . ' / ∞'" class="badge-ghost badge-sm" />
                            @else
                                <x-badge :value="$column['current_count'] . ' / ' . $column['max_participants']"
                                    class="badge-sm {{ $column['over_capacity'] ? 'badge-error' : 'badge-success badge-soft' }}" />
                            @endif
                            @if ($canManage && ! $column['is_pool'])
                                <x-button class="btn-ghost btn-xs btn-circle" icon="o-pencil-square"
                                    :tooltip="__('Edit group')"
                                    wire:click="editPack({{ $column['pack_id'] }})" :aria-label="__('Edit group')" />
                                <x-button class="btn-ghost btn-xs btn-circle text-error" icon="o-trash"
                                    :tooltip="__('Remove group')"
                                    wire:click="confirmRemovePack({{ $column['pack_id'] }})" :aria-label="__('Remove group')" />
                            @endif
                        </div>
                    </div>

                    {{-- Pool-only quick filters (age category + ranking series).
                         Visible to everyone; drag-drop still works on shown cards. --}}
                    @if ($column['is_pool'])
                        <div class="flex shrink-0 items-center gap-2 border-b border-base-300 px-2 py-1.5">
                            <x-select wire:model.live="poolAgeFilter"
                                :options="$poolAgeOptions"
                                :placeholder="__('All ages')" placeholder-value=""
                                class="select-xs" :label="null" />
                            <x-select wire:model.live="poolSeriesFilter"
                                :options="$poolSeriesOptions"
                                :placeholder="__('All series')" placeholder-value=""
                                class="select-xs" :label="null" />
                        </div>
                    @endif

                    {{-- Scrollable member list (drag-drop group) --}}
                    <ul class="min-h-16 flex-1 space-y-1.5 overflow-y-auto p-2"
                        @if ($canManage)
                            wire:sort="moveAssignment"
                            wire:sort:group="board"
                            wire:sort:group-id="{{ $column['id'] }}"
                        @endif>
                        @foreach ($column['cards'] as $card)
                            <li wire:key="card-{{ $card['id'] }}"
                                wire:sort:item="{{ $card['id'] }}"
                                class="rounded-md border border-base-300 bg-base-100 px-2 py-1.5 shadow-sm {{ $canManage ? 'cursor-grab active:cursor-grabbing' : '' }}">
                                <div class="flex items-center gap-1.5">
                                    <span class="grow truncate text-sm font-medium">{{ $card['name'] }}</span>
                                    @if ($card['age_label'])
                                        <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-base-content/40">
                                            {{ \Illuminate\Support\Str::substr($card['age_label'], 0, 3) }}</span>
                                    @endif
                                    @if ($card['ranking'])
                                        <span class="shrink-0 rounded bg-base-200 px-1 text-xs font-bold text-base-content/70">{{ $card['ranking'] }}</span>
                                    @endif
                                </div>
                                @if ($card['is_competitive'] || $card['can_drive'] || $card['wants_to_be_captain'] || $card['volunteer_help'])
                                    <div class="mt-1 flex items-center gap-2 text-base-content/45">
                                        @if ($card['is_competitive'])
                                            <span title="{{ __('Competitive') }}"><x-icon name="o-trophy" class="h-3.5 w-3.5 text-success" /></span>
                                        @endif
                                        @if ($card['can_drive'])
                                            <span class="inline-flex items-center gap-0.5 text-info" title="{{ __('Drives') }}">
                                                <x-icon name="o-truck" class="h-3.5 w-3.5" />
                                                @if ($card['seats_available'] !== null)<span class="text-xs font-semibold">{{ $card['seats_available'] }}</span>@endif
                                            </span>
                                        @endif
                                        @if ($card['wants_to_be_captain'])
                                            <span title="{{ __('Captain') }}"><x-icon name="o-megaphone" class="h-3.5 w-3.5 text-warning-content" /></span>
                                        @endif
                                        @if ($card['volunteer_help'])
                                            <span title="{{ __('Volunteer') }}"><x-icon name="o-hand-raised" class="h-3.5 w-3.5" /></span>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Add / edit hypothetical pack modal --}}
        <x-app-modal wire:model="showPackModal"
            :title="$editingPackId ? __('Edit group') : __('Add a group')" separator :open="$showPackModal">
            <div class="space-y-4">
                <x-input :label="__('Group name')" wire:model="packName"
                    :placeholder="__('e.g. Tuesday Advanced')" />
                <x-input :label="__('Level')" wire:model="packLevel"
                    :placeholder="__('e.g. B, beginners…')" />
                <x-select :label="__('Day of week')" wire:model="packDayOfWeek"
                    :options="[
                        ['id' => 1, 'name' => __('Monday')],
                        ['id' => 2, 'name' => __('Tuesday')],
                        ['id' => 3, 'name' => __('Wednesday')],
                        ['id' => 4, 'name' => __('Thursday')],
                        ['id' => 5, 'name' => __('Friday')],
                        ['id' => 6, 'name' => __('Saturday')],
                        ['id' => 7, 'name' => __('Sunday')],
                    ]"
                    :placeholder="__('Any day')" placeholder-value="" />
                <x-input type="number" min="0" :label="__('Capacity')"
                    wire:model="packMaxParticipants"
                    :hint="__('Leave empty for unlimited.')" />
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="closePackModal" />
                <x-button :label="$editingPackId ? __('Save') : __('Add')" class="btn-primary"
                    wire:click="{{ $editingPackId ? 'savePack' : 'addPack' }}" spinner />
            </x-slot:actions>
        </x-app-modal>

        {{-- Import CSV modal --}}
        <x-app-modal wire:model="showImportModal" :title="__('Import CSV')" separator :open="$showImportModal">
            <div class="space-y-4">
                <p class="text-sm text-base-content/60">
                    {{ __('Upload a CSV exported from this board. Members are matched by licence, then by email.') }}
                </p>
                <x-file wire:model="importFile" accept=".csv,text/csv" :label="__('CSV file')" />
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('showImportModal', false)" />
                <x-button :label="__('Import')" class="btn-primary"
                    wire:click="import" spinner="import" />
            </x-slot:actions>
        </x-app-modal>

        {{-- Remove group confirm modal (project modal, no native JS confirm) --}}
        <x-confirm-modal model="confirmRemovePackModal" :title="__('Remove this group?')"
            :confirmLabel="__('Remove')" confirmAction="removePack" :open="$confirmRemovePackModal">
            <p>{{ __('Its members will return to the pool.') }}</p>
        </x-confirm-modal>
    @endif
</div>
