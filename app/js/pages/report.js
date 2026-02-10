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
            const exportExcelBtn = document.getElementById('export-excel-btn');
            const exportDoctorBtn = document.getElementById('export-doctor-btn');
            const exportPdfBtn = document.getElementById('export-pdf-btn');
            const entriesTable = document.getElementById('entries-table');
            const trendsSection = document.getElementById('trends-section');
            const compareSection = document.getElementById('compare-section');
            let reportChart = null;
            let weightChart = null;
            const escapeHtml = utils?.escapeHtml || ((value) => value);

            const reportUtils = global.FokusLogReportUtils;
            if (!reportUtils) {
                utils.error('FokusLogReportUtils wurde nicht geladen.');
                return;
            }

            const {
                formatDateLabel,
                labelForTimeSlot,
                groupEntriesByDate,
                buildChartData
            } = reportUtils;

            // Emoji-Mapping für Skala-Werte (1-5)
            const valueToEmoji = (value) => {
                const emojiMap = {
                    1: '😭',
                    2: '😢',
                    3: '😐',
                    4: '🙂',
                    5: '😊'
                };
                return emojiMap[value] || value || '-';
            };

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
                    utils.error('Fehler beim Laden der Benutzer für den Filter:', error);
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
                    utils.error('Fehler beim Laden der Einträge:', error);
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
                                <td style="text-align: center;">${valueToEmoji(entry.mood)}</td>
                                <td style="text-align: center;">${valueToEmoji(entry.focus)}</td>
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
                    utils.error('Fehler beim Export:', error);
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
                    utils.error('PDF Export Fehler:', error);
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

            // Trend-Analyse laden
            const loadTrends = async () => {
                if (!trendsSection) return;
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter?.value;
                
                try {
                    let url = `/api/report/trends?date_from=${dateFrom}&date_to=${dateTo}`;
                    if (userId) url += `&user_id=${userId}`;
                    
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        displayTrends(data);
                        trendsSection.style.display = 'block';
                    } else {
                        trendsSection.style.display = 'none';
                    }
                } catch (error) {
                    utils.error('Fehler beim Laden der Trends:', error);
                    trendsSection.style.display = 'none';
                }
            };

            const displayTrends = (data) => {
                const warningsContainer = document.getElementById('warnings-container');
                const insightsContainer = document.getElementById('insights-container');
                const statsSummary = document.getElementById('stats-summary');
                
                // Warnungen anzeigen
                if (warningsContainer) {
                    if (data.warnings && data.warnings.length > 0) {
                        warningsContainer.innerHTML = data.warnings.map(w => `
                            <div class="warning-card ${w.severity || ''}">
                                <span class="warning-icon">${w.severity === 'warning' ? '⚠️' : '⚡'}</span>
                                <div class="warning-content">
                                    <h4>${escapeHtml(w.message)}</h4>
                                    <p>${escapeHtml(w.recommendation || '')}</p>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        warningsContainer.innerHTML = '<p style="color: #38a169;">✓ Keine auffälligen Muster erkannt</p>';
                    }
                }
                
                // Insights anzeigen
                if (insightsContainer) {
                    if (data.insights && data.insights.length > 0) {
                        insightsContainer.innerHTML = data.insights.map(i => `
                            <div class="insight-card">
                                <span class="insight-icon">${i.icon || '💡'}</span>
                                <div class="insight-content">
                                    <h4>${escapeHtml(i.message)}</h4>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        insightsContainer.innerHTML = '';
                    }
                }
                
                // Statistiken anzeigen
                if (statsSummary && data.stats) {
                    const avg = data.stats.averages || {};
                    statsSummary.innerHTML = `
                        <div class="stat-item">
                            <span class="stat-value">${data.stats.entry_count || 0}</span>
                            <span class="stat-label">Einträge</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${data.stats.days_covered || 0}</span>
                            <span class="stat-label">Tage</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${avg.mood != null ? valueToEmoji(Math.round(avg.mood)) + ' ' + avg.mood.toFixed(1) : '-'}</span>
                            <span class="stat-label">Ø Stimmung</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${avg.focus != null ? valueToEmoji(Math.round(avg.focus)) + ' ' + avg.focus.toFixed(1) : '-'}</span>
                            <span class="stat-label">Ø Fokus</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">${avg.sleep != null ? avg.sleep.toFixed(1) + 'h' : '-'}</span>
                            <span class="stat-label">Ø Schlaf</span>
                        </div>
                    `;
                }
            };

            // Wochenvergleich laden
            const loadComparison = async () => {
                if (!compareSection) return;
                const userId = userFilter?.value;
                
                try {
                    let url = '/api/report/compare?type=week';
                    if (userId) url += `&user_id=${userId}`;
                    
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        displayComparison(data);
                        compareSection.style.display = 'block';
                    } else {
                        compareSection.style.display = 'none';
                    }
                } catch (error) {
                    utils.error('Fehler beim Laden des Vergleichs:', error);
                    compareSection.style.display = 'none';
                }
            };

            const displayComparison = (data) => {
                const container = document.getElementById('compare-container');
                if (!container || !data.comparison) return;
                
                const metricLabels = {
                    mood: 'Stimmung',
                    focus: 'Fokus',
                    sleep: 'Schlaf',
                    appetite: 'Appetit'
                };
                
                const directionArrows = {
                    improved: '↑',
                    declined: '↓',
                    stable: '→'
                };
                
                const directionLabels = {
                    improved: 'Verbessert',
                    declined: 'Verschlechtert',
                    stable: 'Stabil'
                };
                
                let html = '';
                for (const [metric, values] of Object.entries(data.comparison)) {
                    if (!values.this_week && !values.last_week) continue;
                    
                    html += `
                        <div class="compare-metric">
                            <h4>${metricLabels[metric] || metric}</h4>
                            <div class="compare-values">
                                <div class="compare-period">
                                    <span class="value">${values.last_week?.toFixed(1) || '-'}</span>
                                    <span class="label">Letzte Woche</span>
                                </div>
                                <span class="compare-arrow ${values.direction}">${directionArrows[values.direction] || '→'}</span>
                                <div class="compare-period">
                                    <span class="value">${values.this_week?.toFixed(1) || '-'}</span>
                                    <span class="label">Diese Woche</span>
                                </div>
                            </div>
                            <div class="compare-change ${values.direction}">
                                ${directionLabels[values.direction] || 'Stabil'} 
                                ${values.change ? `(${values.change > 0 ? '+' : ''}${values.change.toFixed(1)})` : ''}
                            </div>
                        </div>
                    `;
                }
                
                if (html === '') {
                    html = '<p>Nicht genügend Daten für einen Vergleich vorhanden.</p>';
                }
                
                container.innerHTML = html;
            };

            // Hilfsfunktion: Dateinamen aus Header extrahieren (RFC 5987 Support)
            const getFilenameFromHeader = (header) => {
                if (!header) return null;
                // 1. Priorität: filename*=UTF-8''... (für Umlaute)
                const matchesStar = header.match(/filename\*=UTF-8''([^;]+)/i);
                if (matchesStar && matchesStar[1]) {
                    return decodeURIComponent(matchesStar[1]);
                }
                // 2. Fallback: filename="..."
                const matches = header.match(/filename="?([^"]+)"?/i);
                return (matches && matches[1]) ? matches[1] : null;
            };

            // Hilfsfunktion: Server-Export auslösen
            const triggerServerExport = async (url, defaultFilename) => {
                try {
                    const response = await fetch(url);
                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.error || `Server-Fehler: ${response.status}`);
                    }
                    const blob = await response.blob();
                    const downloadUrl = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = downloadUrl;
                    const header = response.headers.get('Content-Disposition');
                    a.download = getFilenameFromHeader(header) || defaultFilename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(downloadUrl);
                } catch (error) {
                    utils.error('Export fehlgeschlagen:', error);
                    alert('Der Export konnte nicht erstellt werden:\n' + error.message);
                }
            };

            // Excel-Export (Server-seitig)
            const exportToExcel = async () => {
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter?.value;
                
                if (!dateFrom || !dateTo) {
                    alert('Bitte wählen Sie einen Datumsbereich aus.');
                    return;
                }
                
                let url = `/api/report/export/excel?date_from=${dateFrom}&date_to=${dateTo}&format=detailed`;
                if (userId) url += `&user_id=${userId}`;
                
                await triggerServerExport(url, `fokuslog_export_${dateFrom}_${dateTo}.csv`);
            };

            // Arzt-Export
            const exportForDoctor = async () => {
                const dateFrom = dateFromInput?.value;
                const dateTo = dateToInput?.value;
                const userId = userFilter?.value;
                
                if (!dateFrom || !dateTo) {
                    alert('Bitte wählen Sie einen Datumsbereich aus.');
                    return;
                }
                
                let url = `/api/report/export/excel?date_from=${dateFrom}&date_to=${dateTo}&format=doctor`;
                if (userId) url += `&user_id=${userId}`;
                
                await triggerServerExport(url, `fokuslog_arztbericht_${dateFrom}_${dateTo}.csv`);
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
                    utils.error('Fehler beim Laden der Gewichtsdaten:', error);
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
                    loadTrends();
                    loadComparison();
                });
            }
            if (userFilter) {
                userFilter.addEventListener('change', () => {
                    loadEntries();
                    loadWeightData();
                    loadTrends();
                    loadComparison();
                });
            }
            if (exportCsvBtn) {
                exportCsvBtn.addEventListener('click', exportToCSV);
            }
            if (exportExcelBtn) {
                exportExcelBtn.addEventListener('click', exportToExcel);
            }
            if (exportDoctorBtn) {
                exportDoctorBtn.addEventListener('click', exportForDoctor);
            }
            if (exportPdfBtn) {
                exportPdfBtn.addEventListener('click', exportToPDF);
            }
            loadUsersForFilter();
            setDefaultDates();
            loadEntries();
            loadTrends();
            loadComparison();
            if (document.getElementById('weight-history')) {
                loadWeightData();
            }
        }
    };
})(window);
