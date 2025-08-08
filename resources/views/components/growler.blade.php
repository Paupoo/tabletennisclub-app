@if (session()->has('success'))
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('notify', {
                detail: {
                    message: @json(session('success')),
                    type: 'success'
                }
            }));
        });
    </script>
@endif
@if ($errors->any())
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            @foreach ($errors->all() as $error)
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        message: @json($error),
                        type: 'error'
                    }
                }));
            @endforeach
        });
    </script>
@endif

@if (session()->has('info'))
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('notify', {
                detail: {
                    message: @json(session('info')),
                    type: 'info'
                }
            }));
        });
    </script>
@endif

@if (session()->has('warning'))
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('notify', {
                detail: {
                    message: @json(session('warning')),
                    type: 'warning'
                }
            }));
        });
    </script>
@endif

<div x-data="{
    notifications: [],
    add(message, type = 'info') {
        const id = Date.now();
        this.notifications.push({ id, message, type, progress: 100 });

        // Décrémentation progressive de la barre
        const interval = setInterval(() => {
            const notif = this.notifications.find(n => n.id === id);
            if (notif) {
                notif.progress -= 0.2; // Ajustez cette valeur pour la vitesse
                if (notif.progress <= 0) {
                    clearInterval(interval);
                    this.remove(id);
                }
            } else {
                clearInterval(interval);
            }
        }, 25);
    },
    remove(id) {
        this.notifications = this.notifications.filter(notification => notification.id !== id);
    }
}"
    @notify.window="add($event.detail.message || $event.detail[0].message, $event.detail.type || $event.detail[0].type)"
    class="fixed bottom-8 right-8 z-50 flex flex-col gap-2 w-xl">

    <!-- Template pour chaque notification -->
    <template x-for="notification in notifications" :key="notification.id">
        <div x-show="true" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-4"
            :class="{
                'bg-green-100 border-green-500 text-green-700': notification.type === 'success',
                'bg-blue-100 border-blue-500 text-blue-700': notification.type === 'info',
                'bg-yellow-100 border-yellow-500 text-yellow-700': notification.type === 'warning',
                'bg-red-100 border-red-500 text-red-700': notification.type === 'error'
            }"
            class="rounded-lg border-l-4 p-4 shadow-md flex flex-col overflow-hidden relative">

            <!-- Barre de progression -->
            <div :class="{
                'bg-green-600': notification.type === 'success',
                'bg-blue-600': notification.type === 'info',
                'bg-yellow-600': notification.type === 'warning',
                'bg-red-600': notification.type === 'error'
            }"
                class="h-1 absolute top-0 right-0 transition-all duration-100 ease-linear"
                :style="`width: ${notification.progress}%`">
            </div>

            <!-- Contenu de la notification -->
            <div class="flex justify-between items-center mt-1">
                <p x-text="notification.message" class="text-md"></p>

                <!-- Bouton de fermeture -->
                <button @click="remove(notification.id)"
                    class="text-gray-400 hover:text-gray-600 focus:outline-hidden ml-4">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>
