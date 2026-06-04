export default () => ({
  familyMembers: 1,
  competitors: 0,
  trainingSessions: 0,
  validateCompetitors() {
    if (this.competitors > this.familyMembers) {
      this.competitors = 0;
    }
  },
  getRecreationalMembers() {
    return this.familyMembers - this.competitors;
  },
  calculateBase() {
    const recreationalCost = this.getRecreationalMembers() * 60;
    const competitionCost = this.competitors * 125;
    return recreationalCost + competitionCost;
  },
  calculateTotal() {
    const base = this.calculateBase();
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
  goToContactWithData() {
    const params = new URLSearchParams({
      interest: 'JOIN_US',
      familyMembers: this.familyMembers,
      competitors: this.competitors,
      trainingSessions: this.trainingSessions
    });
    const contactSection = document.getElementById('contact');
    if (contactSection) {
      const currentUrl = new URL(window.location.href);
      currentUrl.hash = 'contact';
      params.forEach((value, key) => {
        currentUrl.searchParams.set(key, value);
      });
      window.history.pushState({}, '', currentUrl);
      contactSection.scrollIntoView({ behavior: 'smooth' });
      window.dispatchEvent(new CustomEvent('prepopulateContact', {
        detail: {
          interest: 'JOIN_US',
          familyMembers: this.familyMembers,
          competitors: this.competitors,
          trainingSessions: this.trainingSessions
        }
      }));
    }
  }
})