<?php
// File ini untuk membentuk aturan hasil dari algoritma c45

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

// menangkap user_id uang aktif
$user_id = $argv[1];  // Parameter pertama, user_id
$case_num = $argv[2];  // Parameter kedua, case_num

// Lakukan proses sesuai dengan user_id dan case_num
echo "Menjalankan algoritma C4.5 dengan user_id: $user_id dan case_num: $case_num\n";

// membentuk tabel untuk aturan
$tabelrule="rule_user_".$user_id;

$result = $conn->query("SHOW TABLES LIKE '$tabelrule'");

// periksa apakah tabel sudah ada
if ($result->num_rows > 0) {
    // jika sudah ada hapus isi data tabel
    $trunrule = $conn->query("truncate table $tabelrule");
} else {
    //membentuk tabel untuk proses
    $sql = "CREATE table $tabelrule (
            `rule_id` int(11) primary key NOT NULL AUTO_INCREMENT,
            `if_part` varchar(200) NOT NULL,
            `then_part` varchar(200) NOT NULL,
            `user_id` int(4) NOT NULL,
            `case_num` int(11) NOT NULL
            )";
    if ($conn->query($sql) === TRUE) {
        echo "create table successfully";
    } else {
        echo "Error creating tabel: " . $conn->error;
    }
}

// membentuk tabel rule berdasarkan data pada tree
$tabeltree="tree_user_".$user_id;
// pembentukan rule menggunakan pendekatan backward. 
// cari goal data
$goaldata = $conn->query("select * from $tabeltree where goal='T'");
while ($row= $goaldata->fetch_assoc()) {
    $tree_id=$row["tree_id"];
    $treelevel=$row["treelevel"];
    $parent_node=$row["parent_node"];
    $atribut_id=$row["atribut_id"];
    $atribut_name=$row["atribut_name"];
    $value_id=$row["value_id"];
    $value_name=$row["value_name"];
    $case_num=$row["case_num"];

    $thenpart=$atribut_id."_".$atribut_name."=".$value_id."_".$value_name;
    $bagianif="";
    //echo "bagian then part:   ".$thenpart."  parent_node:  ".$parent_node."<br>";
    if ($parent_node<>0) {
        $ulang=1;
    } else {
        $ulang=0;
    }
    // lakukan perulangan sampai semua fakta tersusun untuk bagian IF
    While ($ulang==1)
    {
        $ifdata = $conn->query("select * from $tabeltree where tree_id='$parent_node'");
        if ($row= $ifdata->fetch_assoc()) {
            $tree_id=$row["tree_id"];
            $treelevel=$row["treelevel"];
            $parent_node=$row["parent_node"];
            $atribut_id=$row["atribut_id"];
            $atribut_name=$row["atribut_name"];
            $value_id=$row["value_id"];
            $value_name=$row["value_name"];

            $ifpart=$atribut_id."_".$atribut_name."=".$value_id."_".$value_name;
            if (strlen($bagianif>0))
            {
                $bagianif=$ifpart." and ".$bagianif;
            } else {
                $bagianif=$ifpart.$bagianif;
            }
            //echo "bagian if part:   ".$bagianif."  parent_node:  ".$parent_node."<br>";
            if ($parent_node==0) {
                $ulang=0;
            }
        }
        
    }
    // insert data rule
    $sql = "INSERT INTO $tabelrule (if_part, then_part, user_id, case_num)
            VALUES ('$bagianif', '$thenpart', $user_id, $case_num)";
            
    if (mysqli_query($conn, $sql)) {
        //echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($conn);
    }
            

  
}
echo "selesai pembentukan rule";
$conn->close(); 
?>