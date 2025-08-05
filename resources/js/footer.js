// resources/js/footer.js
function showLicense() {
    const modal = document.getElementById('licenseModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

function hideLicense() {
    const modal = document.getElementById('licenseModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
}

// Attendre que le DOM soit chargé ET vérifier l'existence
document.addEventListener('DOMContentLoaded', function() {
    const licenseModal = document.getElementById('licenseModal');
    
    // Seulement ajouter les event listeners si la modal existe
    if (licenseModal) {
        // Fermer la modal en cliquant à l'extérieur
        licenseModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideLicense();
            }
        });

        // Fermer la modal avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !licenseModal.classList.contains('hidden')) {
                hideLicense();
            }
        });
    }
});

// Rendre les fonctions disponibles globalement
window.showLicense = showLicense;
window.hideLicense = hideLicense;