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
    trainingSessions: 0,
    calculateTotal() {
        const base = this.familyMembers * 160;
        let training = 0;

        if (this.trainingSessions > 0) {
            if (this.trainingSessions == 1) {
              if (this.familyMembers > 1 || this.trainingSessions > 1 ) {
                training += 80;
              } else {
                training += 90;
              }
            }
            else if (this.trainingSessions > 1) {
                training += this.trainingSessions * 80;
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