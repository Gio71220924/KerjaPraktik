<?php
// fungsi ini untuk membuat data kasus user

// membuka database
// menghubungkan ke database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expertt";


// menangkap user_id uang aktif
// Mengambil parameter yang diteruskan dari Laravel
$user_id = $argv[1];  // Parameter pertama, user_id
$case_num = $argv[2];  // Parameter kedua, case_num

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// membuat data kasus berdasarkan atribut dan atribut value pada sistem
// field/kolom tabel berupakan atribut dengan tipe data enaum bergantung pada nilai atribut pada tabel atribut value

$tabeldatakasus="case_user_".$user_id;
$tampungcreate="";

$result = $conn->query("SHOW TABLES LIKE '$tabeldatakasus'");

// periksa apakah tabel sudah ada
if ($result->num_rows > 0) {
    // jika sudah ada drop tabel
    $droptabel = $conn->query("drop table $tabeldatakasus");
} 

//proses membentuk data kasus
// baca data atribut utk user aktif
$bacaatribut = $conn->query("select * from atribut where user_id=$user_id order by goal desc");
// ambil atribut utk kasus tampung dalam array
$firstdata=1;
$nilaiatt=""; // digunakan untuk menampung nilai enum pada atribut
while ($row= $bacaatribut->fetch_assoc()) {
    $atribut_id=$row["atribut_id"];
    $atribut_name=$row["atribut_name"];
    $case_num=$row["case_num"];
    $goal=$row["goal"];

    $kolomatt=$atribut_id."_".$atribut_name;
    
    //memasukan data ke dalam array
    //membentuk array 2 dimensi
    if ($firstdata==1) {
        $casearray=array(array($atribut_id,$kolomatt,$goal,$case_num,$nilaiatt));
        $firstdata=0;
    } else {
        $pusharraydata=array($atribut_id,$kolomatt,$goal,$case_num,$nilaiatt);
        $casearray[]=$pusharraydata;
    }
}
// mengambil nilai atribut_value
// deteksi jumlah atribut
$jlhatribut=count($casearray);
//akses nilai atribut berdasarkan data pada array

for ($x = 0; $x < $jlhatribut; $x++) {
    $kolomatt_id=$casearray[$x][0];

    // lakukan query untuk mengambil atribut value
    $namafield=$casearray[$x][1];
    $attenum="";
    //menentukan isi enum dari atribut berdasarkan nilai atribut pada atribut value
    $bacaenum = $conn->query("select * from atribut_value where user_id=$user_id and atribut_id=$kolomatt_id");
    while ($row= $bacaenum->fetch_assoc())
    {
        // baca urutan nilai dari atribut
        $valueenum="'".$row["value_id"]."_".$row["value_name"]."'";
        if (strlen($attenum)==0)
        {
            $attenum=$valueenum;
        } else {
        $attenum=$attenum.",".$valueenum;
        }
        //echo "isi attenum: ".$attenum."<br>";
    }

    // tampung di array yang menampung struktur data kasus
    $casearray[$x][4]=$attenum;
    $tipedata=" enum(".$attenum."), ";
    $tampungcreate=$tampungcreate.$namafield.$tipedata;
}
echo "isi tampung create: ".$tampungcreate."<br>";
//membentuk tabel untuk menampung data kasus

$sql = "CREATE table $tabeldatakasus (
        `case_id` int(11) primary key NOT NULL AUTO_INCREMENT,
        $tampungcreate
        `user_id` int(11) NOT NULL,
        `case_num` int(11) NOT NULL
        )";

if ($conn->query($sql) === TRUE) {
    echo "create table successfully";
} else {
    echo "Error creating tabel: " . $conn->error;
}

// copy struktur data kasus ke data untuk pengujian
$tabeltestkasus="test_case_user_".$user_id;

$result = $conn->query("SHOW TABLES LIKE '$tabeltestkasus'");

// periksa apakah tabel sudah ada
if ($result->num_rows > 0) {
    // jika sudah ada drop tabel
    $droptabel = $conn->query("drop table $tabeltestkasus");
} 

// Buat tabel baru berdasarkan tabel kasus asli
$createtestdata = "CREATE TABLE $tabeltestkasus LIKE $tabeldatakasus";
if ($conn->query($createtestdata) === TRUE) {
    echo "Tabel test kasus berhasil dibuat. <br>";

    // Tambahkan kolom algoritma setelah tabel dibuat
    $addColumn = "ALTER TABLE $tabeltestkasus ADD COLUMN algoritma VARCHAR(50) NOT NULL DEFAULT ''";
    
    if ($conn->query($addColumn) === TRUE) {
        echo "Kolom algoritma berhasil ditambahkan.";
    } else {
        echo "Error menambahkan kolom: " . $conn->error;
    }
} else {
    echo "Error membuat tabel: " . $conn->error;
}

$conn->close();
?>