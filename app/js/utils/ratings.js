(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.FokusLog = root.FokusLog || {};
        root.FokusLog.utils = root.FokusLog.utils || {};
        root.FokusLog.utils.ratingHints = factory();
    }
}(typeof globalThis !== 'undefined' ? globalThis : window, function () {
    const shouldShowHelpHint = (value) => value === 1 || value === 5;

    const hintIntensityText = (value) => {
        if (value === 1) return 'sehr niedrigen';
        if (value === 5) return 'sehr hohen';
        return 'auffälligen';
    };

    const buildHelpHintMessage = ({ value, label, helpUrl }) => {
        const safeLabel = label || 'diesem Bereich';
        const url = helpUrl || 'help/index.html';
        const intensity = hintIntensityText(value);
        return `Hinweis: Bei ${intensity} Werten in ${safeLabel} findest du Ideen im <a href="${url}" target="_blank" rel="noopener">Hilfe-Bereich</a>.`;
    };

    return {
        shouldShowHelpHint,
        buildHelpHintMessage,
        hintIntensityText
    };
}));
