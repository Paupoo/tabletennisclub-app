export default () => ({
  selectedCategory: "all",
  filterEvents(category) {
    this.selectedCategory = category
  },
})