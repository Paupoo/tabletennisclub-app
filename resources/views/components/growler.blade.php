{{-- Notifications Flash Session - Gardé identique --}}
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

@if (session()->has('error'))
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.dispatchEvent(new CustomEvent('notify', {
                detail: {
                    message: @json(session('error')),
                    type: 'error'
                }
            }));
        });
    </script>
@endif

{{-- Votre growler original avec améliorations responsive uniquement --}}
<div x-data="{
    notifications: [],
    add(message, type = 'info') {
        const id = Date.now();
        this.notifications.push({ id, message, type, progress: 100 });

        // Décrémentation progressive de la barre (votre logique originale)
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
    class="fixed z-50 flex flex-col gap-2 
           bottom-4 right-4 w-80 max-w-[calc(100vw-2rem)]
           sm:bottom-6 sm:right-6 sm:w-96 sm:max-w-[calc(100vw-3rem)]
           md:bottom-8 md:right-8 md:w-xl md:max-w-md">

    <!-- Template pour chaque notification (votre structure originale) -->
    <template x-for="notification in notifications" :key="notification.id">
        <div x-show="true" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4" 
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             :class="{
                'bg-green-100 border-green-500 text-green-700': notification.type === 'success',
                'bg-blue-100 border-blue-500 text-blue-700': notification.type === 'info',
                'bg-yellow-100 border-yellow-500 text-yellow-700': notification.type === 'warning',
                'bg-red-100 border-red-500 text-red-700': notification.type === 'error'
             }"
             class="rounded-lg border-l-4 p-3 sm:p-4 shadow-md flex flex-col overflow-hidden relative">

            <!-- Barre de progression (identique à votre version) -->
            <div :class="{
                'bg-green-600': notification.type === 'success',
                'bg-blue-600': notification.type === 'info',
                'bg-yellow-600': notification.type === 'warning',
                'bg-red-600': notification.type === 'error'
            }"
                class="h-1 absolute top-0 right-0 transition-all duration-100 ease-linear"
                :style="`width: ${notification.progress}%`">
            </div>

            <!-- Contenu de la notification (structure originale avec responsive) -->
            <div class="flex justify-between items-start mt-1 gap-3">
                <p x-html="notification.message" 
                   class="text-sm sm:text-base flex-1 leading-relaxed break-words"></p>

                <!-- Bouton de fermeture -->
                <button @click="remove(notification.id)"
                        class="text-gray-400 hover:text-gray-600 focus:outline-none flex-shrink-0 p-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>