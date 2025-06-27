

function showLicense() {
    document.getElementById('licenseModal').classList.remove('hidden');
    document.getElementById('licenseModal').classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function hideLicense() {
    document.getElementById('licenseModal').classList.add('hidden');
    document.getElementById('licenseModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// Fermer la modal en cliquant à l'extérieur
document.getElementById('licenseModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLicense();
    }
});

// Fermer la modal avec la touche Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('licenseModal').classList.contains('hidden')) {
        hideLicense();
    }
});

// Rendre les fonctions disponibles globalement pour les onclick
window.showLicense = showLicense;
window.hideLicense = hideLicense;