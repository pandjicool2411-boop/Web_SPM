<?php

require_once "../config/auth.php";
require_once "../config/database.php";

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Scan Unit Cek - Sistem Scan Unit</title>

    <!-- Library QR Scanner -->
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
           HEADER
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
                0 6px 20px rgba(15, 23, 42, 0.05);
        }

        /* =========================
           SCANNER HEADER
        ========================= */

        .scanner-title {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 15px;

            margin-bottom: 15px;
        }

        .scanner-title h2 {
            margin: 0;

            font-size: 18px;
        }

        .camera-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;

            border-radius: 999px;

            background: #eff6ff;
            color: #1d4ed8;

            font-size: 12px;
            font-weight: 700;
        }

        .camera-dot {
            width: 7px;
            height: 7px;

            border-radius: 50%;

            background: #2563eb;
        }

        /* =========================
           SCANNER
        ========================= */

        .scanner-wrapper {
            background: #f8fafc;

            border: 1px solid #e5e7eb;
            border-radius: 15px;

            padding: 12px;

            overflow: hidden;
        }

        #reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }

        /*
         * Sedikit merapikan elemen bawaan
         * dari html5-qrcode.
         */

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

            cursor: pointer;
            font-weight: 600;
        }

        #reader__dashboard button:hover {
            background: #1d4ed8 !important;
        }

        #reader__status_span {
            font-size: 13px !important;
        }

        /* =========================
           SCANNER HINT
        ========================= */

        .scanner-hint {
            margin-top: 13px;

            text-align: center;

            color: #6b7280;
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

            color: #991b1b;

            border-radius: 11px;

            font-size: 14px;
            line-height: 1.5;
        }

        /* =========================
           RESULT
        ========================= */

        .result {
            display: none;

            margin-top: 25px;

            border: 1px solid #e5e7eb;
            border-radius: 15px;

            overflow: hidden;

            background: #ffffff;
        }

        .result-header {
            padding: 18px 20px;

            background: #f8fafc;

            border-bottom: 1px solid #e5e7eb;
        }

        .result-header h2 {
            margin: 0 0 5px;

            font-size: 18px;
        }

        .result-header p {
            margin: 0;

            color: #6b7280;

            font-size: 13px;
        }

        .result-body {
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
           STATUS
        ========================= */

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 7px 12px;

            border-radius: 999px;

            font-size: 12px;
            font-weight: 700;
        }

        .status.tersedia {
            background: #dcfce7;
            color: #166534;
        }

        .status.disewakan {
            background: #fee2e2;
            color: #991b1b;
        }

        /* =========================
           SCAN AGAIN
        ========================= */

        .scan-again {
            width: 100%;

            margin-top: 8px;

            padding: 12px 16px;

            border: none;
            border-radius: 10px;

            background: #2563eb;
            color: #ffffff;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition:
                background 0.2s ease,
                transform 0.1s ease;
        }

        .scan-again:hover {
            background: #1d4ed8;
        }

        .scan-again:active {
            transform: scale(0.98);
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

            .scanner-title {
                align-items: flex-start;
                flex-direction: column;
            }

            .scanner-wrapper {
                padding: 8px;
            }

            .result-body {
                padding-left: 17px;
                padding-right: 17px;
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

        <h1>Scan Unit Cek</h1>

        <p>
            Gunakan kamera untuk melihat informasi
            dan status unit berdasarkan QR Code.
        </p>

    </div>

    <div class="card">

        <div class="scanner-title">

            <h2>Arahkan Kamera ke QR</h2>

            <div class="camera-status">
                <span class="camera-dot"></span>
                Kamera Scanner
            </div>

        </div>

        <div class="scanner-wrapper">

            <div id="reader"></div>

        </div>

        <div class="scanner-hint">
            Pastikan seluruh QR Code terlihat jelas
            di dalam area kamera.
        </div>

        <div
            id="error"
            class="error"
        ></div>

        <div
            id="result"
            class="result"
        >

            <div class="result-header">

                <h2>Informasi Unit</h2>

                <p>
                    Data berikut berasal dari unit
                    yang berhasil dipindai.
                </p>

            </div>

            <div class="result-body">

                <div class="row">

                    <div class="label">
                        Kode Unit
                    </div>

                    <div
                        class="value"
                        id="kode_unit"
                    ></div>

                </div>

                <div class="row">

                    <div class="label">
                        Nama Unit
                    </div>

                    <div
                        class="value"
                        id="nama_unit"
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
                        Terdaftar di Cabang
                    </div>

                    <div
                        class="value"
                        id="nama_cabang"
                    ></div>

                </div>

                <div class="row">

                    <div class="label">
                        Status Unit
                    </div>

                    <div class="value">

                        <span
                            id="status"
                            class="status"
                        ></span>

                    </div>

                </div>

                <button
                    type="button"
                    class="scan-again"
                    onclick="scanAgain()"
                >
                    Scan Unit Lain
                </button>

            </div>

        </div>

    </div>

</div>


<script>

    let scanner = null;
    let sedangMemproses = false;


    /*
     * =========================================
     * MULAI SCANNER
     * =========================================
     */

    function mulaiScanner() {

        if (sedangMemproses) {
            return;
        }

        sedangMemproses = false;

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

                if (sedangMemproses) {
                    return;
                }

                sedangMemproses = true;

                scanner
                    .stop()
                    .then(function () {

                        cekUnit(qrCodeMessage);

                    })
                    .catch(function (error) {

                        console.error(
                            "Gagal menghentikan scanner:",
                            error
                        );

                        cekUnit(qrCodeMessage);

                    });

            },

            function (errorMessage) {

                /*
                 * Error scanning biasa sengaja
                 * tidak ditampilkan karena scanner
                 * memang terus mencari QR.
                 */

            }

        )
        .catch(function (error) {

            sedangMemproses = false;

            tampilError(
                "Kamera tidak dapat digunakan. " +
                "Pastikan izin kamera diberikan " +
                "pada browser."
            );

            console.error(
                "Camera error:",
                error
            );

        });

    }


    /*
     * =========================================
     * CEK UNIT
     * =========================================
     */

    function cekUnit(qrToken) {

        tampilError("");

        fetch(
            "cek-unit.php?qr_token=" +
            encodeURIComponent(qrToken),
            {
                method: "GET",
                credentials: "same-origin"
            }
        )

        .then(function (response) {

            if (!response.ok) {

                throw new Error(
                    "HTTP error: " +
                    response.status
                );

            }

            return response.json();

        })

        .then(function (data) {

            if (data.success && data.unit) {

                tampilHasil(data.unit);

            } else {

                tampilError(
                    data.message ||
                    "Unit tidak ditemukan."
                );

            }

        })

        .catch(function (error) {

            console.error(
                "Cek unit error:",
                error
            );

            tampilError(
                "Terjadi kesalahan saat mengambil " +
                "data unit."
            );

        });

    }


    /*
     * =========================================
     * TAMPIL HASIL
     * =========================================
     */

    function tampilHasil(unit) {

        document.getElementById(
            "result"
        ).style.display = "block";

        document.getElementById(
            "error"
        ).style.display = "none";


        document.getElementById(
            "kode_unit"
        ).textContent =
            unit.kode_unit || "-";


        document.getElementById(
            "nama_unit"
        ).textContent =
            unit.nama_unit || "-";


        document.getElementById(
            "kategori"
        ).textContent =
            unit.kategori || "-";


        document.getElementById(
            "nama_cabang"
        ).textContent =
            unit.nama_cabang || "-";


        const status =
            document.getElementById("status");


        status.textContent =
            unit.status || "-";


        status.className = "status";


        if (unit.status === "TERSEDIA") {

            status.classList.add(
                "tersedia"
            );

        } else if (
            unit.status === "DISEWAKAN"
        ) {

            status.classList.add(
                "disewakan"
            );

        }

    }


    /*
     * =========================================
     * TAMPIL ERROR
     * =========================================
     */

    function tampilError(message) {

        const error =
            document.getElementById("error");


        if (!message) {

            error.textContent = "";
            error.style.display = "none";

            return;
        }


        error.textContent = message;
        error.style.display = "block";

    }


    /*
     * =========================================
     * SCAN LAGI
     * =========================================
     */

    function scanAgain() {

        document.getElementById(
            "result"
        ).style.display = "none";


        tampilError("");


        sedangMemproses = false;


        const reader =
            document.getElementById("reader");


        /*
         * Bersihkan elemen scanner lama
         * sebelum membuat scanner baru.
         */

        reader.innerHTML = "";


        mulaiScanner();

    }


    /*
     * =========================================
     * START
     * =========================================
     */

    mulaiScanner();

</script>

</body>

</html>