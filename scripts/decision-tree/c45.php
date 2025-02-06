<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expertt";

// tangkap data user dan case_num yang aktif saat ini
// $user_id=2;
// $case_num=2;
//$case_num=1;

// Mengambil parameter yang diteruskan dari Laravel
$user_id = $argv[1];  // Parameter pertama, user_id
$case_num = $argv[2];  // Parameter kedua, case_num

// Lakukan proses sesuai dengan user_id dan case_num
echo "Menjalankan algoritma C4.5 dengan user_id: $user_id dan case_num: $case_num\n";

// Tambahkan proses algoritma dan cetak output di sini
echo "Hasil pohon keputusan:\n";
echo "Contoh hasil: Decision tree generated with $user_id and $case_num";

//definisikan variabel awal
$entropy=0;$entropyss=0;$treelevel=0;$parent_node="0"; $goal="F";$Child_node="0";
// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// program ini utk menerapkan algoritma c45 pada data set kasus
// membentuk tabel data untuk proses sesuai user_id
// membentuk tabel untuk proses algoritma c45
$namatabelasal="case_user_".$user_id;
$namatabel="c45_case_user_".$user_id;

// periksa apakah tabel sudah ada
$result = $conn->query("SHOW TABLES LIKE '$namatabel'");

if ($result->num_rows > 0) {
    // jika sudah ada hapus tabel
    $droptable = $conn->query("drop table $namatabel");
} 
//membentuk tabel untuk proses
$sql = "CREATE table $namatabel as (select * from $namatabelasal)";
if ($conn->query($sql) === TRUE) {
    echo "create table successfully";
} else {
    echo "Error creating tabel: " . $conn->error;
}

//create tabel tree untuk setiap user
$treetabel="tree_user_".$user_id;

// periksa apakah tabel sudah ada
$result = $conn->query("SHOW TABLES LIKE '$treetabel'");

if ($result->num_rows > 0) {
    // jika sudah ada hapus isi data tabel
    $truntree = $conn->query("truncate table $treetabel");
} else {
    //membentuk tabel untuk proses
    $sql = "CREATE table $treetabel (
            `tree_id` int(11) primary key NOT NULL AUTO_INCREMENT,
            `treelevel` int(11) NOT NULL,
            `parent_node` varchar(10) NOT NULL,
            `atribut_id` int(11) NOT NULL,
            `atribut_name` varchar(200) NOT NULL,
            `value_id` int(11) NOT NULL,
            `value_name` varchar(100) NOT NULL,
            `Child_node` varchar(10) NOT NULL,
            `user_id` int(4) NOT NULL,
            `case_num` int(11) NOT NULL,
            `goal` enum('T','F') NOT NULL
            )";
    if ($conn->query($sql) === TRUE) {
        echo "create table successfully";
    } else {
        echo "Error creating tabel: " . $conn->error;
    }
}
$ulang=0;
$selesai=0;
While($selesai==0) {
    // 1. choose attribute for root node
    // menghitung jumlah artibut pada kasus
    $sql="SELECT * FROM $namatabel";

    if ($result=mysqli_query($conn,$sql))
    {
    // Return the number of fields in result set
    $fieldcount=mysqli_num_fields($result);
    printf("\n Result set has %d fields.\n",$fieldcount);
    // Free result set
    mysqli_free_result($result);
    }
    
    // menghitung jumlah kasus
    $sqlcasenum = "SELECT count(case_id) as jlhcase FROM $namatabel";
    $casenum = $conn->query($sqlcasenum);

    if ($casenum->num_rows > 0) {
        // output data of each row
        while($row = $casenum->fetch_assoc()) {
        $jumlahkasus=$row["jlhcase"];
            echo "jumlah kasus: " . $row["jlhcase"]. "<br>";
        }
    } else {
        echo "0 results";
    }

    // mendapatkan atribut goal
    $fieldgoal=$fieldcount-3;
    $sql = "SELECT * FROM $namatabel";
        if ($result = mysqli_query($conn,$sql)) {
            $fieldinfogoal = $result -> fetch_field_direct($fieldgoal);
            $namaatributgoal= $fieldinfogoal -> name;
    //echo "isi fieldgoal: ".$fieldinfogoal."<br>";
    //echo "nama atribut goal: ".$namaatributgoal."<br>";
    // menghitung proporsi goal berdasarkan kategori
    
    $result -> free_result();
        }

    $sqlgoalnum = "SELECT $namaatributgoal, count(case_id) as jlhproporsi FROM $namatabel group by $namaatributgoal";
    $goalnum = $conn->query($sqlgoalnum);

    if ($goalnum->num_rows > 0) {
        // output data of each row
        while($row = $goalnum->fetch_assoc()) {
        $proporsi= $row["jlhproporsi"]/$jumlahkasus;
        // menghitung entropy (s)
        $entropy= $entropy- ($proporsi * log($proporsi,2)) ;
        //menyimpan nilai goal
        $valuedarigoal=$row["$namaatributgoal"];
            //echo "kategori: " . $row["5_play"]. "    jumlah kasus: " . $row["jlhproporsi"].
             //   "     Proporsi: ". $proporsi. "     Entropy: ". $entropy."<br>";
        }
    } else {
        echo "0 results";
    }

    // menghitung entropy setiap subset atribut
    // ____________________________________________

    // mengakses atribut beserta subsetnya

    // bentuk array untuk gain
    $gain=array(array());

    For ($totfield=1;$totfield<$fieldcount-3;$totfield++) 
    {
        $sql = "SELECT * FROM $namatabel";
        if ($result = mysqli_query($conn,$sql)) {

            $fieldinfo = $result -> fetch_field_direct($totfield);

            // membentuk array penampung entropy tiap subset atribut 
            // array terdiri dari nama atribut, nama subset, nilai entropy

            // menyimpan nama atribut untuk array 
            $namaatribut= $fieldinfo -> name;
            
            //echo "<br>"."nama atribut: ".$namaatribut."<br>";

            $result -> free_result();
        } 
            // query mengambil subset
            $barisarray=0;
            $sqlsubset = "SELECT $namaatribut, count(case_id) as numsubset FROM $namatabel group by $namaatribut";
            $subset = $conn->query($sqlsubset);

            if ($subset->num_rows > 0) {
            // output data of each row
            // membentuk array dimensional
            $entropysubset=array(array());

            while($row = $subset->fetch_assoc()) {
                
                //echo "<br>"."nama atribut: ".$namaatribut."<br>";

                //membentuk array atribut
                $pushsubset=array($namaatribut,$row[$namaatribut],$row["numsubset"],0);
                $entropysubset[]=$pushsubset;
                
                $barisarray++;
                //echo $entropysubset[1][0]." kategori: ".$entropysubset[1][1]."  num subset: ".$entropysubset[1][2]."<br>";

            }
        } else {
            echo "0 results";
        }
            
        // Query utk mengambil data utk entropy subset: 
        //select 1_outlook, count(case_id) from $namatabel group by 1_outlook, 5_play having 1_outlook="1_sunny";
        
        // menghitung entropy subset
        
        for ($brs = 1; $brs < $barisarray+1; $brs++) {
        
        $entropyss=0;
        $atts=$entropysubset[$brs][0];
        $attvalue= $entropysubset[$brs][1];
        $jumlahentss= $entropysubset[$brs][2];
        $sqlentss = "SELECT $atts, count(case_id) as jlhentss FROM $namatabel group by $atts, $namaatributgoal having $atts='$attvalue'";
        $entss = $conn->query($sqlentss);

        if ($entss->num_rows > 0) {
            // output data of each row
            while($row = $entss->fetch_assoc()) {
            $proporsiss= $row["jlhentss"]/$jumlahentss;
            // menghitung entropy (s)
            $entropyss= $entropyss- ($proporsiss * log($proporsiss,2)) ;
            // mengisi elemen ke 4 array yang merupakan nilai entropy   
            }
            $entropysubset[$brs][3]= $entropyss;  
        } else {
            echo "0 results";
        }
        }
        
        // menghitung semua entropyss
        $allentropyss=0;
        for ($brs = 1; $brs < $barisarray+1; $brs++) {
            $atts=$entropysubset[$brs][0];
            $jumlahentss= $entropysubset[$brs][2];
            $entropyss2=$entropysubset[$brs][3];
            $allentropyss= $allentropyss+(($jumlahentss/$jumlahkasus)*$entropyss2);
        }

        

        // menghitung gain
        $htggain= $entropy-$allentropyss;
        $pushgain=array($atts,$htggain);
        $gain[]=$pushgain;
        // simpan array gain

    }
    
    // deteksi goal: jika semua gain bernilai sama
    $jumlahbarisdata=$fieldcount-4;
    $nilaisama=1;
    $nilaigain=$gain[1][1];
    for ($brs = 2; $brs < $fieldcount-3; $brs++) {
        if($gain[$brs][1] == $nilaigain)
            { 
            $nilaisama++;
        }
    }
    if ($nilaisama==$jumlahbarisdata)
        // berarti sudah goal
        {
            $panjangatt=strlen($namaatributgoal);
            $posisiatribut=strpos($namaatributgoal,"_");
            $atribut_id=substr($namaatributgoal,0,$posisiatribut);
            $atribut_name=substr($namaatributgoal,$posisiatribut+1,$panjangatt);
            $goal="T"; $Child_node="1";
            echo "goooooooooooool: ",$atribut_id.$atribut_name."<br>";

            $panjangvalue=strlen($valuedarigoal);
            $posisivalue=strpos($valuedarigoal,"_");
            $value_id=substr($valuedarigoal,0,$posisivalue);
            $value_name=substr($valuedarigoal,$posisivalue+1,$panjangvalue);

            $user_id=$user_id_dt;
            $case_num=$case_num_dt;

            // menyimpan data pada tree
            $sql2 = "INSERT INTO $treetabel (treelevel, parent_node, atribut_id, atribut_name, value_id, value_name, child_node, user_id, case_num, goal)
            VALUES ($treelevel, $parent_node, $atribut_id, '$atribut_name', $value_id, '$value_name', $Child_node, $user_id, $case_num, '$goal')";


            if (mysqli_query($conn, $sql2)) {
                //echo "New record created successfully";
            } else {
                echo "Error: " . $sql2 . "<br>" . mysqli_error($conn);
            }
            

        }
    else
    {
        //menentukan atribut terpilih
        //tentukan atribut dengan nilai gain terbesar   
        $maxgain=0;
        $attpilih="";
        for ($brs = 1; $brs < $fieldcount-3; $brs++) {
            if($gain[$brs][1] > $maxgain)
                { 
                $attpilih=$gain[$brs][0];
                $maxgain=$gain[$brs][1];
                //echo "atribut terpilih: ",$attpilih."<br>";
                echo "maxgain: ",$maxgain."<br>";
            }
        }
        echo "atribut terpilih..........: ",$attpilih."<br>";

        // simpan atribut terpilih dalam tabel tree
        //menemukan atribut_id 
        // memisahkan atribut_id dengan medeteksi keberadaan karakter "_"
        $panjangatt=strlen($attpilih);
        $posisiatribut=strpos($attpilih,"_");
        $atribut_id=substr($attpilih,0,$posisiatribut);
        $atribut_name=substr($attpilih,$posisiatribut+1,$panjangatt);
        
        //echo "atribut ID: ",$atribut_id."       atribut_name:".$atribut_name. "<br>";
    

        //menyimpan atribut terpilih dalam tabel tree
        //membaca dan mengambil value id dan value name
        
        $sql = "SELECT atribut_id, value_id, value_name, user_id, case_num FROM atribut_value where atribut_id='$atribut_id'";
        $result = $conn->query($sql);

            if ($result->num_rows > 0) {
            // output data of each row
            while($row = $result->fetch_assoc()) {
                $value_id=$row["value_id"];
                $value_name=$row["value_name"];
                $user_id=$row["user_id"];
                $case_num=$row["case_num"];

                // menyimpan data pada tree
                $sql2 = "INSERT INTO $treetabel (treelevel, parent_node, atribut_id, atribut_name, value_id, value_name, child_node, user_id, case_num, goal)
                VALUES ($treelevel, $parent_node, $atribut_id, '$atribut_name', $value_id, '$value_name', $Child_node, $user_id, $case_num, '$goal')";
                
                if (mysqli_query($conn, $sql2)) {
                    echo "New record created successfully";
                } else {
                    echo "Error: " . $sql2 . "<br>" . mysqli_error($conn);
                }
                
            }
            } else {
            echo "0 results";
            }
            //$conn->close();
            //mysqli_close($conn);
        }

        // Persiapan data iterasi berikutnya
        // memeriksa tabel tree apakah semua node pada tree sudah dieksplorasi
        $result = $conn->query("select count(tree_id) as countdata from $treetabel where child_node='0' ");
        if ($row= $result->fetch_assoc()) {
            // jika masih ada yang belum dieksplorasi
            echo "jumlah data yg belum diproses.........:".$row["countdata"]."<br>";
            if ($row["countdata"] == 0){
                $selesai=1;
                echo "tree sudah jadi..............."."<br>";
                break;
            }
            if ($row["countdata"] <> 0){
                $selesai=0;
                // mengambil record pertama data node pada tree
                $result = $conn->query("select * from $treetabel where child_node='0' limit 1");
                // mengambil semua data tree
                if ($row= $result->fetch_assoc()) {
                    $tree_id_dt=$row["tree_id"];
                    $treelevel_dt=$row["treelevel"];
                    $parent_node_dt=$row["parent_node"];
                    $atribut_id_dt=$row["atribut_id"];
                    $atribut_name_dt=$row["atribut_name"];
                    $value_id_dt=$row["value_id"];
                    $value_name_dt=$row["value_name"];
                    $child_node_dt=$row["Child_node"];
                    $user_id_dt=$row["user_id"];
                    $case_num_dt=$row["case_num"];
                    $goal_dt=$row["goal"];

                    //bentuk data kasus yang baru 
                    $attparent=$atribut_id_dt."_".$atribut_name_dt;
                    $attvalue=$value_id_dt."_".$value_name_dt;
                    $parent_node=$tree_id_dt;

                    // ganti child node menjadi 1
                    $result = $conn->query("update $treetabel set child_node='1' where tree_id=$tree_id_dt");
                    
                    // memulai pengulangan
                    //merumuskan where clause
                    $bagianwhere=$attparent."= '$attvalue'";
                    while ($parent_node_dt<>0)
                    {
                        $result = $conn->query("select * from $treetabel where tree_id='$parent_node_dt'");
                        if ($row= $result->fetch_assoc()) {
                            $tree_id_dt=$row["tree_id"];
                            $treelevel_dt=$row["treelevel"];
                            $parent_node_dt=$row["parent_node"];
                            $atribut_id_dt=$row["atribut_id"];
                            $atribut_name_dt=$row["atribut_name"];
                            $value_id_dt=$row["value_id"];
                            $value_name_dt=$row["value_name"];

                            $attparent=$atribut_id_dt."_".$atribut_name_dt;
                            $attvalue=$value_id_dt."_".$value_name_dt;

                            $bagianwhere=$bagianwhere." and ".$attparent."= '$attvalue'";

                        }
                    }
                    echo "bagian where".$bagianwhere."<br>";
                    // periksa apakah tabel sudah ada
                    $result = $conn->query("SHOW TABLES LIKE '$namatabel'");

                    if ($result->num_rows > 0) {
                        // jika sudah ada hapus tabel
                        $droptable = $conn->query("drop table $namatabel");
                    } 
                    //membentuk tabel untuk proses
                    $sql = "CREATE table $namatabel as (select * from $namatabelasal where $bagianwhere)";
                    if ($conn->query($sql) === TRUE) {
                        echo "create table successfully";
                    } else {
                        echo "Error creating tabel: " . $conn->error;
                    }
                }
                //echo " jumlah data: ".$row["countdata"]."<br>";}
            
            } 
        }
        $treelevel=$treelevel_dt+1; $goal="F";$Child_node="0";
        $ulang++;

        //membentuk tabel case_user untuk iterasi berikutnya

    
    
    // menampilkan isi array
   /*  for ($brs = 1; $brs < $fieldcount-3; $brs++) {
        echo "<p><b>Row number $brs</b></p>";
        echo "<ul>";
        for ($col = 0; $col < 2; $col++) {
            echo "<li>".$gain[$brs][$col]."</li>";
        }
        echo "</ul>";
    } 
*/
    //echo "entropy: ".$entropy. "   allentropyss: ".$allentropyss. "   gain:  ".$gain."<br>";

    
    // menampilkan isi array entropysubset
    /*   for ($brs = 1; $brs < 4; $brs++) {
            echo "<p><b>Row number $brs</b></p>";
            echo "<ul>";
            for ($col = 0; $col < 4; $col++) {
                echo "<li>".$entropysubset[$brs][$col]."</li>";
            }
            echo "</ul>";
        }
        */
    
    // membentuk array penampung entropy tiap subset atribut



    // menghitung entropy (Si)

} // end while
$conn->close();
// lanjut ke rule.php
?>