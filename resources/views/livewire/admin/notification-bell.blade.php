<div class="dropdown" x-data="{ open: false }">
    <button
        class="btn btn-ghost btn-sm btn-circle indicator"
        @click="open = !open"
        @click.outside="open = false"
    >
        @if ($unreadCount > 0)
            <span class="indicator-item badge badge-error text-xs">{{ $unreadCount }}</span>
        @endif
        <x-icon name="o-bell" class="h-5 w-5" />
    </button>

    {{-- Dropdown menu --}}
    <div
        class="dropdown-content menu rounded-box w-80 bg-base-100 p-2 shadow"
        :class="{ 'hidden': !open }"
        style="display: none"
        x-show="open"
    >
        @if ($notifications->isNotEmpty())
            <div class="max-h-96 overflow-y-auto">
                @foreach ($notifications as $notification)
                    <button
                        wire:click="markAsRead('{{ $notification->id }}')"
                        class="block w-full p-3 text-left transition-colors @if (is_null($notification->read_at)) bg-blue-50 @endif hover:bg-gray-100 rounded"
                    >
                        <div class="flex items-start gap-3">
                            <div class="mt-1 flex-shrink-0">
                                <x-icon
                                    :name="$notification->data['icon'] ?? 'o-bell'"
                                    class="h-5 w-5"
                                />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900 truncate">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </p>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">
                                    {{ $notification->data['body'] ?? '' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $notification->created_at->diffForHumans() }}
                                </p>
                            </div>
                            @if (is_null($notification->read_at))
                                <div class="flex-shrink-0 w-2 h-2 bg-blue-600 rounded-full mt-2"></div>
                            @endif
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="divider m-0"></div>

            <div class="flex gap-2">
                <a
                    href="{{ route('notifications.index') }}"
                    class="btn btn-ghost btn-sm flex-1 text-xs"
                >
                    {{ __('Voir tout') }}
                </a>
                @if ($unreadCount > 0)
                    <button
                        wire:click="markAllAsRead"
                        class="btn btn-ghost btn-sm flex-1 text-xs"
                    >
                        {{ __('Tout marquer comme lu') }}
                    </button>
                @endif
            </div>
        @else
            <div class="p-4 text-center text-sm text-gray-500">
                {{ __('Aucune notification') }}
            </div>
        @endif
    </div>
</div>
