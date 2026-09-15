<?php

require_once "../config/auth.php";
require_once "../config/database.php";
require_once "../config/csrf.php";

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Scan Unit Keluar - Sistem Scan Unit</title>

    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f7fb;
            color: #111827;
        }

        .container {
            width: 92%;
            max-width: 760px;

            margin: 0 auto;

            padding: 30px 0 50px;
        }

        /* =========================
           BACK
        ========================= */

        .back {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            margin-bottom: 25px;

            color: #2563eb;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;
        }

        .back:hover {
            text-decoration: underline;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0 0 7px;

            font-size: 30px;

            letter-spacing: -0.5px;
        }

        .header p {
            margin: 0;

            color: #6b7280;

            font-size: 15px;

            line-height: 1.6;
        }

        /* =========================
           MAIN CARD
        ========================= */

        .card {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 18px;

            padding: 25px;

            box-shadow:
                0 6px 20px
                rgba(15, 23, 42, 0.05);
        }

        /* =========================
           SECTION HEADER
        ========================= */

        .section-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

            margin-bottom: 15px;
        }

        .section-header h2 {
            margin: 0;

            font-size: 18px;
        }

        .scanner-status {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            border-radius: 999px;

            background: #eff6ff;

            color: #1d4ed8;

            font-size: 12px;

            font-weight: 700;

            white-space: nowrap;
        }

        .status-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #2563eb;
        }

        /* =========================
           SCANNER
        ========================= */

        .scanner-wrapper {
            padding: 10px;

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 15px;

            overflow: hidden;
        }

        #reader {
            width: 100%;

            max-width: 500px;

            margin: 0 auto;
        }

        #reader video {
            width: 100% !important;

            border-radius: 10px;
        }

        #reader img {
            max-width: 100%;
        }

        #reader__dashboard {
            padding-top: 10px !important;
        }

        #reader__dashboard button {
            border: none !important;

            border-radius: 8px !important;

            padding: 9px 14px !important;

            background: #2563eb !important;

            color: #ffffff !important;

            font-weight: 600;

            cursor: pointer;
        }

        #reader__dashboard button:hover {
            background: #1d4ed8 !important;
        }

        #reader__status_span {
            font-size: 13px !important;
        }

        .scanner-hint {
            margin-top: 12px;

            color: #6b7280;

            text-align: center;

            font-size: 13px;

            line-height: 1.5;
        }

        /* =========================
           ERROR
        ========================= */

        .error {
            display: none;

            margin-top: 18px;

            padding: 14px 16px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            border-radius: 11px;

            color: #991b1b;

            font-size: 14px;

            line-height: 1.5;
        }

        /* =========================
           UNIT INFO
        ========================= */

        .unit-info {
            display: none;

            margin-top: 25px;

            border: 1px solid #e5e7eb;

            border-radius: 15px;

            overflow: hidden;

            background: #ffffff;
        }

        .unit-header {
            padding: 18px 20px;

            background: #f8fafc;

            border-bottom: 1px solid #e5e7eb;
        }

        .unit-header h2 {
            margin: 0 0 5px;

            font-size: 18px;
        }

        .unit-header p {
            margin: 0;

            color: #6b7280;

            font-size: 13px;
        }

        .unit-body {
            padding: 5px 20px 20px;
        }

        .row {
            padding: 14px 0;

            border-bottom: 1px solid #e5e7eb;
        }

        .row:last-child {
            border-bottom: none;
        }

        .label {
            margin-bottom: 5px;

            color: #6b7280;

            font-size: 12px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.3px;
        }

        .value {
            color: #111827;

            font-size: 15px;

            font-weight: 600;

            line-height: 1.4;
        }

        /* =========================
           UNIT STATUS
        ========================= */

        .unit-status {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 7px 12px;

            border-radius: 999px;

            font-size: 12px;

            font-weight: 700;
        }

        .unit-status.tersedia {
            background: #dcfce7;

            color: #166534;
        }

        .unit-status.disewakan {
            background: #fee2e2;

            color: #991b1b;
        }

        /* =========================
           FORM
        ========================= */

        .form-area {
            display: none;

            margin-top: 25px;

            padding-top: 25px;

            border-top: 1px solid #e5e7eb;
        }

        .form-title {
            margin-bottom: 5px;

            font-size: 18px;

            font-weight: 700;
        }

        .form-description {
            margin: 0 0 17px;

            color: #6b7280;

            font-size: 13px;

            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            color: #374151;

            font-size: 14px;

            font-weight: 700;
        }

        .input-wrapper {
            position: relative;
        }

        input {
            width: 100%;

            padding: 13px 14px;

            border: 1px solid #d1d5db;

            border-radius: 10px;

            background: #ffffff;

            color: #111827;

            font-size: 18px;

            letter-spacing: 3px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        input:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.12);
        }

        .input-hint {
            margin-top: 7px;

            color: #6b7280;

            font-size: 12px;
        }

        /* =========================
           BUTTON
        ========================= */

        .button {
            width: 100%;

            margin-top: 5px;

            padding: 13px 16px;

            border: none;

            border-radius: 10px;

            background: #2563eb;

            color: #ffffff;

            font-size: 14px;

            font-weight: 700;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease;
        }

        .button:hover {
            background: #1d4ed8;
        }

        .button:active {
            transform: scale(0.98);
        }

        .button:disabled {
            background: #9ca3af;

            cursor: not-allowed;

            transform: none;
        }

        /* =========================
           LOADING
        ========================= */

        .loading {
            display: none;

            margin-top: 18px;

            padding: 12px;

            background: #eff6ff;

            color: #1d4ed8;

            border-radius: 10px;

            text-align: center;

            font-size: 13px;

            font-weight: 600;
        }

        /* =========================
           SUCCESS
        ========================= */

        .success {
            display: none;

            margin-top: 18px;

            padding: 16px;

            background: #ecfdf5;

            border: 1px solid #bbf7d0;

            color: #166534;

            border-radius: 11px;

            font-size: 14px;

            line-height: 1.5;
        }

        .success-title {
            margin-bottom: 4px;

            font-weight: 700;
        }

        /* =========================
           SCAN AGAIN
        ========================= */

        .scan-again {
            display: none;

            width: 100%;

            margin-top: 10px;

            padding: 12px 16px;

            border: 1px solid #d1d5db;

            border-radius: 10px;

            background: #ffffff;

            color: #374151;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;
        }

        .scan-again:hover {
            background: #f9fafb;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 600px) {

            .container {
                width: 94%;

                padding-top: 22px;
            }

            .header h1 {
                font-size: 26px;
            }

            .card {
                padding: 18px;

                border-radius: 15px;
            }

            .section-header {
                align-items: flex-start;

                flex-direction: column;
            }

            .scanner-wrapper {
                padding: 8px;
            }

            .unit-body {
                padding-left: 17px;

                padding-right: 17px;
            }

            .form-area {
                margin-top: 20px;

                padding-top: 20px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <a
        href="dashboard.php"
        class="back"
    >
        ← Kembali ke Dashboard
    </a>


    <div class="header">

        <h1>
            Scan Unit Keluar
        </h1>

        <p>
            Scan QR Code unit yang akan disewakan,
            kemudian masukkan kode penyewa.
        </p>

    </div>


    <div class="card">

        <!-- =========================
             SCANNER
        ========================== -->

        <div class="section-header">

            <h2>
                Scan QR Unit
            </h2>

            <div class="scanner-status">

                <span class="status-dot"></span>

                Scanner aktif

            </div>

        </div>


        <div class="scanner-wrapper">

            <div id="reader"></div>

        </div>


        <div class="scanner-hint">

            Arahkan kamera ke QR Code unit
            sampai berhasil terbaca.

        </div>


        <!-- =========================
             ERROR
        ========================== -->

        <div
            id="error"
            class="error"
        ></div>


        <!-- =========================
             UNIT INFO
        ========================== -->

        <div
            id="unitInfo"
            class="unit-info"
        >

            <div class="unit-header">

                <h2>
                    Informasi Unit
                </h2>

                <p>
                    Periksa unit sebelum dikonfirmasi
                    sebagai unit keluar.
                </p>

            </div>


            <div class="unit-body">

                <div class="row">

                    <div class="label">
                        Kode Unit
                    </div>

                    <div
                        class="value"
                        id="kodeUnit"
                    ></div>

                </div>


                <div class="row">

                    <div class="label">
                        Nama Unit
                    </div>

                    <div
                        class="value"
                        id="namaUnit"
                    ></div>

                </div>


                <div class="row">

                    <div class="label">
                        Kategori
                    </div>

                    <div
                        class="value"
                        id="kategori"
                    ></div>

                </div>


                <div class="row">

                    <div class="label">
                        Cabang
                    </div>

                    <div
                        class="value"
                        id="cabang"
                    ></div>

                </div>


                <div class="row">

                    <div class="label">
                        Status Unit
                    </div>

                    <div class="value">

                        <span
                            id="status"
                            class="unit-status"
                        ></span>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================
             KODE PENYEWA
        ========================== -->

        <div
            id="formArea"
            class="form-area"
        >

            <div class="form-title">
                Kode Penyewa
            </div>

            <p class="form-description">
                Masukkan kode penyewa sebelum
                mengonfirmasi unit keluar.
            </p>


            <div class="form-group">

                <label for="renterCode">
                    Kode Penyewa
                </label>

                <div class="input-wrapper">

                    <input
                        type="text"
                        id="renterCode"
                        maxlength="4"
                        minlength="4"
                        inputmode="numeric"
                        pattern="[0-9]{4}"
                        placeholder="0000"
                        autocomplete="off"
                    >

                </div>

                <div class="input-hint">
                    Kode harus terdiri dari tepat 4 angka.
                </div>

            </div>


            <button
                type="button"
                id="submitButton"
                class="button"
                onclick="prosesUnitKeluar()"
            >
                Konfirmasi Unit Keluar
            </button>

        </div>


        <!-- =========================
             LOADING
        ========================== -->

        <div
            id="loading"
            class="loading"
        >
            Sedang memproses unit...
        </div>


        <!-- =========================
             SUCCESS
        ========================== -->

        <div
            id="success"
            class="success"
        ></div>


        <!-- =========================
             SCAN AGAIN
        ========================== -->

        <button
            type="button"
            id="scanAgainButton"
            class="scan-again"
            onclick="scanAgain()"
        >
            Scan Unit Berikutnya
        </button>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| VARIABEL
|--------------------------------------------------------------------------
*/

let scanner = null;

let qrToken = "";

let scannerStopped = false;


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

const csrfToken =
    "<?= htmlspecialchars(
        csrf_token(),
        ENT_QUOTES,
        "UTF-8"
    ) ?>";


/*
|--------------------------------------------------------------------------
| MULAI SCANNER
|--------------------------------------------------------------------------
*/

function mulaiScanner() {

    scannerStopped = false;

    scanner = new Html5Qrcode("reader");


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


        function (qrCodeMessage) {

            /*
             * Hindari scan berkali-kali
             */

            if (scannerStopped) {
                return;
            }

            scannerStopped = true;


            scanner
                .stop()

                .then(function () {

                    cekUnit(qrCodeMessage);

                })

                .catch(function (error) {

                    console.error(
                        "Scanner stop error:",
                        error
                    );

                    cekUnit(qrCodeMessage);

                });

        },


        function (errorMessage) {

            /*
             * Error scanning biasa
             * tidak perlu ditampilkan.
             */

        }

    )

    .catch(function (error) {

        console.error(
            "Camera error:",
            error
        );

        tampilError(
            "Kamera tidak dapat digunakan. " +
            "Pastikan izin kamera sudah diberikan " +
            "pada browser."
        );

    });

}


/*
|--------------------------------------------------------------------------
| CEK UNIT
|--------------------------------------------------------------------------
*/

function cekUnit(token) {

    qrToken = token;


    fetch(

        "cek-keluar.php?qr_token=" +
        encodeURIComponent(token),

        {
            method: "GET",

            credentials: "same-origin"
        }

    )

    .then(function (response) {

        if (!response.ok) {

            throw new Error(
                "HTTP " +
                response.status
            );

        }

        return response.json();

    })

    .then(function (data) {

        /*
         * Tampilkan data unit jika tersedia
         */

        if (data.unit) {

            document.getElementById(
                "unitInfo"
            ).style.display = "block";


            document.getElementById(
                "kodeUnit"
            ).textContent =
                data.unit.kode_unit ?? "-";


            document.getElementById(
                "namaUnit"
            ).textContent =
                data.unit.nama_unit ?? "-";


            document.getElementById(
                "kategori"
            ).textContent =
                data.unit.kategori ?? "-";


            document.getElementById(
                "cabang"
            ).textContent =
                data.unit.nama_cabang ?? "-";


            const status =
                document.getElementById(
                    "status"
                );


            status.textContent =
                data.unit.status ?? "-";


            status.className =
                "unit-status";


            if (
                data.unit.status ===
                "TERSEDIA"
            ) {

                status.classList.add(
                    "tersedia"
                );

            }
            else if (
                data.unit.status ===
                "DISEWAKAN"
            ) {

                status.classList.add(
                    "disewakan"
                );

            }

        }


        /*
         * Jika backend menolak checkout
         */

        if (!data.success) {

            tampilError(
                data.message ||
                "Unit tidak dapat diproses."
            );

            return;

        }


        /*
         * Pastikan backend mengizinkan
         */

        if (!data.can_checkout) {

            tampilError(
                data.message ||
                "Unit tidak dapat dikeluarkan."
            );

            return;

        }


        /*
         * Tampilkan form kode penyewa
         */

        document.getElementById(
            "formArea"
        ).style.display = "block";


        document.getElementById(
            "renterCode"
        ).focus();

    })

    .catch(function (error) {

        console.error(
            "Cek unit error:",
            error
        );

        tampilError(
            "Terjadi kesalahan saat mengecek unit."
        );

    });

}


/*
|--------------------------------------------------------------------------
| PROSES UNIT KELUAR
|--------------------------------------------------------------------------
*/

function prosesUnitKeluar() {

    const renterCode =
        document
            .getElementById("renterCode")
            .value
            .trim();


    /*
     * Validasi kode penyewa
     */

    if (!/^[0-9]{4}$/.test(renterCode)) {

        tampilError(
            "Kode penyewa harus terdiri dari 4 angka."
        );

        document
            .getElementById("renterCode")
            .focus();

        return;

    }


    /*
     * Ambil tombol
     */

    const submitButton =
        document.getElementById(
            "submitButton"
        );


    /*
     * Cegah double submit
     */

    submitButton.disabled = true;


    /*
     * Tampilkan loading
     */

    document.getElementById(
        "loading"
    ).style.display = "block";


    /*
     * Hilangkan error lama
     */

    tampilError("");


    /*
     * FormData
     */

    const formData =
        new FormData();


    /*
     * CSRF
     */

    formData.append(
        "csrf_token",
        csrfToken
    );


    /*
     * QR Token
     */

    formData.append(
        "qr_token",
        qrToken
    );


    /*
     * Kode Penyewa
     */

    formData.append(
        "renter_code",
        renterCode
    );


    /*
     * Kirim ke backend
     */

    fetch(

        "proses-keluar.php",

        {
            method: "POST",

            body: formData,

            credentials: "same-origin"
        }

    )

    .then(function (response) {

        if (!response.ok) {

            throw new Error(
                "HTTP " +
                response.status
            );

        }

        return response.json();

    })

    .then(function (data) {

        /*
         * Hilangkan loading
         */

        document.getElementById(
            "loading"
        ).style.display = "none";


        /*
         * Berhasil
         */

        if (data.success) {

            document.getElementById(
                "formArea"
            ).style.display = "none";


            const success =
                document.getElementById(
                    "success"
                );


            success.style.display = "block";


            success.textContent =
                data.message ||
                "Unit berhasil dikeluarkan.";


            /*
             * Update status visual
             */

            const status =
                document.getElementById(
                    "status"
                );


            status.textContent =
                "DISEWAKAN";


            status.className =
                "unit-status disewakan";


            /*
             * Tampilkan tombol scan lagi
             */

            document.getElementById(
                "scanAgainButton"
            ).style.display = "block";


            return;

        }


        /*
         * Jika gagal
         */

        submitButton.disabled = false;


        tampilError(
            data.message ||
            "Unit gagal diproses."
        );

    })

    .catch(function (error) {

        console.error(
            "Proses unit error:",
            error
        );


        document.getElementById(
            "loading"
        ).style.display = "none";


        submitButton.disabled = false;


        tampilError(
            "Terjadi kesalahan saat memproses unit."
        );

    });

}


/*
|--------------------------------------------------------------------------
| TAMPILKAN ERROR
|--------------------------------------------------------------------------
*/

function tampilError(message) {

    const error =
        document.getElementById(
            "error"
        );


    if (!message) {

        error.textContent = "";

        error.style.display = "none";

        return;

    }


    error.textContent = message;

    error.style.display = "block";

}


/*
|--------------------------------------------------------------------------
| SCAN LAGI
|--------------------------------------------------------------------------
*/

function scanAgain() {

    /*
     * Sembunyikan hasil
     */

    document.getElementById(
        "unitInfo"
    ).style.display = "none";


    document.getElementById(
        "formArea"
    ).style.display = "none";


    document.getElementById(
        "success"
    ).style.display = "none";


    document.getElementById(
        "scanAgainButton"
    ).style.display = "none";


    /*
     * Reset error
     */

    tampilError("");


    /*
     * Reset input
     */

    document.getElementById(
        "renterCode"
    ).value = "";


    /*
     * Reset tombol
     */

    document.getElementById(
        "submitButton"
    ).disabled = false;


    /*
     * Reset scanner state
     */

    scannerStopped = false;


    /*
     * Bersihkan reader lama
     */

    document.getElementById(
        "reader"
    ).innerHTML = "";


    /*
     * Mulai scanner baru
     */

    mulaiScanner();

}


/*
|--------------------------------------------------------------------------
| START
|--------------------------------------------------------------------------
*/

mulaiScanner();

</script>

</body>

</html>