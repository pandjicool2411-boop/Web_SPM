<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

require_login();

$search = trim($_GET['search'] ?? '');

$units = [];

try {

    /*
    |--------------------------------------------------------------------------
    | Query unit
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            u.id,
            u.kode_unit,
            u.nama_unit,
            u.kategori,
            u.jumlah,
            u.status,
            u.owner_branch_id,
            u.created_at,
            u.updated_at,

            b.nama_cabang

        FROM units u

        INNER JOIN branches b
            ON b.id = u.owner_branch_id

        WHERE 1 = 1
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | Pencarian
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $sql .= "
            AND (
                u.kode_unit LIKE ?
                OR u.nama_unit LIKE ?
                OR u.kategori LIKE ?
                OR b.nama_cabang LIKE ?
            )
        ";

        $keyword = '%' . $search . '%';

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }


    $sql .= "
        ORDER BY
            b.id ASC,
            u.kategori ASC,
            u.kode_unit ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $units = $stmt->fetchAll();


} catch (PDOException $e) {

    $units = [];

}


/*
|--------------------------------------------------------------------------
| Kelompokkan berdasarkan cabang
|--------------------------------------------------------------------------
*/

$groupedBranches = [];

foreach ($units as $unit) {

    $branchId = $unit['owner_branch_id'];

    if (!isset($groupedBranches[$branchId])) {

        $groupedBranches[$branchId] = [
            'id' => $branchId,
            'nama_cabang' => $unit['nama_cabang'],
            'categories' => []
        ];
    }


    $category = $unit['kategori'] ?: 'Tanpa Kategori';


    if (!isset(
        $groupedBranches[$branchId]['categories'][$category]
    )) {

        $groupedBranches[$branchId]['categories'][$category] = [];

    }


    $groupedBranches[$branchId]['categories'][$category][] =
        $unit;
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

    <title>Cek Semua Unit - Sistem Scan Unit</title>


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
            width: min(1200px, 94%);
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
            flex-wrap: wrap;
            margin-bottom: 22px;
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
        | Search
        |--------------------------------------------------------------------------
        */

        .search-card {
            background: #ffffff;
            padding: 18px;
            border-radius: 13px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            margin-bottom: 22px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            min-width: 0;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        .search-input:focus {
            border-color: #2563eb;
        }

        .btn {
            border: none;
            cursor: pointer;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 8px;
            font-size: 14px;
            display: inline-block;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        .btn-qr {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .btn-qr:hover {
            background: #dbeafe;
        }


        /*
        |--------------------------------------------------------------------------
        | Branch
        |--------------------------------------------------------------------------
        */

        .branch-card {
            background: #ffffff;
            border-radius: 14px;
            margin-bottom: 22px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .branch-header {
            background: #f8fafc;
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .branch-header h2 {
            margin: 0;
            font-size: 19px;
        }

        .branch-id {
            color: #6b7280;
            font-size: 12px;
            margin-top: 5px;
        }


        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        .category {
            padding: 18px 20px 5px;
        }

        .category-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Unit Grid
        |--------------------------------------------------------------------------
        */

        .unit-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .unit-card {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            background: #ffffff;
        }

        .unit-code {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .unit-name {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .unit-quantity {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .status-tersedia {
            background: #dcfce7;
            color: #166534;
        }

        .status-disewa {
            background: #fef3c7;
            color: #92400e;
        }


        /*
        |--------------------------------------------------------------------------
        | Unit Actions
        |--------------------------------------------------------------------------
        */

        .unit-actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .unit-actions .btn {
            padding: 8px 10px;
            font-size: 12px;
        }


        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        .empty {
            background: #ffffff;
            border-radius: 13px;
            padding: 40px 20px;
            text-align: center;
            color: #6b7280;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
        }

        .empty-icon {
            font-size: 38px;
            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .unit-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 650px) {

            .container {
                margin: 20px auto;
            }

            .title h1 {
                font-size: 22px;
            }

            .search-form {
                flex-direction: column;
            }

            .unit-grid {
                grid-template-columns: 1fr;
            }

            .branch-header,
            .category {
                padding-left: 15px;
                padding-right: 15px;
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
                🔎 Cek Semua Unit
            </h1>

            <p>
                Melihat unit dari seluruh cabang.
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- SEARCH -->

    <div class="search-card">

        <form
            method="GET"
            action="cek-unit.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                class="search-input"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Cari kode unit, nama unit, kategori, atau cabang..."
            >

            <button
                type="submit"
                class="btn btn-primary"
            >
                🔍 Cari
            </button>

            <?php if ($search !== ''): ?>

                <a
                    href="cek-unit.php"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- DATA UNIT -->

    <?php if (empty($groupedBranches)): ?>

        <div class="empty">

            <div class="empty-icon">
                📦
            </div>

            <?php if ($search !== ''): ?>

                <strong>
                    Unit tidak ditemukan.
                </strong>

                <p>
                    Coba gunakan kata pencarian yang berbeda.
                </p>

            <?php else: ?>

                <strong>
                    Belum ada unit.
                </strong>

                <p>
                    Data unit belum tersedia pada sistem.
                </p>

            <?php endif; ?>

        </div>

    <?php else: ?>


        <?php foreach ($groupedBranches as $branch): ?>

            <div class="branch-card">


                <!-- CABANG -->

                <div class="branch-header">

                    <h2>
                        🏢
                        <?= htmlspecialchars(
                            $branch['nama_cabang']
                        ) ?>
                    </h2>

                    <div class="branch-id">

                        ID Cabang:
                        <?= (int) $branch['id'] ?>

                    </div>

                </div>


                <!-- KATEGORI -->

                <?php foreach (
                    $branch['categories']
                    as $categoryName => $categoryUnits
                ): ?>

                    <div class="category">

                        <div class="category-title">

                            📦
                            <?= htmlspecialchars(
                                $categoryName
                            ) ?>

                            <span style="
                                color:#9ca3af;
                                font-weight:normal;
                            ">
                                (<?= count($categoryUnits) ?> unit)
                            </span>

                        </div>


                        <div class="unit-grid">


                            <?php foreach (
                                $categoryUnits
                                as $unit
                            ): ?>

                                <div class="unit-card">


                                    <div class="unit-code">

                                        <?= htmlspecialchars(
                                            $unit['kode_unit']
                                        ) ?>

                                    </div>


                                    <div class="unit-name">

                                        <?= htmlspecialchars(
                                            $unit['nama_unit']
                                        ) ?>

                                    </div>


                                    <div class="unit-quantity">

                                        Jumlah:
                                        <?= (int) $unit['jumlah'] ?>

                                    </div>


                                    <?php if (
                                        $unit['status'] ===
                                        'TERSEDIA'
                                    ): ?>

                                        <span class="
                                            status
                                            status-tersedia
                                        ">
                                            TERSEDIA
                                        </span>

                                    <?php else: ?>

                                        <span class="
                                            status
                                            status-disewa
                                        ">
                                            DISEWAKAN
                                        </span>

                                    <?php endif; ?>


                                    <div class="unit-actions">

                                        <a
                                            href="qr.php?id=<?= (int) $unit['id'] ?>"
                                            class="btn btn-qr"
                                        >
                                            Lihat QR
                                        </a>

                                    </div>


                                </div>

                            <?php endforeach; ?>


                        </div>

                    </div>

                <?php endforeach; ?>


            </div>

        <?php endforeach; ?>

    <?php endif; ?>


</div>

</body>

</html>