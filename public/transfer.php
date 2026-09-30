<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/csrf.php';

require_login();

$error = $_SESSION['transfer_error'] ?? '';
unset($_SESSION['transfer_error']);

$success = $_SESSION['transfer_success'] ?? '';
unset($_SESSION['transfer_success']);

/*
|--------------------------------------------------------------------------
| Helper status
|--------------------------------------------------------------------------
*/
function status_class($status)
{
    switch ($status) {
        case 'PENDING':
            return 'pending';

        case 'SIAP_DI_KONFIRMASI':
            return 'ready';

        case 'COMPLETED':
            return 'completed';

        case 'REJECTED':
            return 'rejected';

        default:
            return 'unknown';
    }
}

function status_label($status)
{
    switch ($status) {
        case 'PENDING':
            return 'PENDING';

        case 'SIAP_DI_KONFIRMASI':
            return 'SIAP DIKONFIRMASI';

        case 'COMPLETED':
            return 'SELESAI';

        case 'REJECTED':
            return 'DITOLAK';

        default:
            return $status;
    }
}

/*
|--------------------------------------------------------------------------
| Buat transfer baru
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'buat_transfer') {

        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $_SESSION['transfer_error'] = 'Token keamanan tidak valid.';
            header("Location: transfer.php");
            exit;
        }

        $unit_id = (int) ($_POST['unit_id'] ?? 0);
        $to_branch_id = (int) ($_POST['to_branch_id'] ?? 0);

        if ($unit_id <= 0 || $to_branch_id <= 0) {
            $_SESSION['transfer_error'] =
                'Unit dan cabang tujuan wajib dipilih.';

            header("Location: transfer.php");
            exit;
        }

        try {

            $pdo->beginTransaction();

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

            $stmt->execute([$unit_id]);
            $unit = $stmt->fetch();

            if (!$unit) {
                throw new Exception('Unit tidak ditemukan.');
            }

            /*
            |--------------------------------------------------------------------------
            | Unit harus milik cabang user
            |--------------------------------------------------------------------------
            */
            if ((int) $unit['owner_branch_id'] !== (int) user_branch_id()) {
                throw new Exception(
                    'Anda hanya dapat mentransfer unit milik cabang sendiri.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Unit harus tersedia
            |--------------------------------------------------------------------------
            */
            if ($unit['status'] !== 'TERSEDIA') {
                throw new Exception(
                    'Unit yang akan ditransfer harus berstatus TERSEDIA.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Cabang tujuan tidak boleh sama
            |--------------------------------------------------------------------------
            */
            if ($to_branch_id === (int) user_branch_id()) {
                throw new Exception(
                    'Cabang tujuan harus berbeda dengan cabang asal.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Pastikan cabang tujuan ada
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id, nama_cabang
                FROM branches
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$to_branch_id]);
            $target_branch = $stmt->fetch();

            if (!$target_branch) {
                throw new Exception(
                    'Cabang tujuan tidak ditemukan.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Cek transfer aktif
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT id
                FROM unit_transfers
                WHERE unit_id = ?
                  AND status IN ('PENDING', 'SIAP_DI_KONFIRMASI')
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([$unit_id]);

            if ($stmt->fetch()) {
                throw new Exception(
                    'Unit ini masih memiliki transfer yang sedang diproses.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Buat transfer
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                INSERT INTO unit_transfers
                (
                    unit_id,
                    from_branch_id,
                    to_branch_id,
                    requested_by,
                    scanned_by,
                    approved_by,
                    status,
                    requested_at,
                    scanned_at,
                    approved_at,
                    completed_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    NULL,
                    NULL,
                    'PENDING',
                    NOW(),
                    NULL,
                    NULL,
                    NULL
                )
            ");

            $stmt->execute([
                $unit['id'],
                user_branch_id(),
                $to_branch_id,
                user_id()
            ]);

            /*
            |--------------------------------------------------------------------------
            | Riwayat
            |--------------------------------------------------------------------------
            */
            $description =
                'Permintaan transfer unit ' .
                $unit['kode_unit'] .
                ' dari ' .
                user_branch_name() .
                ' ke ' .
                $target_branch['nama_cabang'] .
                '.';

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
                    'TRANSFER',
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
                'Permintaan transfer unit ' .
                $unit['kode_unit'] .
                ' berhasil dibuat. Silakan lakukan scan QR unit.';

            header("Location: transfer.php");
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $_SESSION['transfer_error'] = $e->getMessage();

            header("Location: transfer.php");
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil semua cabang
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        id,
        nama_cabang
    FROM branches
    WHERE id != ?
    ORDER BY nama_cabang ASC
");

$stmt->execute([user_branch_id()]);
$branches = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Ambil unit milik cabang sendiri
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.kode_unit,
        u.nama_unit,
        u.kategori,
        u.jumlah,
        u.status
    FROM units u
    WHERE u.owner_branch_id = ?
    ORDER BY
        u.kategori ASC,
        u.nama_unit ASC,
        u.kode_unit ASC
");

$stmt->execute([user_branch_id()]);
$units = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Transfer keluar dari cabang sendiri
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        ut.id,
        ut.status,
        ut.requested_at,
        ut.scanned_at,
        ut.approved_at,
        ut.completed_at,

        u.kode_unit,
        u.nama_unit,
        u.kategori,

        fb.nama_cabang AS from_branch_name,
        tb.nama_cabang AS to_branch_name

    FROM unit_transfers ut

    INNER JOIN units u
        ON u.id = ut.unit_id

    INNER JOIN branches fb
        ON fb.id = ut.from_branch_id

    INNER JOIN branches tb
        ON tb.id = ut.to_branch_id

    WHERE ut.from_branch_id = ?

    ORDER BY ut.id DESC
");

$stmt->execute([user_branch_id()]);
$outgoing_transfers = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Transfer masuk ke cabang sendiri
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT
        ut.id,
        ut.status,
        ut.requested_at,
        ut.scanned_at,
        ut.approved_at,
        ut.completed_at,

        u.kode_unit,
        u.nama_unit,
        u.kategori,

        fb.nama_cabang AS from_branch_name,
        tb.nama_cabang AS to_branch_name

    FROM unit_transfers ut

    INNER JOIN units u
        ON u.id = ut.unit_id

    INNER JOIN branches fb
        ON fb.id = ut.from_branch_id

    INNER JOIN branches tb
        ON tb.id = ut.to_branch_id

    WHERE ut.to_branch_id = ?

    ORDER BY ut.id DESC
");

$stmt->execute([user_branch_id()]);
$incoming_transfers = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Transfer Unit</title>

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
            max-width: 1100px;
            margin: 0 auto;
            padding: 20px;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .back {
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        h1 {
            margin: 0;
            font-size: 27px;
        }

        h2 {
            margin-top: 0;
        }

        h3 {
            margin-bottom: 8px;
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            background: #ffffff;
            font-size: 15px;
        }

        select:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            border: 0;
            cursor: pointer;
            border-radius: 9px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-success {
            background: #16a34a;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        th,
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f8fafc;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status.ready {
            background: #dbeafe;
            color: #1e40af;
        }

        .status.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status.unknown {
            background: #e5e7eb;
            color: #374151;
        }

        .action {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .action a {
            display: inline-block;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
        }

        .action-scan {
            background: #2563eb;
            color: #ffffff;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 10px;
        }

        .info-text {
            margin-top: 5px;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 14px;
            }

            .card {
                padding: 16px;
            }

            h1 {
                font-size: 23px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="topbar">

        <a href="dashboard.php" class="back">
            ← Dashboard
        </a>

        <h1>
            Transfer Unit
        </h1>

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


    <!-- ==========================================================
         BUAT TRANSFER
         ========================================================== -->

    <div class="card">

        <h2>
            Buat Transfer Baru
        </h2>

        <p class="info-text">
            Pilih unit milik cabang Anda dan tentukan cabang tujuan.
            Setelah dibuat, unit harus discan terlebih dahulu.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="buat_transfer"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token()) ?>"
            >

            <div class="form-grid">

                <div>

                    <label for="unit_id">
                        Unit
                    </label>

                    <select
                        name="unit_id"
                        id="unit_id"
                        required
                    >

                        <option value="">
                            -- Pilih Unit --
                        </option>

                        <?php foreach ($units as $unit): ?>

                            <option
                                value="<?= (int) $unit['id'] ?>"
                                <?= $unit['status'] !== 'TERSEDIA' ? 'disabled' : '' ?>
                            >
                                <?= htmlspecialchars($unit['kode_unit']) ?>
                                -
                                <?= htmlspecialchars($unit['nama_unit']) ?>
                                [<?= htmlspecialchars($unit['kategori']) ?>]
                                <?= $unit['status'] !== 'TERSEDIA'
                                    ? ' - ' . htmlspecialchars($unit['status'])
                                    : '' ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label for="to_branch_id">
                        Cabang Tujuan
                    </label>

                    <select
                        name="to_branch_id"
                        id="to_branch_id"
                        required
                    >

                        <option value="">
                            -- Pilih Cabang Tujuan --
                        </option>

                        <?php foreach ($branches as $branch): ?>

                            <option
                                value="<?= (int) $branch['id'] ?>"
                            >
                                <?= htmlspecialchars($branch['nama_cabang']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        Buat Transfer
                    </button>

                </div>

            </div>

        </form>

    </div>


    <!-- ==========================================================
         TRANSFER KELUAR
         ========================================================== -->

    <div class="card">

        <h2>
            Transfer Keluar
        </h2>

        <?php if (!$outgoing_transfers): ?>

            <div class="empty">
                Belum ada transfer keluar.
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Unit
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Tujuan
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Dibuat
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($outgoing_transfers as $transfer): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($transfer['kode_unit']) ?>
                                </strong>

                                <br>

                                <?= htmlspecialchars($transfer['nama_unit']) ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['kategori']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['to_branch_name']) ?>
                            </td>

                            <td>

                                <span
                                    class="status <?= status_class($transfer['status']) ?>"
                                >
                                    <?= htmlspecialchars(
                                        status_label($transfer['status'])
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['requested_at']) ?>
                            </td>

                            <td>

                                <div class="action">

                                    <?php if ($transfer['status'] === 'PENDING'): ?>

                                        <a
                                            href="scan-transfer.php?id=<?= (int) $transfer['id'] ?>"
                                            class="action-scan"
                                        >
                                            Scan QR
                                        </a>

                                    <?php elseif ($transfer['status'] === 'SIAP_DI_KONFIRMASI'): ?>

                                        <span style="color:#1e40af;font-size:13px;">
                                            Menunggu konfirmasi
                                        </span>

                                    <?php elseif ($transfer['status'] === 'COMPLETED'): ?>

                                        <span style="color:#166534;font-size:13px;">
                                            Transfer selesai
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- ==========================================================
         TRANSFER MASUK
         ========================================================== -->

    <div class="card">

        <h2>
            Transfer Masuk
        </h2>

        <?php if (!$incoming_transfers): ?>

            <div class="empty">
                Belum ada transfer masuk.
            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Unit
                            </th>

                            <th>
                                Kategori
                            </th>

                            <th>
                                Dari Cabang
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Dibuat
                            </th>

                            <th>
                                Aksi
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($incoming_transfers as $transfer): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= htmlspecialchars($transfer['kode_unit']) ?>
                                </strong>

                                <br>

                                <?= htmlspecialchars($transfer['nama_unit']) ?>

                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['kategori']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['from_branch_name']) ?>
                            </td>

                            <td>

                                <span
                                    class="status <?= status_class($transfer['status']) ?>"
                                >
                                    <?= htmlspecialchars(
                                        status_label($transfer['status'])
                                    ) ?>
                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars($transfer['requested_at']) ?>
                            </td>

                            <td>

                                <div class="action">

                                    <?php if ($transfer['status'] === 'SIAP_DI_KONFIRMASI'): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin mengonfirmasi unit ini? Kepemilikan unit akan berpindah ke cabang Anda.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars(csrf_token()) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="transfer_id"
                                                value="<?= (int) $transfer['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                formaction="proses-terima-transfer.php"
                                                class="btn-success"
                                            >
                                                Konfirmasi
                                            </button>

                                        </form>

                                    <?php elseif ($transfer['status'] === 'PENDING'): ?>

                                        <span style="color:#92400e;font-size:13px;">
                                            Menunggu scan asal
                                        </span>

                                    <?php elseif ($transfer['status'] === 'COMPLETED'): ?>

                                        <span style="color:#166534;font-size:13px;">
                                            Sudah diterima
                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>