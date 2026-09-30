<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$branchId = user_branch_id();


/*
|--------------------------------------------------------------------------
| FILTER BULAN DAN TAHUN
|--------------------------------------------------------------------------
*/

$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');

$selectedMonth = isset($_GET['bulan'])
    ? (int)$_GET['bulan']
    : $currentMonth;

$selectedYear = isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : $currentYear;


/*
|--------------------------------------------------------------------------
| VALIDASI FILTER
|--------------------------------------------------------------------------
*/

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = $currentMonth;
}

if ($selectedYear < 2020 || $selectedYear > ($currentYear + 5)) {
    $selectedYear = $currentYear;
}


/*
|--------------------------------------------------------------------------
| NAMA BULAN
|--------------------------------------------------------------------------
*/

$namaBulan = [
    1  => 'Januari',
    2  => 'Februari',
    3  => 'Maret',
    4  => 'April',
    5  => 'Mei',
    6  => 'Juni',
    7  => 'Juli',
    8  => 'Agustus',
    9  => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];


/*
|--------------------------------------------------------------------------
| TANGGAL AWAL DAN AKHIR BULAN
|--------------------------------------------------------------------------
*/

$startDate = sprintf(
    '%04d-%02d-01 00:00:00',
    $selectedYear,
    $selectedMonth
);

$endDate = date(
    'Y-m-d H:i:s',
    strtotime($startDate . ' +1 month')
);


/*
|--------------------------------------------------------------------------
| AMBIL DATA RIWAYAT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        uh.id,
        uh.unit_id,
        uh.performed_by,
        uh.branch_id,
        uh.action,
        uh.description,
        uh.created_at,

        u.kode_unit,
        u.nama_unit,
        u.kategori,

        usr.nama AS performed_by_name,

        b.nama_cabang

    FROM unit_history uh

    INNER JOIN units u
        ON u.id = uh.unit_id

    LEFT JOIN users usr
        ON usr.id = uh.performed_by

    INNER JOIN branches b
        ON b.id = uh.branch_id

    WHERE uh.branch_id = ?
      AND uh.created_at >= ?
      AND uh.created_at < ?

    ORDER BY
        uh.created_at DESC,
        uh.id DESC
");

$stmt->execute([
    $branchId,
    $startDate,
    $endDate
]);

$history = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| JUMLAH AKTIVITAS
|--------------------------------------------------------------------------
*/

$totalHistory = count($history);


/*
|--------------------------------------------------------------------------
| HITUNG PER ACTION
|--------------------------------------------------------------------------
*/

$actionCount = [];

foreach ($history as $item) {

    $action = $item['action'];

    if (!isset($actionCount[$action])) {
        $actionCount[$action] = 0;
    }

    $actionCount[$action]++;
}


/*
|--------------------------------------------------------------------------
| LABEL ACTION
|--------------------------------------------------------------------------
*/

function actionLabel($action)
{
    $labels = [

        'KELUAR' => 'Unit Keluar',

        'MASUK' => 'Unit Masuk',

        'EDIT_UNIT' => 'Edit Unit',

        'TAMBAH_UNIT' => 'Tambah Unit',

        'TRANSFER' => 'Transfer Unit',

        'TRANSFER_KELUAR' => 'Transfer Keluar',

        'TRANSFER_MASUK' => 'Transfer Masuk',

        'BATAL_MASUK' => 'Batalkan Unit Masuk'

    ];

    return $labels[$action] ?? $action;
}


/*
|--------------------------------------------------------------------------
| CLASS ACTION
|--------------------------------------------------------------------------
*/

function actionClass($action)
{
    switch ($action) {

        case 'KELUAR':
            return 'action-keluar';

        case 'MASUK':
            return 'action-masuk';

        case 'EDIT_UNIT':
            return 'action-edit';

        case 'TAMBAH_UNIT':
            return 'action-tambah';

        case 'TRANSFER':
        case 'TRANSFER_KELUAR':
        case 'TRANSFER_MASUK':
            return 'action-transfer';

        case 'BATAL_MASUK':
            return 'action-batal';

        default:
            return 'action-default';
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Riwayat Unit</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }


        .container {
            width: 100%;
            max-width: 1150px;
            margin: 0 auto;
            padding: 25px 20px 50px;
        }


        /* =========================================================
           TOPBAR
        ========================================================= */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }


        .topbar h1 {
            margin: 0;
            font-size: 28px;
        }


        .back-btn {
            display: inline-block;
            text-decoration: none;
            background: #374151;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
        }


        .back-btn:hover {
            background: #1f2937;
        }


        /* =========================================================
           INFO
        ========================================================= */

        .info-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }


        .info-box h2 {
            margin: 0 0 8px;
        }


        .info-box p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* =========================================================
           FILTER
        ========================================================= */

        .filter-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }


        .filter-box h2 {
            margin: 0 0 15px;
            font-size: 20px;
        }


        .filter-form {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }


        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }


        .filter-group label {
            font-size: 13px;
            font-weight: bold;
        }


        .filter-group select {
            min-width: 160px;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }


        .filter-btn {
            border: none;
            background: #2563eb;
            color: white;
            padding: 11px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }


        .filter-btn:hover {
            background: #1d4ed8;
        }


        .reset-btn {
            display: inline-block;
            text-decoration: none;
            background: #e5e7eb;
            color: #374151;
            padding: 11px 18px;
            border-radius: 8px;
            font-size: 14px;
        }


        .reset-btn:hover {
            background: #d1d5db;
        }


        /* =========================================================
           SUMMARY
        ========================================================= */

        .summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }


        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }


        .summary-title {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 7px;
        }


        .summary-number {
            font-size: 28px;
            font-weight: bold;
        }


        /* =========================================================
           HISTORY
        ========================================================= */

        .history-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }


        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
        }


        .history-header h2 {
            margin: 0;
            font-size: 21px;
        }


        .count-badge {
            background: #e5e7eb;
            color: #374151;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }


        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        .history-item {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px;
        }


        .history-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }


        .unit-code {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 4px;
        }


        .unit-name {
            font-size: 14px;
            color: #374151;
            margin-bottom: 7px;
        }


        .history-description {
            font-size: 14px;
            line-height: 1.6;
            color: #4b5563;
        }


        .history-meta {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.7;
        }


        /* =========================================================
           ACTION BADGES
        ========================================================= */

        .action-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }


        .action-keluar {
            background: #fee2e2;
            color: #991b1b;
        }


        .action-masuk {
            background: #dcfce7;
            color: #166534;
        }


        .action-edit {
            background: #dbeafe;
            color: #1d4ed8;
        }


        .action-tambah {
            background: #ede9fe;
            color: #6d28d9;
        }


        .action-transfer {
            background: #fef3c7;
            color: #92400e;
        }


        .action-batal {
            background: #ffedd5;
            color: #c2410c;
        }


        .action-default {
            background: #e5e7eb;
            color: #374151;
        }


        /* =========================================================
           EMPTY
        ========================================================= */

        .empty {
            text-align: center;
            padding: 40px 15px;
            color: #6b7280;
        }


        .empty-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }


        /* =========================================================
           RESPONSIVE
        ========================================================= */

        @media (max-width: 700px) {

            .container {
                padding: 15px 12px 35px;
            }


            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }


            .topbar h1 {
                font-size: 23px;
            }


            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }


            .filter-group select,
            .filter-btn,
            .reset-btn {
                width: 100%;
            }


            .summary {
                grid-template-columns: 1fr;
            }


            .history-top {
                flex-direction: column;
            }


            .action-badge {
                align-self: flex-start;
            }
        }

    </style>

</head>


<body>

<div class="container">


    <!-- ==========================================================
         TOPBAR
    =========================================================== -->

    <div class="topbar">

        <h1>
            📜 Riwayat Unit
        </h1>


        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Kembali ke Dashboard
        </a>

    </div>


    <!-- ==========================================================
         INFO CABANG
    =========================================================== -->

    <div class="info-box">

        <h2>
            <?= htmlspecialchars(user_branch_name()) ?>
        </h2>

        <p>
            Riwayat aktivitas unit untuk
            <?= htmlspecialchars($namaBulan[$selectedMonth]) ?>
            <?= (int)$selectedYear ?>.
        </p>

    </div>


    <!-- ==========================================================
         FILTER
    =========================================================== -->

    <div class="filter-box">

        <h2>
            Filter Riwayat
        </h2>


        <form
            method="GET"
            action="riwayat.php"
            class="filter-form"
        >

            <div class="filter-group">

                <label for="bulan">
                    Bulan
                </label>

                <select
                    name="bulan"
                    id="bulan"
                >

                    <?php foreach ($namaBulan as $nomor => $nama): ?>

                        <option
                            value="<?= (int)$nomor ?>"
                            <?= $selectedMonth === $nomor ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($nama) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group">

                <label for="tahun">
                    Tahun
                </label>

                <select
                    name="tahun"
                    id="tahun"
                >

                    <?php

                    $startYear = min(
                        2020,
                        $selectedYear
                    );

                    $endYear = max(
                        $currentYear,
                        $selectedYear
                    );

                    for (
                        $year = $endYear;
                        $year >= $startYear;
                        $year--
                    ):
                    ?>

                        <option
                            value="<?= (int)$year ?>"
                            <?= $selectedYear === $year ? 'selected' : '' ?>
                        >
                            <?= (int)$year ?>
                        </option>

                    <?php endfor; ?>

                </select>

            </div>


            <button
                type="submit"
                class="filter-btn"
            >
                🔍 Tampilkan
            </button>


            <a
                href="riwayat.php"
                class="reset-btn"
            >
                Bulan Ini
            </a>

        </form>

    </div>


    <!-- ==========================================================
         SUMMARY
    =========================================================== -->

    <div class="summary">

        <div class="summary-card">

            <div class="summary-title">
                Total Aktivitas
            </div>

            <div class="summary-number">
                <?= $totalHistory ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Periode
            </div>

            <div class="summary-number">
                <?= htmlspecialchars($namaBulan[$selectedMonth]) ?>
                <?= (int)$selectedYear ?>
            </div>

        </div>

    </div>


    <!-- ==========================================================
         RIWAYAT
    =========================================================== -->

    <div class="history-box">

        <div class="history-header">

            <h2>
                Aktivitas
            </h2>

            <span class="count-badge">
                <?= $totalHistory ?> aktivitas
            </span>

        </div>


        <?php if ($totalHistory === 0): ?>

            <div class="empty">

                <div class="empty-icon">
                    📭
                </div>

                <div>
                    Belum ada riwayat pada
                    <strong>
                        <?= htmlspecialchars($namaBulan[$selectedMonth]) ?>
                        <?= (int)$selectedYear ?>
                    </strong>.
                </div>

            </div>

        <?php else: ?>


            <div class="history-list">


                <?php foreach ($history as $item): ?>

                    <div class="history-item">


                        <div class="history-top">


                            <div>

                                <div class="unit-code">
                                    <?= htmlspecialchars($item['kode_unit']) ?>
                                </div>


                                <div class="unit-name">
                                    <?= htmlspecialchars($item['nama_unit']) ?>
                                </div>


                                <div class="history-description">
                                    <?= htmlspecialchars($item['description']) ?>
                                </div>

                            </div>


                            <span
                                class="action-badge <?= htmlspecialchars(actionClass($item['action'])) ?>"
                            >
                                <?= htmlspecialchars(actionLabel($item['action'])) ?>
                            </span>

                        </div>


                        <div class="history-meta">

                            Kategori:
                            <?= htmlspecialchars($item['kategori']) ?>

                            &nbsp; • &nbsp;

                            Dilakukan oleh:
                            <?= htmlspecialchars($item['performed_by_name'] ?? 'Sistem') ?>

                            &nbsp; • &nbsp;

                            <?= htmlspecialchars($item['created_at']) ?>

                        </div>


                    </div>

                <?php endforeach; ?>


            </div>

        <?php endif; ?>


    </div>


</div>

</body>

</html>