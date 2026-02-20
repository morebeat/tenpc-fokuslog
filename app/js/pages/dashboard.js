(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});
    const components = FokusLog.components || (FokusLog.components = {});

    pages.dashboard = {
        init: async function({ user, utils }) {
            if (!user) return;

            // 1. Gamification (Child or everyone with points)
            if (user.role === 'child' || (user.points && user.points > 0)) {
                const gamificationContainer = document.getElementById('gamification-stats') || this.createGamificationContainer();
                if (components.gamification) {
                    const rankData = await components.gamification.fetchRank(utils);
                    if (rankData) {
                        user.rank_info = rankData;
                    }
                    await components.gamification.render(gamificationContainer, user, utils);
                }
            }

            // 2. Admin Section
            if (components.dashboardHelpers) {
                components.dashboardHelpers.toggleAdminSection(user);
            }

            // 3. First Steps (if new user)
            if (!user.points || user.points === 0) {
                const firstStepsContainer = document.getElementById('first-steps-card') || this.createFirstStepsContainer();
                if (components.dashboardHelpers) {
                    await components.dashboardHelpers.renderFirstSteps(firstStepsContainer, utils);
                }
            }
        },

        createGamificationContainer: function() {
            const container = document.createElement('div');
            container.id = 'gamification-stats';
            container.style.marginTop = '20px';
            container.style.marginBottom = '20px';
            this._insertAfterWelcome(container);
            return container;
        },

        createFirstStepsContainer: function() {
            const container = document.createElement('div');
            container.id = 'first-steps-card';
            this._insertAfterWelcome(container);
            return container;
        },

        _insertAfterWelcome: function(element) {
            const welcomeMsg = document.getElementById('welcome');
            if (welcomeMsg && welcomeMsg.parentNode) {
                welcomeMsg.parentNode.insertBefore(element, welcomeMsg.nextSibling);
            } else {
                const main = document.querySelector('main');
                if (main) main.prepend(element);
            }
        }
    };
})(window);
