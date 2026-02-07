<?php
declare(strict_types=1);

namespace FokusLog\Controller;

use PDO;
use Throwable;

/**
 * Controller für Reporting, Analysen und Exporte.
 */
class ReportController extends BaseController
{
    /**
     * GET /report/trends
     * Analysiert Trends und erkennt auffällige Muster.
     */
    public function trends(): void
    {
        try {
            $user = $this->requireAuth();
            $params = $this->getQueryParams();
            
            $targetUserId = (int)$user['id'];
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                if ($stmt->fetch()) {
                    $targetUserId = $uid;
                }
            }

            // Letzte 14 Tage für Trendanalyse
            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-14 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');

            $sql = 'SELECT date, time, mood, focus, sleep, appetite, irritability, hyperactivity, 
                           medication_id, dose, side_effects, weight
                    FROM entries 
                    WHERE user_id = ? AND date BETWEEN ? AND ?
                    ORDER BY date ASC, FIELD(time, "morning", "noon", "evening")';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetUserId, $dateFrom, $dateTo]);
            $entries = $stmt->fetchAll();

            $warnings = [];
            $insights = [];
            $trends = [];

            if (count($entries) >= 3) {
                // Appetit-Warnung: 3+ Tage mit niedrigem Appetit (1-2)
                $lowAppetiteDays = $this->detectConsecutivePattern($entries, 'appetite', [1, 2], 3);
                if ($lowAppetiteDays) {
                    $warnings[] = [
                        'type' => 'appetite_low',
                        'severity' => 'warning',
                        'message' => "Niedriger Appetit an {$lowAppetiteDays} aufeinanderfolgenden Tagen erkannt",
                        'recommendation' => 'Besprechen Sie dies beim nächsten Arzttermin. Kleine, häufige Mahlzeiten können helfen.'
                    ];
                }

                // Stimmungs-Warnung: Abwärtstrend
                $moodTrend = $this->calculateTrend($entries, 'mood');
                if ($moodTrend['slope'] < -0.15 && $moodTrend['confidence'] > 0.5) {
                    $warnings[] = [
                        'type' => 'mood_declining',
                        'severity' => 'attention',
                        'message' => 'Abnehmende Stimmungswerte im Zeitraum erkannt',
                        'recommendation' => 'Achten Sie auf mögliche Auslöser und sprechen Sie mit Ihrem Arzt.'
                    ];
                }
                $trends['mood'] = $moodTrend;

                // Fokus-Trend
                $focusTrend = $this->calculateTrend($entries, 'focus');
                $trends['focus'] = $focusTrend;
                if ($focusTrend['slope'] > 0.1 && $focusTrend['confidence'] > 0.5) {
                    $insights[] = [
                        'type' => 'focus_improving',
                        'message' => 'Der Fokus verbessert sich tendenziell',
                        'icon' => '📈'
                    ];
                }

                // Schlaf-Qualität
                $sleepTrend = $this->calculateTrend($entries, 'sleep');
                $trends['sleep'] = $sleepTrend;
                $avgSleep = $this->calculateAverage($entries, 'sleep');
                if ($avgSleep !== null && $avgSleep < 3) {
                    $warnings[] = [
                        'type' => 'sleep_poor',
                        'severity' => 'attention',
                        'message' => 'Durchschnittliche Schlafqualität ist niedrig (' . round($avgSleep, 1) . '/5)',
                        'recommendation' => 'Überprüfen Sie die Schlafhygiene und Medikamenten-Timing.'
                    ];
                }

                // Reizbarkeit-Warnung
                $highIrritabilityDays = $this->detectConsecutivePattern($entries, 'irritability', [4, 5], 3);
                if ($highIrritabilityDays) {
                    $warnings[] = [
                        'type' => 'irritability_high',
                        'severity' => 'warning',
                        'message' => "Erhöhte Reizbarkeit an {$highIrritabilityDays} aufeinanderfolgenden Tagen",
                        'recommendation' => 'Dies könnte ein Rebound-Effekt sein. Besprechen Sie Dosierung und Timing mit dem Arzt.'
                    ];
                }

                // Gewichtsverlust-Warnung
                $weightWarning = $this->checkWeightTrend($entries);
                if ($weightWarning) {
                    $warnings[] = $weightWarning;
                }

                // Nebenwirkungen-Häufung
                $sideEffectCount = array_reduce($entries, fn($count, $e) => 
                    $count + (!empty($e['side_effects']) ? 1 : 0), 0);
                if ($sideEffectCount >= 5) {
                    $warnings[] = [
                        'type' => 'side_effects_frequent',
                        'severity' => 'attention',
                        'message' => "Nebenwirkungen wurden in {$sideEffectCount} Einträgen dokumentiert",
                        'recommendation' => 'Dokumentieren Sie die Art der Nebenwirkungen und besprechen Sie Alternativen.'
                    ];
                }

                // Positive Insights
                $avgMood = $this->calculateAverage($entries, 'mood');
                $avgFocus = $this->calculateAverage($entries, 'focus');
                if ($avgMood !== null && $avgMood >= 4) {
                    $insights[] = [
                        'type' => 'mood_good',
                        'message' => 'Stimmung ist durchschnittlich gut (' . round($avgMood, 1) . '/5)',
                        'icon' => '😊'
                    ];
                }
                if ($avgFocus !== null && $avgFocus >= 4) {
                    $insights[] = [
                        'type' => 'focus_good',
                        'message' => 'Fokus ist durchschnittlich gut (' . round($avgFocus, 1) . '/5)',
                        'icon' => '🎯'
                    ];
                }
            }

            // Statistiken
            $stats = [
                'entry_count' => count($entries),
                'days_covered' => count(array_unique(array_column($entries, 'date'))),
                'averages' => [
                    'mood' => $this->calculateAverage($entries, 'mood'),
                    'focus' => $this->calculateAverage($entries, 'focus'),
                    'sleep' => $this->calculateAverage($entries, 'sleep'),
                    'appetite' => $this->calculateAverage($entries, 'appetite'),
                    'irritability' => $this->calculateAverage($entries, 'irritability'),
                    'hyperactivity' => $this->calculateAverage($entries, 'hyperactivity'),
                ]
            ];

            $this->respond(200, [
                'warnings' => $warnings,
                'insights' => $insights,
                'trends' => $trends,
                'stats' => $stats,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]);

        } catch (Throwable $e) {
            app_log('ERROR', 'trends_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler bei der Trendanalyse: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /report/compare
     * Vergleicht zwei Zeiträume oder Medikamente.
     */
    public function compare(): void
    {
        try {
            $user = $this->requireAuth();
            $params = $this->getQueryParams();

            $targetUserId = (int)$user['id'];
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                if ($stmt->fetch()) {
                    $targetUserId = $uid;
                }
            }

            $compareType = $params['type'] ?? 'week'; // 'week', 'medication', 'custom'

            if ($compareType === 'week') {
                // Woche-über-Woche Vergleich
                $result = $this->compareWeeks($targetUserId);
            } elseif ($compareType === 'medication') {
                // Medikamenten-Vergleich
                $medId1 = (int)($params['med1'] ?? 0);
                $medId2 = (int)($params['med2'] ?? 0);
                $result = $this->compareMedications($targetUserId, $medId1, $medId2);
            } else {
                // Custom Zeitraum-Vergleich
                $period1From = $params['period1_from'] ?? '';
                $period1To = $params['period1_to'] ?? '';
                $period2From = $params['period2_from'] ?? '';
                $period2To = $params['period2_to'] ?? '';
                $result = $this->comparePeriods($targetUserId, $period1From, $period1To, $period2From, $period2To);
            }

            $this->respond(200, $result);

        } catch (Throwable $e) {
            app_log('ERROR', 'compare_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Vergleich: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /report/export/excel
     * Exportiert Daten als Excel-kompatibles Format (CSV mit BOM für Excel).
     */
    public function exportExcel(): void
    {
        try {
            $user = $this->requireAuth();
            $params = $this->getQueryParams();

            $targetUserId = (int)$user['id'];
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                $targetUserData = $stmt->fetch();
                if ($targetUserData) {
                    $targetUserId = $uid;
                }
            }

            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');
            $format = $params['format'] ?? 'detailed'; // 'detailed', 'summary', 'doctor'

            // Einträge laden
            $sql = 'SELECT e.*, m.name AS medication_name, u.username,
                           GROUP_CONCAT(t.name SEPARATOR ", ") as tags
                    FROM entries e 
                    LEFT JOIN medications m ON e.medication_id = m.id 
                    LEFT JOIN users u ON e.user_id = u.id 
                    LEFT JOIN entry_tags et ON e.id = et.entry_id
                    LEFT JOIN tags t ON et.tag_id = t.id
                    WHERE e.user_id = ? AND e.date BETWEEN ? AND ?
                    GROUP BY e.id
                    ORDER BY e.date DESC, FIELD(e.time, "morning", "noon", "evening")';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetUserId, $dateFrom, $dateTo]);
            $entries = $stmt->fetchAll();

            if ($format === 'doctor') {
                $csv = $this->buildDoctorReportCSV($entries, $targetUserId, $dateFrom, $dateTo);
            } elseif ($format === 'summary') {
                $csv = $this->buildSummaryCSV($entries, $dateFrom, $dateTo);
            } else {
                $csv = $this->buildDetailedCSV($entries);
            }

            // Response als Download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="fokuslog_export_' . $dateFrom . '_' . $dateTo . '.csv"');
            
            // BOM für Excel UTF-8 Erkennung
            echo "\xEF\xBB\xBF";
            echo $csv;
            exit;

        } catch (Throwable $e) {
            app_log('ERROR', 'export_excel_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler beim Export: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /report/summary
     * Gibt eine Zusammenfassung für einen Zeitraum zurück (für PDF-Report).
     */
    public function summary(): void
    {
        try {
            $user = $this->requireAuth();
            $params = $this->getQueryParams();

            $targetUserId = (int)$user['id'];
            $username = $user['username'];
            
            if (($user['role'] === 'parent' || $user['role'] === 'adult') && !empty($params['user_id'])) {
                $uid = (int)$params['user_id'];
                $stmt = $this->pdo->prepare('SELECT id, username FROM users WHERE id = ? AND family_id = ?');
                $stmt->execute([$uid, $user['family_id']]);
                $targetUserData = $stmt->fetch();
                if ($targetUserData) {
                    $targetUserId = $uid;
                    $username = $targetUserData['username'];
                }
            }

            $dateFrom = $params['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $params['date_to'] ?? date('Y-m-d');

            // Einträge laden
            $sql = 'SELECT e.*, m.name AS medication_name
                    FROM entries e 
                    LEFT JOIN medications m ON e.medication_id = m.id 
                    WHERE e.user_id = ? AND e.date BETWEEN ? AND ?
                    ORDER BY e.date ASC';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetUserId, $dateFrom, $dateTo]);
            $entries = $stmt->fetchAll();

            // Medikamentenverteilung
            $medicationStats = [];
            foreach ($entries as $entry) {
                $medName = $entry['medication_name'] ?? 'Ohne Medikation';
                if (!isset($medicationStats[$medName])) {
                    $medicationStats[$medName] = ['count' => 0, 'mood_sum' => 0, 'focus_sum' => 0, 'mood_count' => 0, 'focus_count' => 0];
                }
                $medicationStats[$medName]['count']++;
                if ($entry['mood']) {
                    $medicationStats[$medName]['mood_sum'] += $entry['mood'];
                    $medicationStats[$medName]['mood_count']++;
                }
                if ($entry['focus']) {
                    $medicationStats[$medName]['focus_sum'] += $entry['focus'];
                    $medicationStats[$medName]['focus_count']++;
                }
            }

            foreach ($medicationStats as $med => &$stats) {
                $stats['avg_mood'] = $stats['mood_count'] > 0 ? round($stats['mood_sum'] / $stats['mood_count'], 1) : null;
                $stats['avg_focus'] = $stats['focus_count'] > 0 ? round($stats['focus_sum'] / $stats['focus_count'], 1) : null;
                unset($stats['mood_sum'], $stats['focus_sum'], $stats['mood_count'], $stats['focus_count']);
            }

            // Tageszeit-Analyse
            $timeSlotStats = ['morning' => [], 'noon' => [], 'evening' => []];
            foreach ($entries as $entry) {
                $slot = $entry['time'];
                if (isset($timeSlotStats[$slot])) {
                    if ($entry['mood']) $timeSlotStats[$slot]['mood'][] = $entry['mood'];
                    if ($entry['focus']) $timeSlotStats[$slot]['focus'][] = $entry['focus'];
                }
            }

            foreach ($timeSlotStats as $slot => &$data) {
                $data['avg_mood'] = !empty($data['mood']) ? round(array_sum($data['mood']) / count($data['mood']), 1) : null;
                $data['avg_focus'] = !empty($data['focus']) ? round(array_sum($data['focus']) / count($data['focus']), 1) : null;
                $data['count'] = max(count($data['mood'] ?? []), count($data['focus'] ?? []));
                unset($data['mood'], $data['focus']);
            }

            // Nebenwirkungen sammeln
            $sideEffects = [];
            foreach ($entries as $entry) {
                if (!empty($entry['side_effects'])) {
                    $sideEffects[] = [
                        'date' => $entry['date'],
                        'time' => $entry['time'],
                        'text' => $entry['side_effects'],
                        'medication' => $entry['medication_name']
                    ];
                }
            }

            $this->respond(200, [
                'username' => $username,
                'period' => ['from' => $dateFrom, 'to' => $dateTo],
                'total_entries' => count($entries),
                'days_with_entries' => count(array_unique(array_column($entries, 'date'))),
                'averages' => [
                    'mood' => $this->calculateAverage($entries, 'mood'),
                    'focus' => $this->calculateAverage($entries, 'focus'),
                    'sleep' => $this->calculateAverage($entries, 'sleep'),
                    'appetite' => $this->calculateAverage($entries, 'appetite'),
                    'irritability' => $this->calculateAverage($entries, 'irritability'),
                ],
                'medication_stats' => $medicationStats,
                'time_slot_stats' => $timeSlotStats,
                'side_effects' => array_slice($sideEffects, 0, 10), // Max 10 für Übersicht
                'side_effects_total' => count($sideEffects)
            ]);

        } catch (Throwable $e) {
            app_log('ERROR', 'summary_failed', ['error' => $e->getMessage()]);
            $this->respond(500, ['error' => 'Fehler bei der Zusammenfassung: ' . $e->getMessage()]);
        }
    }

    // ========== Hilfsfunktionen ==========

    private function detectConsecutivePattern(array $entries, string $field, array $values, int $minDays): ?int
    {
        $dateValues = [];
        foreach ($entries as $entry) {
            $date = $entry['date'];
            $value = $entry[$field] ?? null;
            if ($value !== null && in_array((int)$value, $values, true)) {
                $dateValues[$date] = true;
            }
        }

        if (count($dateValues) < $minDays) {
            return null;
        }

        $dates = array_keys($dateValues);
        sort($dates);
        
        $consecutive = 1;
        $maxConsecutive = 1;
        
        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = new \DateTime($dates[$i - 1]);
            $currDate = new \DateTime($dates[$i]);
            $diff = $prevDate->diff($currDate)->days;
            
            if ($diff === 1) {
                $consecutive++;
                $maxConsecutive = max($maxConsecutive, $consecutive);
            } else {
                $consecutive = 1;
            }
        }

        return $maxConsecutive >= $minDays ? $maxConsecutive : null;
    }

    private function calculateTrend(array $entries, string $field): array
    {
        $values = [];
        $i = 0;
        foreach ($entries as $entry) {
            if (isset($entry[$field]) && $entry[$field] !== null && $entry[$field] !== '') {
                $values[] = ['x' => $i, 'y' => (float)$entry[$field]];
            }
            $i++;
        }

        if (count($values) < 3) {
            return ['slope' => 0, 'confidence' => 0, 'direction' => 'stable', 'data_points' => count($values)];
        }

        // Lineare Regression
        $n = count($values);
        $sumX = array_sum(array_column($values, 'x'));
        $sumY = array_sum(array_column($values, 'y'));
        $sumXY = array_reduce($values, fn($sum, $v) => $sum + ($v['x'] * $v['y']), 0);
        $sumX2 = array_reduce($values, fn($sum, $v) => $sum + ($v['x'] * $v['x']), 0);

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if ($denominator == 0) {
            return ['slope' => 0, 'confidence' => 0, 'direction' => 'stable', 'data_points' => $n];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        
        // R² für Confidence
        $yMean = $sumY / $n;
        $ssRes = 0;
        $ssTot = 0;
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        foreach ($values as $v) {
            $predicted = $intercept + $slope * $v['x'];
            $ssRes += pow($v['y'] - $predicted, 2);
            $ssTot += pow($v['y'] - $yMean, 2);
        }
        
        $rSquared = $ssTot > 0 ? 1 - ($ssRes / $ssTot) : 0;

        $direction = $slope > 0.05 ? 'up' : ($slope < -0.05 ? 'down' : 'stable');

        return [
            'slope' => round($slope, 3),
            'confidence' => round(max(0, $rSquared), 2),
            'direction' => $direction,
            'data_points' => $n
        ];
    }

    private function calculateAverage(array $entries, string $field): ?float
    {
        $values = array_filter(array_column($entries, $field), fn($v) => $v !== null && $v !== '');
        if (empty($values)) {
            return null;
        }
        return round(array_sum($values) / count($values), 2);
    }

    private function checkWeightTrend(array $entries): ?array
    {
        $weights = [];
        foreach ($entries as $entry) {
            if (!empty($entry['weight'])) {
                $weights[$entry['date']] = (float)$entry['weight'];
            }
        }

        if (count($weights) < 2) {
            return null;
        }

        ksort($weights);
        $weightValues = array_values($weights);
        $firstWeight = $weightValues[0];
        $lastWeight = end($weightValues);
        
        $change = $lastWeight - $firstWeight;
        $percentChange = ($change / $firstWeight) * 100;

        if ($percentChange < -3) { // Mehr als 3% Gewichtsverlust
            return [
                'type' => 'weight_loss',
                'severity' => abs($percentChange) > 5 ? 'warning' : 'attention',
                'message' => sprintf('Gewichtsverlust von %.1f kg (%.1f%%) im Zeitraum', abs($change), abs($percentChange)),
                'recommendation' => 'Appetitprobleme sind eine häufige Nebenwirkung. Besprechen Sie Strategien mit dem Arzt.'
            ];
        }

        return null;
    }

    private function compareWeeks(int $userId): array
    {
        $today = new \DateTime();
        $thisWeekStart = (clone $today)->modify('monday this week')->format('Y-m-d');
        $thisWeekEnd = $today->format('Y-m-d');
        
        $lastWeekStart = (clone $today)->modify('monday last week')->format('Y-m-d');
        $lastWeekEnd = (clone $today)->modify('sunday last week')->format('Y-m-d');

        $thisWeek = $this->getPeriodStats($userId, $thisWeekStart, $thisWeekEnd);
        $lastWeek = $this->getPeriodStats($userId, $lastWeekStart, $lastWeekEnd);

        $comparison = [];
        foreach (['mood', 'focus', 'sleep', 'appetite'] as $metric) {
            $thisVal = $thisWeek['averages'][$metric];
            $lastVal = $lastWeek['averages'][$metric];
            
            if ($thisVal !== null && $lastVal !== null) {
                $change = $thisVal - $lastVal;
                $comparison[$metric] = [
                    'this_week' => $thisVal,
                    'last_week' => $lastVal,
                    'change' => round($change, 2),
                    'change_percent' => $lastVal > 0 ? round(($change / $lastVal) * 100, 1) : 0,
                    'direction' => $change > 0.2 ? 'improved' : ($change < -0.2 ? 'declined' : 'stable')
                ];
            }
        }

        return [
            'type' => 'week',
            'periods' => [
                'this_week' => ['from' => $thisWeekStart, 'to' => $thisWeekEnd, 'stats' => $thisWeek],
                'last_week' => ['from' => $lastWeekStart, 'to' => $lastWeekEnd, 'stats' => $lastWeek]
            ],
            'comparison' => $comparison
        ];
    }

    private function compareMedications(int $userId, int $medId1, int $medId2): array
    {
        if ($medId1 === 0 || $medId2 === 0) {
            return ['error' => 'Bitte zwei Medikamente zum Vergleich auswählen'];
        }

        $sql = 'SELECT e.*, m.name AS medication_name
                FROM entries e 
                LEFT JOIN medications m ON e.medication_id = m.id 
                WHERE e.user_id = ? AND e.medication_id = ?
                ORDER BY e.date DESC
                LIMIT 50';

        $stmt = $this->pdo->prepare($sql);
        
        $stmt->execute([$userId, $medId1]);
        $entries1 = $stmt->fetchAll();
        
        $stmt->execute([$userId, $medId2]);
        $entries2 = $stmt->fetchAll();

        $stats1 = $this->calculateEntriesStats($entries1);
        $stats2 = $this->calculateEntriesStats($entries2);

        $medName1 = !empty($entries1) ? $entries1[0]['medication_name'] : 'Medikament 1';
        $medName2 = !empty($entries2) ? $entries2[0]['medication_name'] : 'Medikament 2';

        $comparison = [];
        foreach (['mood', 'focus', 'sleep', 'appetite', 'irritability'] as $metric) {
            $val1 = $stats1[$metric] ?? null;
            $val2 = $stats2[$metric] ?? null;
            
            if ($val1 !== null && $val2 !== null) {
                $diff = $val1 - $val2;
                $comparison[$metric] = [
                    $medName1 => $val1,
                    $medName2 => $val2,
                    'difference' => round($diff, 2),
                    'better' => $diff > 0.2 ? $medName1 : ($diff < -0.2 ? $medName2 : 'equal')
                ];
            }
        }

        return [
            'type' => 'medication',
            'medications' => [
                ['id' => $medId1, 'name' => $medName1, 'entry_count' => count($entries1), 'stats' => $stats1],
                ['id' => $medId2, 'name' => $medName2, 'entry_count' => count($entries2), 'stats' => $stats2]
            ],
            'comparison' => $comparison
        ];
    }

    private function comparePeriods(int $userId, string $p1From, string $p1To, string $p2From, string $p2To): array
    {
        if (empty($p1From) || empty($p1To) || empty($p2From) || empty($p2To)) {
            return ['error' => 'Alle vier Datumsangaben erforderlich'];
        }

        $period1 = $this->getPeriodStats($userId, $p1From, $p1To);
        $period2 = $this->getPeriodStats($userId, $p2From, $p2To);

        $comparison = [];
        foreach (['mood', 'focus', 'sleep', 'appetite'] as $metric) {
            $val1 = $period1['averages'][$metric];
            $val2 = $period2['averages'][$metric];
            
            if ($val1 !== null && $val2 !== null) {
                $change = $val2 - $val1;
                $comparison[$metric] = [
                    'period1' => $val1,
                    'period2' => $val2,
                    'change' => round($change, 2),
                    'direction' => $change > 0.2 ? 'improved' : ($change < -0.2 ? 'declined' : 'stable')
                ];
            }
        }

        return [
            'type' => 'custom',
            'periods' => [
                ['from' => $p1From, 'to' => $p1To, 'stats' => $period1],
                ['from' => $p2From, 'to' => $p2To, 'stats' => $period2]
            ],
            'comparison' => $comparison
        ];
    }

    private function getPeriodStats(int $userId, string $dateFrom, string $dateTo): array
    {
        $sql = 'SELECT * FROM entries WHERE user_id = ? AND date BETWEEN ? AND ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $dateFrom, $dateTo]);
        $entries = $stmt->fetchAll();

        return [
            'entry_count' => count($entries),
            'days' => count(array_unique(array_column($entries, 'date'))),
            'averages' => [
                'mood' => $this->calculateAverage($entries, 'mood'),
                'focus' => $this->calculateAverage($entries, 'focus'),
                'sleep' => $this->calculateAverage($entries, 'sleep'),
                'appetite' => $this->calculateAverage($entries, 'appetite'),
            ]
        ];
    }

    private function calculateEntriesStats(array $entries): array
    {
        return [
            'mood' => $this->calculateAverage($entries, 'mood'),
            'focus' => $this->calculateAverage($entries, 'focus'),
            'sleep' => $this->calculateAverage($entries, 'sleep'),
            'appetite' => $this->calculateAverage($entries, 'appetite'),
            'irritability' => $this->calculateAverage($entries, 'irritability'),
        ];
    }

    private function buildDetailedCSV(array $entries): string
    {
        $headers = ['Datum', 'Uhrzeit', 'Benutzer', 'Medikament', 'Dosis', 'Stimmung', 'Fokus', 
                    'Schlaf', 'Appetit', 'Reizbarkeit', 'Hyperaktivität', 'Gewicht', 
                    'Nebenwirkungen', 'Besondere Ereignisse', 'Tags', 'Notizen'];
        
        $csv = implode(';', $headers) . "\n";

        foreach ($entries as $entry) {
            $timeLabels = ['morning' => 'Morgen', 'noon' => 'Mittag', 'evening' => 'Abend'];
            $row = [
                $this->formatGermanDate($entry['date']),
                $timeLabels[$entry['time']] ?? $entry['time'],
                $entry['username'] ?? '',
                $entry['medication_name'] ?? '',
                $entry['dose'] ?? '',
                $entry['mood'] ?? '',
                $entry['focus'] ?? '',
                $entry['sleep'] ?? '',
                $entry['appetite'] ?? '',
                $entry['irritability'] ?? '',
                $entry['hyperactivity'] ?? '',
                $entry['weight'] ?? '',
                str_replace(["\n", "\r", ";"], [" ", " ", ","], $entry['side_effects'] ?? ''),
                str_replace(["\n", "\r", ";"], [" ", " ", ","], $entry['special_events'] ?? ''),
                $entry['tags'] ?? '',
                str_replace(["\n", "\r", ";"], [" ", " ", ","], $entry['other_effects'] ?? '')
            ];
            $csv .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        return $csv;
    }

    private function buildSummaryCSV(array $entries, string $dateFrom, string $dateTo): string
    {
        $csv = "FokusLog Zusammenfassung\n";
        $csv .= "Zeitraum: " . $this->formatGermanDate($dateFrom) . " - " . $this->formatGermanDate($dateTo) . "\n";
        $csv .= "Anzahl Einträge: " . count($entries) . "\n\n";

        $csv .= "Durchschnittswerte:\n";
        $csv .= "Metrik;Durchschnitt\n";
        
        $metrics = ['mood' => 'Stimmung', 'focus' => 'Fokus', 'sleep' => 'Schlaf', 
                    'appetite' => 'Appetit', 'irritability' => 'Reizbarkeit'];
        
        foreach ($metrics as $key => $label) {
            $avg = $this->calculateAverage($entries, $key);
            $csv .= "$label;" . ($avg !== null ? number_format($avg, 1, ',', '') : '-') . "\n";
        }

        return $csv;
    }

    private function buildDoctorReportCSV(array $entries, int $userId, string $dateFrom, string $dateTo): string
    {
        // Benutzer-Info
        $stmt = $this->pdo->prepare('SELECT username, gender, initial_weight FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch();

        $csv = "MEDIKAMENTEN-TAGEBUCH EXPORT FÜR ARZTBESUCH\n";
        $csv .= "==========================================\n\n";
        $csv .= "Patient: " . ($userInfo['username'] ?? 'Unbekannt') . "\n";
        $csv .= "Zeitraum: " . $this->formatGermanDate($dateFrom) . " - " . $this->formatGermanDate($dateTo) . "\n";
        $csv .= "Erstellt am: " . $this->formatGermanDate(date('Y-m-d')) . "\n\n";

        // Zusammenfassung
        $csv .= "ZUSAMMENFASSUNG\n";
        $csv .= "---------------\n";
        $csv .= "Anzahl dokumentierter Tage: " . count(array_unique(array_column($entries, 'date'))) . "\n";
        $csv .= "Anzahl Einträge gesamt: " . count($entries) . "\n\n";

        $csv .= "DURCHSCHNITTSWERTE (Skala 1-5):\n";
        $metrics = ['mood' => 'Stimmung', 'focus' => 'Fokus', 'sleep' => 'Schlafqualität', 
                    'appetite' => 'Appetit', 'irritability' => 'Reizbarkeit'];
        
        foreach ($metrics as $key => $label) {
            $avg = $this->calculateAverage($entries, $key);
            $csv .= "  $label: " . ($avg !== null ? number_format($avg, 1, ',', '') . "/5" : 'keine Daten') . "\n";
        }

        // Nebenwirkungen
        $sideEffects = array_filter(array_column($entries, 'side_effects'));
        if (!empty($sideEffects)) {
            $csv .= "\nDOKUMENTIERTE NEBENWIRKUNGEN:\n";
            foreach ($entries as $entry) {
                if (!empty($entry['side_effects'])) {
                    $csv .= "  " . $this->formatGermanDate($entry['date']) . ": " . $entry['side_effects'] . "\n";
                }
            }
        }

        // Gewichtsverlauf
        $weights = array_filter(array_map(fn($e) => $e['weight'] ? ['date' => $e['date'], 'weight' => $e['weight']] : null, $entries));
        if (!empty($weights)) {
            $csv .= "\nGEWICHTSVERLAUF:\n";
            foreach ($weights as $w) {
                $csv .= "  " . $this->formatGermanDate($w['date']) . ": " . $w['weight'] . " kg\n";
            }
        }

        $csv .= "\n\nDETAILDATEN\n";
        $csv .= "===========\n";
        $csv .= $this->buildDetailedCSV($entries);

        return $csv;
    }

    private function formatGermanDate(string $date): string
    {
        $parts = explode('-', $date);
        if (count($parts) === 3) {
            return $parts[2] . '.' . $parts[1] . '.' . $parts[0];
        }
        return $date;
    }
}
