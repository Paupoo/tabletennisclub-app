<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>
    
    <x-header separator subtitle="{{ __('Manage your account and privacy preferences') }}"
            title="{{ __('Settings') }}" />

        <x-form wire:submit="save">

            <!-- Section Appearance -->
            <x-admin.shared.form-section :separator="true" :subtitle="__('Select your UI preferences')" :title="__('Appearance')">
                <livewire:club-admin.users.user-space.settings.appearance-settings :user="$user" />
            </x-admin.shared.form-section>

            <!-- Section Privacy -->
            <x-admin.shared.form-section :separator="true" :subtitle="__('Select the information you share with other members.')" :title="__('Privacy')">
                <livewire:club-admin.users.user-space.settings.privacy-settings />
            </x-admin.shared.form-section>

            <!-- Section Notifications -->
            <x-admin.shared.form-section :separator="true" :subtitle="__('Set up your reminders and notifications preferences')" :title="__('Notifications')">
                <div class="col-span-6 md:col-span-4">
                    <x-checkbox label="{{ __('Match reminders (24h before)') }}" wire:model="notification_match" />
                    <x-checkbox label="{{ __('Results of my team (Ottignies B)') }}"
                        wire:model="notification_team_result" />
                    <x-checkbox label="{{ __('New training spots available') }}"
                        wire:model="notification_new_training" />
                    <x-checkbox label="{{ __('Waitlist availability alerts') }}"
                        wire:model="notification_waiting_list" />
                    <x-checkbox label="{{ __('Club news and events') }}" wire:model="notification_news_events" />
                </div>
            </x-admin.shared.form-section>

            <!-- Section Security -->
            <x-admin.shared.form-section :separator="false" :subtitle="__('Secure your account')" :title="__('Security')">
                <div class="col-span-6 md:col-span-4">
                    <x-password
                        hint="{{ __('Minimum 8 charachters, with at least 1 letter, 1 number and 1 special character') }}"
                        label="Password" wire:model="password" />
                    <x-password label="Password Confirmation" wire:model="password_confirmation" />
                </div>
            </x-admin.shared.form-section>

            {{-- Pas de x-slot:actions, on met les boutons dans le slot principal --}}
            <div class="col-span-6 mt-6 flex justify-end gap-3">
                <x-button label="{{ __('Reset') }}" />
                <x-button class="btn-primary" label="{{ __('Update') }}" spinner="save" type="submit" />
            </div>
        </x-form>
</div>