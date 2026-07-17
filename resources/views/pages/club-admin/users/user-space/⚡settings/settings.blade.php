<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header separator :subtitle="__('Manage your account and privacy preferences')"
            :title="__('Settings')" />

    <!-- Section Appearance -->
    <x-admin.shared.form-section :separator="true" :subtitle="__('Select your UI preferences')" :title="__('Appearance')">
        <livewire:club-admin.users.user-space.settings.appearance-settings :user="$user" />
    </x-admin.shared.form-section>

    <!-- Section Notifications -->
    <x-admin.shared.form-section :separator="true" :subtitle="__('Choose which optional notifications you receive')" :title="__('Notifications')">
        <div class="col-span-6 md:col-span-4">
            <div class="space-y-5">
                <x-toggle class="toggle-primary"
                    :label="__('New tournaments and events')"
                    :hint="__('Be notified when the club publishes a new tournament.')"
                    wire:model.live="notifyNewTournaments" />

                <x-toggle class="toggle-primary"
                    :label="__('Interclub availability reminders')"
                    :hint="__('A reminder when your captain requests your availability.')"
                    wire:model.live="notifyAvailabilityRequests" />

                <x-toggle class="toggle-primary"
                    :label="__('Selections and lineup announcements')"
                    :hint="__('When you are selected or the weekly lineup is announced.')"
                    wire:model.live="notifyInterclubSelections" />
            </div>
            <p class="mt-4 text-xs text-base-content/50">
                {{ __('Important notices (payments, spot offers, account security) are always sent.') }}
            </p>
        </div>
    </x-admin.shared.form-section>

    <!-- Section Privacy / Contact visibility -->
    <x-admin.shared.form-section :separator="true" :subtitle="__('Choose which of your details other members can see')" :title="__('Contact visibility')">
        <div class="col-span-6 md:col-span-4">
            <div class="space-y-5">
                <x-toggle class="toggle-primary"
                    :label="__('Share my phone number')"
                    :hint="__('Visible to other club members in the directory.')"
                    wire:model.live="sharePhone" />

                <x-toggle class="toggle-primary"
                    :label="__('Share my email address')"
                    :hint="__('Visible to other club members in the directory.')"
                    wire:model.live="shareEmail" />

                <x-toggle class="toggle-primary"
                    :label="__('Share my postal address')"
                    :hint="__('Visible to other club members in the directory.')"
                    wire:model.live="shareAddress" />
            </div>
            <p class="mt-4 text-xs text-base-content/50">
                {{ __('Nothing is shared by default. The committee and your team captain keep access to organise club activities.') }}
            </p>
        </div>
    </x-admin.shared.form-section>

    <!-- Section Security (password) -->
    <x-admin.shared.form-section :separator="false" :subtitle="__('Secure your account')" :title="__('Security')">
        <div class="max-w-sm">
            <x-form wire:submit="updatePassword">
                <x-password
                    :hint="__('Minimum 8 characters, with at least 1 letter, 1 number and 1 special character')"
                    :label="__('New password')" wire:model="password" />
                <x-password :label="__('Password Confirmation')" wire:model="password_confirmation" />

                <x-slot:actions>
                    <x-button class="btn-primary" :label="__('Update password')"
                        spinner="updatePassword" type="submit" />
                </x-slot:actions>
            </x-form>
        </div>
    </x-admin.shared.form-section>

    <!-- Zone de danger (RGPD) -->
    <div class="mt-8 border-t border-error/20 pt-6">
        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-error/60">
            {{ __('Danger zone') }}
        </p>
        <x-card class="border border-error/20 bg-error/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-medium text-error">{{ __('Request account deletion') }}</p>
                    <p class="mt-0.5 text-sm text-base-content/60">
                        {{ __('Send a deletion request to the administrator. Your data will be anonymized. Note: requests with pending payments may be delayed.') }}
                    </p>
                </div>
                @if ($user->gdpr_erasure_requested_at)
                    <x-badge
                        :value="__('Request sent on :date', ['date' => $user->gdpr_erasure_requested_at->format('d/m/Y')])"
                        class="badge-warning badge-soft shrink-0" />
                @else
                    <x-button
                        class="btn-error btn-soft btn-sm shrink-0"
                        icon="o-trash"
                        :label="__('Request deletion')"
                        wire:click="requestErasure"
                        spinner="requestErasure" />
                @endif
            </div>
        </x-card>
    </div>
</div>
