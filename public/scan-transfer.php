<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$transfer_id = (int) ($_GET['id'] ?? 0);

if ($transfer_id <= 0) {
    header("Location: transfer.php");
    exit;
}

$error = $_SESSION['transfer_error'] ?? '';
unset($_SESSION['transfer_error']);

$success = $_SESSION['transfer_success'] ?? '';
unset($_SESSION['transfer_success']);

/*
|--------------------------------------------------------------------------
| Proses scan transfer
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'scan_transfer') {

        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['transfer_error'] = 'Token keamanan tidak valid.';
            header("Location: scan-transfer.php?id=" . $transfer_id);
            exit;
        }

        $posted_transfer_id = (int) ($_POST['transfer_id'] ?? 0);
        $kode_unit = trim($_POST['kode_unit'] ?? '');

        if ($posted_transfer_id !== $transfer_id) {
            $_SESSION['transfer_error'] = 'Transfer tidak valid.';
            header("Location: transfer.php");
            exit;
        }

        if ($kode_unit === '') {
            $_SESSION['transfer_error'] = 'Kode unit belum diisi.';
            header("Location: scan-transfer.php?id=" . $transfer_id);
            exit;
        }

        try {

            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | Lock data transfer
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT
                    ut.*,
                    u.kode_unit,
                    u.nama_unit,
                    u.kategori,
                    u.jumlah,
                    u.owner_branch_id,
                    u.status AS unit_status,
                    fb.nama_cabang AS from_branch_name,
                    tb.nama_cabang AS to_branch_name
                FROM unit_transfers ut
                INNER JOIN units u
                    ON u.id = ut.unit_id
                INNER JOIN branches fb
                    ON fb.id = ut.from_branch_id
                INNER JOIN branches tb
                    ON tb.id = ut.to_branch_id
                WHERE ut.id = ?
                FOR UPDATE
            ");

            $stmt->execute([$transfer_id]);
            $transfer = $stmt->fetch();

            if (!$transfer) {
                throw new Exception('Data transfer tidak ditemukan.');
            }

            /*
            |--------------------------------------------------------------------------
            | Pastikan user adalah cabang asal
            |--------------------------------------------------------------------------
            */
            if ((int) $transfer['from_branch_id'] !== (int) user_branch_id()) {
                throw new Exception(
                    'Anda tidak memiliki akses untuk melakukan scan transfer ini.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Transfer harus masih PENDING
            |--------------------------------------------------------------------------
            */
            if ($transfer['status'] !== 'PENDING') {
                if ($transfer['status'] === 'SIAP_DI_KONFIRMASI') {
                    throw new Exception(
                        'Transfer ini sudah discan dan sedang menunggu konfirmasi cabang tujuan.'
                    );
                }

                if ($transfer['status'] === 'COMPLETED') {
                    throw new Exception(
                        'Transfer ini sudah selesai.'
                    );
                }

                if ($transfer['status'] === 'REJECTED') {
                    throw new Exception(
                        'Transfer ini sudah ditolak.'
                    );
                }

                throw new Exception(
                    'Transfer tidak dapat diproses dengan status saat ini.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Lock unit
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    kode_unit,
                    nama_unit,
                    kategori,
                    jumlah,
                    owner_branch_id,
                    status
                FROM units
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([
                $transfer['unit_id']
            ]);

            $unit = $stmt->fetch();

            if (!$unit) {
                throw new Exception('Unit transfer tidak ditemukan.');
            }

            /*
            |--------------------------------------------------------------------------
            | QR harus sama persis dengan kode_unit
            |--------------------------------------------------------------------------
            */
            if ($kode_unit !== $unit['kode_unit']) {
                throw new Exception(
                    'QR/kode unit tidak sesuai dengan unit yang akan ditransfer.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Pastikan unit masih dimiliki cabang asal
            |--------------------------------------------------------------------------
            */
            if ((int) $unit['owner_branch_id'] !== (int) user_branch_id()) {
                throw new Exception(
                    'Unit ini sudah tidak dimiliki oleh cabang asal.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Unit harus tersedia
            |--------------------------------------------------------------------------
            */
            if ($unit['status'] !== 'TERSEDIA') {
                throw new Exception(
                    'Unit tidak dapat ditransfer karena statusnya bukan TERSEDIA.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update transfer
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                UPDATE unit_transfers
                SET
                    scanned_by = ?,
                    scanned_at = NOW(),
                    status = 'SIAP_DI_KONFIRMASI'
                WHERE id = ?
            ");

            $stmt->execute([
                user_id(),
                $transfer_id
            ]);

            /*
            |--------------------------------------------------------------------------
            | Simpan riwayat
            |--------------------------------------------------------------------------
            */
            $description =
                'Unit ' . $unit['kode_unit'] .
                ' discan untuk transfer dari ' .
                $transfer['from_branch_name'] .
                ' ke ' .
                $transfer['to_branch_name'] .
                '. Menunggu konfirmasi cabang tujuan.';

            $stmt = $pdo->prepare("
                INSERT INTO unit_history
                (
                    unit_id,
                    performed_by,
                    branch_id,
                    action,
                    description,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'TRANSFER_KELUAR',
                    ?,
                    NOW()
                )
            ");

            $stmt->execute([
                $unit['id'],
                user_id(),
                user_branch_id(),
                $description
            ]);

            $pdo->commit();

            $_SESSION['transfer_success'] =
                'Unit berhasil discan. Transfer sekarang menunggu konfirmasi cabang tujuan.';

            header("Location: transfer.php");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['transfer_error'] = $e->getMessage();

            header("Location: scan-transfer.php?id=" . $transfer_id);
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data transfer untuk ditampilkan
|--------------------------------------------------------------------------
*/
try {

    $stmt = $pdo->prepare("
        SELECT
            ut.*,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.owner_branch_id,
            u.status AS unit_status,
            fb.nama_cabang AS from_branch_name,
            tb.nama_cabang AS to_branch_name
        FROM unit_transfers ut
        INNER JOIN units u
            ON u.id = ut.unit_id
        INNER JOIN branches fb
            ON fb.id = ut.from_branch_id
        INNER JOIN branches tb
            ON tb.id = ut.to_branch_id
        WHERE ut.id = ?
        LIMIT 1
    ");

    $stmt->execute([$transfer_id]);
    $transfer = $stmt->fetch();

    if (!$transfer) {
        header("Location: transfer.php");
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Hanya cabang asal yang boleh membuka halaman scan
    |--------------------------------------------------------------------------
    */
    if ((int) $transfer['from_branch_id'] !== (int) user_branch_id()) {
        http_response_code(403);
        die("Anda tidak memiliki akses ke transfer ini.");
    }

} catch (Throwable $e) {
    die("Terjadi kesalahan saat mengambil data transfer.");
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

    <title>Scan Transfer Unit</title>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .container {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
            padding: 20px;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .back {
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        h1 {
            margin: 0;
            font-size: 25px;
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 18px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info {
            background: #f8fafc;
            padding: 13px;
            border-radius: 10px;
        }

        .label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .value {
            font-weight: bold;
            word-break: break-word;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: bold;
            background: #fef3c7;
            color: #92400e;
        }

        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 12px;
        }

        .scanner-title {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .scanner-info {
            margin-top: 0;
            color: #64748b;
            font-size: 14px;
        }

        .manual {
            margin-top: 20px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input[type="text"] {
            width: 100%;
            padding: 13px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-size: 16px;
            outline: none;
        }

        input[type="text"]:focus {
            border-color: #2563eb;
        }

        button {
            border: 0;
            cursor: pointer;
            border-radius: 9px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: bold;
        }

        .btn-primary {
            width: 100%;
            background: #2563eb;
            color: white;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            padding: 13px;
            border-radius: 9px;
            margin-top: 15px;
            font-size: 14px;
        }

        .flow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .flow-item {
            padding: 8px 12px;
            border-radius: 8px;
            background: #f1f5f9;
            font-size: 13px;
            font-weight: bold;
        }

        .arrow {
            color: #64748b;
            font-weight: bold;
        }

        @media (max-width: 600px) {

            .container {
                padding: 14px;
            }

            h1 {
                font-size: 21px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 16px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="topbar">
        <a href="transfer.php" class="back">
            ← Kembali
        </a>

        <h1>Scan Transfer Unit</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <div class="card">

        <h2 style="margin-top:0;">
            Detail Transfer
        </h2>

        <div class="info-grid">

            <div class="info">
                <div class="label">
                    Kode Unit
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['kode_unit']) ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Nama Unit
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['nama_unit']) ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Kategori
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['kategori']) ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Jumlah
                </div>

                <div class="value">
                    <?= (int) $transfer['jumlah'] ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Cabang Asal
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['from_branch_name']) ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Cabang Tujuan
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['to_branch_name']) ?>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Status Transfer
                </div>

                <div>
                    <span class="status">
                        <?= htmlspecialchars($transfer['status']) ?>
                    </span>
                </div>
            </div>

            <div class="info">
                <div class="label">
                    Status Unit
                </div>

                <div class="value">
                    <?= htmlspecialchars($transfer['unit_status']) ?>
                </div>
            </div>

        </div>

        <div class="flow">
            <div class="flow-item">
                PENDING
            </div>

            <div class="arrow">
                →
            </div>

            <div class="flow-item">
                SIAP DIKONFIRMASI
            </div>

            <div class="arrow">
                →
            </div>

            <div class="flow-item">
                COMPLETED
            </div>
        </div>

    </div>

    <?php if ($transfer['status'] === 'PENDING'): ?>

        <div class="card">

            <h2 class="scanner-title">
                Scan QR Unit
            </h2>

            <p class="scanner-info">
                Arahkan kamera ke QR unit yang akan ditransfer.
                QR harus berisi kode unit yang sesuai.
            </p>

            <div id="reader"></div>

            <div class="manual">

                <h3 style="margin-top:0;">
                    Input Manual
                </h3>

                <p class="scanner-info">
                    Kalau kamera bermasalah, masukkan kode unit secara manual.
                </p>

                <form method="POST" id="transferForm">

                    <input
                        type="hidden"
                        name="action"
                        value="scan_transfer"
                    >

                    <input
                        type="hidden"
                        name="transfer_id"
                        value="<?= $transfer_id ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(csrf_token()) ?>"
                    >

                    <label for="kode_unit">
                        Kode Unit
                    </label>

                    <input
                        type="text"
                        id="kode_unit"
                        name="kode_unit"
                        placeholder="Contoh: A1"
                        autocomplete="off"
                        required
                    >

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Konfirmasi Scan
                    </button>

                </form>

            </div>

            <div class="warning">
                <strong>Perhatian:</strong>
                Setelah scan berhasil, unit belum berpindah kepemilikan.
                Cabang tujuan masih harus melakukan konfirmasi.
            </div>

        </div>

    <?php elseif ($transfer['status'] === 'SIAP_DI_KONFIRMASI'): ?>

        <div class="card">

            <div class="alert alert-success" style="margin-bottom:0;">
                Unit sudah berhasil discan dan sekarang menunggu
                konfirmasi dari cabang tujuan.
            </div>

        </div>

    <?php else: ?>

        <div class="card">

            <div class="alert alert-error" style="margin-bottom:0;">
                Transfer ini tidak dapat melakukan scan lagi.
            </div>

        </div>

    <?php endif; ?>

</div>

<script>

let scannerLocked = false;

function submitScan(kodeUnit) {

    if (scannerLocked) {
        return;
    }

    scannerLocked = true;

    const input = document.getElementById('kode_unit');

    if (input) {
        input.value = kodeUnit;
    }

    const form = document.getElementById('transferForm');

    if (form) {
        form.submit();
    }
}

function onScanSuccess(decodedText) {

    const kodeUnit = decodedText.trim();

    if (!kodeUnit) {
        return;
    }

    submitScan(kodeUnit);
}

function onScanFailure(error) {
    // Tidak perlu menampilkan error terus-menerus
}

<?php if ($transfer['status'] === 'PENDING'): ?>

document.addEventListener('DOMContentLoaded', function () {

    const scanner = new Html5Qrcode("reader");

    scanner.start(
        {
            facingMode: "environment"
        },
        {
            fps: 10,
            qrbox: {
                width: 250,
                height: 250
            }
        },
        onScanSuccess,
        onScanFailure
    ).catch(function (error) {

        console.log("Kamera tidak dapat digunakan:", error);

    });

});

<?php endif; ?>

</script>

</body>
</html>