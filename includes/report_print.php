<?php
/**
 * Reusable Report Print Header & Footer
 * Include this in report files to add professional print layout.
 * 
 * Required vars before including:
 *   $pdo              - PDO connection
 *   $reportTitle      - Title of the report (e.g. "LAPORAN PENGELUARAN PROYEK")
 *   $reportPeriod     - Optional period text (e.g. "Periode: April 2026")
 * 
 * Usage:
 *   Set $reportTitle, $reportPeriod then include this file.
 *   Call renderReportPrintHeader() at the top of your content.
 *   Call renderReportPrintFooter() at the bottom.
 */

// Fetch company data once
if (!isset($__reportCompany)) {
    $__companyStmt = $pdo->query("SELECT * FROM companies ORDER BY id ASC LIMIT 1");
    $__reportCompany = $__companyStmt->fetch();
}

function renderReportPrintHeader($reportTitle, $reportPeriod = '', $company = null) {
    global $__reportCompany;
    $c = $company ?? $__reportCompany;
    $companyName = isset($c['name']) ? htmlspecialchars($c['name']) : 'PT. MEGA KARYA MODERN';
    $address = '';
    if (!empty($c['address'])) $address .= htmlspecialchars($c['address']);
    if (!empty($c['city'])) $address .= ', ' . htmlspecialchars($c['city']);
    if (!empty($c['province'])) $address .= ', ' . htmlspecialchars($c['province']);
    $contact = '';
    if (!empty($c['phone'])) $contact .= 'Telp: ' . htmlspecialchars($c['phone']);
    if (!empty($c['email'])) $contact .= ($contact ? ' | ' : '') . 'Email: ' . htmlspecialchars($c['email']);
    $logoUrl = (!empty($c['logo'])) ? getCompanyLogo($c['logo']) : null;
    ?>
    <div class="report-print-header">
        <div class="rph-inner">
            <div class="rph-logo-row">
                <?php if ($logoUrl): ?>
                    <img src="<?= $logoUrl ?>" alt="Logo">
                <?php endif; ?>
                <div>
                    <p class="rph-company-name"><?= $companyName ?></p>
                    <?php if ($address): ?>
                        <p class="rph-address"><?= $address ?></p>
                    <?php endif; ?>
                    <?php if ($contact): ?>
                        <p class="rph-address"><?= $contact ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="rph-title-section">
            <h2><?= htmlspecialchars($reportTitle) ?></h2>
            <?php if ($reportPeriod): ?>
                <p class="rph-period"><?= htmlspecialchars($reportPeriod) ?></p>
            <?php endif; ?>
            <p class="rph-period">Tanggal Cetak: <?= date('d-m-Y H:i') ?></p>
        </div>
    </div>
    <?php
}

function renderReportPrintFooter() {
    $user = getCurrentUser();
    $name = isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'System';
    ?>
    <div class="report-print-footer">
        Dicetak oleh: <strong><?= $name ?></strong> &mdash; <?= date('d-m-Y H:i:s') ?>
    </div>
    <?php
}
