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

    <!-- Section Security -->
    <x-admin.shared.form-section :separator="true" :subtitle="__('Secure your account')" :title="__('Security')">
        <div class="col-span-6 md:col-span-4">
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

    <!-- À venir : préférences de notifications & confidentialité -->
    <x-admin.shared.form-section :separator="false" :subtitle="__('What is coming next')" :title="__('Coming soon')">
        <div class="col-span-6 md:col-span-4">
            <div class="flex items-start gap-3 rounded-xl border border-dashed border-base-300 bg-base-200/40 p-4">
                <x-icon name="o-bell" class="mt-0.5 h-5 w-5 shrink-0 text-base-content/40" />
                <p class="text-sm text-base-content/60">
                    {{ __('Notification preferences (match reminders, waitlist alerts, club news) and privacy options are on their way. They will appear here as soon as they are ready.') }}
                </p>
            </div>
        </div>
    </x-admin.shared.form-section>
</div>
