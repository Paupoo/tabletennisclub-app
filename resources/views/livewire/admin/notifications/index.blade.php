<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Notifications') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Gérez vos notifications') }}</p>
        </div>

        @if ($unreadCount > 0)
            <button
                wire:click="markAllAsRead"
                class="btn btn-primary btn-sm"
            >
                {{ __('Tout marquer comme lu') }}
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="flex gap-2">
        <button
            wire:click="$set('filter', 'all')"
            class="btn btn-sm @if ($filter === 'all') btn-active @else btn-ghost @endif"
        >
            {{ __('Toutes') }}
        </button>
        <button
            wire:click="$set('filter', 'unread')"
            class="btn btn-sm @if ($filter === 'unread') btn-active @else btn-ghost @endif"
        >
            {{ __('Non lues') }} @if ($unreadCount > 0)<span class="badge badge-error">{{ $unreadCount }}</span>@endif
        </button>
    </div>

    {{-- Notifications list --}}
    <div class="space-y-2">
        @forelse ($notifications as $notification)
            <button
                wire:click="markAsRead('{{ $notification->id }}')"
                class="block w-full p-4 text-left transition-colors @if (is_null($notification->read_at)) bg-blue-50 @else bg-gray-50 @endif hover:bg-gray-100 rounded-lg border border-gray-200"
            >
                <div class="flex items-start gap-4">
                    <div class="mt-1 flex-shrink-0">
                        <x-icon
                            :name="$notification->data['icon'] ?? 'o-bell'"
                            class="h-6 w-6 text-gray-600"
                        />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </h3>
                                <p class="text-gray-600 mt-1">
                                    {{ $notification->data['body'] ?? '' }}
                                </p>
                            </div>

                            @if (is_null($notification->read_at))
                                <div class="flex-shrink-0 w-3 h-3 bg-blue-600 rounded-full mt-1"></div>
                            @endif
                        </div>

                        <p class="text-sm text-gray-500 mt-2">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            </button>
        @empty
            <div class="text-center py-12">
                <x-icon name="o-bell" class="h-12 w-12 text-gray-300 mx-auto mb-4" />
                <p class="text-gray-500">{{ __('Aucune notification') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if ($notifications->hasPages())
        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
