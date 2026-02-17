/**
 * @jest-environment jsdom
 */

// This test suite assumes a Jest-like environment with jsdom.
// To run, you would typically use a command like `jest`.

// Mock the global FokusLog object before loading the script.
// In a real Jest setup, this might be in a setup file.
global.FokusLog = {
    components: {},
    utils: {}
};

// Load the script to be tested.
// Jest's module system will handle this.
require('../../app/js/gamification.js');

describe('Gamification Module', () => {
    let mockUtils;
    let mockConfetti;

    beforeEach(() => {
        // Set up fake timers to control setTimeout
        jest.useFakeTimers();

        // Mock dependencies
        mockUtils = {
            toast: jest.fn(),
        };
        mockConfetti = jest.fn();
        global.confetti = mockConfetti;
    });

    afterEach(() => {
        // Restore real timers and clean up mocks
        jest.useRealTimers();
        delete global.confetti;
    });

    describe('notifyAchievements', () => {
        it('should do nothing if gamificationData is null or points_earned is zero or less', () => {
            FokusLog.components.gamification.notifyAchievements(null, mockUtils);
            FokusLog.components.gamification.notifyAchievements(undefined, mockUtils);
            FokusLog.components.gamification.notifyAchievements({ points_earned: 0, streak: 5 }, mockUtils);
            FokusLog.components.gamification.notifyAchievements({ points_earned: -10, streak: 5 }, mockUtils);

            // Advance timers to ensure no async code runs
            jest.runAllTimers();

            expect(mockUtils.toast).not.toHaveBeenCalled();
            expect(mockConfetti).not.toHaveBeenCalled();
        });

        it('should show a toast for points earned and trigger confetti', () => {
            const gamificationData = { points_earned: 10, streak: 5 };
            FokusLog.components.gamification.notifyAchievements(gamificationData, mockUtils);

            // Fast-forward time by 500ms to trigger the first setTimeout
            jest.advanceTimersByTime(500);

            expect(mockUtils.toast).toHaveBeenCalledTimes(1);
            expect(mockUtils.toast).toHaveBeenCalledWith('+10 Punkte! Streak: 5 Tage 🔥', 'gamification', 5000);
            expect(mockConfetti).toHaveBeenCalledTimes(1);
        });

        it('should show toasts for points and a new badge', () => {
            const gamificationData = {
                points_earned: 10,
                streak: 10,
                new_badges: [{ name: 'Bronze Streak' }]
            };
            FokusLog.components.gamification.notifyAchievements(gamificationData, mockUtils);

            // Fast-forward time to trigger all timers
            jest.runAllTimers();

            expect(mockUtils.toast).toHaveBeenCalledTimes(2);
            expect(mockUtils.toast).toHaveBeenCalledWith('+10 Punkte! Streak: 10 Tage 🔥', 'gamification', 5000);
            expect(mockUtils.toast).toHaveBeenCalledWith('🏆 Neues Abzeichen: Bronze Streak', 'badge', 6000);
            expect(mockConfetti).toHaveBeenCalledTimes(1);
        });

        it('should show toasts for points and multiple new badges with correct delays', () => {
            const gamificationData = {
                points_earned: 20,
                streak: 30,
                new_badges: [
                    { name: 'Silver Streak' },
                    { name: 'Perfect Month' }
                ]
            };
            FokusLog.components.gamification.notifyAchievements(gamificationData, mockUtils);

            // Run all timers
            jest.runAllTimers();
            
            expect(mockUtils.toast).toHaveBeenCalledTimes(3);
            expect(mockUtils.toast.mock.calls[0]).toEqual(['+20 Punkte! Streak: 30 Tage 🔥', 'gamification', 5000]);
            expect(mockUtils.toast.mock.calls[1]).toEqual(['🏆 Neues Abzeichen: Silver Streak', 'badge', 6000]);
            expect(mockUtils.toast.mock.calls[2]).toEqual(['🏆 Neues Abzeichen: Perfect Month', 'badge', 6000]);
            expect(mockConfetti).toHaveBeenCalledTimes(1);
        });
    });
});