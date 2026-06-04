export default () => ({
  mobileMenuOpen: false,
  toggleMobileMenu() {
    this.mobileMenuOpen = !this.mobileMenuOpen
  },
  closeMobileMenu() {
    this.mobileMenuOpen = false
  },
})