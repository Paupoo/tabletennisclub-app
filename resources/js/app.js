import "./bootstrap"
import "./news-filter"
import "./footer"

// Configuration Alpine.js pour les animations
Alpine.data("scrollAnimations", () => ({
  init() {
    // Intersection Observer pour les animations au scroll
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible")
          }
        })
      },
      { threshold: 0.1 },
    )

    document.querySelectorAll(".animate-on-scroll").forEach((el) => {
      observer.observe(el)
    })
  },
}))

Alpine.data("priceCalculator", () => ({
  familyMembers: 1,
  competitors: 0,
  trainingSessions: 0,
  // Méthode pour valider que le nombre de compétiteurs ne dépasse pas le nombre de membres
  validateCompetitors() {
    if (this.competitors > this.familyMembers) {
      this.competitors = 0;
    }
  },

  // Calcule le nombre de membres récréatifs
  getRecreationalMembers() {
    return this.familyMembers - this.competitors;
  },

  // Calcule le total
  calculateBase() {
    // Licences : récréatives (60€) + compétition (125€)
    const recreationalCost = this.getRecreationalMembers() * 60;
    const competitionCost = this.competitors * 125;
    const base = recreationalCost + competitionCost;

    return base;
  },

  // Calcule le total
  calculateTotal() {
    // Licences : récréatives (60€) + compétition (125€)
    const recreationalCost = this.getRecreationalMembers() * 60;
    const competitionCost = this.competitors * 125;
    const base = recreationalCost + competitionCost;

    // Séances d'entraînement
    let training = 0;
    if (this.trainingSessions > 0) {
      if (this.trainingSessions == 1) {
        training += this.familyMembers > 1 ? 80 : 90;
      } else if (this.trainingSessions > 1) {
        const firstSession = this.familyMembers > 1 ? 80 : 90;
        const additionalSessions = (this.trainingSessions - 1) * 80;
        training = firstSession + additionalSessions;
      }
    }

    return base + training;
  },

  // Nouvelle méthode pour naviguer vers le contact avec les données
  goToContactWithData() {
    const params = new URLSearchParams({
      interest: 'join',
      familyMembers: this.familyMembers,
      competitors: this.competitors,
      trainingSessions: this.trainingSessions
    });

    // Scroll vers la section contact avec les paramètres
    const contactSection = document.getElementById('contact');
    if (contactSection) {
      // Ajouter les paramètres à l'URL sans rechargement
      const currentUrl = new URL(window.location.href);
      currentUrl.hash = 'contact';
      params.forEach((value, key) => {
        currentUrl.searchParams.set(key, value);
      });
      window.history.pushState({}, '', currentUrl);

      // Scroll vers la section
      contactSection.scrollIntoView({ behavior: 'smooth' });

      // Déclencher l'événement pour mettre à jour le formulaire
      window.dispatchEvent(new CustomEvent('prepopulateContact', {
        detail: {
          interest: 'join',
          familyMembers: this.familyMembers,
          competitors: this.competitors,
          trainingSessions: this.trainingSessions
        }
      }));
    }
  }
}));

// Configuration pour le formulaire de contact
Alpine.data("contactForm", (oldInterest = '', oldFamily = 1, oldCompetitors = 0, oldSessions = 0) => ({
  submitted: false,
  loading: false,
  showMembershipFields: false,
  selectedInterest: oldInterest,

  // Champs pour l'adhésion (réutilisation du calculateur)
  familyMembers: oldFamily,
  competitors: oldCompetitors,
  trainingSessions: oldSessions,

  init() {
    // Initialiser en fonction de old() côté Blade
    this.showMembershipFields = this.selectedInterest === 'join';
    // Vérifier les paramètres URL au chargement
    this.checkUrlParams();

    // Écouter l'événement de prépopulation
    window.addEventListener('prepopulateContact', (event) => {
      this.prepopulateFromData(event.detail);
    });
  },

  // Vérifier les paramètres URL pour prépopuler
  checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const interest = urlParams.get('interest');

    if (interest === 'join') {
      const familyMembers = urlParams.get('familyMembers');
      const competitors = urlParams.get('competitors');
      const trainingSessions = urlParams.get('trainingSessions');

      this.prepopulateFromData({
        interest,
        familyMembers: parseInt(familyMembers) || 1,
        competitors: parseInt(competitors) || 0,
        trainingSessions: parseInt(trainingSessions) || 0
      });
    }
  },

  // Prépopuler le formulaire avec les données
  prepopulateFromData(data) {
    this.selectedInterest = data.interest;
    this.familyMembers = data.familyMembers;
    this.competitors = data.competitors;
    this.trainingSessions = data.trainingSessions;

    // Déclencher l'affichage des champs d'adhésion
    this.showMembershipFields = data.interest === 'join';

    // Mettre à jour le select dans le DOM
    this.$nextTick(() => {
      const selectElement = document.getElementById('interest');
      if (selectElement) {
        selectElement.value = data.interest;
        selectElement.dispatchEvent(new Event('change'));
      }
    });
  },

  // Méthode appelée quand le type de demande change
  onRequestTypeChange(event) {
    this.showMembershipFields = event.target.value === 'join';
    // Réinitialiser les valeurs si on change d'option
    if (!this.showMembershipFields) {
      this.familyMembers = 1;
      this.competitors = 0;
      this.trainingSessions = 0;
    }
  },

  // Méthode pour valider que le nombre de compétiteurs ne dépasse pas le nombre de membres
  validateCompetitors() {
    if (this.competitors > this.familyMembers) {
      this.competitors = 0;
    }
  },

  // Calcule le nombre de membres récréatifs
  getRecreationalMembers() {
    return this.familyMembers - this.competitors;
  },

  // Calcule le coût des séances d'entraînement
  calculateTrainingCost() {

    const trainingSessions = parseInt(this.trainingSessions);

    if (trainingSessions === 0) return 0;

    let training = 0;
    if (trainingSessions === 1) {
      training += this.familyMembers > 1 ? 80 : 90;
    } else if (trainingSessions > 1) {
      const firstSession = this.familyMembers > 1 ? 80 : 90;
      const additionalSessions = (trainingSessions - 1) * 80;
      training = firstSession + additionalSessions;
    }

    return training;
  },

  // Calcule le total (même logique que le calculateur)
  calculateTotal() {
    if (!this.showMembershipFields) return 0;

    const recreationalCost = this.getRecreationalMembers() * 60;
    const competitionCost = this.competitors * 125;
    const base = recreationalCost + competitionCost;

    return base + this.calculateTrainingCost();
  },

  async submitForm(event) {
    this.loading = true
    try {
        const formData = new FormData(event.target)
        
        // Ajouter les données d'adhésion si applicable
        if (this.showMembershipFields) {
            formData.append('membership_family_members', this.familyMembers);
            formData.append('membership_competitors', this.competitors);
            formData.append('membership_training_sessions', this.trainingSessions);
            formData.append('membership_total_cost', this.calculateTotal());
        }

        console.log('Envoi vers:', event.target.action);
        console.log('Données à envoyer:', Object.fromEntries(formData));

        const response = await fetch(event.target.action, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                "Accept": "application/json", // Important !
            },
            body: formData,
        })
        
        console.log('Status de la réponse:', response.status);
        console.log('Headers de la réponse:', response.headers);
        
        // Vérifier le type de contenu avant de parser
        const contentType = response.headers.get('content-type');
        console.log('Content-Type:', contentType);
        
        if (!contentType || !contentType.includes('application/json')) {
            // Si ce n'est pas du JSON, lire comme texte pour voir l'erreur
            const text = await response.text();
            console.error('Réponse non-JSON reçue:', text);
            throw new Error('Le serveur a renvoyé du HTML au lieu de JSON. Voir la console pour les détails.');
        }
        
        const result = await response.json()
        
        if (result.success) {
            this.submitted = true
            event.target.reset()
            this.showMembershipFields = false;
            this.familyMembers = 1;
            this.competitors = 0;
            this.trainingSessions = 0;
            setTimeout(() => (this.submitted = false), 5000)
        } else {
            console.error("Erreur dans la réponse:", result)
            alert("Erreur: " + (result.message || 'Erreur inconnue'))
        }
    } catch (error) {
        console.error("Erreur complète:", error)
        alert("Erreur: " + error.message)
    } finally {
        this.loading = false
    }
},
}))

// Configuration pour les filtres d'événements
Alpine.data("eventFilters", () => ({
  selectedCategory: "all",

  filterEvents(category) {
    this.selectedCategory = category
  },
}))

// Configuration pour la navigation mobile
Alpine.data("navigation", () => ({
  mobileMenuOpen: false,

  toggleMobileMenu() {
    this.mobileMenuOpen = !this.mobileMenuOpen
  },

  closeMobileMenu() {
    this.mobileMenuOpen = false
  },
}))