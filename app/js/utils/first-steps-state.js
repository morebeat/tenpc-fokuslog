(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.FokusLog = root.FokusLog || {};
        root.FokusLog.utils = root.FokusLog.utils || {};
        root.FokusLog.utils.firstStepsState = factory();
    }
}(typeof globalThis !== 'undefined' ? globalThis : window, function () {
    const computeFirstStepsVisibility = ({ hasMedications, hasEntries }) => {
        const showMedCta = !hasMedications;
        const showEntryCta = !hasEntries;
        const hideCard = !showMedCta && !showEntryCta;
        return { showMedCta, showEntryCta, hideCard };
    };

    return {
        computeFirstStepsVisibility
    };
}));
