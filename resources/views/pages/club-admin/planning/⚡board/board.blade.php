<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @if ($plan === null)
        {{-- ── Plan management ─────────────────────────────────────────── --}}
        <x-header progress-indicator separator :title="__('Planning board')"
            :subtitle="$season?->name" />

        @if ($canManage)
            <x-card class="mb-4">
                <form wire:submit="createPlan" class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div class="flex-1">
                        <x-input :label="__('New plan name')"
                            wire:model="newPlanName"
                            :placeholder="__('e.g. Scenario A')" />
                    </div>
                    <x-button type="submit" class="btn-primary" icon="o-plus"
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
                            class="flex items-center justify-between gap-3 py-3">
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
                            <div class="flex shrink-0 items-center gap-1">
                                <x-button class="btn-sm btn-ghost" icon="o-arrow-right"
                                    :label="__('Open')"
                                    wire:click="selectPlan({{ $p->id }})" />
                                @if ($canManage && $p->status->value !== 'archived')
                                    <x-button class="btn-sm btn-ghost" icon="o-archive-box"
                                        :tooltip="__('Archive')"
                                        wire:click="archivePlan({{ $p->id }})"
                                        wire:confirm="{{ __('Archive this plan?') }}" />
                                @endif
                                @if ($canManage)
                                    <x-button class="btn-sm btn-ghost text-error" icon="o-trash"
                                        :tooltip="__('Delete')"
                                        wire:click="deletePlan({{ $p->id }})"
                                        wire:confirm="{{ __('Delete this plan permanently?') }}" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    @else
        {{-- ── Board ───────────────────────────────────────────────────── --}}
        <x-header progress-indicator separator :title="$plan->name"
            :subtitle="$season?->name">
            <x-slot:actions>
                @if ($overCapacityCount > 0)
                    <x-badge class="badge-error gap-1"
                        :value="$overCapacityCount . ' ' . __('over capacity')" />
                @endif
                @if ($canManage)
                    <x-button class="btn-primary btn-sm" icon="o-plus"
                        :label="__('Add a group')" wire:click="openAddPack" />
                @endif
                <x-dropdown :label="__('Export')" icon="o-arrow-down-tray" class="btn-ghost btn-sm">
                    <x-menu-item :title="__('CSV')" wire:click="export('csv')" />
                    <x-menu-item :title="__('ODS')" wire:click="export('ods')" />
                    <x-menu-item :title="__('XLSX')" wire:click="export('xlsx')" />
                </x-dropdown>
                @if ($canManage)
                    <x-button class="btn-ghost btn-sm" icon="o-arrow-up-tray"
                        :label="__('Import CSV')" wire:click="openImport" />
                @endif
                <x-button class="btn-ghost btn-sm" icon="o-arrow-left"
                    :label="__('Back to plans')" wire:click="closePlan" />
            </x-slot:actions>
        </x-header>

        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach ($columns as $column)
                <div wire:key="col-{{ $column['id'] }}"
                    class="flex w-72 shrink-0 flex-col rounded-xl border border-base-200 bg-base-100">
                    {{-- Column header with capacity tension --}}
                    <div class="flex items-center justify-between gap-2 border-b border-base-200 px-3 py-2
                        {{ $column['over_capacity'] ? 'bg-error/10' : '' }}">
                        <span class="truncate font-semibold">{{ $column['name'] }}</span>
                        <div class="flex shrink-0 items-center gap-1">
                            @if ($column['is_pool'])
                                <x-badge :value="(string) $column['current_count']" class="badge-ghost badge-sm" />
                            @elseif ($column['max_participants'] === null)
                                <x-badge :value="$column['current_count'] . ' / ∞'" class="badge-ghost badge-sm" />
                            @else
                                <x-badge :value="$column['current_count'] . ' / ' . $column['max_participants']"
                                    class="badge-sm {{ $column['over_capacity'] ? 'badge-error' : 'badge-success badge-soft' }}" />
                            @endif
                            @if ($canManage && ! $column['is_pool'])
                                <x-button class="btn-ghost btn-xs btn-circle" icon="o-pencil-square"
                                    :tooltip="__('Edit group')"
                                    wire:click="editPack({{ $column['pack_id'] }})" />
                                <x-button class="btn-ghost btn-xs btn-circle text-error" icon="o-trash"
                                    :tooltip="__('Remove group')"
                                    wire:click="removePack({{ $column['pack_id'] }})"
                                    wire:confirm="{{ __('Remove this group? Its members will return to the pool.') }}" />
                            @endif
                        </div>
                    </div>

                    {{-- Member cards (drag-drop group) --}}
                    <ul class="flex min-h-24 flex-col gap-2 p-2"
                        @if ($canManage)
                            wire:sort="moveAssignment"
                            wire:sort:group="board"
                            wire:sort:group-id="{{ $column['id'] }}"
                        @endif>
                        @foreach ($column['cards'] as $card)
                            <li wire:key="card-{{ $card['id'] }}"
                                wire:sort:item="{{ $card['id'] }}"
                                class="rounded-lg border border-base-200 bg-base-200/40 p-2 {{ $canManage ? 'cursor-grab' : '' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium">{{ $card['name'] }}</span>
                                    @if ($card['ranking'])
                                        <span class="shrink-0 text-xs text-base-content/60">{{ $card['ranking'] }}</span>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    @if ($card['age_label'])
                                        <x-badge :value="$card['age_label']" class="badge-ghost badge-xs" />
                                    @endif
                                    @if ($card['is_competitive'])
                                        <x-badge :value="__('Competitive')" class="badge-success badge-soft badge-xs" />
                                    @endif
                                    @if ($card['can_drive'])
                                        <x-badge class="badge-info badge-soft badge-xs"
                                            :value="__('Drives') . ($card['seats_available'] !== null ? ' ' . $card['seats_available'] : '')" />
                                    @endif
                                    @if ($card['wants_to_be_captain'])
                                        <x-badge :value="__('Captain')" class="badge-warning badge-soft badge-xs" />
                                    @endif
                                    @if ($card['volunteer_help'])
                                        <x-badge :value="__('Volunteer')" class="badge-neutral badge-soft badge-xs" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Add / edit hypothetical pack modal --}}
        <x-modal wire:model="showPackModal"
            :title="$editingPackId ? __('Edit group') : __('Add a group')" separator>
            <div class="space-y-4">
                <x-input :label="__('Group name')" wire:model="packName"
                    :placeholder="__('e.g. Tuesday Advanced')" required />
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
                <x-button :label="__('Cancel')" @click="$wire.showPackModal = false" />
                <x-button :label="$editingPackId ? __('Save') : __('Add')" class="btn-primary"
                    wire:click="{{ $editingPackId ? 'savePack' : 'addPack' }}" spinner />
            </x-slot:actions>
        </x-modal>

        {{-- Import CSV modal --}}
        <x-modal wire:model="showImportModal" :title="__('Import CSV')" separator>
            <div class="space-y-4">
                <p class="text-sm text-base-content/60">
                    {{ __('Upload a CSV exported from this board. Members are matched by licence, then by email.') }}
                </p>
                <x-file wire:model="importFile" accept=".csv,text/csv" :label="__('CSV file')" />
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" @click="$wire.showImportModal = false" />
                <x-button :label="__('Import')" class="btn-primary"
                    wire:click="import" spinner="import" />
            </x-slot:actions>
        </x-modal>
    @endif
</div>
