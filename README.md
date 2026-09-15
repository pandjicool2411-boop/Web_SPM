# Sistem Scan Unit QR

Sistem Scan Unit QR adalah aplikasi berbasis web yang digunakan untuk membantu pengelolaan unit rental menggunakan **QR Code** sebagai identitas setiap unit.

Sistem ini dirancang untuk mendukung operasional beberapa cabang, mulai dari pendataan unit, pengecekan unit melalui QR, proses unit keluar dan masuk, riwayat aktivitas, hingga pemindahan kepemilikan unit antar cabang.

---

## 📌 Fitur Utama

### 🔐 Authentication

* Registrasi akun berdasarkan cabang.
* Login menggunakan username dan password.
* Password disimpan menggunakan hashing.
* Logout dan perlindungan session.
* Setiap akun terhubung dengan satu cabang.

### 🏢 Multi Cabang

Sistem mendukung 4 cabang:

1. Medan Pancing
2. Medan Menteng
3. Medan Padang Bulan
4. Padang

Setiap user memiliki satu cabang sebagai cabang utama.

User dapat melihat informasi unit dari seluruh cabang, tetapi hanya dapat mengelola unit yang terdaftar pada cabangnya sendiri.

### 📦 Manajemen Unit

Setiap unit memiliki:

* Kode unit
* Nama unit
* Kategori
* QR Token
* Cabang pemilik
* Status unit

Status unit:

* `TERSEDIA`
* `DISEWAKAN`

QR Code merupakan identitas permanen dari unit dan tetap sama meskipun unit dipindahkan ke cabang lain.

### 📷 Scan Unit Cek

Fitur ini digunakan untuk mengecek informasi unit menggunakan kamera.

Setelah QR berhasil dipindai, sistem menampilkan:

* Nama unit
* Kode unit
* Kategori
* Cabang terdaftar
* Status unit

Fitur ini bersifat **read-only**, sehingga tidak mengubah data unit.

### 🚚 Scan Unit Keluar

Digunakan ketika unit disewakan.

Alurnya:

```text
Scan QR Unit
      ↓
Sistem mengecek unit
      ↓
Masukkan kode penyewa 4 digit
      ↓
Konfirmasi
      ↓
Unit menjadi DISEWAKAN
      ↓
Data rental dan riwayat tersimpan
```

Kode penyewa terdiri dari tepat **4 digit angka**.

Unit hanya dapat dikeluarkan oleh cabang yang memiliki unit tersebut.

### 📥 Unit Masuk

Unit masuk tidak membutuhkan scan QR kembali.

Alurnya:

```text
Buka Unit Masuk
      ↓
Pilih unit yang sedang disewakan
      ↓
Konfirmasi unit masuk
      ↓
Rental menjadi SELESAI
      ↓
Unit menjadi TERSEDIA
      ↓
Riwayat tersimpan
```

### 🔄 Transfer Unit Antar Cabang

Unit dapat dipindahkan dari satu cabang ke cabang lain tanpa mengganti QR Code.

Alurnya:

```text
Cabang asal
    ↓
Request Transfer
    ↓
PENDING
    ↓
Cabang asal melakukan approval
    ↓
APPROVED
    ↓
Cabang tujuan scan QR
    ↓
COMPLETED
    ↓
Kepemilikan unit berpindah
```

Transfer hanya dapat dilakukan terhadap unit yang berstatus `TERSEDIA`.

Selama transfer masih `PENDING`, cabang tujuan belum dapat mendaftarkan unit tersebut.

### 📜 Riwayat

Sistem mencatat aktivitas penting terhadap unit, seperti:

* Unit dibuat
* Unit keluar
* Unit masuk
* Request transfer
* Approval transfer
* Rejection transfer
* Penyelesaian transfer
* Perubahan data unit

Riwayat bersifat **append-only**, sehingga aktivitas yang sudah tercatat tidak diubah melalui aplikasi.

---

# 🛡️ Hak Akses

Sistem menerapkan pembatasan berdasarkan cabang pemilik unit.

| Aktivitas                               | Cabang Sendiri | Cabang Lain |
| --------------------------------------- | :------------: | :---------: |
| Melihat unit                            |        ✅       |      ✅      |
| Scan Cek                                |        ✅       |      ✅      |
| Edit unit                               |        ✅       |      ❌      |
| Unit Keluar                             |        ✅       |      ❌      |
| Unit Masuk                              |        ✅       |      ❌      |
| Request Transfer                        |        ✅       |      ❌      |
| Mengelola unit setelah transfer selesai |        ✅       |      ❌      |

> Hak akses tidak hanya dibatasi melalui tampilan. Backend juga melakukan pengecekan kepemilikan cabang sebelum menjalankan operasi penting.

---

# 🧰 Teknologi

Sistem dibangun menggunakan:

* **PHP**
* **MySQL**
* **HTML5**
* **CSS3**
* **JavaScript**
* **PDO**
* **HTML5 QR Code Scanner**
* **XAMPP**

---

# 📁 Struktur Folder

```text
sistem_scan_unit/
│
├── config/
│   ├── database.php
│   ├── auth.php
│   └── csrf.php
│
├── public/
│   ├── test-db.php
│   ├── branches.php
│   ├── register.php
│   ├── login.php
│   ├── dashboard.php
│   ├── logout.php
│   │
│   ├── units.php
│   ├── qr.php
│   ├── edit-unit.php
│   │
│   ├── scan-cek.php
│   ├── cek-unit.php
│   │
│   ├── scan-keluar.php
│   ├── cek-keluar.php
│   ├── proses-keluar.php
│   │
│   ├── unit-masuk.php
│   ├── proses-masuk.php
│   │
│   ├── riwayat.php
│   │
│   ├── transfer.php
│   ├── proses-transfer.php
│   ├── scan-transfer.php
│   └── proses-terima-transfer.php
│
└── README.md
```

---

# 🗄️ Struktur Database

Database yang digunakan:

```text
sistem_scan_unit
```

Database terdiri dari 6 tabel utama:

```text
branches
users
units
rentals
unit_transfers
unit_history
```

Relasi sederhananya:

```text
branches
   │
   ├── users
   │
   └── units
          │
          ├── rentals
          │
          ├── unit_transfers
          │
          └── unit_history
```

---

# ⚙️ Instalasi

## 1. Install XAMPP

Pastikan XAMPP sudah terinstall pada komputer.

Jalankan:

* Apache
* MySQL

Pastikan keduanya berjalan.

---

## 2. Letakkan Project

Salin folder project ke:

```text
C:\xampp\htdocs\
```

Sehingga menjadi:

```text
C:\xampp\htdocs\sistem_scan_unit
```

---

## 3. Buat Database

Buka:

```text
http://localhost/phpmyadmin
```

Buat database baru dengan nama:

```text
sistem_scan_unit
```

Gunakan:

```text
utf8mb4
```

sebagai character set jika tersedia.

---

## 4. Import Struktur Database

Jika memiliki file backup `.sql`, pilih database:

```text
sistem_scan_unit
```

kemudian:

```text
Import → Choose File → pilih file .sql → Import
```

Jika belum memiliki backup database, jalankan SQL struktur database yang digunakan oleh project.

---

# 🔌 Konfigurasi Database

File konfigurasi berada di:

```text
config/database.php
```

Konfigurasi default XAMPP:

```php
$host = "localhost";
$dbname = "sistem_scan_unit";
$username = "root";
$password = "";
```

Jika konfigurasi MySQL XAMPP berbeda, sesuaikan bagian tersebut.

---

# ▶️ Menjalankan Sistem

Setelah Apache dan MySQL aktif, buka browser:

```text
http://localhost/sistem_scan_unit/public/
```

Halaman utama yang dapat digunakan:

```text
http://localhost/sistem_scan_unit/public/login.php
```

---

# 👤 Membuat Akun

Jika belum memiliki akun:

1. Buka halaman Register.
2. Masukkan nama.
3. Masukkan nomor HP.
4. Pilih cabang.
5. Masukkan username.
6. Masukkan password.
7. Submit registrasi.
8. Login menggunakan akun tersebut.

Setiap akun hanya terhubung dengan **satu cabang**.

---

# 🏢 Menambahkan Cabang

Sistem menggunakan empat cabang:

```text
Medan Pancing
Medan Menteng
Medan Padang Bulan
Padang
```

Nama cabang harus konsisten karena digunakan sebagai referensi pada data user, unit, dan transfer.

---

# 📦 Alur Penggunaan Unit

## Menambahkan Unit

```text
Daftar Unit
    ↓
Tambah Unit
    ↓
Masukkan kode unit
    ↓
Masukkan nama unit
    ↓
Masukkan kategori
    ↓
Simpan
```

Setiap unit mendapatkan QR Token unik.

---

## Melihat QR Unit

Buka menu:

```text
QR Unit
```

QR dapat digunakan sebagai identitas fisik unit.

QR tidak berubah ketika unit berpindah cabang.

---

# 📷 Alur Scan Cek

```text
Scan Unit Cek
      ↓
Izinkan penggunaan kamera
      ↓
Scan QR
      ↓
Informasi unit ditampilkan
```

Scan Cek tidak mengubah status maupun kepemilikan unit.

---

# 🚚 Alur Unit Keluar

```text
Scan Unit Keluar
      ↓
Scan QR
      ↓
Validasi cabang pemilik
      ↓
Masukkan kode penyewa 4 digit
      ↓
Konfirmasi
      ↓
Status = DISEWAKAN
```

Sistem menyimpan data rental dan aktivitas unit.

---

# 📥 Alur Unit Masuk

```text
Unit Masuk
      ↓
Pilih rental aktif
      ↓
Konfirmasi
      ↓
Status rental = SELESAI
      ↓
Status unit = TERSEDIA
```

Tidak diperlukan scan QR pada saat unit masuk.

---

# 🔄 Alur Transfer

### Tahap 1 — Request

Cabang pemilik membuat request:

```text
Cabang Asal → Cabang Tujuan
```

Status:

```text
PENDING
```

### Tahap 2 — Approval

Cabang asal menyetujui transfer.

Status:

```text
APPROVED
```

### Tahap 3 — Registrasi Cabang Tujuan

Cabang tujuan:

```text
Edit Daftar Unit
      ↓
Scan QR Unit
      ↓
Sistem memvalidasi transfer
      ↓
Unit diterima
```

Status transfer:

```text
COMPLETED
```

Kepemilikan unit kemudian berubah menjadi cabang tujuan.

QR Code tetap sama.

---

# 🔒 Keamanan

Sistem menerapkan beberapa mekanisme keamanan:

### Password Hashing

Password tidak disimpan dalam bentuk plaintext.

Digunakan:

```php
password_hash()
```

dan:

```php
password_verify()
```

### Prepared Statement

Query database menggunakan PDO prepared statement untuk mengurangi risiko SQL Injection.

### CSRF Protection

Form yang melakukan perubahan data menggunakan CSRF token.

### Session Protection

Halaman yang membutuhkan login dilindungi oleh sistem authentication.

### Ownership Validation

Backend melakukan validasi terhadap:

```text
user.branch_id
```

dan:

```text
unit.owner_branch_id
```

sebelum menjalankan operasi yang berkaitan dengan unit.

### Input Validation

Data dari user divalidasi sebelum diproses.

### Output Escaping

Data yang ditampilkan ke HTML di-escape untuk mengurangi risiko XSS.

### Database Transaction

Operasi penting seperti rental dan transfer menggunakan transaction untuk menjaga konsistensi data.

---

# 📱 Penggunaan Kamera di HP

Fitur scanner menggunakan kamera browser.

Pada lingkungan localhost komputer, scanner dapat digunakan melalui:

```text
http://localhost/
```

Namun ketika sistem diakses melalui HP menggunakan alamat IP komputer, browser dapat membatasi akses kamera karena kebutuhan **secure context**.

Untuk penggunaan production melalui jaringan/Internet, gunakan:

```text
HTTPS
```

agar akses kamera dapat berjalan dengan baik.

---

# 💾 Backup Database

Backup database dapat dilakukan melalui phpMyAdmin.

Langkah:

```text
phpMyAdmin
    ↓
Pilih sistem_scan_unit
    ↓
Export
    ↓
Quick
    ↓
SQL
    ↓
Export
```

Simpan file backup dengan nama yang mudah dikenali, misalnya:

```text
2026-09-15_sistem_scan_unit.sql
```

Disarankan melakukan backup sebelum melakukan perubahan besar pada database.

---

# 🧪 Checklist Pengujian

## Authentication

```text
[✓] Register
[✓] Login
[✓] Logout
[✓] Session protection
[✓] Akses halaman tanpa login ditolak
```

## Unit

```text
[✓] Tambah unit
[✓] Menampilkan unit
[✓] Edit unit
[✓] QR unit
[✓] QR unik
[✓] Status TERSEDIA
[✓] Status DISEWAKAN
```

## Scanner

```text
[✓] Scan Unit Cek
[✓] Scan Unit Keluar
[✓] Validasi QR
[✓] Validasi cabang
[✓] Kode penyewa 4 digit
```

## Rental

```text
[✓] Unit keluar
[✓] Rental aktif
[✓] Unit masuk
[✓] Rental selesai
[✓] Status unit kembali TERSEDIA
```

## Transfer

```text
[✓] Request transfer
[✓] Status PENDING
[✓] Approval
[✓] Status APPROVED
[✓] Scan QR transfer
[✓] Status COMPLETED
[✓] Kepemilikan berpindah
[✓] QR tetap sama
```

## Permission

```text
[✓] Melihat unit cabang lain
[✓] Scan cek cabang lain
[✓] Tidak dapat edit unit cabang lain
[✓] Tidak dapat mengeluarkan unit cabang lain
[✓] Tidak dapat mengelola unit cabang lain
[✓] Hak akses berpindah setelah transfer selesai
```

## Security

```text
[✓] Password hashing
[✓] Prepared statement
[✓] CSRF protection
[✓] Session protection
[✓] Input validation
[✓] Output escaping
[✓] Backend ownership validation
[✓] Transaction pada proses penting
```

---

# 📊 Status Unit

| Status      | Keterangan                                      |
| ----------- | ----------------------------------------------- |
| `TERSEDIA`  | Unit tersedia dan dapat digunakan/disewakan     |
| `DISEWAKAN` | Unit sedang berada dalam transaksi rental aktif |

---

# 🔄 Status Transfer

| Status      | Keterangan                                    |
| ----------- | --------------------------------------------- |
| `PENDING`   | Transfer baru dibuat dan menunggu persetujuan |
| `APPROVED`  | Transfer telah disetujui cabang asal          |
| `REJECTED`  | Transfer ditolak                              |
| `COMPLETED` | Unit telah diterima dan kepemilikan berpindah |

---

# 📝 Catatan Pengembangan

Beberapa hal yang perlu diperhatikan ketika sistem dikembangkan lebih lanjut:

* Jangan mengubah `qr_token` ketika unit ditransfer.
* Jangan mengubah `owner_branch_id` secara langsung dari form edit unit.
* Validasi kepemilikan unit harus tetap dilakukan di backend.
* Jangan menyimpan password dalam bentuk plaintext.
* Jangan menghapus validasi CSRF dari form yang mengubah data.
* Jangan menampilkan error database secara langsung kepada user pada production.
* Jika sistem dipasang pada server production, gunakan HTTPS.

---

# 👨‍💻 Environment Pengembangan

Project dikembangkan dan dijalankan menggunakan:

```text
OS      : Windows
Server  : XAMPP
Backend : PHP
Database: MySQL
Editor  : Visual Studio Code
```

---

# 📌 Ringkasan Sistem

Sistem Scan Unit QR menggunakan QR Code sebagai identitas permanen setiap unit.

Setiap unit memiliki satu cabang pemilik. User dapat melihat unit dari seluruh cabang, tetapi hanya cabang pemilik yang dapat melakukan pengelolaan unit.

Proses rental menggunakan QR untuk **unit keluar**, sedangkan **unit masuk dilakukan melalui daftar rental aktif tanpa scan QR**.

Untuk perpindahan unit, sistem menggunakan mekanisme **request → approval → penerimaan melalui scan QR**. Setelah transfer selesai, kepemilikan unit berpindah ke cabang tujuan tanpa mengganti QR Code.

Dengan mekanisme tersebut, sistem dapat digunakan untuk membantu pencatatan unit rental, pemantauan status unit, pelacakan aktivitas, dan pengelolaan perpindahan unit antar cabang secara terstruktur.
