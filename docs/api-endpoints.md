# API Endpoints Documentation - Sistem Klasifikasi SVM

Dokumentasi ini menjelaskan semua endpoint API yang tersedia dalam Sistem Klasifikasi SVM - DutaShell, termasuk fungsi masing-masing endpoint, parameter yang dibutuhkan, dan contoh penggunaannya.

## Daftar Endpoint

### Authentication

#### `GET /` - Halaman Login User
**Deskripsi**: Menampilkan halaman login untuk user biasa  
**Metode**: `GET`  
**Akses**: Publik  
**Fungsi**: Menampilkan form login untuk user biasa  

#### `POST /` - Proses Login User
**Deskripsi**: Memproses permintaan login user  
**Metode**: `POST`  
**Akses**: Publik  
**Fungsi**: Mengautentikasi user dan mengarahkan ke dashboard  

#### `GET /admin` - Halaman Login Admin
**Deskripsi**: Menampilkan halaman login untuk admin  
**Metode**: `GET`  
**Akses**: Publik  
**Fungsi**: Menampilkan form login untuk admin  

#### `POST /admin` - Proses Login Admin
**Deskripsi**: Memproses permintaan login admin  
**Metode**: `POST`  
**Akses**: Publik  
**Fungsi**: Mengautentikasi admin dan mengarahkan ke dashboard admin  

#### `GET /registration` - Halaman Registrasi
**Deskripsi**: Menampilkan form registrasi user baru  
**Metode**: `GET`  
**Akses**: Publik  
**Fungsi**: Menampilkan form untuk registrasi user baru  

#### `POST /registration` - Proses Registrasi
**Deskripsi**: Memproses permintaan registrasi user baru  
**Metode**: `POST`  
**Akses**: Publik  
**Fungsi**: Menyimpan data user baru ke database  

#### `POST /logout` - Logout User
**Deskripsi**: Memproses permintaan logout  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Menghapus session user dan mengarahkan ke halaman login  

---

### User Management (Admin)

#### `GET /user` - Daftar Semua User
**Deskripsi**: Menampilkan daftar semua user  
**Metode**: `GET`  
**Akses**: Protected (admin)  
**Fungsi**: Menampilkan daftar user beserta status aktif/tidak aktif  

#### `PUT /user/{id}/activate` - Aktifkan User
**Deskripsi**: Mengaktifkan user yang dinonaktifkan  
**Metode**: `PUT`  
**Parameter**: `id` (ID user yang akan diaktifkan)  
**Akses**: Protected (admin)  
**Fungsi**: Mengaktifkan akun user sehingga bisa login kembali  

#### `PUT /user/{id}/inactivate` - Nonaktifkan User
**Deskripsi**: Menonaktifkan user  
**Metode**: `PUT`  
**Parameter**: `id` (ID user yang akan dinonaktifkan)  
**Akses**: Protected (admin)  
**Fungsi**: Menonaktifkan akun user sehingga tidak bisa login  

---

### Profile Management

#### `GET /profile` - Edit Profil User
**Deskripsi**: Menampilkan form edit profil  
**Metode**: `GET`  
**Akses**: Protected (user)  
**Fungsi**: Menampilkan form untuk mengedit informasi profil user  

#### `POST /profile/update` - Update Profil User
**Deskripsi**: Memproses update profil user  
**Metode**: `POST`  
**Akses**: Protected (user)  
**Fungsi**: Menyimpan perubahan informasi profil user  

#### `GET /profile/admin` - Edit Profil Admin
**Deskripsi**: Menampilkan form edit profil admin  
**Metode**: `GET`  
**Akses**: Protected (admin)  
**Fungsi**: Menampilkan form untuk mengedit informasi profil admin  

#### `POST /profile/admin/update` - Update Profil Admin
**Deskripsi**: Memproses update profil admin  
**Metode**: `POST`  
**Akses**: Protected (admin)  
**Fungsi**: Menyimpan perubahan informasi profil admin  

---

### Attribute Management

#### `GET /attributte` - Daftar Semua Atribut
**Deskripsi**: Menampilkan daftar semua atribut  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan daftar atribut yang telah dibuat oleh user  

#### `GET /attributte/create` - Form Tambah Atribut
**Deskripsi**: Menampilkan form untuk menambah atribut baru  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk membuat atribut baru  

#### `POST /attributte` - Simpan Atribut Baru
**Deskripsi**: Menyimpan data atribut baru  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan atribut baru ke database  

#### `GET /attributte/{id}/edit` - Form Edit Atribut
**Deskripsi**: Menampilkan form edit atribut  
**Metode**: `GET`  
**Parameter**: `id` (ID atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk mengedit atribut tertentu  

#### `PUT /attributte/{id}` - Update Atribut
**Deskripsi**: Memperbarui data atribut  
**Metode**: `PUT`  
**Parameter**: `id` (ID atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan perubahan data atribut ke database  

#### `DELETE /attributte/{id}` - Hapus Atribut
**Deskripsi**: Menghapus atribut  
**Metode**: `DELETE`  
**Parameter**: `id` (ID atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menghapus atribut dari database  

---

### Attribute Value Management

#### `GET /attributteValue` - Daftar Semua Nilai Atribut
**Deskripsi**: Menampilkan daftar semua nilai atribut  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan daftar nilai-nilai yang telah ditentukan untuk atribut kategorikal  

#### `GET /attributteValue/create` - Form Tambah Nilai Atribut
**Deskripsi**: Menampilkan form untuk menambah nilai atribut baru  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk membuat nilai baru untuk atribut  

#### `POST /attributteValue` - Simpan Nilai Atribut Baru
**Deskripsi**: Menyimpan data nilai atribut baru  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan nilai atribut baru ke database  

#### `GET /attributteValue/{id}/edit` - Form Edit Nilai Atribut
**Deskripsi**: Menampilkan form edit nilai atribut  
**Metode**: `GET`  
**Parameter**: `id` (ID nilai atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk mengedit nilai atribut tertentu  

#### `PUT /attributteValue/{id}` - Update Nilai Atribut
**Deskripsi**: Memperbarui data nilai atribut  
**Metode**: `PUT`  
**Parameter**: `id` (ID nilai atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan perubahan data nilai atribut ke database  

#### `DELETE /attributteValue/{id}` - Hapus Nilai Atribut
**Deskripsi**: Menghapus nilai atribut  
**Metode**: `DELETE`  
**Parameter**: `id` (ID nilai atribut)  
**Akses**: Protected (harus login)  
**Fungsi**: Menghapus nilai atribut dari database  

---

### Data Generation (Case Management)

#### `GET /generateCase` - Form Generate Case
**Deskripsi**: Menampilkan form untuk generate atau manage data  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan interface untuk menambah, edit, atau generate data training secara manual atau otomatis  

#### `POST /generateCase` - Generate Case Otomatis
**Deskripsi**: Generate data training secara otomatis  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Membuat data training baru secara otomatis berdasarkan definisi atribut  

#### `POST /generateCase/store` - Simpan Data Manual
**Deskripsi**: Menyimpan data training manual  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan data training yang diinput manual ke database  

#### `GET /generateCase/new` - Form Data Baru
**Deskripsi**: Menampilkan form untuk menambah data baru  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form kosong untuk input data training manual  

#### `GET /generateCase/{case_id}/edit` - Edit Data Tertentu
**Deskripsi**: Menampilkan form edit data  
**Metode**: `GET`  
**Parameter**: `case_id` (ID data)  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk mengedit data training tertentu  

#### `PUT /generateCase/{case_id}` - Update Data
**Deskripsi**: Memperbarui data training  
**Metode**: `PUT`  
**Parameter**: `case_id` (ID data)  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan perubahan data training ke database  

#### `DELETE /generateCase/{case_id}` - Hapus Data
**Deskripsi**: Menghapus data training  
**Metode**: `DELETE`  
**Parameter**: `case_id` (ID data)  
**Akses**: Protected (harus login)  
**Fungsi**: Menghapus data training dari database  

---

### Support Vector Machine (SVM) Endpoints

#### `GET /SupportVectorMachine` - Halaman SVM
**Deskripsi**: Menampilkan halaman utama SVM  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan antarmuka untuk training dan prediksi SVM, termasuk form input, riwayat training, dan distribusi kelas  

#### `POST /SupportVectorMachine/generate` - Training Model SVM
**Deskripsi**: Melatih model SVM  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Parameter**: 
- `kernel` (string) - jenis kernel SVM (sgd, rbf, sigmoid)  
**Fungsi**: Melatih model SVM menggunakan data training yang tersedia dan menyimpan hasilnya, termasuk akurasi, confusion matrix, dan metadata lainnya  

#### `POST /SupportVectorMachine/store` - Training dan Prediksi Sekaligus
**Deskripsi**: Training model dan melakukan prediksi sekaligus  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Parameter**:
- `kernel` (string) - jenis kernel SVM
- `attr` (array) - array nilai atribut untuk prediksi  
**Fungsi**: Melakukan training model SVM dari data training, kemudian melakukan prediksi terhadap input tertentu dan menyimpan hasil ke riwayat inferensi  

---

### Consultation (Batch Processing)

#### `GET /consultation` - Form Consultation
**Deskripsi**: Menampilkan form consultation  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan interface untuk batch processing dan analisis data  

#### `POST /consultation/store` - Simpan Consultation
**Deskripsi**: Menyimpan data consultation  
**Metode**: `POST`  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan data consultation ke database  

#### `GET /consultation/new` - Form Consultation Baru
**Deskripsi**: Menampilkan form untuk consultation baru  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form kosong untuk input consultation baru  

#### `GET /consultation/{case_id}/edit` - Edit Consultation Tertentu
**Deskripsi**: Menampilkan form edit consultation  
**Metode**: `GET`  
**Parameter**: `case_id` (ID consultation)  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan form untuk mengedit consultation tertentu  

#### `PUT /consultation/{case_id}` - Update Consultation
**Deskripsi**: Memperbarui data consultation  
**Metode**: `PUT`  
**Parameter**: `case_id` (ID consultation)  
**Akses**: Protected (harus login)  
**Fungsi**: Menyimpan perubahan data consultation ke database  

#### `DELETE /consultation/{case_id}` - Hapus Consultation
**Deskripsi**: Menghapus data consultation  
**Metode**: `DELETE`  
**Parameter**: `case_id` (ID consultation)  
**Akses**: Protected (harus login)  
**Fungsi**: Menghapus data consultation dari database  

---

### Inference & History (Riwayat Prediksi)

#### `GET /inference` - Halaman Inference
**Deskripsi**: Menampilkan halaman inference  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan interface untuk analisis inference dan riwayat prediksi  

#### `GET /inference/{user_id}/{case_num}` - Generate Inference
**Deskripsi**: Generate inference untuk user dan case tertentu  
**Metode**: `GET`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan proses inference berdasarkan data yang tersedia dan menampilkan hasilnya  

#### `POST /inference/{user_id}/{case_num}` - Generate Inference (POST)
**Deskripsi**: Membuat inference untuk user dan case tertentu  
**Metode**: `POST`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan proses inference dan menyimpan hasil ke database  

---

### Decision Tree & Rule Management

#### `GET /tree` - Tampilkan Tree
**Deskripsi**: Menampilkan struktur decision tree  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menampilkan representasi visual atau teks dari decision tree  

#### `GET /tree/generate` - Generate Tree
**Deskripsi**: Generate decision tree dari data  
**Metode**: `GET`  
**Akses**: Protected (harus login)  
**Fungsi**: Menghasilkan decision tree berdasarkan data training yang tersedia  

#### `GET /rule/{user_id}/{case_num}` - Generate Rule
**Deskripsi**: Generate aturan inference  
**Metode**: `GET`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menghasilkan aturan-aturan inference berdasarkan data training  

---

### Forward Chaining & Backward Chaining

#### `GET /forwardChaining/{user_id}/{case_num}` - Generate Forward Chaining
**Deskripsi**: Generate proses forward chaining  
**Metode**: `GET`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan proses forward chaining untuk inferensi  

#### `POST /forwardChaining/{user_id}/{case_num}` - Generate Forward Chaining (POST)
**Deskripsi**: Proses forward chaining  
**Metode**: `POST`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan dan menyimpan hasil proses forward chaining  

#### `GET /backwardChaining/{user_id}/{case_num}` - Generate Backward Chaining
**Deskripsi**: Generate proses backward chaining  
**Metode**: `GET`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan proses backward chaining untuk inferensi  

#### `POST /backwardChaining/{user_id}/{case_num}` - Generate Backward Chaining (POST)
**Deskripsi**: Proses backward chaining  
**Metode**: `POST`  
**Parameter**:
- `user_id` (integer) - ID user
- `case_num` (integer) - Nomor kasus  
**Akses**: Protected (harus login)  
**Fungsi**: Menjalankan dan menyimpan hasil proses backward chaining  

---

## Keterangan Tambahan

### Format Respons API
Sebagian besar endpoint mengembalikan redirect dengan pesan sukses atau error melalui session flash data. Beberapa endpoint mengembalikan view dengan data yang sesuai.

### Validasi
Semua endpoint yang menerima input data akan melakukan validasi sebelum menyimpan ke database. Kesalahan validasi akan ditampilkan sebagai pesan error.

### Akses Data
Setiap user hanya dapat mengakses data miliknya sendiri. Sistem menggunakan filter berdasarkan `user_id` untuk memastikan isolasi data antar user.

### Struktur Database
- Data training disimpan dalam tabel `case_user_{user_id}`
- Riwayat training SVM disimpan dalam tabel `svm_user_{user_id}`
- Riwayat inferensi disimpan dalam tabel `inferensi_user_{user_id}`
- Data pengujian disimpan dalam tabel `test_case_user_{user_id}`

---
**Tanggal Pembuatan**: 10 Desember 2025