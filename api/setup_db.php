    }
}

// Nachträgliche Schema-Anpassungen (Migrationen) für existierende Tabellen
$migrations = [
    "ALTER TABLE medications ADD COLUMN default_dose VARCHAR(50)"
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Migration ausgeführt: $sql<br>\n";
    } catch (PDOException $e) {
        // Fehler 1060: Duplicate column name (Spalte existiert bereits) - ignorieren
        if (($e->errorInfo[1] ?? 0) != 1060) {
            echo "❌ Migrations-Fehler: " . $e->getMessage() . "<br>\n";
        }
    }
}

echo "<br>Setup abgeschlossen.";
