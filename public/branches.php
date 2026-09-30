<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$branches = [];
$error = '';

try {

    /*
    |--------------------------------------------------------------------------
    | Ambil semua cabang
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            b.id,
            b.nama_cabang,
            b.created_at,

            COUNT(u.id) AS total_unit,

            SUM(
                CASE
                    WHEN u.status = 'TERSEDIA'
                    THEN 1
                    ELSE 0
                END
            ) AS unit_tersedia,

            SUM(
                CASE
                    WHEN u.status = 'DISEWAKAN'
                    THEN 1
                    ELSE 0
                END
            ) AS unit_disewa

        FROM branches b

        LEFT JOIN units u
            ON u.owner_branch_id = b.id

        GROUP BY
            b.id,
            b.nama_cabang,
            b.created_at

        ORDER BY b.id ASC
    ");

    $branches = $stmt->fetchAll();

} catch (PDOException $e) {

    $error = 'Data cabang tidak dapat ditampilkan.';
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

    <title>Daftar Cabang - Sistem Scan Unit</title>

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
            width: min(1100px, 94%);
            margin: 30px auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .title h1 {
            margin: 0;
            font-size: 25px;
        }

        .title p {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        .back {
            text-decoration: none;
            background: #e5e7eb;
            color: #111827;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
        }

        .back:hover {
            background: #d1d5db;
        }


        /*
        |--------------------------------------------------------------------------
        | Info
        |--------------------------------------------------------------------------
        */

        .info {
            background: #eff6ff;
            color: #1e40af;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | Error
        |--------------------------------------------------------------------------
        */

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | Grid
        |--------------------------------------------------------------------------
        */

        .branch-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }


        /*
        |--------------------------------------------------------------------------
        | Card
        |--------------------------------------------------------------------------
        */

        .branch-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .branch-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .branch-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: #eff6ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .branch-name {
            flex: 1;
        }

        .branch-name h2 {
            margin: 0;
            font-size: 18px;
        }

        .branch-id {
            margin-top: 5px;
            color: #6b7280;
            font-size: 12px;
        }

        .your-branch {
            background: #dcfce7;
            color: #166534;
            padding: 6px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }


        /*
        |--------------------------------------------------------------------------
        | Statistik
        |--------------------------------------------------------------------------
        */

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .stat {
            background: #f8fafc;
            border-radius: 9px;
            padding: 12px 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 20px;
            font-weight: bold;
        }

        .stat-label {
            margin-top: 4px;
            color: #6b7280;
            font-size: 11px;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        footer {
            text-align: center;
            color: #9ca3af;
            font-size: 12px;
            padding: 30px 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .container {
                margin: 20px auto;
            }

            .branch-grid {
                grid-template-columns: 1fr;
            }

            .title h1 {
                font-size: 22px;
            }

        }

        @media (max-width: 450px) {

            .branch-head {
                flex-wrap: wrap;
            }

            .stats {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="topbar">

        <div class="title">

            <h1>
                🏢 Daftar Cabang
            </h1>

            <p>
                Informasi seluruh cabang yang terdaftar
                pada sistem.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- INFO -->

    <div class="info">

        Sistem memiliki
        <strong>4 cabang tetap</strong>.
        Data cabang tidak dapat ditambah,
        diubah, atau dihapus melalui halaman ini.

    </div>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- CABANG -->

    <?php if (empty($branches)): ?>

        <div class="branch-card">

            Belum ada data cabang.

        </div>

    <?php else: ?>

        <div class="branch-grid">

            <?php foreach ($branches as $branch): ?>

                <?php
                    $isOwnBranch =
                        (int) $branch['id'] ===
                        (int) user_branch_id();

                    $totalUnit =
                        (int) ($branch['total_unit'] ?? 0);

                    $unitTersedia =
                        (int) ($branch['unit_tersedia'] ?? 0);

                    $unitDisewa =
                        (int) ($branch['unit_disewa'] ?? 0);
                ?>

                <div class="branch-card">


                    <div class="branch-head">

                        <div class="branch-icon">
                            🏢
                        </div>

                        <div class="branch-name">

                            <h2>
                                <?= htmlspecialchars(
                                    $branch['nama_cabang']
                                ) ?>
                            </h2>

                            <div class="branch-id">

                                ID Cabang:
                                <?= (int) $branch['id'] ?>

                            </div>

                        </div>


                        <?php if ($isOwnBranch): ?>

                            <span class="your-branch">
                                CABANG ANDA
                            </span>

                        <?php endif; ?>

                    </div>


                    <div class="stats">


                        <div class="stat">

                            <div class="stat-number">
                                <?= $totalUnit ?>
                            </div>

                            <div class="stat-label">
                                Total Unit
                            </div>

                        </div>


                        <div class="stat">

                            <div class="stat-number">
                                <?= $unitTersedia ?>
                            </div>

                            <div class="stat-label">
                                Tersedia
                            </div>

                        </div>


                        <div class="stat">

                            <div class="stat-number">
                                <?= $unitDisewa ?>
                            </div>

                            <div class="stat-label">
                                Disewa
                            </div>

                        </div>


                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <footer>

        Sistem Scan Unit V2

    </footer>

</div>

</body>

</html>