const test = require('node:test');
const assert = require('node:assert/strict');

const utils = require('../../app/js/utils/reporting-utils.js');

const sampleEntries = [
    { date: '2024-05-01', time: 'morning', medication_name: 'Ritalin', dose: '10mg', mood: 4, focus: 5, sleep: 3 },
    { date: '2024-05-01', time: 'evening', medication_name: 'Ritalin', dose: '10mg', mood: 2, focus: 3 },
    { date: '2024-05-02', time: 'noon', medication_name: null, dose: null, mood: null, focus: 4 }
];

test('formatDateLabel formats ISO strings', () => {
    assert.equal(utils.formatDateLabel('2024-02-05'), '05.02.2024');
});

test('labelForTimeSlot maps known slots', () => {
    assert.equal(utils.labelForTimeSlot('noon'), 'Mittag');
    assert.equal(utils.labelForTimeSlot('unknown'), 'Unbekannt');
});

test('groupEntriesByDate groups entries chronologisch', () => {
    const grouped = utils.groupEntriesByDate(sampleEntries);
    assert.ok(Array.isArray(grouped['2024-05-01']), 'Sollte Array für 2024-05-01 geben');
    assert.equal(grouped['2024-05-01'].length, 2);
    assert.equal(grouped['2024-05-02'].length, 1);
});

test('buildChartData produces aligned datasets', () => {
    const { labels, moodData, focusData, sleepData } = utils.buildChartData(sampleEntries);
    assert.equal(labels.length, sampleEntries.length);
    assert.deepEqual(labels[0], ['02.05.2024', 'Mittag', '-']);
    assert.equal(moodData[1], 2);
    assert.equal(focusData[0], 4);
    assert.equal(sleepData[0], null);
    assert.equal(sleepData[2], 3, 'Nur Morgen-Einträge enthalten Schlafwerte');
});
