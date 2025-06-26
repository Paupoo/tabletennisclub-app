import "./bootstrap"
import "./news-filter"

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
      calculateTotal() {
          // Licences : récréatives (60€) + compétition (125€)
          const recreationalCost = this.getRecreationalMembers() * 60;
          const competitionCost = this.competitors * 125;
          const base = recreationalCost + competitionCost;
          
          // Séances d'entraînement
          let training = 0;
          if (this.trainingSessions > 0) {
              if (this.trainingSessions === 1) {
                  training += this.familyMembers > 1 ? 80 : 90;
              } else if (this.trainingSessions > 1) {
                  const firstSession = this.familyMembers > 1 ? 80 : 90;
                  const additionalSessions = (this.trainingSessions - 1) * 80;
                  training = firstSession + additionalSessions;
              }
          }
          
          return base + training;
        }
  }));

// Configuration pour le formulaire de contact
Alpine.data("contactForm", () => ({
  submitted: false,
  loading: false,

  async submitForm(event) {
    this.loading = true

    try {
      const formData = new FormData(event.target)
      const data = Object.fromEntries(formData)

      const response = await fetch(event.target.action, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
        },
        body: JSON.stringify(data),
      })

      const result = await response.json()

      if (result.success) {
        this.submitted = true
        event.target.reset()
        setTimeout(() => (this.submitted = false), 5000)
      } else {
        console.error("Erreur:", result.message)
        alert("Une erreur est survenue. Veuillez réessayer.")
      }
    } catch (error) {
      console.error("Erreur:", error)
      alert("Une erreur est survenue. Veuillez réessayer.")
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