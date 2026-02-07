(function (global) {
    'use strict';

    const TIME_LABELS = {
        morning: 'Morgen',
        noon: 'Mittag',
        evening: 'Abend'
    };

    function formatDateLabel(dateString) {
        if (typeof dateString !== 'string') {
            throw new TypeError('Datum muss ein String sein');
        }
        const trimmed = dateString.trim();
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(trimmed);
        if (!match) {
            throw new Error('Ungültiges Datum');
        }
        const [, year, month, day] = match;
        return `${day}.${month}.${year}`;
    }

    function labelForTimeSlot(slot) {
        return TIME_LABELS[slot] || 'Unbekannt';
    }

    function groupEntriesByDate(entries) {
        if (!Array.isArray(entries)) {
            throw new TypeError('entries must be an array');
        }
        return entries.reduce((acc, entry) => {
            const date = entry && entry.date;
            if (!date) {
                return acc;
            }
            if (!acc[date]) {
                acc[date] = [];
            }
            acc[date].push(entry);
            return acc;
        }, {});
    }

    function buildChartData(entries) {
        if (!Array.isArray(entries)) {
            throw new TypeError('entries must be an array');
        }
        if (entries.length === 0) {
            return { labels: [], moodData: [], focusData: [], sleepData: [] };
        }

        const sortedEntries = entries.slice().reverse();
        const labels = [];
        const moodData = [];
        const focusData = [];
        const sleepData = [];

        sortedEntries.forEach((entry) => {
            const formattedDate = entry.date ? formatDateLabel(entry.date) : '-';
            const medInfo = entry.medication_name ? `${entry.medication_name} ${entry.dose ?? ''}`.trim() : '-';
            labels.push([formattedDate, labelForTimeSlot(entry.time), medInfo]);

            const mood = entry.mood === 0 || entry.mood ? parseInt(entry.mood, 10) : null;
            const focus = entry.focus === 0 || entry.focus ? parseInt(entry.focus, 10) : null;
            const sleep = entry.time === 'morning' && (entry.sleep === 0 || entry.sleep)
                ? parseInt(entry.sleep, 10)
                : null;

            moodData.push(Number.isNaN(mood) ? null : mood);
            focusData.push(Number.isNaN(focus) ? null : focus);
            sleepData.push(Number.isNaN(sleep) ? null : sleep);
        });

        return { labels, moodData, focusData, sleepData };
    }

    const utils = {
        formatDateLabel,
        labelForTimeSlot,
        groupEntriesByDate,
        buildChartData
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = utils;
    } else {
        global.FokusLogReportUtils = utils;
    }
})(typeof globalThis !== 'undefined' ? globalThis : window);
