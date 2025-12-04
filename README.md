# Sistem Klasifikasi SVM - DutaShell

Aplikasi berbasis Laravel untuk klasifikasi data menggunakan Support Vector Machine (SVM) dengan dukungan multi-kernel, multi-class, serta antarmuka web dan CLI.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.9-red)](https://laravel.com)

## Fitur Utama

- Multi-kernel SVM: Linear/SGD, RBF, dan Sigmoid dengan parameter yang dapat dikonfigurasi
- Multi-class classification dengan riwayat training dan inferensi
- Antarmuka web untuk manajemen atribut, data, training, dan prediksi
- Confidence score pada setiap prediksi dan penyimpanan model dalam format JSON
- Dukungan CLI untuk training dan inferensi

## Persyaratan

- PHP 8.2+
- Composer
- MySQL atau MariaDB
- Node.js dan npm

## Instalasi Singkat

```bash
# Clone repository
git clone <repository-url>
cd KerjaPraktik

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Konfigurasi database di .env
# DB_DATABASE=nama_database
# DB_USERNAME=username
# DB_PASSWORD=password

# Jalankan migrasi
php artisan migrate

# Build assets
npm run build

# Jalankan server
php artisan serve
```

Akses aplikasi di `http://localhost:8000`.

## Konfigurasi SVM (.env)

```env
# Path ke script training (opsional)
SVM_SCRIPT=scripts/decision-tree/SVM.php

# Path ke script inferensi (opsional)
SVM_INFER_SCRIPT=scripts/decision-tree/SVMInfer.php

# Simpan model setelah training (1=yes, 0=no)
SVM_SAVE_MODEL=1

# Proporsi data testing (0.0-0.9)
SVM_TEST_RATIO=0.3

# Decision threshold untuk klasifikasi binary
SVM_THRESHOLD=0.0

# Seed untuk train/test split (opsional)
SVM_SPLIT_SEED=42
```

## Cara Menggunakan (Ringkas)

1. Setup atribut di menu Attribute Management dan tandai satu sebagai goal/target.
2. Masukkan data training melalui Generate Case (manual atau generate otomatis).
3. Training model di menu Support Vector Machine, pilih kernel (sgd, rbf, sigmoid), lalu klik *Train Model*.
4. Prediksi menggunakan kernel yang sama, isi nilai atribut, lalu klik *Predict* untuk melihat hasil dan confidence score.

## Dokumentasi

- `docs/INDEX.md` - Peta dokumentasi dan tautan cepat.
- `docs/README.md` - Ringkasan sistem, instalasi, dan konfigurasi.
- `docs/user-guide.md` - Panduan penggunaan aplikasi.

## Testing

```bash
# Jalankan seluruh test
php artisan test

# Test tertentu
php artisan test tests/Feature/SVMControllerTest.php
```

## Teknologi

- Backend: PHP 8.2, Laravel 11.9
- Database: MySQL/MariaDB
- ML: Rubix ML dan implementasi SVM kustom
- Frontend: Blade Templates, Vite, JavaScript

## Dukungan

- Dokumentasi: folder `docs/`
- Issue: buka tiket di repository
- Email: contact@yourdomain.com

---

**Versi**: 1.0.0  
**Terakhir diperbarui**: Desember 2025
