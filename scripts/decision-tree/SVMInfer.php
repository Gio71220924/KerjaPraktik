<?php
// php scripts/decision-tree/SVMInfer.php <user_id> <case_num> [--table=test_case_user_{id}] [--kernel=sgd]
declare(strict_types=1);

function envv(string $k, $d=null){
  $v=getenv($k); if($v!==false && $v!=='') return $v;
  if(isset($_ENV[$k]) && $_ENV[$k]!=='') return $_ENV[$k];
  if(isset($_SERVER[$k]) && $_SERVER[$k]!=='') return $_SERVER[$k];
  return $d;
}

$host=(string)envv('DB_HOST','127.0.0.1');
$port=(int)envv('DB_PORT',3307);
$database=(string)envv('DB_DATABASE','expertt');
$username=(string)envv('DB_USERNAME','root');
$password=(string)envv('DB_PASSWORD','');

function table_exists(mysqli $db,string $schema,string $table):bool{
  $schema=$db->real_escape_string($schema);
  $table =$db->real_escape_string($table);
  $q=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema='{$schema}' AND table_name='{$table}' LIMIT 1");
  return $q && $q->num_rows>0;
}

if (PHP_SAPI!=='cli' || $argc<3){
  fwrite(STDERR,"Usage: php SVMInfer.php <user_id> <case_num> [--table=table_name] [--kernel=kernel_name]\n");
  exit(1);
}
$userId=(int)$argv[1];
$caseNum=(int)$argv[2];
$overrideTable=null;
$preferredKernel=null;
for($i=3;$i<$argc;$i++){
  if (str_starts_with($argv[$i],'--table=')) {
    $overrideTable=substr($argv[$i],8);
  } elseif (str_starts_with($argv[$i],'--kernel=')) {
    $preferredKernel=strtolower(trim(substr($argv[$i],9)));
    if ($preferredKernel==='') $preferredKernel=null;
  }
}

mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($host,$username,$password,$database,$port);
$db->set_charset('utf8mb4');

$storageDir = function_exists('storage_path') ? storage_path('app/svm') : (getcwd().DIRECTORY_SEPARATOR.'svm_models');
// Jika diberikan --kernel=nama, prioritaskan file JSON dengan suffix kernel tersebut
// (mis. svm_user_{id}_rbf.json). Bila tidak ditemukan, fallback ke daftar default.
$candidateKernels=['sgd','rbf','sigmoid'];
$candidates=[];
if($preferredKernel){
  $candidates[]=$storageDir."/svm_user_{$userId}_{$preferredKernel}.json";
}
foreach($candidateKernels as $k){
  if($preferredKernel===$k) continue;
  $candidates[]=$storageDir."/svm_user_{$userId}_{$k}.json";
}
$modelPath=null; $model=null; $chosenKernel=null;
foreach($candidates as $m){
  if (is_file($m)) {
    $modelPath=$m; $model=json_decode(file_get_contents($m), true);
    $chosenKernel=preg_match('#_([a-z0-9]+)\.json$#i',$m,$mm)?strtolower($mm[1]):null;
    break;
  }
}
$fallbackNote=null;
if($preferredKernel){
  if(!$model){
    $fallbackNote="NOTE: Model kernel '{$preferredKernel}' tidak ditemukan, mencoba fallback kernel lain.";
  } elseif($chosenKernel===null || $chosenKernel!==$preferredKernel){
    $alt=$chosenKernel ?: 'lain';
    $fallbackNote="NOTE: Model kernel '{$preferredKernel}' tidak ditemukan, memakai kernel '{$alt}'.";
  }
}
if($fallbackNote){ fwrite(STDOUT,$fallbackNote."\n"); }
if(!$model){ fwrite(STDERR,"Model JSON tidak ditemukan untuk user {$userId}.\n"); exit(1); }

$W          = $model['weights'] ?? null;
$biasIndex  = $model['bias_index'] ?? null;
$goalCol    = $model['goal_column'] ?? null;
$labelMap   = $model['label_map'] ?? ['+1'=>'POS','-1'=>'NEG'];
$classes    = $model['classes'] ?? null;            // multi-class: daftar label
$numClasses = is_array($classes) ? count($classes) : null;
$baseIndex  = $model['feature_index'] ?? [];
$numMinmax  = $model['numeric_minmax'] ?? [];
$kernelType = $model['kernel'] ?? 'sgd';
$kernelMeta = $model['kernel_meta'] ?? [];
if(!$W || $biasIndex===null || !$baseIndex){ fwrite(STDERR,"Model JSON tidak valid.\n"); exit(1); }

$sourceTable = $overrideTable ?: (table_exists($db,$database,"test_case_user_{$userId}") ? "test_case_user_{$userId}" : null);
if(!$sourceTable){ fwrite(STDERR,"Tabel test_case_user_{$userId} tidak ada.\n"); exit(1); }

$q = $db->prepare("SELECT * FROM `{$sourceTable}` WHERE algoritma IN ('SVM','Support Vector Machine')");
$q->execute(); $cases=$q->get_result();
if($cases->num_rows===0){ echo "NOTE: tidak ada case dengan algoritma SVM.\n"; exit(0); }

$infTbl="inferensi_user_{$userId}";
if(!table_exists($db,$database,$infTbl)){
  $db->query("
    CREATE TABLE `{$infTbl}` (
      `inf_id` int(11) NOT NULL AUTO_INCREMENT,
      `case_id` varchar(100) NOT NULL,
      `case_goal` varchar(200) NOT NULL,
      `rule_id` varchar(100) NOT NULL,
      `rule_goal` varchar(200) NOT NULL,
      `match_value` decimal(5,4) NOT NULL,
      `cocok` enum('1','0') NOT NULL,
      `user_id` int(11) NOT NULL,
      `waktu` decimal(16,14) NOT NULL DEFAULT 0,
      PRIMARY KEY (`inf_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} else {
  // bersihkan baris SVM lama biar tidak dobel
  $db->query("DELETE FROM `{$infTbl}` WHERE rule_id='SVM'");
}

function applyKernel(array $xBase, string $type, array $meta, array $baseIndex): array {
  if ($type==='sgd') return $xBase;

  if ($type==='rbf'){
    $D=(int)($meta['D'] ?? 1024); $gamma=(float)($meta['gamma'] ?? 0.25);
    $seed=(int)($meta['seed'] ?? crc32(json_encode(array_keys($baseIndex)))); mt_srand($seed);
    $B=count($xBase); $omega=[]; $b=[]; $z=array_fill(0,$D,0.0);
    for($j=0;$j<$D;$j++){
      $row=[]; for($k=0;$k<$B;$k++){
        $u1=max(mt_rand()/mt_getrandmax(),1e-12); $u2=mt_rand()/mt_getrandmax();
        $n=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2);
        $row[]=sqrt(2.0*$gamma)*$n;
      }
      $omega[]=$row; $b[]=(mt_rand()/mt_getrandmax())*2.0*M_PI;
    }
    $scale=sqrt(2.0/$D);
    for($j=0;$j<$D;$j++){
      $dot=0.0; for($k=0;$k<$B;$k++) $dot+=$omega[$j][$k]*$xBase[$k];
      $z[$j]=$scale*cos($dot+$b[$j]);
    }
    return $z;
  }

  if ($type==='sigmoid'){
    $D=(int)($meta['D'] ?? 1024); $scale=(float)($meta['scale'] ?? 1.0); $coef0=(float)($meta['coef0'] ?? 0.0);
    $seed=(int)($meta['seed'] ?? (14641 ^ crc32(json_encode(array_keys($baseIndex))))); mt_srand($seed);
    $B=count($xBase); $W=[]; $b=[]; $z=array_fill(0,$D,0.0);
    for($j=0;$j<$D;$j++){
      $row=[]; for($k=0;$k<$B;$k++){
        $u1=max(mt_rand()/mt_getrandmax(),1e-12); $u2=mt_rand()/mt_getrandmax();
        $n=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2);
        $row[]=$scale*$n;
      } $W[]=$row; $b[]=$coef0;
    }
    $norm=sqrt(1.0/$D);
    for($j=0;$j<$D;$j++){
      $dot=0.0; for($k=0;$k<$B;$k++) $dot+=$W[$j][$k]*$xBase[$k];
      $z[$j]=$norm*tanh($dot+$b[$j]);
    }
    return $z;
  }

  return $xBase;
}

function sigmoid_conf(float $margin): float {
  $c=1.0/(1.0+exp(-abs($margin)));
  return max(0.0,min(1.0,$c));
}

$B=count($baseIndex);
function encodeRow(array $row, array $baseIndex, array $numMinmax, int $B): array {
  $x=array_fill(0,$B,0.0);
  // numeric
  foreach ($numMinmax as $col=>$mm){
    $min=(float)$mm['min']; $max=(float)$mm['max'];
    $fv = isset($row[$col]) && is_numeric($row[$col]) ? (float)$row[$col] : 0.0;
    $idx = $baseIndex["NUM::{$col}"] ?? null;
    if ($idx!==null){
      $x[$idx] = ($max>$min)? ($fv-$min)/($max-$min) : 0.0;
    }
  }
  // categorical one-hot
  foreach ($baseIndex as $key=>$idx){
    if (!str_starts_with($key,'CAT::')) continue;
    [, $col, $val] = explode('::',$key,3);
    $got = isset($row[$col]) ? (string)$row[$col] : '';
    if ($got!=='' && $got===$val) $x[$idx]=1.0;
  }
  return $x;
}

$before = table_exists($db,$database,$infTbl) ? (int)($db->query("SELECT COUNT(*) c FROM `{$infTbl}`")->fetch_assoc()['c']) : 0;

$start=microtime(true);
$ins=$db->prepare("INSERT INTO `{$infTbl}`(case_id, case_goal, rule_id, rule_goal, match_value, cocok, user_id, waktu) VALUES (?,?,?,?,?,?,?,?)");
$cnt=0;

while($r=$cases->fetch_assoc()){
  $caseId=(string)($r['case_id'] ?? (++$cnt));
  $xBase=encodeRow($r,$baseIndex,$numMinmax,$B);
  $z=applyKernel($xBase,$kernelType,$kernelMeta,$baseIndex);
  $z[] = 1.0;

  // Jika model multi-class (weights matriks & ada 'classes'), pilih kelas dengan skor tertinggi
  if (is_array($classes) && isset($W[0]) && is_array($W[0])) {
    $bestIdx = null;
    $bestScore = null;
    $L = count($z);
    for ($c=0; $c<$numClasses; $c++) {
      $dot = 0.0;
      for ($i=0; $i<$L; $i++) $dot += ($W[$c][$i] ?? 0.0) * $z[$i];
      if ($bestScore === null || $dot > $bestScore) {
        $bestScore = $dot;
        $bestIdx   = $c;
      }
    }
    $pred = $classes[$bestIdx] ?? 'UNKNOWN';
    $dot  = (float)$bestScore;
  } else {
    // Backward-compat: model binary lama (label_map +1/-1)
    $dot=0.0; for($i=0;$i<count($z);$i++) $dot+=($W[$i]??0.0)*$z[$i];
    $sign=($dot>=0)?'+1':'-1'; $pred=$labelMap[$sign] ?? $sign;
  }

  $goalKey = $goalCol ?: (array_key_exists('goal',$r)?'goal':null);
  $actual  = $goalKey && array_key_exists($goalKey,$r) ? (string)$r[$goalKey] : '';
  $caseGoal= $goalKey ? "{$goalKey} = {$actual}" : '';
  $cocok   = ($actual!=='' && $pred===$actual) ? '1':'0';

  $conf   = number_format(sigmoid_conf($dot), 4, '.', '');
  $elapsed= number_format(microtime(true)-$start, 14, '.', '');
  $ruleId ='SVM';
  $ruleGoal= ($goalKey ? "{$goalKey} = " : "") . "{$pred} | kernel={$kernelType}";

  $ins->bind_param("ssssdsid",$caseId,$caseGoal,$ruleId,$ruleGoal,$conf,$cocok,$userId,$elapsed);
  $ins->execute();
}
$ins->close();

$after=(int)($db->query("SELECT COUNT(*) c FROM `{$infTbl}`")->fetch_assoc()['c']);
$added=$after-$before;
echo "OK: inferred {$added} row(s) into {$infTbl}\n";
