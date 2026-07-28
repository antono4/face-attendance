# Sistem Absensi dengan Face Recognition dan Payroll

Aplikasi Absensi berbasis web dengan teknologi Face Recognition untuk verifikasi kehadiran dan sistem Payroll untuk penggajian.

## 📋 Fitur

- **Autentikasi Login** - Login dengan NIP dan password
- **Face Recognition** - Pendaftaran dan verifikasi wajah menggunakan face-api.js
- **Absensi Otomatis** - Rekam kehadiran masuk dan keluar secara otomatis
- **Dashboard Admin** - Kelola pegawai dan lihat rekapitulasi absensi
- **Dashboard Pegawai** - Absensi dan lihat riwayat gaji
- **Payroll System** - Generate gaji berdasarkan total kehadiran

## 🛠️ Persiapan

### 1. Struktur Folder

```
absensi-face/
├── index.html          # Frontend SPA (HTML + CSS + JS)
├── api.php             # Backend API
├── schema.sql          # SQL Database Schema
├── README.md           # Dokumentasi
└── models/             # Folder untuk model face-api.js
    ├── ssd_mobilenetv1_model-weights_manifest.json
    ├── ssd_mobilenetv1_model-weights_blob
    ├── tiny_face_detector_model-weights_manifest.json
    ├── tiny_face_detector_model-weights_blob
    ├── face_landmark_68_model-weights_manifest.json
    ├── face_landmark_68_model-weights_blob
    ├── face_recognition_model-weights_manifest.json
    └── face_recognition_model-weights_blob
```

### 2. Download Model Face-API.js

Model diperlukan untuk face detection dan recognition. Download dari GitHub:

```bash
# Buat folder models
mkdir models

# Download menggunakan curl atau wget
cd models

# SSD MobileNet V1 (Face Detection)
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-weights_manifest.json
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ssd_mobilenetv1_model-weights_blob

# Tiny Face Detector
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_manifest.json
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/tiny_face_detector_model-weights_blob

# Face Landmark 68
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_manifest.json
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_landmark_68_model-weights_blob

# Face Recognition
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_manifest.json
wget https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/face_recognition_model-weights_blob
```

Atau download semua model sekaligus:

```bash
wget -r -np -nH --cut-dirs=2 -R "index.html*" \
    https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/ \
    -P models/
```

### 3. Setup Database MySQL

```bash
# Login ke MySQL
mysql -u root -p

# Jalankan schema SQL
SOURCE schema.sql;
```

Atau melalui command line:

```bash
mysql -u root -p < schema.sql
```

### 4. Konfigurasi Database

Buka file `api.php` dan sesuaikan konfigurasi database:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'absensi_face');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 5. Jalankan Aplikasi

Gunakan PHP built-in server:

```bash
cd /path/to/folder
php -S localhost:8000
```

Buka browser: `http://localhost:8000`

## 👤 Akun Demo

| Role | NIP | Password |
|------|-----|----------|
| Admin | 999999 | admin123 |
| Pegawai | 100001 | employee123 |
| Pegawai | 100002 | employee123 |
| Pegawai | 100003 | employee123 |

## 📱 Cara Penggunaan

### Untuk Admin:

1. Login dengan akun admin
2. **Daftarkan Wajah Pegawai:**
   - Klik "Daftarkan Wajah Pegawai"
   - Pilih pegawai dari daftar
   - Izinkan akses kamera
   - Posisikan wajah di dalam frame
   - Tunggu hingga wajah terdeteksi dan terdaftar
3. **Generate Payroll:**
   - Pilih bulan dan tahun
   - Klik "Generate Payroll"
   - Lihat hasil di popup modal
4. **Lihat Rekap Absensi:**
   - Data absensi akan otomatis tampil
   - Filter berdasarkan bulan dan tahun

### Untuk Pegawai:

1. Login dengan akun pegawai
2. **Daftarkan Wajah (jika belum):**
   - Hubungi admin untuk mendaftarkan wajah
3. **Absensi:**
   - Klik tombol "ABSEN SEKARANG"
   - Izinkan akses kamera
   - Posisikan wajah di dalam frame
   - Klik pada video untuk verifikasi
   - Wajah akan diverifikasi dengan data terdaftar
   - Absensi masuk/keluar akan tercatat otomatis
4. **Lihat Riwayat Gaji:**
   - Scroll ke bawah untuk melihat riwayat payroll

## 🔧 API Endpoints

| Action | Method | Description |
|--------|--------|-------------|
| `login` | POST | Autentikasi user |
| `register_face` | POST | Simpan face descriptor |
| `verify_face` | POST | Verifikasi kecocokan wajah |
| `absen` | POST | Catat absensi masuk/keluar |
| `get_rekap_absen` | POST | Ambil rekap absensi |
| `get_payroll` | POST | Ambil riwayat gaji |
| `generate_payroll` | POST | Generate payroll bulanan |
| `get_users` | POST | Ambil daftar users |

## 📊 Struktur Database

### Tabel `users`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK AI | ID unik |
| nip | VARCHAR(20) | Nomor Induk Pegawai |
| nama_lengkap | VARCHAR(100) | Nama lengkap |
| password | VARCHAR(255) | Password (hashed) |
| role | ENUM | admin/pegawai |
| face_descriptor | TEXT | JSON array 128-dimensi |
| gaji_pokok | DECIMAL(12,2) | Gaji pokok bulanan |

### Tabel `absensi`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK AI | ID unik |
| user_id | INT FK | Reference ke users |
| tanggal | DATE | Tanggal absensi |
| waktu_masuk | TIME | Jam masuk |
| waktu_keluar | TIME | Jam keluar |
| status | ENUM | hadir/terlambat |

### Tabel `payroll`
| Column | Type | Description |
|--------|------|-------------|
| id | INT PK AI | ID unik |
| user_id | INT FK | Reference ke users |
| bulan | INT | Bulan (1-12) |
| tahun | INT | Tahun |
| total_kehadiran | INT | Jumlah hari hadir |
| total_gaji | DECIMAL(12,2) | Total gaji |

## 🔐 Keamanan

- Password di-hash menggunakan `password_hash()` PHP
- SQL Injection prevention dengan Prepared Statements
- CORS headers dikonfigurasi
- Session storage untuk data user

## 📝 Catatan Pengembangan

- Pastikan server mendukung HTTPS untuk akses kamera
- Model face-api.js perlu di-download terpisah
- Untuk production, gunakan web server yang proper (Apache/Nginx)
- Database credentials harus di-secure dengan environment variables

## 📄 License

MIT License
