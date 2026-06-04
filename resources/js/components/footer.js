// resources/js/footer.js

/**
 * Gestion de la modale Licence MIT
 */
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

/**
 * Gestion de la modale Politique de Confidentialité
 */
function showPrivacyPolicy() {
    const modal = document.getElementById('privacyModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
}

function hidePrivacyPolicy() {
    const modal = document.getElementById('privacyModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
}

/**
 * Fonction générique pour fermer une modale
 * @param {string} modalId - ID de la modale à fermer
 * @param {Function} hideFunction - Fonction spécifique de fermeture
 */
function closeModal(modalId, hideFunction) {
    const modal = document.getElementById(modalId);
    if (modal && !modal.classList.contains('hidden')) {
        hideFunction();
    }
}

/**
 * Initialisation des event listeners au chargement du DOM
 */
document.addEventListener('DOMContentLoaded', function() {
    const licenseModal = document.getElementById('licenseModal');
    const privacyModal = document.getElementById('privacyModal');
    
    // Configuration pour la modale Licence MIT
    if (licenseModal) {
        // Fermer en cliquant à l'extérieur
        licenseModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideLicense();
            }
        });
    }
    
    // Configuration pour la modale Politique de Confidentialité
    if (privacyModal) {
        // Fermer en cliquant à l'extérieur
        privacyModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hidePrivacyPolicy();
            }
        });
    }
    
    // Fermer les modales avec la touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('licenseModal', hideLicense);
            closeModal('privacyModal', hidePrivacyPolicy);
        }
    });
});

// Rendre les fonctions disponibles globalement
window.showLicense = showLicense;
window.hideLicense = hideLicense;
window.showPrivacyPolicy = showPrivacyPolicy;
window.hidePrivacyPolicy = hidePrivacyPolicy;