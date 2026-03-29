<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

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
                            <x-toggle class="toggle-primary" wire:model="public_profile" />
                        </div>

                        <div class="flex items-center justify-between py-4">
                            <div>
                                <div class="text-sm font-bold">{{ __('Show Phone Number') }}</div>
                                <div class="text-xs opacity-60">{{ __('Visible to all registered members') }}</div>
                            </div>
                            <x-toggle class="toggle-primary" wire:model="public_phone_number" />
                        </div>

                        <div class="flex items-center justify-between py-4">
                            <div>
                                <div class="text-sm font-bold">{{ __('Show Email Address') }}</div>
                                <div class="text-xs opacity-60">
                                    {{ __('Display your email on your public profile') }}
                                </div>
                            </div>
                            <x-toggle class="toggle-primary" wire:model="public_email" />
                        </div>
                    </div>

                        <x-admin.shared.info-alert>
                        {{ __('Your profile is visible to other members by default. You can choose to hide certain information or make your profile private. Your captain will always have access to your phone number for emergencies.') }}
                    </x-admin.shared.info-alert>
                </div>
            </div>