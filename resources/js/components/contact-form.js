export default (oldInterest = '', oldFamily = 1, oldCompetitors = 0, oldSessions = 0) => ({
  submitted: false,
  loading: false,
  showMembershipFields: false,
  selectedInterest: oldInterest,
  familyMembers: oldFamily,
  competitors: oldCompetitors,
  trainingSessions: oldSessions,
  init() {
    this.showMembershipFields = this.selectedInterest === 'JOIN_US';
    this.checkUrlParams();
    window.addEventListener('prepopulateContact', (event) => {
      this.prepopulateFromData(event.detail);
    });
  },
  checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const interest = urlParams.get('interest');
    if (interest === 'JOIN_US') {
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
  prepopulateFromData(data) {
    this.selectedInterest = data.interest;
    this.familyMembers = data.familyMembers;
    this.competitors = data.competitors;
    this.trainingSessions = data.trainingSessions;
    this.showMembershipFields = data.interest === 'JOIN_US';
    this.$nextTick(() => {
      const selectElement = document.getElementById('interest');
      if (selectElement) {
        selectElement.value = data.interest;
        selectElement.dispatchEvent(new Event('change'));
      }
    });
  },
  onRequestTypeChange(event) {
    this.showMembershipFields = event.target.value === 'JOIN_US';
    if (!this.showMembershipFields) {
      this.familyMembers = 1;
      this.competitors = 0;
      this.trainingSessions = 0;
    }
  },
  validateCompetitors() {
    if (this.competitors > this.familyMembers) {
      this.competitors = 0;
    }
  },
  getRecreationalMembers() {
    return this.familyMembers - this.competitors;
  },
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
        if (this.showMembershipFields) {
            formData.append('membership_family_members', this.familyMembers);
            formData.append('membership_competitors', this.competitors);
            formData.append('membership_training_sessions', this.trainingSessions);
            formData.append('membership_total_cost', this.calculateTotal());
        }
        const response = await fetch(event.target.action, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                "Accept": "application/json",
            },
            body: formData,
        })
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Le serveur a renvoyé du HTML au lieu de JSON.');
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
            alert("Erreur: " + (result.message || 'Erreur inconnue'))
        }
    } catch (error) {
        alert("Erreur: " + error.message)
    } finally {
        this.loading = false
    }
  },
})