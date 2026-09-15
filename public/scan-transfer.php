<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

$transfer_id = filter_input(
    INPUT_GET,
    "transfer_id",
    FILTER_VALIDATE_INT
);

if (
    $transfer_id === false ||
    $transfer_id === null ||
    $transfer_id <= 0
) {
    http_response_code(400);
    die("ID transfer tidak valid.");
}

$branch_id = (int) $_SESSION["branch_id"];


/*
|--------------------------------------------------------------------------
| Ambil data transfer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        unit_transfers.id,
        unit_transfers.unit_id,
        unit_transfers.from_branch_id,
        unit_transfers.to_branch_id,
        unit_transfers.status,

        units.kode_unit,
        units.nama_unit,
        units.kategori,
        units.qr_token,
        units.status AS unit_status,

        from_branch.nama_cabang AS dari_cabang,
        to_branch.nama_cabang AS ke_cabang

    FROM unit_transfers

    INNER JOIN units
        ON unit_transfers.unit_id = units.id

    INNER JOIN branches AS from_branch
        ON unit_transfers.from_branch_id = from_branch.id

    INNER JOIN branches AS to_branch
        ON unit_transfers.to_branch_id = to_branch.id

    WHERE unit_transfers.id = ?

    LIMIT 1
");

$stmt->execute([$transfer_id]);

$transfer = $stmt->fetch();


if (!$transfer) {
    http_response_code(404);
    die("Data transfer tidak ditemukan.");
}


/*
|--------------------------------------------------------------------------
| Pastikan transfer ditujukan ke cabang yang sedang login
|--------------------------------------------------------------------------
*/

if (
    (int) $transfer["to_branch_id"] !== $branch_id
) {
    http_response_code(403);
    die(
        "Anda tidak memiliki akses untuk menerima transfer ini."
    );
}


/*
|--------------------------------------------------------------------------
| Pastikan transfer masih APPROVED
|--------------------------------------------------------------------------
*/

if ($transfer["status"] !== "APPROVED") {
    http_response_code(409);
    die(
        "Transfer belum disetujui atau sudah selesai."
    );
}


/*
|--------------------------------------------------------------------------
| Pastikan unit masih tersedia
|--------------------------------------------------------------------------
|
| Unit yang sedang disewakan tidak boleh diproses sebagai transfer.
|
*/

if ($transfer["unit_status"] !== "TERSEDIA") {
    http_response_code(409);
    die(
        "Unit tidak tersedia untuk diterima."
    );
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

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            color: #222;
        }

        .container {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }

        .back:hover {
            text-decoration: underline;
        }

        .card {
            background: #ffffff;
            padding: 22px;
            border-radius: 14px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 10px;
        }

        h2 {
            margin-top: 0;
        }

        .unit-info {
            line-height: 1.9;
        }

        .unit-info strong {
            color: #111827;
        }

        .transfer-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
        }

        #reader {
            width: 100%;
            margin-top: 15px;
            overflow: hidden;
            border-radius: 12px;
        }

        #reader video {
            width: 100% !important;
            height: auto !important;
            border-radius: 12px;
        }

        .start-button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .start-button:hover {
            background: #1d4ed8;
        }

        .start-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .stop-button {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 9px;
            background: #dc2626;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            display: none;
        }

        .stop-button:hover {
            background: #b91c1c;
        }

        .result {
            display: none;
            margin-top: 18px;
            padding: 15px;
            border-radius: 10px;
            line-height: 1.6;
        }

        .result.info {
            display: block;
            background: #dbeafe;
            color: #1e40af;
        }

        .result.success {
            display: block;
            background: #dcfce7;
            color: #166534;
        }

        .result.error {
            display: block;
            background: #fee2e2;
            color: #991b1b;
        }

        .result.warning {
            display: block;
            background: #fef3c7;
            color: #92400e;
        }

        .camera-select-wrapper {
            margin-top: 15px;
            display: none;
        }

        .camera-select-wrapper label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        .camera-select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            font-size: 15px;
        }

        .note {
            margin-top: 15px;
            padding: 13px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.6;
        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .card {
                padding: 17px;
            }

            h1 {
                font-size: 25px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <a
        href="transfer.php"
        class="back"
    >
        ← Kembali ke Transfer
    </a>


    <!-- INFORMASI TRANSFER -->

    <div class="card">

        <h1>Scan QR Unit</h1>

        <p>
            Scan QR unit fisik untuk menyelesaikan transfer.
        </p>

        <div class="unit-info">

            <strong>
                Unit yang akan diterima:
            </strong>

            <br>

            Kode:
            <strong>
                <?= htmlspecialchars(
                    $transfer["kode_unit"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>

            <br>

            Nama:
            <?= htmlspecialchars(
                $transfer["nama_unit"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br>

            Kategori:
            <?= htmlspecialchars(
                $transfer["kategori"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br>

            Dari:
            <?= htmlspecialchars(
                $transfer["dari_cabang"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br>

            Ke:
            <?= htmlspecialchars(
                $transfer["ke_cabang"],
                ENT_QUOTES,
                "UTF-8"
            ) ?>

            <br>

            <span class="transfer-badge">
                APPROVED
            </span>

        </div>

    </div>


    <!-- SCANNER -->

    <div class="card">

        <h2>Scanner</h2>

        <button
            type="button"
            id="startButton"
            class="start-button"
            onclick="mulaiScanner()"
        >
            📷 Mulai Kamera
        </button>


        <button
            type="button"
            id="stopButton"
            class="stop-button"
            onclick="hentikanScanner()"
        >
            ⛔ Hentikan Kamera
        </button>


        <div
            id="cameraSelectWrapper"
            class="camera-select-wrapper"
        >

            <label for="cameraSelect">
                Pilih Kamera
            </label>

            <select
                id="cameraSelect"
                class="camera-select"
                onchange="gantiKamera()"
            >
            </select>

        </div>


        <div id="reader"></div>


        <div
            id="result"
            class="result"
        ></div>


        <div class="note">

            <strong>Cara menggunakan:</strong>

            <br>

            1. Klik <strong>Mulai Kamera</strong>.

            <br>

            2. Izinkan browser menggunakan kamera.

            <br>

            3. Arahkan kamera ke QR unit.

            <br>

            4. Pastikan QR tersebut adalah QR permanen dari

            <strong>
                <?= htmlspecialchars(
                    $transfer["kode_unit"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </strong>.

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| DATA TRANSFER
|--------------------------------------------------------------------------
*/

const transferId =
    <?= json_encode($transfer_id) ?>;


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

const csrfToken =
    <?= json_encode(csrf_token()) ?>;


/*
|--------------------------------------------------------------------------
| VARIABLE SCANNER
|--------------------------------------------------------------------------
*/

let scanner = null;

let scannerAktif = false;

let sedangMemproses = false;

let daftarKamera = [];


/*
|--------------------------------------------------------------------------
| ELEMENT
|--------------------------------------------------------------------------
*/

const startButton =
    document.getElementById("startButton");

const stopButton =
    document.getElementById("stopButton");

const result =
    document.getElementById("result");

const cameraSelectWrapper =
    document.getElementById("cameraSelectWrapper");

const cameraSelect =
    document.getElementById("cameraSelect");


/*
|--------------------------------------------------------------------------
| TAMPILKAN PESAN
|--------------------------------------------------------------------------
*/

function tampilkanPesan(pesan, tipe) {

    result.style.display = "block";

    result.className =
        "result " + tipe;

    result.innerHTML = pesan;
}


/*
|--------------------------------------------------------------------------
| AMBIL DAFTAR KAMERA
|--------------------------------------------------------------------------
*/

async function ambilDaftarKamera() {

    try {

        daftarKamera =
            await Html5Qrcode.getCameras();


        if (
            !daftarKamera ||
            daftarKamera.length === 0
        ) {

            throw new Error(
                "Tidak ada kamera yang terdeteksi."
            );
        }


        cameraSelect.innerHTML = "";


        daftarKamera.forEach(
            (kamera, index) => {

                const option =
                    document.createElement("option");

                option.value =
                    kamera.id;

                option.textContent =
                    kamera.label ||
                    `Kamera ${index + 1}`;

                cameraSelect.appendChild(option);
            }
        );


        /*
        | Cari kamera belakang
        */

        const kameraBelakang =
            daftarKamera.find(
                kamera =>
                    /back|rear|environment|belakang/i
                    .test(kamera.label)
            );


        if (kameraBelakang) {

            cameraSelect.value =
                kameraBelakang.id;
        }


        cameraSelectWrapper.style.display =
            daftarKamera.length > 1
                ? "block"
                : "none";


        return true;

    }
    catch (error) {

        console.error(
            "Gagal mendapatkan daftar kamera:",
            error
        );


        const detail =
            error && error.message
                ? error.message
                : String(error);


        tampilkanPesan(
            "Kamera tidak dapat ditemukan." +
            "<br><br>" +
            "<strong>Detail:</strong> " +
            detail +
            "<br><br>" +
            "Pastikan browser memiliki izin menggunakan kamera.",
            "error"
        );


        return false;
    }
}


/*
|--------------------------------------------------------------------------
| MULAI SCANNER
|--------------------------------------------------------------------------
*/

async function mulaiScanner() {

    if (scannerAktif) {
        return;
    }


    sedangMemproses = false;


    startButton.disabled = true;

    startButton.innerText =
        "⏳ Memeriksa kamera...";


    tampilkanPesan(
        "Memeriksa kamera...",
        "info"
    );


    try {

        if (!scanner) {

            scanner =
                new Html5Qrcode("reader");
        }


        const kameraTersedia =
            await ambilDaftarKamera();


        if (!kameraTersedia) {

            startButton.disabled = false;

            startButton.innerText =
                "📷 Coba Lagi";

            return;
        }


        const cameraId =
            cameraSelect.value ||
            daftarKamera[0].id;


        await scanner.start(

            cameraId,

            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                },

                aspectRatio: 1.0
            },

            function (decodedText) {

                if (sedangMemproses) {
                    return;
                }


                sedangMemproses = true;


                prosesScan(decodedText);
            },

            function (errorMessage) {

                // Abaikan error scan biasa.

            }
        );


        scannerAktif = true;


        startButton.style.display =
            "none";

        stopButton.style.display =
            "block";


        tampilkanPesan(
            "Kamera aktif. Arahkan kamera ke QR unit.",
            "info"
        );

    }
    catch (error) {

        console.error(
            "ERROR START CAMERA:",
            error
        );


        scannerAktif = false;


        const detail =
            error && error.message
                ? error.message
                : String(error);


        startButton.disabled =
            false;

        startButton.innerText =
            "📷 Coba Lagi";


        stopButton.style.display =
            "none";


        tampilkanPesan(
            "Kamera tidak dapat digunakan." +
            "<br><br>" +
            "<strong>Detail:</strong> " +
            detail +
            "<br><br>" +
            "Pastikan izin kamera sudah diberikan " +
            "dan halaman dibuka melalui koneksi yang mendukung kamera.",
            "error"
        );
    }
}


/*
|--------------------------------------------------------------------------
| HENTIKAN SCANNER
|--------------------------------------------------------------------------
*/

async function hentikanScanner() {

    if (
        !scanner ||
        !scannerAktif
    ) {
        return;
    }


    try {

        await scanner.stop();

        scanner.clear();

    }
    catch (error) {

        console.error(
            "Gagal menghentikan scanner:",
            error
        );
    }


    scannerAktif = false;

    sedangMemproses = false;


    startButton.style.display =
        "block";

    startButton.disabled =
        false;

    startButton.innerText =
        "📷 Mulai Kamera";


    stopButton.style.display =
        "none";


    cameraSelectWrapper.style.display =
        "none";


    tampilkanPesan(
        "Kamera dihentikan.",
        "info"
    );
}


/*
|--------------------------------------------------------------------------
| GANTI KAMERA
|--------------------------------------------------------------------------
*/

async function gantiKamera() {

    if (!scannerAktif) {
        return;
    }


    try {

        await scanner.stop();

        scannerAktif = false;

        sedangMemproses = false;


        const cameraId =
            cameraSelect.value;


        await scanner.start(

            cameraId,

            {
                fps: 10,

                qrbox: {
                    width: 250,
                    height: 250
                },

                aspectRatio: 1.0
            },

            function (decodedText) {

                if (sedangMemproses) {
                    return;
                }


                sedangMemproses = true;


                prosesScan(decodedText);
            },

            function (errorMessage) {

                // Abaikan error scan biasa.

            }
        );


        scannerAktif = true;


        tampilkanPesan(
            "Kamera berhasil diganti.",
            "info"
        );

    }
    catch (error) {

        console.error(
            "Gagal mengganti kamera:",
            error
        );


        scannerAktif = false;


        tampilkanPesan(
            "Kamera gagal diganti." +
            "<br><br>" +
            "<strong>Detail:</strong> " +
            (
                error.message ||
                String(error)
            ),
            "error"
        );
    }
}


/*
|--------------------------------------------------------------------------
| PROSES HASIL SCAN
|--------------------------------------------------------------------------
*/

async function prosesScan(decodedText) {

    /*
    | Hentikan kamera
    */

    if (
        scanner &&
        scannerAktif
    ) {

        try {

            await scanner.stop();

        }
        catch (error) {

            console.error(error);
        }
    }


    scannerAktif = false;


    startButton.style.display =
        "none";

    stopButton.style.display =
        "none";


    tampilkanPesan(
        "QR berhasil terbaca.<br>" +
        "Memeriksa data unit...",
        "info"
    );


    /*
    |--------------------------------------------------------------------------
    | Kirim ke server
    |--------------------------------------------------------------------------
    */

    try {

        const body =
            new URLSearchParams();

        body.append(
            "csrf_token",
            csrfToken
        );

        body.append(
            "transfer_id",
            transferId
        );

        body.append(
            "qr_token",
            decodedText
        );


        const response =
            await fetch(
                "proses-terima-transfer.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded"
                    },

                    body: body.toString()
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Ambil JSON
        |--------------------------------------------------------------------------
        */

        const data =
            await response.json();


        /*
        |--------------------------------------------------------------------------
        | BERHASIL
        |--------------------------------------------------------------------------
        */

        if (
            response.ok &&
            data.success
        ) {

            tampilkanPesan(

                "✅ " +
                data.message +

                "<br><br>" +

                "<strong>" +
                "Unit sekarang terdaftar di cabang Anda." +
                "</strong>" +

                "<br><br>" +

                "<a href='transfer.php'>" +
                "← Kembali ke Transfer" +
                "</a>",

                "success"
            );


            return;
        }


        /*
        |--------------------------------------------------------------------------
        | GAGAL
        |--------------------------------------------------------------------------
        */

        sedangMemproses = false;


        const pesan =
            data.message ||
            "Transfer gagal diproses.";


        tampilkanPesan(

            "❌ " +
            pesan +

            "<br><br>" +

            "Pastikan QR yang discan adalah QR " +
            "dari unit yang sedang ditransfer.",

            "error"
        );


        startButton.style.display =
            "block";

        startButton.disabled =
            false;

        startButton.innerText =
            "📷 Scan Lagi";

    }
    catch (error) {

        console.error(
            "ERROR PROSES TRANSFER:",
            error
        );


        sedangMemproses = false;


        tampilkanPesan(

            "Terjadi kesalahan saat memproses transfer." +
            "<br><br>" +

            "Silakan coba lagi.",

            "error"
        );


        startButton.style.display =
            "block";

        startButton.disabled =
            false;

        startButton.innerText =
            "📷 Coba Lagi";
    }
}

</script>

</body>

</html>