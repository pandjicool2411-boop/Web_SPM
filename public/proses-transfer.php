<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: transfer.php");
    exit;
}

verify_csrf();

$action = $_POST["action"] ?? "";

$branch_id = (int) $_SESSION["branch_id"];
$user_id = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Validasi action
|--------------------------------------------------------------------------
*/

$allowed_actions = [
    "buat",
    "approve",
    "reject"
];

if (!in_array($action, $allowed_actions, true)) {
    http_response_code(400);
    die("Aksi tidak valid.");
}


/*
|--------------------------------------------------------------------------
| AJUKAN TRANSFER
|--------------------------------------------------------------------------
*/

if ($action === "buat") {

    $unit_id = filter_input(
        INPUT_POST,
        "unit_id",
        FILTER_VALIDATE_INT
    );

    $to_branch_id = filter_input(
        INPUT_POST,
        "to_branch_id",
        FILTER_VALIDATE_INT
    );


    /*
    | Validasi ID
    */

    if (
        $unit_id === false ||
        $unit_id === null ||
        $unit_id <= 0
    ) {
        http_response_code(400);
        die("ID unit tidak valid.");
    }


    if (
        $to_branch_id === false ||
        $to_branch_id === null ||
        $to_branch_id <= 0
    ) {
        http_response_code(400);
        die("Cabang tujuan tidak valid.");
    }


    /*
    | Cabang tujuan tidak boleh sama
    */

    if ($branch_id === $to_branch_id) {
        http_response_code(400);
        die("Cabang tujuan tidak boleh sama dengan cabang asal.");
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
                owner_branch_id,
                status
            FROM units
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$unit_id]);

        $unit = $stmt->fetch();


        if (!$unit) {
            throw new Exception("Unit tidak ditemukan.");
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan unit milik cabang yang sedang login
        |--------------------------------------------------------------------------
        */

        if ((int) $unit["owner_branch_id"] !== $branch_id) {

            throw new Exception(
                "Anda tidak memiliki hak untuk mentransfer unit ini."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Unit harus tersedia
        |--------------------------------------------------------------------------
        */

        if ($unit["status"] !== "TERSEDIA") {

            throw new Exception(
                "Unit sedang disewakan dan tidak dapat ditransfer."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan cabang tujuan ada
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM branches
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$to_branch_id]);

        $target_branch = $stmt->fetch();


        if (!$target_branch) {
            throw new Exception(
                "Cabang tujuan tidak ditemukan."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan tidak ada transfer aktif
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT id
            FROM unit_transfers
            WHERE unit_id = ?
              AND status IN ('PENDING', 'APPROVED')
            LIMIT 1
        ");

        $stmt->execute([$unit_id]);

        if ($stmt->fetch()) {

            throw new Exception(
                "Unit sedang memiliki proses transfer aktif."
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
                status
            )
            VALUES (?, ?, ?, ?, 'PENDING')
        ");

        $stmt->execute([
            $unit_id,
            $branch_id,
            $to_branch_id,
            $user_id
        ]);


        /*
        |--------------------------------------------------------------------------
        | Simpan history
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO unit_history
            (
                unit_id,
                performed_by,
                branch_id,
                action,
                description
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $unit_id,
            $user_id,
            $branch_id,
            "TRANSFER_DIAJUKAN",
            "Transfer unit diajukan ke cabang tujuan."
        ]);


        $pdo->commit();


        header("Location: transfer.php");
        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            "Gagal membuat transfer: " .
            $e->getMessage()
        );

        http_response_code(500);

        die("Transfer gagal diproses.");
    }
}


/*
|--------------------------------------------------------------------------
| SETUJUI / TOLAK TRANSFER
|--------------------------------------------------------------------------
*/

if (
    $action === "approve" ||
    $action === "reject"
) {

    $transfer_id = filter_input(
        INPUT_POST,
        "transfer_id",
        FILTER_VALIDATE_INT
    );


    /*
    |--------------------------------------------------------------------------
    | Validasi transfer ID
    |--------------------------------------------------------------------------
    */

    if (
        $transfer_id === false ||
        $transfer_id === null ||
        $transfer_id <= 0
    ) {
        http_response_code(400);
        die("ID transfer tidak valid.");
    }


    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Lock transfer
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                unit_id,
                from_branch_id,
                to_branch_id,
                requested_by,
                approved_by,
                status
            FROM unit_transfers
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$transfer_id]);

        $transfer = $stmt->fetch();


        if (!$transfer) {

            throw new Exception(
                "Data transfer tidak ditemukan."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan user adalah pemilik cabang asal
        |--------------------------------------------------------------------------
        */

        if (
            (int) $transfer["from_branch_id"]
            !== $branch_id
        ) {

            throw new Exception(
                "Anda tidak memiliki hak untuk memproses transfer ini."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan user memang pengaju transfer
        |--------------------------------------------------------------------------
        */

        if (
            (int) $transfer["requested_by"]
            !== $user_id
        ) {

            throw new Exception(
                "Anda tidak memiliki hak untuk memproses transfer ini."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Transfer harus masih PENDING
        |--------------------------------------------------------------------------
        */

        if ($transfer["status"] !== "PENDING") {

            throw new Exception(
                "Transfer ini sudah diproses sebelumnya."
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
                owner_branch_id,
                status
            FROM units
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $transfer["unit_id"]
        ]);

        $unit = $stmt->fetch();


        if (!$unit) {

            throw new Exception(
                "Unit tidak ditemukan."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Pastikan kepemilikan belum berubah
        |--------------------------------------------------------------------------
        */

        if (
            (int) $unit["owner_branch_id"]
            !== (int) $transfer["from_branch_id"]
        ) {

            throw new Exception(
                "Kepemilikan unit sudah berubah."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE
        |--------------------------------------------------------------------------
        */

        if ($action === "approve") {


            /*
            | Unit harus masih tersedia
            */

            if ($unit["status"] !== "TERSEDIA") {

                throw new Exception(
                    "Unit tidak tersedia sehingga transfer tidak dapat disetujui."
                );
            }


            /*
            | Update transfer dengan guard PENDING
            */

            $stmt = $pdo->prepare("
                UPDATE unit_transfers
                SET
                    status = 'APPROVED',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
                  AND status = 'PENDING'
                  AND from_branch_id = ?
                  AND requested_by = ?
            ");

            $stmt->execute([
                $user_id,
                $transfer_id,
                $branch_id,
                $user_id
            ]);


            if ($stmt->rowCount() !== 1) {

                throw new Exception(
                    "Transfer gagal disetujui."
                );
            }


            /*
            | History approve
            */

            $stmt = $pdo->prepare("
                INSERT INTO unit_history
                (
                    unit_id,
                    performed_by,
                    branch_id,
                    action,
                    description
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $transfer["unit_id"],
                $user_id,
                $branch_id,
                "TRANSFER_DISETUJUI",
                "Transfer unit disetujui oleh cabang asal."
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT
        |--------------------------------------------------------------------------
        */

        else {


            /*
            | Update transfer dengan guard PENDING
            */

            $stmt = $pdo->prepare("
                UPDATE unit_transfers
                SET
                    status = 'REJECTED'
                WHERE id = ?
                  AND status = 'PENDING'
                  AND from_branch_id = ?
                  AND requested_by = ?
            ");

            $stmt->execute([
                $transfer_id,
                $branch_id,
                $user_id
            ]);


            if ($stmt->rowCount() !== 1) {

                throw new Exception(
                    "Transfer gagal ditolak."
                );
            }


            /*
            | History reject
            */

            $stmt = $pdo->prepare("
                INSERT INTO unit_history
                (
                    unit_id,
                    performed_by,
                    branch_id,
                    action,
                    description
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $transfer["unit_id"],
                $user_id,
                $branch_id,
                "TRANSFER_DITOLAK",
                "Transfer unit ditolak oleh cabang asal."
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        header("Location: transfer.php");
        exit;


    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            "Gagal memproses transfer: " .
            $e->getMessage()
        );

        http_response_code(500);

        die("Proses transfer gagal.");
    }
}


/*
|--------------------------------------------------------------------------
| Aksi tidak dikenal
|--------------------------------------------------------------------------
*/

http_response_code(400);

die("Aksi tidak dikenal.");