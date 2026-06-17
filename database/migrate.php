<?php
/**
 * Database Migration Runner
 * PT Mega Karya Modern - MKM Procurement
 */

require_once __DIR__ . '/../config.php';

// Detect CLI or Browser
$isCli = (php_sapi_name() === 'cli');

try {
    // 1. Create migrations tracking table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) UNIQUE NOT NULL,
            run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (Exception $e) {
    if ($isCli) {
        echo "Failed to create migrations table: " . $e->getMessage() . "\n";
    } else {
        echo "<h3>Failed to create migrations table</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    }
    exit(1);
}

// 2. Fetch ran migrations
$ranMigrations = $pdo->query("SELECT migration_name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

// 3. Scan database folder for migrate_*.php files
$migrationFiles = glob(__DIR__ . '/migrate_*.php');
sort($migrationFiles); // Ensure alphabetical order

$results = [];
$runCount = 0;

foreach ($migrationFiles as $file) {
    $filename = basename($file);
    
    // Skip if already run
    if (in_array($filename, $ranMigrations)) {
        $results[] = [
            'file' => $filename,
            'status' => 'skipped',
            'log' => 'Already migrated.'
        ];
        continue;
    }
    
    $log = '';
    $returnVar = 0;
    
    // Execute migration in an isolated PHP process to prevent fatal exit() from stopping the runner
    if (function_exists('exec')) {
        $escapedFile = escapeshellarg($file);
        $phpCmd = 'php';
        $command = "$phpCmd $escapedFile 2>&1";
        
        $output = [];
        exec($command, $output, $returnVar);
        $log = implode("\n", $output);
    } else {
        // Fallback: direct include if exec is disabled on host
        ob_start();
        try {
            include $file;
            $returnVar = 0;
        } catch (Exception $ex) {
            echo "Error: " . $ex->getMessage() . "\n";
            $returnVar = 1;
        }
        $log = ob_get_clean();
    }
    
    if ($returnVar === 0) {
        // Log migration to tracking table
        $stmt = $pdo->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        $stmt->execute([$filename]);
        
        $results[] = [
            'file' => $filename,
            'status' => 'success',
            'log' => $log
        ];
        $runCount++;
    } else {
        $results[] = [
            'file' => $filename,
            'status' => 'failed',
            'log' => "Process exited with code $returnVar.\nLog:\n" . $log
        ];
    }
}

// 4. Output results based on environment
if ($isCli) {
    echo "\n=== MKM Procurement - Database Migration Runner ===\n";
    echo "Scanning: " . count($migrationFiles) . " migration files found.\n\n";
    
    foreach ($results as $res) {
        $statusStr = strtoupper($res['status']);
        if ($res['status'] === 'success') {
            echo "[" . $statusStr . "] " . $res['file'] . "\n";
            echo "         " . str_replace("\n", "\n         ", trim($res['log'])) . "\n\n";
        } elseif ($res['status'] === 'skipped') {
            echo "[" . $statusStr . "] " . $res['file'] . "\n\n";
        } else {
            echo "[" . $statusStr . "] " . $res['file'] . "\n";
            echo "         " . str_replace("\n", "\n         ", trim($res['log'])) . "\n\n";
        }
    }
    
    echo "Migration finished. $runCount applied successfully.\n";
} else {
    // Beautiful HTML view with Montserrat/Work Sans & Industrial styling
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Database Migrasi - MKM Procurement</title>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            body {
                font-family: 'Work Sans', -apple-system, BlinkMacSystemFont, sans-serif;
                background-color: #f7f9fb;
                color: #191c1e;
                margin: 0;
                padding: 40px 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                box-sizing: border-box;
            }
            .migration-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                width: 100%;
                max-width: 800px;
                padding: 30px;
                box-sizing: border-box;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .migration-card:hover {
                transform: translateY(-2px);
                box-shadow: 4px 4px 0px #1e293b;
            }
            .header {
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 15px;
                margin-bottom: 25px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .title {
                font-family: 'Montserrat', sans-serif;
                font-weight: 800;
                font-size: 20px;
                letter-spacing: -0.01em;
                color: #091426;
                margin: 0;
                text-transform: uppercase;
            }
            .title i {
                color: #f28c28;
                margin-right: 10px;
            }
            .badge-count {
                background-color: #1e293b;
                color: #ffffff;
                font-family: 'Montserrat', sans-serif;
                font-weight: 600;
                font-size: 12px;
                padding: 4px 10px;
                border-radius: 9999px;
            }
            .migration-list {
                display: flex;
                flex-direction: column;
                gap: 15px;
            }
            .migration-item {
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                padding: 15px;
                background-color: #ffffff;
            }
            .migration-item-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 600;
            }
            .migration-name {
                font-family: 'Work Sans', sans-serif;
                color: #1e293b;
                font-size: 15px;
            }
            .status-badge {
                font-family: 'Work Sans', sans-serif;
                font-weight: 700;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 3px 8px;
                border-radius: 4px;
            }
            .status-success {
                background-color: #dcfce7;
                color: #15803d;
            }
            .status-skipped {
                background-color: #f1f5f9;
                color: #475569;
            }
            .status-failed {
                background-color: #fee2e2;
                color: #b91c1c;
            }
            .log-box {
                margin-top: 10px;
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                padding: 10px;
                font-family: monospace;
                font-size: 12px;
                color: #334155;
                white-space: pre-wrap;
                max-height: 150px;
                overflow-y: auto;
            }
            .footer-info {
                margin-top: 30px;
                text-align: center;
                font-family: 'Montserrat', sans-serif;
                font-weight: 600;
                font-size: 13px;
                color: #64748b;
                border-top: 1px solid #e2e8f0;
                padding-top: 20px;
            }
            .btn-back {
                display: inline-block;
                margin-top: 15px;
                font-family: 'Montserrat', sans-serif;
                font-weight: 600;
                font-size: 13px;
                text-decoration: none;
                color: #ffffff;
                background-color: #1e293b;
                padding: 8px 16px;
                border-radius: 4px;
                transition: background-color 0.2s ease;
            }
            .btn-back:hover {
                background-color: #0f172a;
            }
        </style>
    </head>
    <body>
        <div class="migration-card">
            <div class="header">
                <h1 class="title"><i class="fas fa-database"></i> Database Migration Runner</h1>
                <span class="badge-count"><?= count($migrationFiles) ?> Files</span>
            </div>
            
            <div class="migration-list">
                <?php if (empty($results)): ?>
                    <div style="text-align: center; color: #64748b; padding: 20px 0;">No migration files found.</div>
                <?php else: ?>
                    <?php foreach ($results as $res): ?>
                        <div class="migration-item">
                            <div class="migration-item-header">
                                <span class="migration-name"><?= htmlspecialchars($res['file']) ?></span>
                                <?php
                                $statusClass = 'status-skipped';
                                if ($res['status'] === 'success') $statusClass = 'status-success';
                                if ($res['status'] === 'failed') $statusClass = 'status-failed';
                                ?>
                                <span class="status-badge <?= $statusClass ?>"><?= $res['status'] ?></span>
                            </div>
                            <?php if ($res['status'] !== 'skipped' && !empty($res['log'])): ?>
                                <div class="log-box"><?= htmlspecialchars(trim($res['log'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="footer-info">
                <?= $runCount ?> migration(s) applied successfully.
                <br>
                <a href="../modules/dashboard/index.php" class="btn-back"><i class="fas fa-home"></i> Ke Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
