{{-- resources/views/admin/contacts/compose-email.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue">Composer un email</h2>
                    <p class="text-gray-600 mt-1">
                        Destinataire : <strong>{{ $contact->first_name }} {{ $contact->last_name }}</strong> 
                        ({{ $contact->email }})
                    </p>
                </div>
                <a href="{{ route('admin.contacts.show', $contact) }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    ← Retour
                </a>
            </div>

            {{-- Affichage des erreurs --}}
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <strong class="font-bold">Erreur{{ $errors->count() > 1 ? 's' : '' }} :</strong>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.contacts.send-custom-email', $contact) }}" method="POST" 
                  x-data="{ 
                      subject: '{{ old('subject', '') }}',
                      message: '{{ old('message', '') }}',
                      showPreview: false,
                      subjectTemplates: [
                          'Bienvenue dans notre club !',
                          'Informations complémentaires demandées',
                          'Réponse à votre demande de contact',
                          'Prochaines étapes pour votre adhésion'
                      ]
                  }">
                @csrf

                <!-- Template d'objet rapide -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Suggestions d'objet
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="template in subjectTemplates">
                            <button type="button"
                                    @click="subject = template"
                                    class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-full transition-colors cursor-pointer"
                                    x-text="template">
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Sujet de l'email -->
                <div class="mb-6">
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                        Sujet de l'email *
                    </label>
                    <input type="text" 
                           id="subject" 
                           name="subject" 
                           x-model="subject"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                           placeholder="Entrez le sujet de votre email"
                           required>
                </div>

                <!-- Message de l'email -->
                <div class="mb-6">
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                        Message *
                    </label>
                    <textarea id="message" 
                              name="message" 
                              rows="12"
                              x-model="message"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y"
                              placeholder="Bonjour {{ $contact->first_name }},

Merci pour votre intérêt pour notre club...

Cordialement,
L'équipe de {{ config('app.name') }}"
                              required></textarea>
                    
                    <p class="text-sm text-gray-500 mt-1">
                        Variables disponibles : {{ $contact->first_name }}, {{ $contact->last_name }}, {{ $contact->email }}
                    </p>
                </div>

                <!-- Options -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Options</h3>
                    
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" 
                               name="send_copy" 
                               value="1"
                               class="rounded border-gray-300 text-club-blue focus:ring-club-blue">
                        <span class="text-sm text-gray-700">
                            M'envoyer une copie de cet email
                        </span>
                    </label>
                </div>

                <!-- Aperçu -->
                <div class="mb-6">
                    <button type="button" 
                            @click="showPreview = !showPreview"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors">
                        <span x-text="showPreview ? 'Masquer l\'aperçu' : 'Voir l\'aperçu'"></span>
                    </button>

                    <div x-show="showPreview" 
                         x-transition
                         class="mt-4 p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <div class="mb-4">
                            <strong>À :</strong> {{ $contact->email }}<br>
                            <strong>Sujet :</strong> <span x-text="subject || '[Aucun sujet]'"></span>
                        </div>
                        <div class="border-t pt-4">
                            <div class="whitespace-pre-wrap text-sm" x-text="message || '[Aucun message]'"></div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3">
                    <a href="{{ route('admin.contacts.show', $contact) }}" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors text-center">
                        Annuler
                    </a>
                    <button type="submit" 
                            class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors"
                            :disabled="!subject || !message">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                        Envoyer l'email
                    </button>
                </div>
            </form>
        </div>
    </x-admin-block>
</x-app-layout> 