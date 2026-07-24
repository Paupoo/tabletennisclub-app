<div class="relative">
    <label
        for="notification-panel-toggle"
        aria-label="{{ __('Notifications') }}"
        class="btn btn-ghost btn-sm btn-circle"
    >
        <span class="relative inline-flex">
            <x-icon name="o-bell" class="h-5 w-5" />
            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-error px-0.5 text-[9px] font-bold leading-none text-error-content">
                    {{ $unreadCount }}
                </span>
            @endif
        </span>
    </label>

    {{-- Pure CSS checkbox toggle: no JS required for open/close, only the Escape
    shortcut below uses Alpine, kept isolated from the actual show/hide mechanism. --}}
    <input type="checkbox" id="notification-panel-toggle" class="peer hidden" x-data @keydown.escape.window="$el.checked = false" />

    {{-- Backdrop --}}
    <label
        for="notification-panel-toggle"
        aria-hidden="true"
        class="fixed inset-0 z-40 hidden bg-black/50 peer-checked:block"
    ></label>

    {{-- Bottom sheet --}}
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="notification-bell-heading"
        class="fixed inset-x-0 bottom-0 z-50 hidden max-h-[75vh] translate-y-full flex-col overflow-hidden rounded-t-2xl border-t-2 border-secondary bg-base-100 shadow-2xl transition-transform duration-300 peer-checked:flex peer-checked:translate-y-0"
    >
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-base-300 p-3">
            <h2 id="notification-bell-heading" class="min-w-0 truncate text-sm font-bold text-primary">
                {{ __('Notifications') }}
            </h2>
            <div class="flex min-w-0 items-center gap-2">
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllAsRead"
                        aria-label="{{ __('Tout marquer comme lu') }}"
                        class="min-w-0 truncate text-xs font-semibold text-primary"
                    >
                        {{ __('Tout marquer comme lu') }}
                    </button>
                @endif
                <label
                    for="notification-panel-toggle"
                    aria-label="{{ __('Fermer') }}"
                    class="btn btn-ghost btn-xs btn-circle shrink-0"
                >
                    <x-icon name="o-x-mark" class="h-4 w-4" />
                </label>
            </div>
        </div>

        @if ($notifications->isNotEmpty())
            <div class="flex-1 overflow-y-auto">
                @foreach ($notifications as $notification)
                    <button
                        dusk="notification-{{ $notification->id }}"
                        wire:click="markAsRead('{{ $notification->id }}')"
                        @click="document.getElementById('notification-panel-toggle').checked = false"
                        class="flex w-full items-start gap-3 border-l-4 p-3 text-left transition-colors hover:bg-base-200
                            @if (is_null($notification->read_at)) border-primary bg-primary/5 @else border-transparent @endif"
                    >
                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <x-icon
                                :name="$notification->data['icon'] ?? 'o-bell'"
                                class="h-4 w-4 text-primary"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-base-content">
                                    {{ $notification->data['title'] ?? 'Notification' }}
                                </p>
                                @if (is_null($notification->read_at))
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-secondary"></span>
                                @endif
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-xs text-base-content/70">
                                {{ $notification->data['body'] ?? '' }}
                            </p>
                            <p class="mt-1 text-xs text-base-content/45">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </button>
                @endforeach
            </div>

            <div
                class="shrink-0 border-t border-base-300 p-3"
                style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));"
            >
                <a href="{{ route('notifications.index') }}" class="btn btn-primary btn-block btn-sm">
                    {{ __('Voir toutes les notifications') }}
                </a>
            </div>
        @else
            <div class="flex flex-col items-center justify-center gap-2 p-10 text-center text-base-content/50">
                <x-icon name="o-bell-slash" class="h-8 w-8" />
                <span class="text-sm">{{ __('Aucune notification') }}</span>
            </div>
        @endif
    </div>
</div>
