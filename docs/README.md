# Dokumentasi Utama - Sistem Klasifikasi SVM

Website sistem pakar berbasis Laravel untuk menggunakan algoritma Support Vector Machine (SVM) dengan dukungan multi-kernel, multi-class, dan antarmuka web.

## Daftar Isi

- Ringkasan
- Fitur Utama
- Teknologi
- Instalasi
- Konfigurasi
- Penggunaan Cepat
- Struktur Direktori
- Dokumentasi Terkait
- Lisensi dan Dukungan

## Ringkasan

Aplikasi ini menyediakan alur lengkap mulai dari definisi atribut, input data training, pelatihan model SVM, hingga prediksi dan monitoring hasil. Dirancang untuk kebutuhan praktikum atau proyek produksi ringan dengan fokus pada kemudahan penggunaan.

### Kapabilitas

- Multi-kernel SVM: Linear/SGD, RBF, Sigmoid
- Multi-class classification dengan confidence score
- Penyimpanan model dalam format JSON
- Riwayat training, inferensi, dan monitoring kinerja

## Fitur Utama

1. Manajemen Data
   - Definisi atribut dengan tipe numerik atau kategorikal
   - Import atau input manual data training
   - Validasi dan preprocessing dasar

2. Training Model SVM
   - Kernel Linear (SGD), RBF, dan Sigmoid
   - Parameter: epochs, learning rate, regularization, test ratio
   - Monitoring progres dan hasil training

3. Prediksi dan Inferensi
   - Form prediksi single instance
   - Batch prediction melalui modul Consultation
   - Confidence score per kelas dan riwayat inferensi

4. Visualisasi dan Reporting
   - Distribusi kelas
   - Riwayat training dengan status dan execution time

5. User Management
   - Multi-user dengan peran dasar (Admin/User)
   - Aktivasi/deaktivasi user

## Teknologi

- Backend: PHP 8.2+, Laravel 11.9
- Frontend: Blade Templates, Vite, JavaScript/jQuery
- Database: MySQL/MariaDB
- Machine Learning: Rubix ML dan implementasi SVM kustom berbasis SGD

## Instalasi

### Persyaratan Sistem
- PHP 8.2+
- Composer
- MySQL atau MariaDB
- Node.js dan npm

### Langkah Instalasi

1. Clone repository
   ```bash
   git clone <repository-url>
   cd KerjaPraktik
   ```

2. Install dependencies
   ```bash
   composer install
   npm install
   ```

3. Setup environment
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Konfigurasi database di `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nama_database
   DB_USERNAME=username
   DB_PASSWORD=password
   ```

5. Migrasi database
   ```bash
   php artisan migrate
   ```

6. Build assets
   ```bash
   npm run build
   ```

7. Jalankan server
   ```bash
   php artisan serve
   ```

Aplikasi dapat diakses di `http://localhost:8000`.

## Konfigurasi

Tambahkan pengaturan SVM di `.env`:

```env
# Path ke script SVM (opsional)
SVM_SCRIPT=scripts/decision-tree/SVM.php
SVM_INFER_SCRIPT=scripts/decision-tree/SVMInfer.php

# Simpan model setelah training
SVM_SAVE_MODEL=1

# Proporsi data testing
SVM_TEST_RATIO=0.3

# Decision threshold binary
SVM_THRESHOLD=0.0

# Seed split (opsional)
SVM_SPLIT_SEED=42
```

Lokasi penyimpanan model:
- `storage/app/svm/`
- `svm_models/` (fallback)

Pastikan direktori tersebut writable oleh PHP.

## Penggunaan Cepat

1. Login
   - User: `http://localhost:8000/`
   - Admin: `http://localhost:8000/admin`

2. Setup data
   - Definisikan atribut di menu Attribute Management (tandai satu sebagai goal)
   - Input data training di Generate Case (manual atau generate)

3. Training model (web)
   - Masuk ke Support Vector Machine
   - Pilih kernel: `sgd`, `rbf:D=128:gamma=0.25`, atau `sigmoid:D=128:scale=1.0:coef0=0.0`
   - Klik *Train Model* dan pantau hasil

4. Training model (CLI)
   ```bash
   php scripts/decision-tree/SVM.php <user_id> <case_num> [kernel] [options]
   # Contoh:
   php scripts/decision-tree/SVM.php 1 1 rbf:D=128:gamma=0.5 --epochs=50 --lambda=0.0001
   ```

5. Prediksi
   - Gunakan kernel yang sama dengan saat training
   - Isi nilai atribut pada form prediksi, lalu klik *Predict*
   - Untuk batch prediction, gunakan menu Consultation

## Struktur Direktori

```
KerjaPraktik/
|-- app/
|   |-- Http/Controllers/SVMController.php
|   `-- Models/
|-- docs/
|   |-- README.md
|   |-- INDEX.md
|   `-- user-guide.md
|-- scripts/
|   `-- decision-tree/
|       |-- SVM.php
|       `-- SVMInfer.php
|-- storage/app/svm/
`-- svm_models/
```

## Dokumentasi Terkait

- `docs/INDEX.md` - Indeks dokumentasi dan tautan cepat
- `docs/user-guide.md` - Panduan pengguna aplikasi
- `README.md` (root) - Quick start singkat

## Lisensi dan Dukungan

- Lisensi: MIT (sesuai metadata proyek)
- Dokumentasi: folder `docs/`
- Bantuan: buat issue di repository atau email `contact@yourdomain.com`

---

**Versi**: 1.0.0  
**Terakhir diperbarui**: Desember 2025
