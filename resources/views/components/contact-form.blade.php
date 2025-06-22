<section id="contact" class="py-20 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Contactez-Nous</h2>
            <p class="text-xl text-gray-600">
                Des questions ? Envie de nous rendre visite ? Nous serions ravis de vous entendre !
            </p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-8 animate-on-scroll">
            <form action="{{ route('contact.store') }}" method="POST" x-data="{ submitted: false }" @submit.prevent="
                fetch('{{ route('contact.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(Object.fromEntries(new FormData($event.target)))
                }).then(() => {
                    submitted = true;
                    setTimeout(() => submitted = false, 3000);
                    $event.target.reset();
                })
            ">
                @csrf
                <div class="grid md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nom Complet</label>
                        <input type="text" id="name" name="name" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Adresse Email</label>
                        <input type="email" id="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Numéro de Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                </div>
                
                <div class="mb-6">
                    <label for="interest" class="block text-sm font-medium text-gray-700 mb-2">Je suis intéressé par</label>
                    <select id="interest" name="interest" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                        <option>Devenir membre</option>
                        <option>Cours de coaching</option>
                        <option>Informations sur les tournois</option>
                        <option>Location d'installations</option>
                        <option>Autre</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="message" name="message" rows="4" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent" placeholder="Parlez-nous de votre expérience au tennis de table ou de toute question que vous avez..."></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="bg-club-blue text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-club-blue-light transition-colors transform hover:scale-105" :class="{ 'bg-club-yellow hover:bg-club-yellow': submitted }">
                        <span x-show="!submitted">Envoyer le Message</span>
                        <span x-show="submitted">Message Envoyé ! ✓</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
