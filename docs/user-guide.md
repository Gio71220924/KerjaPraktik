# Panduan Pengguna

Panduan ini menjelaskan cara menggunakan Sistem Klasifikasi SVM mulai dari login, menyiapkan data, melatih model, hingga melakukan prediksi.

## Daftar Isi

- Pendahuluan
- Login dan Akses
- Manajemen Atribut
- Manajemen Nilai Atribut
- Manajemen Data
- Training Model
- Prediksi
- Monitoring dan Reporting
- Best Practices
- Tips & Tricks
- FAQ

## Pendahuluan

Support Vector Machine (SVM) adalah algoritma machine learning untuk klasifikasi. Aplikasi ini mendukung:
- Binary dan multi-class classification
- Kernel Linear/SGD, RBF, dan Sigmoid
- Confidence score untuk setiap prediksi

Workflow umum: definisikan atribut -> input data -> training -> prediksi -> evaluasi.

## Login dan Akses

  - buka `http://localhost:8000`

## Manajemen Atribut

### Membuat Atribut Baru
1. Navigasi ke menu **Attribute**.
2. Klik **Add Attribute**.
3. Isi form:
   - Attribute Name (contoh: `Age`, `Income`, `Credit_Status`)
   - Attribute Type: `Numeric` atau `Categorical`
   - Goal: centang jika atribut adalah target/label yang diprediksi
4. Klik **Save**.

Catatan: hanya satu atribut yang boleh menjadi **Goal**.

### Mengedit Atribut
1. Pada halaman Attribute, klik ikon **Edit** pada baris yang diinginkan.
2. Ubah nilai yang diperlukan.
3. Klik **Update**.

Perubahan pada atribut goal akan memengaruhi seluruh model yang sudah dilatih.

### Menghapus Atribut
1. Klik ikon **Delete** pada baris atribut.
2. Konfirmasi penghapusan.

Menghapus atribut akan menghapus data terkait pada dataset.

## Manajemen Nilai Atribut

Untuk atribut kategorikal, definisikan nilai yang valid:
1. Navigasi ke menu **Attribute Value**.
2. Klik **Add Value**.
3. Pilih atribut yang akan diberi nilai.
4. Masukkan nilai (contoh: untuk `Credit_Status` -> `Approved`, `Rejected`, `Pending`).
5. Klik **Save**.

## Manajemen Data

### Input Data Training

**Input manual**
1. Navigasi ke **Generate Case**.
2. Klik **New Case**.
3. Isi nilai setiap atribut (numeric: angka, categorical: pilih dari dropdown).
4. Klik **Save**.

**Generate otomatis**
1. Di **Generate Case**, klik **Generate**.
2. Masukkan jumlah data yang ingin dibuat.
3. Sistem membuat data acak berdasarkan definisi atribut (cocok untuk testing).

### Melihat atau Mengubah Data
1. Buka tabel data di **Generate Case**.
2. Gunakan **Search/Filter/Sort** sesuai kebutuhan.
3. Klik ikon **Edit** untuk memperbarui baris, atau **Delete** untuk menghapus.

## Training Model

### Prasyarat
- Minimal satu atribut ditandai sebagai goal.
- Data training berisi minimal 2 kelas berbeda pada atribut goal.
- Disarankan minimal 10-20 baris data untuk hasil awal.

### Memilih Kernel
- `sgd`: baseline cepat untuk data yang relatif linear.
- `rbf:D=1024:gamma=0.25`: untuk pola non-linear; ubah `D` atau `gamma` jika perlu.
- `sigmoid:D=1024:scale=1.0:coef0=0.0`: untuk pola dengan batas keputusan sigmoid-like.

### Training via Web
1. Buka **Support Vector Machine**.
2. Pilih kernel yang diinginkan atau masukkan string kernel kustom.
3. Klik **Train Model**.
4. Tunggu hingga status berhasil dan catat hasil metrik.

Contoh keluaran:
```
Training berhasil
- Total samples: 100 (Train 70 / Test 30)
- Training Accuracy: 95.7%
- Test Accuracy: 93.3%
- Model saved: storage/app/svm/svm_user_1_rbf.json
```

### Training via CLI
```bash
php scripts/decision-tree/SVM.php <user_id> <case_num> [kernel] [options]
# Contoh:
php scripts/decision-tree/SVM.php 1 1 rbf:D=1024:gamma=0.5 --epochs=50 --lambda=0.0001
```

Opsi umum:
- `--epochs=<int>`
- `--lambda=<float>`
- `--eta0=<float>`
- `--test_ratio=<float>`

## Prediksi

### Prediksi Single Instance
1. Di halaman **Support Vector Machine**, buka form prediksi.
2. Pilih kernel yang sama dengan model yang dilatih.
3. Isi nilai untuk setiap atribut (kecuali goal).
4. Klik **Predict**.
5. Lihat hasil kelas, confidence score, dan detail model.

### Batch Prediction (Consultation)
1. Navigasi ke **Consultation**.
2. Pilih **Action Type: Support Vector Machine**.
3. Pilih kernel.
4. Masukkan data test atau import dari file.
5. Proses akan melakukan training lalu prediksi untuk seluruh data.

## Monitoring dan Reporting

- **Training History**: status berhasil/gagal, execution time, lokasi model, timestamp.
- **Inference History**: hasil prediksi, confidence, waktu eksekusi.
- **Class Distribution**: pantau sebaran kelas untuk mendeteksi class imbalance.

## Best Practices

**Data**
- Pastikan atribut goal terisi lengkap.
- Jaga keseimbangan kelas jika memungkinkan.
- Bersihkan outlier dan nilai kosong sebelum training.

**Model**
- Mulai dengan `sgd` sebagai baseline, gunakan `rbf` untuk pola kompleks.
- Simpan model setelah training (`SVM_SAVE_MODEL=1`).
- Catat parameter yang menghasilkan performa terbaik.

**Prediksi**
- Gunakan kernel yang sama dengan saat training.
- Perhatikan confidence score; waspadai prediksi dengan confidence rendah.
- Retrain model secara berkala ketika ada data baru.

## Tips & Tricks

- Meningkatkan akurasi: tambah data berkualitas, lakukan feature engineering, coba parameter kernel berbeda.
- Optimasi kecepatan: gunakan kernel `sgd`, kurangi dimensi `D` untuk RBF/Sigmoid, atau sampling data besar.
- Debugging: pastikan data input berada pada rentang yang mirip dengan data training dan cek apakah kelas goal mencukupi.

## FAQ

**Berapa minimum data yang dibutuhkan?**  
Minimal 20 baris, namun 100+ disarankan untuk model yang stabil.

**Kernel mana yang harus dipilih?**  
Mulai dari `sgd`; gunakan `rbf` bila akurasi kurang; coba `sigmoid` untuk pola khusus.

**Apakah mendukung regresi?**  
Saat ini hanya klasifikasi; regresi dapat ditambahkan di rilis berikutnya.

**Bagaimana jika model tidak ditemukan?**  
Pastikan `SVM_SAVE_MODEL=1` saat training dan cek direktori `storage/app/svm/` atau `svm_models/`.
