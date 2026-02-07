(function (global) {
    const FokusLog = global.FokusLog || (global.FokusLog = {});
    const pages = FokusLog.pages || (FokusLog.pages = {});

    pages.report = {
        init: async ({ utils }) => {
            const dateFromInput = document.getElementById('date_from');
            const dateToInput = document.getElementById('date_to');
            const userFilterContainer = document.getElementById('user-filter-container');
            const userFilter = document.getElementById('user-filter');
            const filterBtn = document.getElementById('filter-btn');
            const exportCsvBtn = document.getElementById('export-csv-btn');
            const exportPdfBtn = document.getElementById('export-pdf-btn');
            const entriesTable = document.getElementById('entries-table');
            let reportChart = null;
            let weightChart = null;
            const escapeHtml = utils?.escapeHtml || ((value) => value);

            const reportUtils = global.FokusLogReportUtils;
            if (!reportUtils) {
                console.error('FokusLogReportUtils wurde nicht geladen.');
                return;
            }

            const {
                formatDateLabel,
                labelForTimeSlot,
                groupEntriesByDate,
                buildChartData
            } = reportUtils;

            const loadUsersForFilter = async () => {
                try {
                    const meResponse = await fetch('/api/me');
                    if (!meResponse.ok) {
                        if (userFilterContainer) userFilterContainer.style.display = 'none';
                        return;
                    }
                    const me = await meResponse.json();
                    if (me.role === 'parent' && userFilterContainer) {
                        const usersResponse = await fetch('/api/users');
                        if (usersResponse.ok) {
                            const data = await usersResponse.json();
                            if (data.users && userFilter) {
                                userFilter.innerHTML = '';
                                let selfOption = document.createElement('option');
                                selfOption.value = me.id;
                                selfOption.textContent = `${me.username} (Ich)`;
                                userFilter.appendChild(selfOption);
                                data.users.forEach(u => {
                                    if (u.id !== me.id && (u.role === 'parent' || u.role === 'child')) {
                                        const option = document.createElement('option');
                                        option.value = u.id;
                                        option.textContent = u.username;
                                        userFilter.appendChild(option);
                                    }
                                });
                                userFilterContainer.style.display = 'block';
                            }
                        }
                    } else if (userFilterContainer) {
                        userFilterContainer.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Fehler beim Laden der Benutzer für den Filter:', error);
                    if (userFilterContainer) userFilterContainer.style.display = 'none';
                }
            };

            const setDefaultDates = () => {
                const today = new Date();
                const sevenDaysAgo = new Date(today);
                sevenDaysAgo.setDate(today.getDate() - 7);
                if (dateFromInput) dateFromInput.value = sevenDaysAgo.toISOString().split('T')[0];
                if (dateToInput) dateToInput.value = today.toISOString().split('T')[0];
            };

            const loadEntries = async () => {
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter ? userFilter.value : null;
                if (!dateFrom || !dateTo) {
                    entriesTable.innerHTML = '<p>Bitte wählen Sie einen Datumsbereich aus.</p>';
                    return;
                }
                entriesTable.innerHTML = '<p>Lade Einträge...</p>';
                try {
                    let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                    if (userId) {
                        url += `&user_id=${userId}`;
                    }
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.entries && data.entries.length > 0) {
                            displayEntries(data.entries);
                            updateChart(data.entries);
                        } else {
                            entriesTable.innerHTML = '<p>Keine Einträge im ausgewählten Zeitraum gefunden.</p>';
                            updateChart([]);
                        }
                    } else {
                        entriesTable.innerHTML = '<p>Fehler beim Laden der Einträge.</p>';
                    }
                } catch (error) {
                    console.error('Fehler beim Laden der Einträge:', error);
                    entriesTable.innerHTML = '<p>Verbindung nicht möglich.</p>';
                    if (document.getElementById('weight-history')) loadWeightData();
                }
            };

            const displayEntries = (entries) => {
                const grouped = groupEntriesByDate(entries);
                let html = '<div class="accordion-container">';
                Object.keys(grouped).sort().reverse().forEach(date => {
                    const dayEntries = grouped[date];
                    const dateLabel = formatDateLabel(date);
                    html += `
                        <div class="accordion-item">
                            <div class="accordion-header">
                                <span>${dateLabel}</span>
                                <span>${dayEntries.length} Eintrag/Einträge</span>
                            </div>
                            <div class="accordion-content">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Zeit</th>
                                            <th>Med</th>
                                            <th style="text-align: center;">Stimmung</th>
                                            <th style="text-align: center;">Fokus</th>
                                            <th style="text-align: center;">Aktion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    dayEntries.forEach(entry => {
                        const timeLabel = labelForTimeSlot(entry.time);
                        html += `
                            <tr>
                                <td>${timeLabel}</td>
                                <td>${escapeHtml(entry.medication_name) || '-'} (${escapeHtml(entry.dose) || '-'})</td>
                                <td style="text-align: center;">${entry.mood || '-'}</td>
                                <td style="text-align: center;">${entry.focus || '-'}</td>
                                <td style="text-align: center;">
                                    <a href="entry.html?date=${entry.date}&time=${entry.time}&user_id=${entry.user_id}">Bearbeiten</a>
                                </td>
                            </tr>
                        `;
                    });
                    html += '</tbody></table></div></div>';
                });
                html += '</div>';
                entriesTable.innerHTML = html;
                const headers = entriesTable.querySelectorAll('.accordion-header');
                headers.forEach(header => {
                    header.addEventListener('click', () => {
                        const content = header.nextElementSibling;
                        content.style.display = content.style.display === 'block' ? 'none' : 'block';
                    });
                });
            };

            const updateChart = (entries) => {
                const canvas = document.getElementById('reportChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (reportChart) {
                    reportChart.destroy();
                }
                if (!entries || entries.length === 0) {
                    reportChart = new Chart(ctx, {
                        type: 'line',
                        data: { labels: [], datasets: [] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: { display: true, text: 'Verlauf der Metriken (keine Daten)' }
                            }
                        }
                    });
                    return;
                }
                const { labels, moodData, focusData, sleepData } = buildChartData(entries);
                reportChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Stimmung',
                                data: moodData,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: 'Fokus',
                                data: focusData,
                                borderColor: 'rgb(54, 162, 235)',
                                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            },
                            {
                                label: 'Schlaf',
                                data: sleepData,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5,
                                ticks: { stepSize: 1 }
                            },
                            x: {
                                ticks: {
                                    callback: function(value, index) {
                                        const label = this.getLabelForValue(value);
                                        if (!Array.isArray(label)) return label;
                                        const currentDate = label[0];
                                        if (index > 0) {
                                            const prevLabel = this.getLabelForValue(value - 1);
                                            if (Array.isArray(prevLabel) && prevLabel[0] === currentDate) {
                                                return '';
                                            }
                                        }
                                        return currentDate;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: { display: true, position: 'top' },
                            title: { display: true, text: 'Verlauf der Metriken' },
                            tooltip: {
                                callbacks: {
                                    title: function(tooltipItems) {
                                        const label = tooltipItems[0].label;
                                        if (Array.isArray(label)) {
                                            return `${label[0]} (${label[1]})`;
                                        }
                                        return label;
                                    },
                                    afterBody: function(tooltipItems) {
                                        const label = tooltipItems[0].label;
                                        if (Array.isArray(label) && label.length > 2 && label[2] !== '-') {
                                            return ['', 'Medikation:', label[2]];
                                        }
                                        return [];
                                    }
                                }
                            }
                        }
                    }
                });
            };

            const exportToCSV = async () => {
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter ? userFilter.value : null;
                if (!dateFrom || !dateTo) {
                    alert('Bitte wählen Sie einen Datumsbereich aus.');
                    return;
                }
                try {
                    let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                    if (userId) {
                        url += `&user_id=${userId}`;
                    }
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.entries && data.entries.length > 0) {
                            let csv = 'Benutzername,Datum,Zeit,Medikament,Dosis,Stimmung,Fokus,Schlaf,Hyperaktivität,Reizbarkeit,Appetit,Notizen\n';
                            data.entries.forEach(entry => {
                                const timeLabel = labelForTimeSlot(entry.time);
                                const notes = [
                                    entry.other_effects,
                                    entry.side_effects,
                                    entry.special_events,
                                    entry.tags ? `Tags: ${entry.tags}` : null,
                                    entry.teacher_feedback,
                                    entry.emotional_reactions
                                ].filter(Boolean).join('; ');
                                csv += `"${entry.username || ''}",`;
                                csv += `"${formatDateLabel(entry.date)}",`;
                                csv += `"${timeLabel}",`;
                                csv += `"${entry.medication_name || ''}",`;
                                csv += `"${entry.dose || ''}",`;
                                csv += `"${entry.mood || ''}",`;
                                csv += `"${entry.focus || ''}",`;
                                csv += `"${entry.sleep || ''}",`;
                                csv += `"${entry.hyperactivity || ''}",`;
                                csv += `"${entry.irritability || ''}",`;
                                csv += `"${entry.appetite || ''}",`;
                                csv += `"${(notes || '').replace(/"/g, '""')}"\n`;
                            });
                            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                            const link = document.createElement('a');
                            const urlBlob = URL.createObjectURL(blob);
                            link.setAttribute('href', urlBlob);
                            link.setAttribute('download', `fokuslog_export_${dateFrom}_${dateTo}.csv`);
                            link.style.visibility = 'hidden';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            alert('Keine Einträge zum Exportieren gefunden.');
                        }
                    } else {
                        alert('Fehler beim Laden der Einträge.');
                    }
                } catch (error) {
                    console.error('Fehler beim Export:', error);
                    alert('Fehler beim Exportieren der Daten.');
                }
            };

            const exportToPDF = async () => {
                const { jsPDF } = global.jspdf || {};
                if (!jsPDF) {
                    alert('PDF-Export nicht verfügbar.');
                    return;
                }
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter ? userFilter.value : null;
                const username = userFilter && userFilter.selectedIndex !== -1 ? userFilter.options[userFilter.selectedIndex].text : 'Aktueller Benutzer';
                if (!dateFrom || !dateTo) {
                    alert('Bitte wählen Sie einen Datumsbereich aus.');
                    return;
                }
                let entries = [];
                try {
                    let url = `/api/entries?date_from=${dateFrom}&date_to=${dateTo}`;
                    if (userId) {
                        url += `&user_id=${userId}`;
                    }
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.entries && data.entries.length > 0) {
                            entries = data.entries;
                        }
                    } else {
                        throw new Error('Fehler beim Laden der Einträge für PDF-Export.');
                    }
                } catch (error) {
                    console.error(error);
                    alert(error.message);
                    return;
                }
                const pdf = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
                pdf.setFontSize(18);
                pdf.text(`FokusLog Report für ${username}`, 14, 22);
                pdf.setFontSize(11);
                pdf.setTextColor(100);
                pdf.text(`Zeitraum: ${formatDateLabel(dateFrom)} - ${formatDateLabel(dateTo)}`, 14, 28);
                const canvasReport = document.getElementById('reportChart');
                const canvasWeight = document.getElementById('weightChart');
                let yPos = 40;
                if (canvasReport && canvasReport.height > 10) {
                    const reportImgData = canvasReport.toDataURL('image/png');
                    pdf.addImage(reportImgData, 'PNG', 14, yPos, 180, 100);
                    yPos += 110;
                }
                if (canvasWeight && canvasWeight.height > 10) {
                    if (yPos + 100 > 280) {
                        pdf.addPage();
                        yPos = 20;
                    }
                    const weightImgData = canvasWeight.toDataURL('image/png');
                    pdf.addImage(weightImgData, 'PNG', 14, yPos, 180, 100);
                }
                if (entries.length > 0) {
                    pdf.addPage();
                    const tableHead = [['Datum', 'Zeit', 'Medikament', 'Dosis', 'Stimmung', 'Fokus', 'Schlaf', 'Notizen']];
                    const tableBody = entries.map(entry => {
                        const timeLabel = labelForTimeSlot(entry.time);
                        const notes = [
                            entry.other_effects,
                            entry.side_effects,
                            entry.special_events,
                            entry.tags ? `Tags: ${entry.tags}` : null,
                            entry.teacher_feedback,
                            entry.emotional_reactions
                        ].filter(Boolean).join('; ');
                        return [
                            formatDateLabel(entry.date),
                            timeLabel,
                            entry.medication_name || '-',
                            entry.dose || '-',
                            entry.mood || '-',
                            entry.focus || '-',
                            entry.sleep || '-',
                            notes || '-'
                        ];
                    });
                    pdf.autoTable({
                        head: tableHead,
                        body: tableBody,
                        startY: 20,
                        theme: 'grid',
                        styles: { fontSize: 8 },
                        headStyles: { fillColor: [1, 60, 74] }
                    });
                }
                pdf.save(`fokuslog_report_${username.replace(/\s/g, '_')}_${dateFrom}_${dateTo}.pdf`);
            };

            const loadWeightData = async () => {
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter ? userFilter.value : null;
                const weightContainer = document.getElementById('weight-history');
                if (!dateFrom || !dateTo || !weightContainer) {
                    if (weightContainer) weightContainer.style.display = 'none';
                    return;
                }
                try {
                    let url = `/api/weight?date_from=${dateFrom}&date_to=${dateTo}`;
                    if (userId) {
                        url += `&user_id=${userId}`;
                    }
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.weights && data.weights.length > 0) {
                            weightContainer.style.display = 'block';
                            updateWeightChart(data.weights);
                        } else {
                            weightContainer.style.display = 'none';
                            if (weightChart) {
                                weightChart.destroy();
                                weightChart = null;
                            }
                        }
                    } else {
                        weightContainer.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Fehler beim Laden der Gewichtsdaten:', error);
                    weightContainer.style.display = 'none';
                }
            };

            const updateWeightChart = (weights) => {
                const canvas = document.getElementById('weightChart');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                if (weightChart) {
                    weightChart.destroy();
                }
                const labels = weights.map(w => formatDateLabel(w.date));
                const data = weights.map(w => w.weight);
                weightChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Gewichtsverlauf (kg)',
                            data: data,
                            borderColor: 'rgb(153, 102, 255)',
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: false }
                        },
                        plugins: {
                            legend: { display: true, position: 'top' },
                            title: { display: true, text: 'Gewichtsverlauf' }
                        }
                    }
                });
            };

            if (filterBtn) {
                filterBtn.addEventListener('click', () => {
                    loadEntries();
                    loadWeightData();
                });
            }
            if (userFilter) {
                userFilter.addEventListener('change', () => {
                    loadEntries();
                    loadWeightData();
                });
            }
            if (exportCsvBtn) {
                exportCsvBtn.addEventListener('click', exportToCSV);
            }
            if (exportPdfBtn) {
                exportPdfBtn.addEventListener('click', exportToPDF);
            }
            loadUsersForFilter();
            setDefaultDates();
            loadEntries();
            if (document.getElementById('weight-history')) {
                loadWeightData();
            }
        }
    };
})(window);
