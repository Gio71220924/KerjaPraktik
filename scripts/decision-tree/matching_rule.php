<?php
// fungsi ini untuk melakukan inferensi melalui pencocokan rule

// membuka database
// menghubungkan ke database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expertt";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// menangkap user_id yang aktif
$user_id = $argv[1];
$case_num = $argv[2];
$awal = microtime(true);
//create tabel temp utk menyimpan inferensi
$tabelinferensi="inferensi_user_".$user_id;

$result = $conn->query("SHOW TABLES LIKE '$tabelinferensi'");

// periksa apakah tabel sudah ada
if ($result->num_rows > 0) {
    // jika sudah ada hapus isi data tabel
    $trunrule = $conn->query("truncate table $tabelinferensi");
} else {
    //membentuk tabel untuk proses
    $sql = "CREATE table $tabelinferensi (
            `inf_id` int(11) primary key NOT NULL AUTO_INCREMENT,
            `case_id` varchar(100) NOT NULL,
            `case_goal` varchar(200) NOT NULL,
            `rule_id` varchar(100) NOT NULL,
            `rule_goal` varchar(200) NOT NULL,
            `match_value` decimal(5,4) NOT NULL,
            `cocok` enum('1','0') NOT NULL,
            `user_id` int(11) NOT NULL,
            `waktu` DECIMAL(16,14) NOT NULL DEFAULT 0
            )";
    
    if ($conn->query($sql) === TRUE) {
        echo "create table successfully";
    } else {
        echo "Error creating tabel: " . $conn->error;
    }
}
// buat array untuk menampung hasil inferensi
// array terdiri dari data rule dan merekam jumlah kecocokan fakta dari kasus dengan rule
$firstdata=1;
// membuka tabel rule dan memindahkan ke dalam array
$tabelrule="rule_user_".$user_id;
$ruledata = $conn->query("select * from $tabelrule");
while ($row= $ruledata->fetch_assoc()) {
    $rule_id=$row["rule_id"];
    $if_part=$row["if_part"];
    $then_part=$row["then_part"];
    //menghitung jumlah fakta setiap rule dengan medeteksi banyaknya karakter "=" pada if_part 
    // pendeteksian karakter menggunakan fungsi substr_count()
    $jumlahfakta=substr_count($if_part,"=");
    // menambahkan jumlah fakta yang cocok
    $faktacocok=0;

    //membentuk array 2 dimensi
    if ($firstdata==1) {
        $matcharray=array(array($rule_id,$if_part,$then_part,$jumlahfakta,$faktacocok));
        $firstdata=0;
    } else {
        $pusharraydata=array($rule_id,$if_part,$then_part,$jumlahfakta,$faktacocok);
        $matcharray[]=$pusharraydata;
    }
}
// hitung data pada array
$jlhmatcharray=count($matcharray);

// melakukan inferensi melalui matching rule antara kasus dengan rule yang dibentuk dari C45
// 1. Mengakses data kasus 
$tabelkasus="test_case_user_".$user_id;
$firstdata=1;
$casedata = "SELECT * FROM $tabelkasus WHERE algoritma = 'Matching Rule'";
if ($result=mysqli_query($conn,$casedata)){
    // menentukan banyak atribut
    $fieldcount=mysqli_num_fields($result);
    //menentukan nama atribut goal yang dideteksi pada posisi ke - dari belakang
    $fieldgoal=$result -> fetch_field_direct($fieldcount-3);
    $namaatributgoal= $fieldgoal -> name;
    if (empty($namaatributgoal)) {
        $namaatributgoal = "Goal belum ditentukan"; // Set nilai default sementara
    }
    // menentukan jumlah fakta dengan rumus: jumlah atribut-4
    $factnum=$fieldcount-4;
    //echo "jumlah fakta: ".$factnum."<br>";
    //menyimpan nama semua fakta
    $i=1;
    for ($i; $i<$factnum+1; $i++)
    {
        $fieldatt=$result -> fetch_field_direct($i);
        $namaatributfact= $fieldatt -> name;
        //buat array penampung atribut
        if ($firstdata==1) {
            $factnamearray=array($namaatributfact);
            $firstdata=0;
        } else {
            $pusharrayfact=$namaatributfact;
            $factnamearray[]=$pusharrayfact;
        }
    }
}

// lakukan penghitungan fakta yang cocok antara case dengan rule
$matchquery = $conn->query($casedata);
while ($row= $matchquery->fetch_assoc()) {
    // mengambil atribut kasus
    $case_id=$row["case_id"];
    $goalstt=$namaatributgoal."=".$row["$namaatributgoal"];
    //echo $case_id." pernyataan goal: ".$goalstt."<br>";
    // memastikan nilai kecocokan setiap rule 0
    for ($r = 0; $r < $jlhmatcharray; $r++) {
        $matcharray[$r][4]=0;
    }

    for ($brs=0; $brs<$factnum; $brs++)
    {
        $factnamestt=$factnamearray[$brs];
        $factstt=$factnamestt."=".$row["$factnamestt"];

        // memeriksa kesesuaian dengan rule
        for ($x = 0; $x < $jlhmatcharray; $x++) {
              $bagianifarray=$matcharray[$x][1];
              $cocok=strstr($bagianifarray,$factstt);
              if (strlen($cocok)>0)
              {
                // berarti ditemukan; maka tambah faktacocok dengan 1
                $bykcocok=$matcharray[$x][4];
                $jlhcocok=$bykcocok+1;
                $matcharray[$x][4]=$jlhcocok;
              }
        }
        //echo "pernyataan fakta: ".$factstt."<br>";
    }

    //pilih persentase kecocokan paling baik utk setiap rule
    $tercocok=0;
    for ($y = 0; $y < $jlhmatcharray; $y++) {
        $jumlahfact=$matcharray[$y][3];
        $jumlahcocok=$matcharray[$y][4];
        $persencocok=($jumlahcocok/$jumlahfact);
        if ($persencocok>$tercocok)
        {
          // simpan no baris array yang paling cocok pertama
          $bariscocok=$y;
          $tercocok=$persencocok;
        }
    }
    $rule_id=$matcharray[$bariscocok][0];
    $rule_goal=$matcharray[$bariscocok][2];
    $match_value=$tercocok;
    if ($goalstt == $rule_goal) {
        $cocok = '1'; // Simpan sebagai string
    } else {
        $cocok = '0'; // Simpan sebagai string
    }
    
    $akhir = microtime(true);
    $lama = $akhir - $awal;
    // Simpan hasil kecocokan fakta terbesar dalam tabel inferensi
    $sql = "INSERT INTO $tabelinferensi (inf_id, case_id, case_goal, rule_id, rule_goal, match_value, cocok, user_id, waktu)
            VALUES ($case_id, $case_id, '$goalstt', $rule_id, '$rule_goal', $match_value, '$cocok', $user_id, $lama)";
    
    // if ($goalstt==$rule_goal)
    // {
    //     $cocok=1;
    // } else {$cocok = 0;}

    // //simpan hasil kecocokan fakta terbesar dalam tabel inferensi
    // $sql = "INSERT INTO $tabelinferensi (case_id, case_goal, rule_id, rule_goal, match_value, cocok, user_id)
    //         VALUES ($case_id, '$goalstt', $rule_id, '$rule_goal', $match_value, $cocok, $user_id)";
            
    if (mysqli_query($conn, $sql)) {
        //echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }

    
    //echo "<br>";
}
// $akhir = microtime(true);
// $lama = $akhir - $awal;
// echo "Lama eksekusi script adalah: ".$lama." microsecond";

// $sql_update = "UPDATE $tabelinferensi SET waktu = $lama WHERE case_id = $case_id AND user_id = $user_id";
// if (mysqli_query($conn, $sql_update)) {
//     //echo "New record created successfully";
// } else {
//     echo "Error: " . $sql . "<br>" . mysqli_error($conn);
// }

// menampilkan isi array
/*for ($brs = 0; $brs < 4; $brs++) {
    echo "<li>".$factnamearray[$brs]."</li>";
    }
*/    


// menampilkan isi array
/*
for ($brs = 0; $brs < 5; $brs++) {
    echo "<p><b>Row number $brs</b></p>";
    echo "<ul>";
    for ($col = 0; $col < 5; $col++) {
        echo "<li>".$matcharray[$brs][$col]."</li>";
    }
    echo "</ul>";
} 
*/
$conn->close();
?>