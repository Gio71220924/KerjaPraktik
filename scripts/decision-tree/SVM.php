<!-- Kode buat model SVM nya -->

<?php
//Hubungkan ke Database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expertt";

// Buat Koneksi
$conn = new mysqli($servername, $username, $password, $dbname, 3307);
// Cek Koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    }
// menangkap user_id yang aktif
$user_id = $argv[1];
$case_num = $argv[2];
$awal = microtime(true);


