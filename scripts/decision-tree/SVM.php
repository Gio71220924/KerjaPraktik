<?php
declare(strict_types=1);

/**
 * SVM SGD (hinge + L2) dengan opsi kernel via feature map:
 *  - sgd (linear/identitas)
 *  - rbf:D=256:gamma=0.25 (Random Fourier Features)
 *  - sigmoid:D=256:scale=1.0:coef0=0.0 (random tanh features; aproksimasi praktis)
 *
 * Fitur:
 *  - Execution time: total, avg/epoch, throughput
 *  - Simpan model (ON/OFF via ENV SVM_SAVE_MODEL)
 *  - Logging ke MySQL: svm_user_{user_id}
 *
 * Run:
 *   php SVM.php <user_id> <case_num> [kernel] [--table=table_name] [--epochs=20] [--lambda=0.0001] [--eta0=0.1]
 */

///////////////////////////// CONFIG /////////////////////////////////
function envv(string $k, $d=null){
  $v=getenv($k); if($v!==false && $v!=='') return $v;
  if(isset($_ENV[$k]) && $_ENV[$k]!=='') return $_ENV[$k];
  if(isset($_SERVER[$k]) && $_SERVER[$k]!=='') return $_SERVER[$k];
  return $d;
}

$SAVE_MODEL          = filter_var(envv('SVM_SAVE_MODEL','1'), FILTER_VALIDATE_BOOLEAN);
$MAX_EXEC_TIME_RAW   = envv('SVM_MAX_EXEC_TIME', '0');    // 0 = unlimited, atau isi detik
$MAX_EXEC_TIME       = is_numeric($MAX_EXEC_TIME_RAW) ? (int)$MAX_EXEC_TIME_RAW : 0;
$MEMORY_LIMIT        = envv('SVM_MEMORY_LIMIT', '-1'); // -1 = unlimited by default; override via env/ini jika ingin membatasi
// Batas jumlah sampel (agar tidak makan RAM berlebihan)
$DEFAULT_MAX_SAMPLES = (int)envv('SVM_MAX_SAMPLES', '0'); // 0 = unlimited secara default
// Paksa hilangkan batas waktu proses CLI; refresh nilai di awal
@ini_set('max_execution_time', (string)$MAX_EXEC_TIME);
@set_time_limit($MAX_EXEC_TIME);
if ($MEMORY_LIMIT) {
  @ini_set('memory_limit', (string)$MEMORY_LIMIT);
}
$MODEL_DIR_FALLBACK  = getcwd() . DIRECTORY_SEPARATOR . 'svm_models';
// Default proporsi data uji dan threshold keputusan (bisa dioverride via ENV / CLI)
$DEFAULT_TEST_RATIO  = (float)envv('SVM_TEST_RATIO','0.3');   // 30% untuk uji (70/30)
$DECISION_THRESHOLD  = (float)envv('SVM_THRESHOLD','0.0');    // dot >= threshold => kelas +1

///////////////////////////// HELPERS /////////////////////////////////
function norm(string $s): string{
  $s=strtolower($s);
  $s=preg_replace('/[^a-z0-9]+/i','_',$s);
  $s=preg_replace('/_+/', '_', $s);
  return trim($s,'_');
}
function findGoalKey(array $cols,string $wanted):?string{
  if(in_array($wanted,$cols,true)) return $wanted;
  $wn=norm($wanted); $m=[];
  foreach($cols as $c){ $m[norm($c)]=$c; }
  return $m[$wn]??null;
}
function table_exists(mysqli $db,string $schema,string $table):bool{
  $schema=$db->real_escape_string($schema);
  $table =$db->real_escape_string($table);
  $q=$db->query("SELECT 1 FROM information_schema.tables WHERE table_schema='{$schema}' AND table_name='{$table}' LIMIT 1");
  return $q && $q->num_rows>0;
}

/** Kernel parser: 'sgd' | 'rbf[:D=...][:gamma=...]' | 'sigmoid[:D=...][:scale=...][:coef0=...]' */
function parseKernel(string $spec): array{
  $parts=explode(':', strtolower(trim($spec)));
  $type=$parts[0]??'sgd'; $cfg=['type'=>$type];
  foreach(array_slice($parts,1) as $kv){
    if(strpos($kv,'=')!==false){
      [$k,$v]=explode('=', $kv, 2);
      $k=trim($k); $v=trim($v);
      $cfg[$k]=is_numeric($v)?(float)$v:$v;
    }
  }
  if($type==='rbf'){
    // default D dari 1024 -> 128 (lebih ringan)
    $cfg['D']     = (int)($cfg['D'] ?? 128);
    $cfg['gamma'] = (float)($cfg['gamma'] ?? 0.25);
  }
  if($type==='sigmoid'){
    // default D dari 1024 -> 128 (lebih ringan)
    $cfg['D']     = (int)($cfg['D'] ?? 128);
    $cfg['scale'] = (float)($cfg['scale'] ?? 1.0);
    $cfg['coef0'] = (float)($cfg['coef0'] ?? 0.0);
  }
  return $cfg;
}

/** Build mapper: return [callable $mapFn, array $meta, int $outDim] */
function buildFeatureMapper(array $baseIndex,array $kcfg): array{
  $B=count($baseIndex);

  // Linear/SGD: identitas
  if($kcfg['type']==='sgd'){
    $f = function(array $x){ return $x; };
    return [$f, ['type'=>'sgd'], $B];
  }

  // RBF: Random Fourier Features (generate bobot on-the-fly supaya tidak memakan RAM besar)
  if($kcfg['type']==='rbf'){
    $D=(int)$kcfg['D']; $gamma=(float)$kcfg['gamma'];
    $seed=crc32(json_encode(array_keys($baseIndex)));
    $scale=sqrt(2.0/$D);
    $randMax=mt_getrandmax();
    $f=function(array $x) use($seed,$gamma,$D,$B,$scale,$randMax){
      $z=array_fill(0,$D,0.0);
      for($j=0;$j<$D;$j++){
        mt_srand($seed+$j, MT_RAND_MT19937);
        $dot=0.0;
        for($k=0;$k<$B;$k++){
          $u1=max(mt_rand()/($randMax?:1),1e-12);
          $u2=mt_rand()/($randMax?:1);
          $n=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2); // ~N(0,1)
          $dot+=sqrt(2.0*$gamma)*$n*$x[$k];
        }
        $b=(mt_rand()/($randMax?:1))*2.0*M_PI;
        $z[$j]=$scale*cos($dot+$b);
      }
      return $z;
    };
    return [$f,['type'=>'rbf','D'=>$D,'gamma'=>$gamma,'seed'=>$seed],$D];
  }

  // Sigmoid: random tanh features (generate bobot on-the-fly supaya tidak memakan RAM besar)
  if($kcfg['type']==='sigmoid'){
    $D=(int)$kcfg['D']; $scale=(float)$kcfg['scale']; $coef0=(float)$kcfg['coef0'];
    $seed=14641 ^ crc32(json_encode(array_keys($baseIndex)));
    $norm=sqrt(1.0/$D);
    $randMax=mt_getrandmax();
    $f=function(array $x) use($seed,$scale,$coef0,$D,$B,$norm,$randMax){
      $z=array_fill(0,$D,0.0);
      for($j=0;$j<$D;$j++){
        mt_srand($seed+$j, MT_RAND_MT19937);
        $dot=0.0;
        for($k=0;$k<$B;$k++){
          $u1=max(mt_rand()/($randMax?:1),1e-12);
          $u2=mt_rand()/($randMax?:1);
          $n=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2);
          $dot+=$scale*$n*$x[$k];
        }
        $z[$j]=$norm*tanh($dot+$coef0);
      }
      return $z;
    };
    return [$f,['type'=>'sigmoid','D'=>$D,'scale'=>$scale,'coef0'=>$coef0,'seed'=>$seed],$D];
  }

  // fallback
  $f = function(array $x){ return $x; };
  return [$f, ['type'=>'sgd'], $B];
}

function log_and_exit_fail(?mysqli $db, int $userId, string $msg, ?string $modelPath=null): void{
  $status='failed'; $exec=0.0;
  if($db instanceof mysqli){
    $logTable="svm_user_{$userId}";
    $db->query("CREATE TABLE IF NOT EXISTS `{$logTable}`(
      id INT AUTO_INCREMENT PRIMARY KEY,
      status VARCHAR(50),
      execution_time DECIMAL(12,6) NULL,
      model_path VARCHAR(1024) NULL,
      output LONGTEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT NULL
    )");
    $stmt=$db->prepare("INSERT INTO `{$logTable}` (status,execution_time,model_path,output,created_at,updated_at)
                        VALUES (?,?,?,?,NOW(),NOW())");
    $stmt->bind_param('sdss',$status,$exec,$modelPath,$msg);
    $stmt->execute();
  }
  // pastikan CLI
  if(defined('STDERR')){
    fwrite(STDERR,"❌ SVM training failed: {$msg}\n");
  } else {
    echo "❌ SVM training failed: {$msg}\n";
  }
  exit(1);
}

function parseKvArgs(array $argv): array{
  $opts=[];
  foreach($argv as $arg){
    if (strpos($arg,'--')===0 && strpos($arg,'=')!==false){
      [$k,$v]=explode('=', substr($arg,2), 2); $opts[$k]=$v;
    }
  }
  return $opts;
}

/** Buat confusion matrix kosong (K x K) */
function emptyConfusion(int $numClasses): array{
  $m=[];
  for($i=0;$i<$numClasses;$i++){
    $m[] = array_fill(0,$numClasses,0);
  }
  return $m;
}

/** Pretty-print confusion matrix ke teks sederhana */
function formatConfusion(array $cm, array $labels): string{
  $n=count($labels);
  if($n===0) return '';
  $width=7;
  foreach($labels as $lbl){ $width=max($width, strlen((string)$lbl)+2); }
  foreach($cm as $row){ foreach($row as $v){ $width=max($width, strlen((string)$v)+2); } }
  $pad = $width;
  $lines=[];
  $header=str_pad('actual\\pred', $pad);
  foreach($labels as $lbl){ $header .= str_pad((string)$lbl, $pad, ' ', STR_PAD_BOTH); }
  $lines[]=$header;
  foreach($cm as $i=>$row){
    $line=str_pad((string)($labels[$i] ?? $i), $pad);
    foreach($row as $v){ $line .= str_pad((string)$v, $pad, ' ', STR_PAD_BOTH); }
    $lines[]=$line;
  }
  return implode(PHP_EOL, $lines);
}

///////////////////////////// ARGS //////////////////////////////
if(PHP_SAPI!=='cli'){
  if(defined('STDERR')) fwrite(STDERR,"Run from CLI.\n"); else echo "Run from CLI.\n";
  exit(1);
}
global $argv,$argc;
if($argc < 3){
  if(defined('STDERR')) fwrite(STDERR,"Usage: php SVM.php <user_id> <case_num> [kernel] [--table=table_name] [--epochs=20] [--lambda=0.0001] [--eta0=0.1]\n");
  else echo "Usage: php SVM.php <user_id> <case_num> [kernel] [--table=table_name] [--epochs=20] [--lambda=0.0001] [--eta0=0.1]\n";
  exit(1);
}
$userId     = (int)$argv[1];
$caseNum    = (int)$argv[2];
$kernelSpec = $argv[3] ?? 'sgd';
$kv         = parseKvArgs($argv);

// override hyper-params dari CLI
$epochs = isset($kv['epochs']) ? max(1, (int)$kv['epochs']) : 20;
$lambda = isset($kv['lambda']) ? max(1e-8, (float)$kv['lambda']) : 1e-4;
$eta0   = isset($kv['eta0'])   ? max(1e-6, (float)$kv['eta0'])   : 0.1;
// proporsi data uji (0..0.9)
$testRatio = isset($kv['test_ratio']) ? (float)$kv['test_ratio'] : $DEFAULT_TEST_RATIO;
if($testRatio < 0.0) $testRatio = 0.0;
if($testRatio > 0.9) $testRatio = 0.9;
// batas jumlah sampel (default dari env)
$maxSamples = isset($kv['max_samples']) ? max(1, (int)$kv['max_samples']) : $DEFAULT_MAX_SAMPLES;
// oversample kelas minoritas pada train (0=off, 1.0=samakan dengan mayoritas)
$oversampleMinor = isset($kv['oversample_minor']) ? max(0.0, (float)$kv['oversample_minor']) : (float)envv('SVM_OVERSAMPLE_MINOR','0');
// class weight: 'auto' untuk 1/freq per kelas, else none
$classWeightMode = isset($kv['class_weight']) ? strtolower((string)$kv['class_weight']) : strtolower((string)envv('SVM_CLASS_WEIGHT','none'));

// table override + validasi nama tabel
$tableOverride = $kv['table'] ?? null;
if ($tableOverride !== null && !preg_match('/^[A-Za-z0-9_]+$/', $tableOverride)) {
  // belum ada koneksi DB di sini; tahan pesan, log setelah DB siap
}

///////////////////////////// DB CONNECT ///////////////////////
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$host=(string)envv('DB_HOST','127.0.0.1');
$port=(int)envv('DB_PORT',3307);
$database=(string)envv('DB_DATABASE','expertt');
$username=(string)envv('DB_USERNAME','root');
$password=(string)envv('DB_PASSWORD','');
try{
  $db=new mysqli($host,$username,$password,$database,$port);
  $db->set_charset('utf8mb4');
}catch(Throwable $e){
  if(defined('STDERR')) fwrite(STDERR,"❌ DB connect failed ({$e->getCode()}): {$e->getMessage()}\n");
  else echo "❌ DB connect failed ({$e->getCode()}): {$e->getMessage()}\n";
  exit(1);
}

// validasi nama tabel override (setelah ada $db)
if ($tableOverride !== null && !preg_match('/^[A-Za-z0-9_]+$/', $tableOverride)) {
  log_and_exit_fail($db, $userId, "Nama tabel override tidak valid.");
}

///////////////////////////// SUMBER & GOAL ////////////////////
if ($tableOverride) {
  if (!table_exists($db, $database, $tableOverride)) {
    log_and_exit_fail($db, $userId, "Tabel override '{$tableOverride}' tidak ditemukan.");
  }
  $sourceTable = $tableOverride;
} else {
  $sourceTable = table_exists($db,$database,"case_user_{$userId}") ? "case_user_{$userId}"
               : (table_exists($db,$database,"test_case_user_{$userId}") ? "test_case_user_{$userId}" : null);
  if($sourceTable===null) log_and_exit_fail($db,$userId,"Tidak ditemukan tabel case_user_{$userId} maupun test_case_user_{$userId}.");
}

$gq=$db->query("SELECT atribut_id, atribut_name FROM atribut WHERE user_id={$userId} AND goal=1 LIMIT 1");
if($gq->num_rows===0) log_and_exit_fail($db,$userId,"Atribut goal belum ditentukan.");
$g=$gq->fetch_assoc(); $goalWanted=$g['atribut_id'].'_'.$g['atribut_name'];

///////////////////////////// AMBIL DATA ///////////////////////
$res=$db->query("SELECT * FROM `{$sourceTable}`");
if($res->num_rows===0) log_and_exit_fail($db,$userId,"Dataset kosong pada {$sourceTable}.");
$first=$res->fetch_assoc(); $columns=array_keys($first);
$goalKey=findGoalKey($columns,$goalWanted);
if($goalKey===null) log_and_exit_fail($db,$userId,"Kolom goal '{$goalWanted}' tidak ditemukan; kolom: ".implode(',',$columns));
$res->data_seek(0);

///////////////////////////// SKEMA FITUR //////////////////////
$skipCols=['case_id','user_id','case_num','algoritma',$goalKey];
$cats=[]; $nums=[]; $labelsRaw=[];
while($row=$res->fetch_assoc()){
  $lab=$row[$goalKey]??null;
  if($lab===null||$lab===''||preg_match('/^(unknown|tidak diketahui)$/i',(string)$lab)) continue;
  $labelsRaw[]=(string)$lab;
  foreach($row as $c=>$v){
    if(in_array($c,$skipCols,true)) continue;
    if(is_numeric($v)){
      $f=(float)$v;
      if(!isset($nums[$c])) $nums[$c]=['min'=>$f,'max'=>$f];
      else{ if($f<$nums[$c]['min'])$nums[$c]['min']=$f; if($f>$nums[$c]['max'])$nums[$c]['max']=$f; }
    }else{
      $s=trim((string)$v); if($s==='') continue;
      if(!isset($cats[$c])) $cats[$c]=[];
      $cats[$c][$s]=true;
    }
  }
}
if(!$labelsRaw) log_and_exit_fail($db,$userId,"Semua label kosong/Unknown pada {$goalKey}.");
$freq=array_count_values($labelsRaw); arsort($freq); $classes=array_keys($freq);
if(count($classes)<2){
  $detail=json_encode($freq, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  log_and_exit_fail($db,$userId,"Butuh >=2 kelas untuk SVM. Distribusi label: {$detail}");
}
// Multi-class: gunakan semua kelas yang ada (>=2)
$classLabels = array_values($classes);       // daftar label unik (string)
$numClasses  = count($classLabels);
// Untuk kompatibilitas lama (binary), tetap definisikan pos/neg dari dua kelas pertama
$posLabel = $classLabels[0];
$negLabel = $classLabels[1] ?? $classLabels[0];
$labelToIndex = [];
foreach ($classLabels as $idx => $lab) {
  $labelToIndex[(string)$lab] = $idx;       // map label -> index 0..K-1
}

///////////////////////////// INDEX FITUR DASAR ///////////////
$baseIndex=[]; $bi=0;
foreach($nums as $c=>$_) $baseIndex["NUM::$c"]=$bi++;
foreach($cats as $c=>$vals) foreach(array_keys($vals) as $v) $baseIndex["CAT::$c::$v"]=$bi++;
$B=$bi;

///////////////////////////// KERNEL MAPPER ////////////////////
$kcfg=parseKernel($kernelSpec);
[$featureMapper,$kernelMeta,$mappedDim]=buildFeatureMapper($baseIndex,$kcfg);
$biasIndex=$mappedDim; $dim=$mappedDim+1;

///////////////////////////// BUILD X, y ///////////////////////
$res->data_seek(0);
$X=[]; $y=[]; $total=0; $skipNoLabel=0; $skipZero=0;
$truncated=false;
while($row=$res->fetch_assoc()){
  $total++;
  $lab=$row[$goalKey]??null;
  if($lab===null||$lab===''||preg_match('/^(unknown|tidak diketahui)$/i',(string)$lab)){ $skipNoLabel++; continue; }
  $lab=(string)$lab;
  if(!isset($labelToIndex[$lab])) continue; // jaga-jaga
  $yi = $labelToIndex[$lab];                // kelas 0..K-1

  $xBase=array_fill(0,$B,0.0);
  foreach($row as $c=>$v){
    if(in_array($c,$skipCols,true)) continue;
    if(isset($nums[$c])){
      $min=$nums[$c]['min']; $max=$nums[$c]['max'];
      $f=is_numeric($v)?(float)$v:0.0;                   // guard numeric null/empty
      $z=($max>$min)?($f-$min)/($max-$min):0.0;
      $xBase[$baseIndex["NUM::$c"]]=$z;
    } elseif(isset($cats[$c])){
      $s=trim((string)$v);
      if($s!=='' && isset($baseIndex["CAT::$c::$s"])) $xBase[$baseIndex["CAT::$c::$s"]]=1.0;
    }
  }
  $z=$featureMapper($xBase); $xi=array_merge($z,[1.0]); // +bias
  $sum=0.0; foreach($xi as $vv) $sum+=abs($vv); if($sum==0.0){ $skipZero++; continue; }
  $X[]=$xi; $y[]=$yi;
  if ($maxSamples > 0 && count($X) >= $maxSamples) { $truncated=true; break; }
}
if(!$X) log_and_exit_fail($db,$userId,"Tidak ada sampel valid. total={$total} kosong_label={$skipNoLabel} fitur_kosong={$skipZero}");
if($truncated && $maxSamples > 0){
  echo "NOTE: Dataset dibatasi {$maxSamples} sampel (set SVM_MAX_SAMPLES atau --max_samples untuk mengubah).\n";
}

///////////////////////////// SPLIT TRAIN/TEST //////////////////////
$totalSamples = count($X);

// Stratified random split per kelas supaya 70/30 tidak sekuensial
$splitSeedEnv = envv('SVM_SPLIT_SEED', null);
if($splitSeedEnv !== null && $splitSeedEnv!==''){
  mt_srand((int)$splitSeedEnv);
}else{
  $autoSeed = (int)(microtime(true) * 1000000);
  try{ $autoSeed ^= random_int(PHP_INT_MIN, PHP_INT_MAX); }catch(Throwable $e){}
  mt_srand($autoSeed);
}

$buckets = [];
foreach($y as $idx=>$clsIdx){
  if(!isset($buckets[$clsIdx])) $buckets[$clsIdx]=[];
  $buckets[$clsIdx][]=$idx;
}

$trainIdx=[]; $testIdx=[];
foreach($buckets as $clsIdx=>$idxList){
  shuffle($idxList);
  $nCls=count($idxList);
  $tCount=(int)round($testRatio*$nCls);
  if($tCount >= $nCls && $nCls>1) $tCount=$nCls-1; // sisakan minimal 1 untuk train
  if($tCount===0 && $nCls>1 && $testRatio>0.0) $tCount=1; // jaga-jaga supaya ada sampel uji
  $testIdx=array_merge($testIdx, array_slice($idxList,0,$tCount));
  $trainIdx=array_merge($trainIdx, array_slice($idxList,$tCount));
}

// fallback: jika tidak ada test set sama sekali, paksa ambil 1 dari bucket terbesar
if(!$testIdx && $totalSamples>1){
  $largest=null; $maxN=0;
  foreach($buckets as $clsIdx=>$idxList){
    $cnt=count($idxList);
    if($cnt>$maxN){ $maxN=$cnt; $largest=$idxList; }
  }
  if($largest){
    shuffle($largest);
    $picked=array_shift($largest);
    $testIdx[]=$picked;
    $trainIdx=array_values(array_filter($trainIdx,function($v) use($picked){ return $v!==$picked; }));
  }
}

shuffle($trainIdx);
shuffle($testIdx);

$nTrain = count($trainIdx);
$nTest  = count($testIdx);

///////////////////////////// CLASS COUNTS & WEIGHTS ///////////
// hitung frekuensi train (sebelum oversample)
$trainCounts = array_fill(0, $numClasses, 0);
foreach($trainIdx as $idx){ $trainCounts[$y[$idx]]++; }

// class weight (auto => 1/freq, dinormalisasi agar rata-rata=1)
$classWeights = array_fill(0, $numClasses, 1.0);
if($classWeightMode === 'auto'){
  $totalTrain = array_sum($trainCounts);
  $avg = $numClasses>0 ? ($totalTrain / max($numClasses,1)) : 0;
  if($avg>0){
    foreach($trainCounts as $c=>$cnt){
      $classWeights[$c] = $cnt>0 ? ($avg / $cnt) : 1.0;
    }
  }
}

///////////////////////////// OVERSAMPLE MINORITY (TRAIN ONLY) //
if($oversampleMinor > 0 && $nTrain > 0){
  // bucketkan train per kelas
  $bucketTrain=[];
  foreach($trainIdx as $idx){
    $cls=$y[$idx];
    if(!isset($bucketTrain[$cls])) $bucketTrain[$cls]=[];
    $bucketTrain[$cls][]=$idx;
  }
  $maxCount = max($trainCounts);
  $target   = (int)ceil($maxCount * $oversampleMinor);
  if($target > 0){
    foreach($bucketTrain as $cls=>$list){
      $cnt=count($list);
      if($cnt===0 || $cnt >= $target) continue;
      // sampling dengan pengulangan sampai mencapai target
      for($k=$cnt; $k<$target; $k++){
        $pick=$list[$k % $cnt]; // deterministic cycle
        $trainIdx[]=$pick;
        $trainCounts[$cls]++; // update count setelah oversample
      }
    }
    shuffle($trainIdx);
    $nTrain = count($trainIdx);
  }
}

///////////////////////////// TRAIN (SGD) //////////////////////
// seed agar shuffle SGD reproducible
mt_srand(42);

$W=[];
for($c=0;$c<$numClasses;$c++){
  $W[$c]=array_fill(0,$dim,0.0); // satu vektor bobot per kelas
}
$n=$nTrain;
$start=microtime(true); $t=0; $epochTimes=[];
for($ep=0;$ep<$epochs;$ep++){
  $eStart=microtime(true);
  $order=$trainIdx; shuffle($order);
  foreach($order as $idx){
    // Refresh batas waktu agar tidak terhenti di tengah epoch (jaga-jaga jika environment membatasi 30s)
    @set_time_limit($MAX_EXEC_TIME);
    $t++; $eta=$eta0/(1.0+$lambda*$eta0*$t);
    $xi=$X[$idx]; $yi=$y[$idx]; $L=count($xi);
    $wSample = $classWeights[$yi] ?? 1.0; // bobot kelas (auto jika diaktifkan)
    // one-vs-rest: update bobot untuk tiap kelas
    for($c=0;$c<$numClasses;$c++){
      $yc = ($c===$yi)?+1.0:-1.0;
      $dot=0.0;
      for($k=0;$k<$L;$k++) $dot+=$W[$c][$k]*$xi[$k];
      if($yc*$dot<1.0){
        for($k=0;$k<$L;$k++) $W[$c][$k]-=$eta*($lambda*$W[$c][$k]-$wSample*$yc*$xi[$k]);
      }else{
        for($k=0;$k<$L;$k++) $W[$c][$k]-=$eta*($lambda*$W[$c][$k]);
      }
    }
  }
  $epochTimes[]=microtime(true)-$eStart;
}
$duration=microtime(true)-$start;
$avgEpoch=array_sum($epochTimes)/max(count($epochTimes),1);
$throughput=$n*$epochs/max($duration,1e-9);

///////////////////////////// TRAIN ACC ///////////////////////
$confTrain = emptyConfusion($numClasses);
$correct=0;
foreach($trainIdx as $idx){
  $xi=$X[$idx]; $yi=$y[$idx]; $L=count($xi);
  $bestIdx=null; $bestScore=null;
  for($c=0;$c<$numClasses;$c++){
    $dot=0.0;
    for($k=0;$k<$L;$k++) $dot+=$W[$c][$k]*$xi[$k];
    if($bestScore===null || $dot>$bestScore){
      $bestScore=$dot; $bestIdx=$c;
    }
  }
  $confTrain[$yi][$bestIdx]++;  // actual=yi, pred=bestIdx
  if($bestIdx===$yi) $correct++;
}
$trainAcc = $n>0 ? $correct/$n : 0.0;
$acc = $trainAcc; // backward compatibility

///////////////////////////// TEST ACC /////////////////////////
$confTest = $nTest>0 ? emptyConfusion($numClasses) : null;
$testAcc = null;
if($nTest>0){
  $correctTest = 0;
  foreach($testIdx as $idx){
    $xi=$X[$idx]; $yi=$y[$idx]; $L=count($xi);
    $bestIdx=null; $bestScore=null;
    for($c=0;$c<$numClasses;$c++){
      $dot=0.0;
      for($k=0;$k<$L;$k++) $dot+=$W[$c][$k]*$xi[$k];
      if($bestScore===null || $dot>$bestScore){
        $bestScore=$dot; $bestIdx=$c;
      }
    }
    if($confTest!==null) $confTest[$yi][$bestIdx]++; // actual=yi, pred=bestIdx
    if($bestIdx===$yi) $correctTest++;
  }
  $testAcc = $correctTest/$nTest;
}

///////////////////////////// SIMPAN MODEL /////////////////////
$modelPath=null;
if($SAVE_MODEL){
  $storageDir = function_exists('storage_path') ? storage_path('app/svm') : null;
  if($storageDir===null) $storageDir = $MODEL_DIR_FALLBACK;
  if(!is_dir($storageDir) && !@mkdir($storageDir,0755,true) && !is_dir($storageDir)){
    log_and_exit_fail($db,$userId,"Gagal membuat direktori model: {$storageDir}");
  }
  // Normalized path so logs tidak campur \\ dan /
  $modelPath = rtrim($storageDir, '/\\') . DIRECTORY_SEPARATOR . "svm_user_{$userId}_{$kcfg['type']}.json";
  $model = [
    'type'=>'svm_sgd',
    'dim'=>$dim,
    'weights'=>$W,
    'bias_index'=>$biasIndex,
    'lambda'=>$lambda,
    'epochs'=>$epochs,
    'eta0'=>$eta0,
    'goal_column'=>$goalKey,
    // Multi-class
    'classes'=>$classLabels,
    'num_classes'=>$numClasses,
    // Backward-compat (binary); diabaikan jika 'classes' tersedia
    'label_map'=>[
      '+1'=>$classLabels[0] ?? null,
      '-1'=>$classLabels[1] ?? null
    ],
    'feature_index'=>$baseIndex,
    'numeric_minmax'=>$nums,
    'kernel'=>$kcfg['type'],
    'kernel_meta'=>$kernelMeta,
  ];
  $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT;
  if(@file_put_contents($modelPath, json_encode($model, $jsonFlags))===false){
    log_and_exit_fail($db,$userId,"Gagal menulis model: {$modelPath}", $modelPath);
  }
}

///////////////////////////// LOG SUCCESS /////////////////////
$logTable="svm_user_{$userId}";
$db->query("CREATE TABLE IF NOT EXISTS `{$logTable}`(
  id INT AUTO_INCREMENT PRIMARY KEY,
  status VARCHAR(50),
  execution_time DECIMAL(12,6) NULL,
  model_path VARCHAR(1024) NULL,
  output LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
)");

$status='success';
$classesStr = implode('|', $classLabels);
$cmTrainText = formatConfusion($confTrain, $classLabels);
$cmTestText  = $confTest ? formatConfusion($confTest, $classLabels) : null;
$cmTrainJson = json_encode($confTrain, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
$cmTestJson  = $confTest ? json_encode($confTest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
$cwModeStr   = $classWeightMode==='auto' ? 'auto' : 'none';
$output="SVM {$kcfg['type']}. source={$sourceTable}; goal={$goalKey}; classes={$classesStr}; ".
        "samples_total={$totalSamples}; train={$nTrain}; test={$nTest}; ".
        "acc_train=".number_format($trainAcc*100,2)."%; ".
        "acc_test=".($testAcc!==null?number_format($testAcc*100,2):'NA')."%; ".
        "threshold={$DECISION_THRESHOLD}; ".
        "Execution time=".number_format($duration,4)."s; epoch_avg=".number_format($avgEpoch,4)."s; ".
        "throughput=".number_format($throughput,1)." samples/s; ".
        "oversample_minor={$oversampleMinor}; class_weight={$cwModeStr}; ".
        "cm_train={$cmTrainJson}; cm_test=".($cmTestJson ?? 'null');
$stmt=$db->prepare("INSERT INTO `{$logTable}`(status,execution_time,model_path,output,created_at,updated_at)
                    VALUES (?,?,?,?,NOW(),NOW())");
$stmt->bind_param('sdss',$status,$duration,$modelPath,$output);
$stmt->execute();

///////////////////////////// OUTPUT CONSOLE ///////////////////
echo "✅ SVM ({$kcfg['type']})\n";
echo "🔧 Hyper: epochs={$epochs}, lambda={$lambda}, eta0={$eta0}\n";
echo "🔧 Kernel meta: " . json_encode($kernelMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
echo "📊 Sampel: {$n} | Akurasi(train): ".number_format($acc*100,2)."%\n";
echo "⏱️ Total: ".number_format($duration,6)." s | Avg/epoch: ".number_format($avgEpoch,6).
     " s | Throughput: ".number_format($throughput,1)." samples/s\n";
echo "🎯 Kelas: +1={$posLabel}, -1={$negLabel}\n";
if($modelPath) echo "📦 Model: {$modelPath}\n";
if($cmTrainText) echo "Confusion matrix (train):\n{$cmTrainText}\n";
if($cmTestText)  echo "Confusion matrix (test):\n{$cmTestText}\n";

echo json_encode([
  'status'=>'success',
  'kernel'=>$kcfg['type'],
  'kernel_meta'=>$kernelMeta,
  'samples'=>[
    'total'=>$totalSamples,
    'train'=>$nTrain,
    'test'=>$nTest,
  ],
  'train_accuracy'=>$trainAcc,
  'test_accuracy'=>$testAcc,
  'threshold'=>$DECISION_THRESHOLD,
  'execution_time'=>[
    'total_sec'=>$duration,
    'avg_epoch'=>$avgEpoch,
    'throughput'=>$throughput
  ],
  'hyperparams'=>[
    'epochs'=>$epochs,
    'lambda'=>$lambda,
    'eta0'=>$eta0,
    'test_ratio'=>$testRatio,
    'oversample_minor'=>$oversampleMinor,
    'class_weight'=>$cwModeStr
  ],
  'model_path'=>$modelPath,
  'source_table'   => $sourceTable,
  'goal_column'    => $goalKey,
  'confusion'      => [
    'labels' => $classLabels,
    'train'  => $confTrain,
    'test'   => $confTest
  ]
], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;

$db->close();
