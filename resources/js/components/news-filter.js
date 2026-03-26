import Alpine from "alpinejs"

// Configuration pour les filtres d'actualités
Alpine.data("newsFilters", () => ({
  selectedYear: new URLSearchParams(window.location.search).get("year") || "",
  selectedMonth: new URLSearchParams(window.location.search).get("month") || "",
  selectedCategory: new URLSearchParams(window.location.search).get("category") || "",
  sortOrder: new URLSearchParams(window.location.search).get("sort") || "desc",

  updateFilters() {
    const params = new URLSearchParams()

    if (this.selectedYear) params.set("year", this.selectedYear)
    if (this.selectedMonth) params.set("month", this.selectedMonth)
    if (this.selectedCategory) params.set("category", this.selectedCategory)
    if (this.sortOrder !== "desc") params.set("sort", this.sortOrder)

    const newUrl = window.location.pathname + (params.toString() ? "?" + params.toString() : "")
    window.location.href = newUrl
  },

  hasActiveFilters() {
    return this.selectedYear || this.selectedMonth || this.selectedCategory
  },

  clearAllFilters() {
    this.selectedYear = ""
    this.selectedMonth = ""
    this.selectedCategory = ""
    this.updateFilters()
  },

  getMonthName(month) {
    const months = {
      "01": "Janvier",
      "02": "Février",
      "03": "Mars",
      "04": "Avril",
      "05": "Mai",
      "06": "Juin",
      "07": "Juillet",
      "08": "Août",
      "09": "Septembre",
      "10": "Octobre",
      "11": "Novembre",
      "12": "Décembre",
    }
    return months[month] || month
  },
}))