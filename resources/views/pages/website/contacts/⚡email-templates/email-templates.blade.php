<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <x-header progress-indicator separator :subtitle="__('Editable response templates with variables')"
        :title="__('Email templates')">
        <x-slot:actions>
            <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New template')" wire:click="openCreate" />
        </x-slot:actions>
    </x-header>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <x-card>
        <x-table :headers="$headers" :rows="$templates" wire:key="email-templates-table">
            @scope('cell_name', $template)
                <span class="font-medium">{{ $template->name }}</span>
            @endscope

            @scope('cell_key', $template)
                <code class="text-xs text-base-content/60">{{ $template->key }}</code>
            @endscope

            @scope('cell_apply_status', $template)
                @if ($template->apply_status)
                    <x-badge class="badge-soft badge-info" :value="ucfirst($template->apply_status)" />
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_flags', $template)
                <div class="flex flex-wrap gap-1">
                    @if ($template->is_questionnaire)
                        <x-badge class="badge-soft badge-warning" :value="__('Questionnaire')" />
                    @endif
                    @unless ($template->is_active)
                        <x-badge class="badge-soft badge-ghost" :value="__('Inactive')" />
                    @endunless
                </div>
            @endscope

            @scope('cell_actions', $template)
                <div class="flex items-center justify-end gap-1">
                    <x-button class="btn-ghost btn-sm"
                        :icon="$template->is_active ? 'o-eye' : 'o-eye-slash'"
                        :tooltip="$template->is_active ? __('Deactivate') : __('Activate')"
                        wire:click="toggleActive({{ $template->id }})" :aria-label="$template->is_active ? __('Deactivate') : __('Activate')" />
                    <x-button class="btn-ghost btn-sm" icon="o-pencil"
                        :tooltip="__('Edit')" wire:click="openEdit({{ $template->id }})" :aria-label="__('Edit')" />
                    <x-button class="btn-ghost btn-sm text-error" icon="o-trash"
                        :tooltip="__('Delete')" wire:click="confirmDelete({{ $template->id }})" :aria-label="__('Delete')" />
                </div>
            @endscope

            <x-slot:empty>
                <x-empty-state
                    icon="o-envelope"
                    :heading="__('No templates yet')"
                    :message="__('Create the first response template to reply to and gather information from contacts.')"
                    :buttonText="__('New template')"
                    wire:click="openCreate" />
            </x-slot:empty>
        </x-table>
    </x-card>

    {{-- ================================================================
         CREATE / EDIT MODAL
    ================================================================ --}}
    <x-app-modal wire:model="formModal" separator
        :title="$editingId ? __('Edit template') : __('New template')">
        <div class="space-y-4">
            <x-input :label="__('Name')" wire:model="formName"
                :placeholder="__('E.g. Welcome message')" />

            <x-input :label="__('Key')" wire:model="formKey"
                :readonly="$keyLocked"
                :hint="$keyLocked
                    ? __('System template — the key cannot be changed.')
                    : __('Lowercase letters, digits and underscores only.')"
                placeholder="welcome_message" />

            <x-input :label="__('Subject')" wire:model="formSubject" />

            <div>
                <x-textarea :label="__('Body')" wire:model="formBody" rows="10" />

                {{-- Variable helper near the body field --}}
                <div class="mt-2 rounded-lg border border-base-200 bg-base-200/40 p-3 text-xs text-base-content/70">
                    <p class="mb-1 font-medium">{{ __('Available variables:') }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($availableVariables as $variable)
                            <code class="rounded bg-base-300/60 px-1.5 py-0.5">{{ $variable }}</code>
                        @endforeach
                    </div>
                </div>
            </div>

            <x-select :label="__('Applied status')" :options="$statusOptions"
                wire:model="formApplyStatus"
                :hint="__('Status applied to the contact when this template is sent.')" />

            <x-toggle :label="__('Information questionnaire')" wire:model="formIsQuestionnaire"
                :hint="__('Mark templates oriented towards gathering missing information.')" />
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('formModal', false)" />
            <x-button class="btn-primary" icon="o-check" :label="__('Save')"
                wire:click="saveTemplate" spinner="saveTemplate" />
        </x-slot:actions>
    </x-app-modal>

    {{-- ================================================================
         DELETE MODAL
    ================================================================ --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this template?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="deleteTemplate">
        <p>{{ __('Are you sure you want to delete this template? This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
