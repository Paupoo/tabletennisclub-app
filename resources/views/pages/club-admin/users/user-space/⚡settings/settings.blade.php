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
                <div class="col-span-6 md:col-span-4">
                    <div class="space-y-4">
                        

                        <div class="divide-base-200 divide-y">
                            <div class="flex items-center justify-between py-4">
                                <div>
                                    <div class="text-sm font-bold">{{ __('Public Profile') }}</div>
                                    <div class="text-xs opacity-60">
                                        {{ __('Allow other members to find you in the directory') }}
                                    </div>
                                </div>
                                <x-toggle class="toggle-primary" wire:model="public_profile" disabled />
                            </div>

                            <div class="flex items-center justify-between py-4">
                                <div>
                                    <div class="text-sm font-bold">{{ __('Show Phone Number') }}</div>
                                    <div class="text-xs opacity-60">{{ __('Visible to all registered members') }}</div>
                                </div>
                                <x-toggle class="toggle-primary" wire:model="public_phone_number" disabled />
                            </div>

                            <div class="flex items-center justify-between py-4">
                                <div>
                                    <div class="text-sm font-bold">{{ __('Show Email Address') }}</div>
                                    <div class="text-xs opacity-60">
                                        {{ __('Display your email on your public profile') }}
                                    </div>
                                </div>
                                <x-toggle class="toggle-primary" wire:model="public_email" disabled />
                            </div>
                        </div>

                            <x-admin.shared.info-alert>
                            {{ __('Your profile is visible to other members by default. You can choose to hide certain information or make your profile private. Your captain will always have access to your phone number for emergencies.') }}
                        </x-admin.shared.info-alert>
                    </div>
                </div>
            </x-admin.shared.form-section>

            <!-- Section Notifications -->
            <x-admin.shared.form-section :separator="true" :subtitle="__('Set up your reminders and notifications preferences')" :title="__('Notifications')">
                <div class="col-span-6 md:col-span-4">
                    <x-checkbox label="{{ __('Match reminders (24h before)') }}" wire:model="notification_match" disabled />
                    <x-checkbox label="{{ __('Results of my team (Ottignies B)') }}"
                        wire:model="notification_team_result" disabled />
                    <x-checkbox label="{{ __('New training spots available') }}"
                        wire:model="notification_new_training" disabled />
                    <x-checkbox label="{{ __('Waitlist availability alerts') }}"
                        wire:model="notification_waiting_list" disabled />
                    <x-checkbox label="{{ __('Club news and events') }}" wire:model="notification_news_events" disabled />
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