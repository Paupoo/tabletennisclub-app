export const MembershipLogic = {
    getRecreationalMembers(familyMembers, competitors) {
        return Math.max(0, familyMembers - competitors);
    },

    calculateBase(familyMembers, competitors) {
        const recreationalCost = this.getRecreationalMembers(familyMembers, competitors) * 60;
        const competitionCost = competitors * 125;
        return recreationalCost + competitionCost;
    },

    calculateTrainingCost(familyMembers, trainingSessions) {
        const sessions = parseInt(trainingSessions) || 0;
        if (sessions === 0) return 0;

        const firstSession = familyMembers > 1 ? 80 : 90;
        if (sessions === 1) return firstSession;

        const additionalSessions = (sessions - 1) * 80;
        return firstSession + additionalSessions;
    },

    calculateTotal(familyMembers, competitors, trainingSessions) {
        const base = this.calculateBase(familyMembers, competitors);
        const training = this.calculateTrainingCost(familyMembers, trainingSessions);
        return base + training;
    }
};