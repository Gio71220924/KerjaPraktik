@extends('layouts.admin')

@section('content')
<style>
  .svm-conf {overflow-x: auto;}
  .svm-conf table {border-collapse: collapse; min-width: 360px;}
  .svm-conf th, .svm-conf td {border: 1px solid #e5e7eb; padding: 6px 8px; text-align: center; font-size: 12px;}
  .svm-conf th {background: #f8fafc; font-weight: 600;}
  .svm-conf .axis {background: #f1f5f9; font-weight: 600;}
  .svm-distrib .bar {height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden;}
  .svm-distrib .fill {display: block; height: 100%; border-radius: 999px;}
  .bench-row {border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 10px; background: #f8fafc;}
  .bench-label {font-weight: 600; margin-bottom: 6px;}
  .bench-kernels {display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;}
  .bench-bar-track {height: 10px; background: #e5e7eb; border-radius: 999px; overflow: hidden;}
  .bench-bar-fill {display: block; height: 100%; border-radius: 999px;}
  .bench-meta {font-size: 12px; color: #6b7280;}
</style>
<div class="container mt-4">
  <h2>Support Vector Machine (SVM)</h2>
  <hr>

  {{-- Flash --}}
  @if(session('svm_ok'))
    <div class="alert alert-success"><pre class="mb-0">{{ session('svm_ok') }}</pre></div>
  @endif
  @if(session('svm_err'))
    <div class="alert alert-danger"><pre class="mb-0">{{ session('svm_err') }}</pre></div>
  @endif
  @php $meta = session('svm_meta'); @endphp
  @if(is_array($meta) && !empty($meta))
    @php
      $samples   = $meta['samples']      ?? [];
      $predict   = $meta['predict']      ?? null;
      $hasPredict= is_array($predict);
      $predConf  = $hasPredict ? ($predict['confidence'] ?? null) : null;
      $predLabel = $hasPredict ? ($predict['label'] ?? null) : null;
      $predKernel= $hasPredict ? ($predict['kernel'] ?? ($meta['kernel'] ?? null)) : null;
      $trainAcc  = isset($meta['train_accuracy']) ? $meta['train_accuracy'] : null;
      $testAcc   = isset($meta['test_accuracy'])  ? $meta['test_accuracy']  : null;
      $threshold = $meta['threshold']      ?? null;
      $hyper     = $meta['hyperparams']    ?? [];
      $conf      = $meta['confusion']      ?? null;
      $labels    = $conf['labels'] ?? [];
      $cmTrain   = $conf['train']  ?? null;
      $cmTest    = $conf['test']   ?? null;
      // distribusi label aktual dari confusion matrix (train/test)
      $distTrain = [];
      $distTest  = [];
      if (is_array($cmTrain)) {
        foreach ($cmTrain as $i => $row) {
          $distTrain[] = [
            'label' => $labels[$i] ?? "Label {$i}",
            'total' => array_sum($row),
          ];
        }
      }
      if (is_array($cmTest)) {
        foreach ($cmTest as $i => $row) {
          $distTest[] = [
            'label' => $labels[$i] ?? "Label {$i}",
            'total' => array_sum($row),
          ];
        }
      }
      $palette = ['#2563eb','#f97316','#10b981','#a855f7','#ef4444','#14b8a6','#f59e0b','#06b6d4'];
    @endphp
    @if(!$hasPredict)
      <div class="alert alert-info">
        <strong>Hasil training SVM:</strong>
        <ul class="mb-0">
          <li>Akurasi train: {{ is_numeric($trainAcc) ? number_format($trainAcc * 100, 2) . '%' : 'NA' }}</li>
          <li>Akurasi test: {{ is_numeric($testAcc) ? number_format($testAcc * 100, 2) . '%' : 'NA' }}</li>
          <li>Kernel yang dipilih: {{ $meta['kernel'] ?? '(tidak tersedia)' }}</li>
          <li>Hyperparameter: epochs = {{ $hyper['epochs'] ?? '?' }}, lambda = {{ $hyper['lambda'] ?? '?' }}, eta0 = {{ $hyper['eta0'] ?? '?' }}, test_ratio = {{ $hyper['test_ratio'] ?? '0.2' }}</li>
          <li>Jumlah data (train/test): {{ $samples['train'] ?? '?' }} / {{ $samples['test'] ?? '?' }}</li>
        </ul>
      </div>
    @endif

    @if($hasPredict)
      <div class="alert alert-primary">
        <strong>Hasil prediksi SVM:</strong>
        @php $top = $predict['top'] ?? []; @endphp
        <ul class="mb-0">
          <li>Prediksi terakhir: {{ $predLabel ?? '(tidak tersedia)' }}</li>
          <li>
            Akurasi prediksi (estimasi keyakinan):
            @if($predConf !== null)
              {{ number_format($predConf*100,2) }}%
              @if($predConf < 0.7)
                <span class="text-warning">(model kurang yakin, &lt; 70%)</span>
              @else
                <span class="text-success">(model cukup/sangat yakin)</span>
              @endif
            @else
              NA
            @endif
          </li>
          <li>Kernel yang dipakai: {{ $predKernel ?? '(tidak tersedia)' }}</li>
          @if(is_array($top) && count($top) > 0)
            <li>
              Top rekomendasi keputusan:
              <ol class="mb-0">
                @foreach($top as $item)
                  <li>
                    {{ $item['label'] ?? '?' }}
                    ({{ isset($item['confidence']) ? number_format($item['confidence']*100,2) . '%' : 'NA' }})
                  </li>
                @endforeach
              </ol>
            </li>
          @endif
          <li>Threshold keputusan: {{ $threshold !== null ? $threshold : 'default (0.0)' }}</li>
        </ul>
      </div>

      {{-- Visualisasi sederhana setelah Predict --}}
      @php
        $confPercent = $predConf !== null ? max(0, min(100, $predConf * 100)) : null;
      @endphp
      @if($confPercent !== null || (is_array($top) && count($top) > 0))
        <div class="mt-2">
          <h6 class="mb-2">Visualisasi Hasil Prediksi</h6>

          {{-- Bar utama untuk label terprediksi --}}
          @if($confPercent !== null)
            <div class="mb-3">
              <div class="d-flex justify-content-between mb-1">
                <span>Prediksi: <strong>{{ $predLabel }}</strong></span>
                <span>{{ number_format($confPercent, 2) }}%</span>
              </div>
              <div class="progress" style="height: 18px;">
                <div
                  class="progress-bar {{ $confPercent < 70 ? 'bg-warning' : 'bg-success' }}"
                  role="progressbar"
                  style="width: {{ $confPercent }}%;"
                  aria-valuenow="{{ $confPercent }}"
                  aria-valuemin="0"
                  aria-valuemax="100"
                ></div>
              </div>
            </div>
          @endif

          {{-- Bar untuk top-N rekomendasi --}}
          @if(is_array($top) && count($top) > 0)
            <div>
              <small class="text-muted d-block mb-1">Top rekomendasi (semakin panjang bar, semakin tinggi keyakinan):</small>
              @foreach($top as $item)
                @php
                  $c = isset($item['confidence']) ? max(0, min(100, $item['confidence'] * 100)) : null;
                @endphp
                <div class="mb-2">
                  <div class="d-flex justify-content-between">
                    <span>{{ $item['label'] ?? '?' }}</span>
                    <span>{{ $c !== null ? number_format($c,2).'%' : 'NA' }}</span>
                  </div>
                  @if($c !== null)
                    <div class="progress" style="height: 12px;">
                      <div
                        class="progress-bar bg-info"
                        role="progressbar"
                        style="width: {{ $c }}%;"
                        aria-valuenow="{{ $c }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                      ></div>
                    </div>
                  @else
                    <div class="progress" style="height: 12px;">
                      <div class="progress-bar bg-secondary" style="width: 100%;"></div>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @endif
    @endif

    {{-- Confusion matrix train/test (langsung tampil setelah predict) --}}
    @if(isset($conf) && is_array($conf) && is_array($labels) && count($labels) > 0)
      <div class="card mb-4" id="svm-confusion">
        <div class="card-header d-flex justify-content-between align-items-center fw-semibold">
          <span>Confusion Matrix (Train/Test)</span>
          <button
            class="btn btn-sm btn-outline-secondary"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#svm-confusion-body"
            aria-expanded="true"
            aria-controls="svm-confusion-body"
          >
            Tampilkan/Sembunyikan
          </button>
        </div>
        <div class="collapse show" id="svm-confusion-body">
          <div class="card-body">
            <div class="row g-4">
            @if(is_array($cmTrain))
              @php
                $maxTrain = 0;
                foreach ($cmTrain as $r) foreach ($r as $v) { if ($v > $maxTrain) $maxTrain = $v; }
                $totalTrainRow = array_sum(array_map('array_sum', $cmTrain));
              @endphp
              <div class="col-lg-6">
                <h6 class="mb-2">Train</h6>
                <div class="svm-conf mb-3">
                  <table>
                    <thead>
                      <tr>
                        <th class="axis">Actual \\ Pred</th>
                        @foreach($labels as $lbl)
                          <th>{{ $lbl }}</th>
                        @endforeach
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($cmTrain as $i => $row)
                        <tr>
                          <th class="axis">{{ $labels[$i] ?? $i }}</th>
                          @foreach($row as $v)
                            @php
                              $ratio = $maxTrain > 0 ? $v / $maxTrain : 0;
                              $alpha = 0.18 + (0.55 * $ratio);
                              $bg    = "rgba(37, 99, 235, {$alpha})";
                              $fg    = $ratio > 0.55 ? '#fff' : '#111';
                            @endphp
                            <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                          @endforeach
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @if(!empty($distTrain))
                  @php $totalTrain = max(1, array_sum(array_column($distTrain, 'total'))); @endphp
                  <div class="svm-distrib">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="fw-semibold">Distribusi Label (Train)</span>
                      <small class="text-muted">Total {{ $totalTrainRow }} sampel</small>
                    </div>
                    @foreach($distTrain as $i => $d)
                      @php
                        $p = $totalTrain > 0 ? ($d['total'] / $totalTrain) * 100 : 0;
                        $color = $palette[$i % count($palette)];
                      @endphp
                      <div class="mb-2">
                        <div class="d-flex justify-content-between small text-muted">
                          <span class="fw-semibold text-dark">{{ $d['label'] }}</span>
                          <span>{{ number_format($p,1) }}% ({{ $d['total'] }})</span>
                        </div>
                        <div class="bar">
                          <span class="fill" style="width: {{ max(3, $p) }}%; background: {{ $color }};"></span>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            @endif

            @if(is_array($cmTest))
              @php
                $maxTest = 0;
                foreach ($cmTest as $r) foreach ($r as $v) { if ($v > $maxTest) $maxTest = $v; }
                $totalTestRow = array_sum(array_map('array_sum', $cmTest));
              @endphp
              <div class="col-lg-6">
                <h6 class="mb-2">Test</h6>
                <div class="svm-conf mb-3">
                  <table>
                    <thead>
                      <tr>
                        <th class="axis">Actual \\ Pred</th>
                        @foreach($labels as $lbl)
                          <th>{{ $lbl }}</th>
                        @endforeach
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($cmTest as $i => $row)
                        <tr>
                          <th class="axis">{{ $labels[$i] ?? $i }}</th>
                          @foreach($row as $v)
                            @php
                              $ratio = $maxTest > 0 ? $v / $maxTest : 0;
                              $alpha = 0.18 + (0.55 * $ratio);
                              $bg    = "rgba(16, 185, 129, {$alpha})";
                              $fg    = $ratio > 0.55 ? '#fff' : '#111';
                            @endphp
                            <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                          @endforeach
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @if(!empty($distTest))
                  @php $totalTest = max(1, array_sum(array_column($distTest, 'total'))); @endphp
                  <div class="svm-distrib">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                      <span class="fw-semibold">Distribusi Label (Test)</span>
                      <small class="text-muted">Total {{ $totalTestRow }} sampel</small>
                    </div>
                    @foreach($distTest as $i => $d)
                      @php
                        $p = $totalTest > 0 ? ($d['total'] / $totalTest) * 100 : 0;
                        $color = $palette[$i % count($palette)];
                      @endphp
                      <div class="mb-2">
                        <div class="d-flex justify-content-between small text-muted">
                          <span class="fw-semibold text-dark">{{ $d['label'] }}</span>
                          <span>{{ number_format($p,1) }}% ({{ $d['total'] }})</span>
                        </div>
                        <div class="bar">
                          <span class="fill" style="width: {{ max(3, $p) }}%; background: {{ $color }};"></span>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            @endif
            </div>
          </div>
        </div>
      </div>
  @endif
  @endif

  {{-- Benchmark ringkas: Akurasi & waktu eksekusi per kernel --}}
  @php
    $bench = $benchmarks ?? [];
    $benchCase = $bench['case_title'] ?? ('Case ' . Auth::id());
    $benchKernels = $bench['kernels'] ?? [];
    $benchColors = ['sgd' => '#2563eb', 'rbf' => '#ef4444', 'sigmoid' => '#10b981', 'sig' => '#10b981'];
    $benchMaxTime = 0.0;
    foreach ($benchKernels as $bk) {
      $t = isset($bk['exec_time']) && is_numeric($bk['exec_time']) ? (float)$bk['exec_time'] : 0.0;
      if ($t > $benchMaxTime) $benchMaxTime = $t;
    }
    $benchMaxTime = $benchMaxTime > 0 ? $benchMaxTime : 1.0;
  @endphp
  <div class="card mb-4" id="svm-benchmark">
    <div class="card-header fw-semibold">Benchmark Kasus Anda</div>
    <div class="card-body">
      <p class="text-muted small mb-3">Metrik diambil dari log training terbaru per kernel untuk {{ $benchCase }} (user {{ Auth::id() }}). Jika belum pernah train, bagian ini akan kosong.</p>

      @if(empty($benchKernels))
        <div class="alert alert-info mb-0">Belum ada data training SVM untuk ditampilkan.</div>
      @else
        <h6 class="mb-2">Akurasi (Train vs Test)</h6>
        <div class="bench-kernels mb-3">
          @foreach($benchKernels as $key => $b)
            @php
              $trainAcc = is_numeric($b['acc_train'] ?? null) ? (float)$b['acc_train'] : null;
              $testAcc  = is_numeric($b['acc_test'] ?? null)  ? (float)$b['acc_test']  : null;
              $trainPct = $trainAcc !== null ? max(3, min(100, $trainAcc)) : 0;
              $testPct  = $testAcc  !== null ? max(3, min(100, $testAcc )) : 0;
              $color    = $benchColors[strtolower($key)] ?? '#475569';
            @endphp
            <div class="bench-row">
              <div class="bench-label">Kernel {{ strtoupper($key) }}</div>
              <div class="bench-bar-track">
                <span class="bench-bar-fill" style="width: {{ $trainPct }}%; background: {{ $color }};"></span>
              </div>
              <div class="bench-meta mt-1">Train: {{ $trainAcc !== null ? number_format($trainAcc, 2) . '%' : 'NA' }}</div>
              <div class="bench-bar-track mt-2">
                <span class="bench-bar-fill" style="width: {{ $testPct }}%; background: {{ $color }}; opacity: 0.65;"></span>
              </div>
              <div class="bench-meta mt-1">Test: {{ $testAcc !== null ? number_format($testAcc, 2) . '%' : 'NA' }}</div>
              <div class="bench-meta mt-2">Sampel: train {{ $b['train_samples'] ?? 'NA' }}, test {{ $b['test_samples'] ?? 'NA' }}</div>
              @if(!empty($b['created_at']))
                <div class="bench-meta mt-1">Log: {{ $b['created_at'] }}</div>
              @endif
            </div>
          @endforeach
        </div>

        {{-- Confusion matrix per kernel --}}
        <div class="card mb-3">
          <div class="card-header fw-semibold">Confusion Matrix per Kernel</div>
          <div class="card-body">
            @foreach($benchKernels as $key => $b)
              @php
                $cmT = $b['cm_train'] ?? null;
                $cmS = $b['cm_test']  ?? null;
                $labels = $b['labels'] ?? [];
                $dim = is_array($cmT) ? count($cmT) : (is_array($cmS) ? count($cmS) : 0);
                if (empty($labels) && $dim > 0) {
                  $labels = [];
                  for ($i = 0; $i < $dim; $i++) { $labels[] = "Label {$i}"; }
                }
              @endphp
              <div class="mb-3">
                <h6 class="mb-2">Kernel {{ strtoupper($key) }}</h6>
                @if(!is_array($cmT) && !is_array($cmS))
                  <div class="text-muted">Belum ada confusion matrix untuk kernel ini.</div>
                @else
                  <div class="row g-3">
                    @if(is_array($cmT))
                      @php $maxT = 0; foreach ($cmT as $r) foreach ($r as $v) { if ($v > $maxT) $maxT = $v; } @endphp
                      <div class="col-md-6">
                        <div class="svm-conf">
                          <table class="mb-2">
                            <thead>
                              <tr>
                                <th class="axis">Train<br>Actual \\ Pred</th>
                                @foreach($labels as $lbl)
                                  <th>{{ $lbl }}</th>
                                @endforeach
                              </tr>
                            </thead>
                            <tbody>
                              @foreach($cmT as $i => $row)
                                <tr>
                                  <th class="axis">{{ $labels[$i] ?? $i }}</th>
                                  @foreach($row as $v)
                                    @php
                                      $ratio = $maxT > 0 ? $v / $maxT : 0;
                                      $alpha = 0.18 + (0.55 * $ratio);
                                      $bg    = "rgba(37, 99, 235, {$alpha})";
                                      $fg    = $ratio > 0.55 ? '#fff' : '#111';
                                    @endphp
                                    <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                                  @endforeach
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      </div>
                    @endif
                    @if(is_array($cmS))
                      @php $maxS = 0; foreach ($cmS as $r) foreach ($r as $v) { if ($v > $maxS) $maxS = $v; } @endphp
                      <div class="col-md-6">
                        <div class="svm-conf">
                          <table class="mb-2">
                            <thead>
                              <tr>
                                <th class="axis">Test<br>Actual \\ Pred</th>
                                @foreach($labels as $lbl)
                                  <th>{{ $lbl }}</th>
                                @endforeach
                              </tr>
                            </thead>
                            <tbody>
                              @foreach($cmS as $i => $row)
                                <tr>
                                  <th class="axis">{{ $labels[$i] ?? $i }}</th>
                                  @foreach($row as $v)
                                    @php
                                      $ratio = $maxS > 0 ? $v / $maxS : 0;
                                      $alpha = 0.18 + (0.55 * $ratio);
                                      $bg    = "rgba(16, 185, 129, {$alpha})";
                                      $fg    = $ratio > 0.55 ? '#fff' : '#111';
                                    @endphp
                                    <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                                  @endforeach
                                </tr>
                              @endforeach
                            </tbody>
                          </table>
                        </div>
                      </div>
                    @endif
                  </div>
                @endif
              </div>
            @endforeach
          </div>
        </div>

        <h6 class="mt-4 mb-2">Waktu Eksekusi (detik)</h6>
        <div class="bench-kernels mb-3">
          @foreach($benchKernels as $key => $b)
            @php
              $time  = is_numeric($b['exec_time'] ?? null) ? (float)$b['exec_time'] : null;
              $ratio = $time !== null ? max(4, min(100, ($time / $benchMaxTime) * 100)) : 0;
              $color = $benchColors[strtolower($key)] ?? '#475569';
            @endphp
            <div class="bench-row">
              <div class="bench-label">Kernel {{ strtoupper($key) }}</div>
              <div class="bench-bar-track">
                <span class="bench-bar-fill" style="width: {{ $ratio }}%; background: {{ $color }};"></span>
              </div>
              <div class="bench-meta mt-1">Waktu: {{ $time !== null ? number_format($time, 4) . ' s' : 'NA' }}</div>
            </div>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- Confusion matrix satu kartu (log training terbaru) --}}
  @if(!empty($benchKernels))
    @php
      $latestKey = null; $latest = null; $latestTs = null;
      foreach ($benchKernels as $key => $b) {
        $ts = isset($b['created_at']) ? strtotime((string)$b['created_at']) : null;
        if ($latest === null || ($ts !== null && $ts >= ($latestTs ?? -INF))) {
          $latest = $b; $latestKey = $key; $latestTs = $ts;
        }
      }
    @endphp
    <div class="card mb-4" id="svm-benchmark-cm">
      <div class="card-header fw-semibold">Confusion Matrix (Log Terakhir)</div>
      <div class="card-body">
        @if($latest === null)
          <div class="alert alert-info mb-0">Belum ada log training yang memuat confusion matrix.</div>
        @else
          <p class="text-muted small mb-3">Diambil dari log training terakhir (kernel {{ strtoupper($latestKey ?? '-') }}).</p>
          @php
            $cmTrain = $latest['cm_train'] ?? null;
            $cmTest  = $latest['cm_test']  ?? null;
            $labels  = $latest['labels']   ?? [];
            $dimTrain= is_array($cmTrain) ? count($cmTrain) : 0;
            $dimTest = is_array($cmTest)  ? count($cmTest)  : 0;
            $dim     = $dimTrain > 0 ? $dimTrain : $dimTest;
            if (empty($labels) && $dim > 0) {
              $labels = [];
              for ($i = 0; $i < $dim; $i++) { $labels[] = "Label {$i}"; }
            }
            // distribusi label actual (train/test) + total dataset
            $distTrain = [];
            $distTest  = [];
            if (is_array($cmTrain)) {
              foreach ($cmTrain as $i => $row) {
                $distTrain[] = [
                  'label' => $labels[$i] ?? "Label {$i}",
                  'total' => array_sum($row),
                ];
              }
            }
            if (is_array($cmTest)) {
              foreach ($cmTest as $i => $row) {
                $distTest[] = [
                  'label' => $labels[$i] ?? "Label {$i}",
                  'total' => array_sum($row),
                ];
              }
            }
            $totalTrain = array_sum(array_column($distTrain, 'total')) ?: 0;
            $totalTest  = array_sum(array_column($distTest, 'total')) ?: 0;
            $palette = ['#2563eb','#f97316','#10b981','#a855f7','#ef4444','#14b8a6','#f59e0b','#06b6d4'];
          @endphp
          <div class="bench-row">
            <div class="bench-label d-flex justify-content-between">
              <span>Kernel {{ strtoupper($latestKey ?? '-') }}</span>
              @if(!empty($latest['created_at']))
                <small class="text-muted">Log: {{ $latest['created_at'] }}</small>
              @endif
            </div>
            <div class="bench-meta mb-2">
              Total dataset (log ini): {{ $totalTrain + $totalTest }} sampel (train {{ $totalTrain }}, test {{ $totalTest }})
            </div>
            @if(!is_array($cmTrain) && !is_array($cmTest))
              <div class="bench-meta">Confusion matrix belum tersedia pada log terakhir.</div>
            @else
              <div class="row g-4">
                @if(is_array($cmTrain))
                  @php
                    $maxTrain = 0;
                    foreach ($cmTrain as $r) foreach ($r as $v) { if ($v > $maxTrain) $maxTrain = $v; }
                  @endphp
                  <div class="col-lg-6">
                    <div class="fw-semibold mb-2">Train</div>
                    <div class="svm-conf mb-2">
                      <table>
                        <thead>
                          <tr>
                            <th class="axis">Actual \\ Pred</th>
                            @foreach($labels as $lbl)
                              <th>{{ $lbl }}</th>
                            @endforeach
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($cmTrain as $i => $row)
                            <tr>
                              <th class="axis">{{ $labels[$i] ?? $i }}</th>
                              @foreach($row as $v)
                                @php
                                  $ratio = $maxTrain > 0 ? $v / $maxTrain : 0;
                                  $alpha = 0.18 + (0.55 * $ratio);
                                  $bg    = "rgba(37, 99, 235, {$alpha})";
                                  $fg    = $ratio > 0.55 ? '#fff' : '#111';
                                @endphp
                                  <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                              @endforeach
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                @endif

                @if(is_array($cmTest))
                  @php
                    $maxTest = 0;
                    foreach ($cmTest as $r) foreach ($r as $v) { if ($v > $maxTest) $maxTest = $v; }
                  @endphp
                  <div class="col-lg-6">
                    <div class="fw-semibold mb-2">Test</div>
                    <div class="svm-conf mb-2">
                      <table>
                        <thead>
                          <tr>
                            <th class="axis">Actual \\ Pred</th>
                            @foreach($labels as $lbl)
                              <th>{{ $lbl }}</th>
                            @endforeach
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($cmTest as $i => $row)
                            <tr>
                              <th class="axis">{{ $labels[$i] ?? $i }}</th>
                              @foreach($row as $v)
                                @php
                                  $ratio = $maxTest > 0 ? $v / $maxTest : 0;
                                  $alpha = 0.18 + (0.55 * $ratio);
                                  $bg    = "rgba(16, 185, 129, {$alpha})";
                                  $fg    = $ratio > 0.55 ? '#fff' : '#111';
                                @endphp
                                <td style="background: {{ $bg }}; color: {{ $fg }};">{{ $v }}</td>
                              @endforeach
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                @endif
              </div>
              {{-- Distribusi label actual --}}
              <div class="row g-4 mt-2">
                @if(!empty($distTrain))
                  <div class="col-lg-6">
                    <div class="svm-distrib">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Distribusi Label (Train)</span>
                        <small class="text-muted">Total {{ $totalTrain }}</small>
                      </div>
                      @foreach($distTrain as $i => $d)
                        @php
                          $p = $totalTrain > 0 ? ($d['total'] / $totalTrain) * 100 : 0;
                          $color = $palette[$i % count($palette)];
                        @endphp
                        <div class="mb-2">
                          <div class="d-flex justify-content-between small text-muted">
                            <span class="fw-semibold text-dark">{{ $d['label'] }}</span>
                            <span>{{ number_format($p,1) }}% ({{ $d['total'] }})</span>
                          </div>
                          <div class="bar">
                            <span class="fill" style="width: {{ max(3, $p) }}%; background: {{ $color }};"></span>
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif
                @if(!empty($distTest))
                  <div class="col-lg-6">
                    <div class="svm-distrib">
                      <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">Distribusi Label (Test)</span>
                        <small class="text-muted">Total {{ $totalTest }}</small>
                      </div>
                      @foreach($distTest as $i => $d)
                        @php
                          $p = $totalTest > 0 ? ($d['total'] / $totalTest) * 100 : 0;
                          $color = $palette[$i % count($palette)];
                        @endphp
                        <div class="mb-2">
                          <div class="d-flex justify-content-between small text-muted">
                            <span class="fw-semibold text-dark">{{ $d['label'] }}</span>
                            <span>{{ number_format($p,1) }}% ({{ $d['total'] }})</span>
                          </div>
                          <div class="bar">
                            <span class="fill" style="width: {{ max(3, $p) }}%; background: {{ $color }};"></span>
                          </div>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif
              </div>
            @endif
          </div>
        @endif
      </div>
    </div>
  @endif

  {{-- ======== TRAIN MANUAL (case_num & sumber training otomatis) ======== --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">
      Train (Otomatis: case_user_{{ Auth::id() }})
    </div>
    <div class="card-body">
      <form action="{{ route('SVM.generate') }}" method="POST" class="row g-3">
        @csrf
        {{-- case_num & table override tidak perlu, semuanya otomatis --}}
        <div class="col-md-6">
          <label class="form-label">Kernel</label>
          <select name="kernel" class="form-select">
            <option value="sgd">SGD (Linear)</option>
            <option value="rbf:D=128:gamma=0.25">RBF — D=128, γ=0.25</option>
            <option value="sigmoid:D=128:scale=1.0:coef0=0.0">Sigmoid — D=128, scale=1.0, coef0=0.0</option>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary w-100">Train</button>
        </div>
      </form>
      <small class="text-muted">Sumber training otomatis: <code>case_user_{{ Auth::id() }}</code>. case_num otomatis: <code>{{ Auth::id() }}</code>.</small>
    </div>
  </div>

  {{-- ======== PREDICT DARI INPUT ATRIBUT (tanpa Goal, tanpa Sumber Training) ======== --}}
  <div class="card mb-4">
    <div class="card-header fw-semibold">Predict dari Input Atribut</div>
    <div class="card-body">
      <form action="{{ route('SVM.storeCase') }}" method="POST" id="svmPredictForm">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Kernel</label>
            {{-- Hanya untuk UI; nilai sebenarnya dikirim lewat hidden #kernelHidden --}}
            <select id="kernelSelect" class="form-select" required>
              <option value="sgd" selected>SGD (Linear)</option>
              <option value="rbf:D=128:gamma=0.25">RBF — D=128, γ=0.25</option>
              <option value="sigmoid:D=128:scale=1.0:coef0=0.0">Sigmoid — D=128, scale=1.0, coef0=0.0</option>
              <option value="custom">Custom…</option>
            </select>
            <small class="text-muted">Format: <code>sgd</code>, <code>rbf:D=...:gamma=...</code>, <code>sigmoid:D=...:scale=...:coef0=...</code></small>
          </div>

          {{-- Panel Custom Kernel --}}
          <div class="col-12"></div>
          <div class="col-md-2 d-none" id="customTypeWrap">
            <label class="form-label">Tipe Custom</label>
            <select id="customType" class="form-select">
              <option value="rbf" selected>rbf</option>
              <option value="sigmoid">sigmoid</option>
              <option value="sgd">sgd</option>
            </select>
          </div>
          <div class="col-md-2 d-none" id="customDWrap">
            <label class="form-label">D</label>
            <input type="number" min="1" step="1" id="customD" class="form-control" value="128">
          </div>
          <div class="col-md-2 d-none" id="customGammaWrap">
            <label class="form-label">gamma (RBF)</label>
            <input type="number" step="0.01" id="customGamma" class="form-control" value="0.25">
          </div>
          <div class="col-md-2 d-none" id="customScaleWrap">
            <label class="form-label">scale (Sigmoid)</label>
            <input type="number" step="0.1" id="customScale" class="form-control" value="1.0">
          </div>
          <div class="col-md-2 d-none" id="customCoef0Wrap">
            <label class="form-label">coef0 (Sigmoid)</label>
            <input type="number" step="0.1" id="customCoef0" class="form-control" value="0.0">
          </div>

          {{-- Nilai kernel yang dikirim ke server --}}
          <input type="hidden" name="kernel" id="kernelHidden" value="sgd">
        </div>

        <hr>

        {{-- Atribut non-goal (dari tabel atribut) --}}
        @if(!empty($atributs) && count($atributs) > 0)
          <div class="mb-3">
            <h6 class="mb-2">Atribut</h6>
            <div class="row g-3">
              @foreach($atributs as $a)
                @php $vals = $valuesByAttr[$a->atribut_id] ?? collect(); @endphp
                <div class="col-md-4">
                  <label class="form-label">{{ ucfirst($a->atribut_name) }}</label>
                  <select name="attr[{{ $a->atribut_id }}]" class="form-select">
                    <option value="">-- pilih --</option>
                    @foreach($vals as $v)
                      <option value="{{ $v->value_id.'_'.$v->value_name }}">
                        {{ explode('_', $v->value_name, 2)[1] ?? $v->value_name }}
                      </option>
                    @endforeach
                  </select>
                  <small class="text-muted">Kolom: <code>{{ $a->atribut_id.'_'.$a->atribut_name }}</code></small>
                </div>
              @endforeach
            </div>
          </div>
        @endif

        {{-- Tidak ada Goal & Tidak ada input kolom case_user --}}
        <div class="mt-2">
          <button class="btn btn-primary">Generate & Predict</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Riwayat (terbaru di atas, nomor urut tampilan) --}}
  @php
    // Jika controller sudah mengembalikan DESC & tanpa 'case', blok ini tetap aman.
    // Kalau masih ada 'case', kita filter di sini juga biar bersih.
    $svmRows = collect($svmData ?? [])->filter(fn($r) => ($r->status ?? '') !== 'case')->values();
  @endphp

  @if($svmRows->count() > 0)
    <h5>Riwayat Training:</h5>
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">No.</th>   {{-- nomor urut tampilan --}}
            <th style="width:120px;">Status</th>
            <th>Output</th>
            <th style="width:200px;">Tanggal</th>
          </tr>
        </thead>
        <tbody>
          @foreach($svmRows as $item)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>
                <span class="badge {{ $item->status === 'success' ? 'bg-success' : 'bg-danger' }}">
                  {{ $item->status }}
                </span>
              </td>
              <td><pre class="mb-0" style="white-space:pre-wrap">{{ $item->output }}</pre></td>
              <td>{{ $item->created_at }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="alert alert-info">Belum ada riwayat training.</div>
  @endif
</div>

{{-- JS untuk compose custom kernel --}}
<script>
(function(){
  const sel = document.getElementById('kernelSelect');
  const hidden = document.getElementById('kernelHidden');

  const wrap = {
    type:  document.getElementById('customTypeWrap'),
    D:     document.getElementById('customDWrap'),
    gamma: document.getElementById('customGammaWrap'),
    scale: document.getElementById('customScaleWrap'),
    coef0: document.getElementById('customCoef0Wrap'),
  };
  const fld = {
    type:  document.getElementById('customType'),
    D:     document.getElementById('customD'),
    gamma: document.getElementById('customGamma'),
    scale: document.getElementById('customScale'),
    coef0: document.getElementById('customCoef0'),
  };

  function toggleCustom(show){
    [wrap.type, wrap.D].forEach(e => e.classList.toggle('d-none', !show));
    wrap.gamma.classList.toggle('d-none', !show || fld.type.value !== 'rbf');
    const showSig = show && fld.type.value === 'sigmoid';
    wrap.scale.classList.toggle('d-none', !showSig);
    wrap.coef0.classList.toggle('d-none', !showSig);
  }
  function compose(){
    if (sel.value !== 'custom') { hidden.value = sel.value; return; }
    const t = fld.type.value;
    if (t === 'sgd') {
      hidden.value = 'sgd';
    } else if (t === 'rbf') {
      const D = Math.max(1, parseInt(fld.D.value||'1024', 10));
      const g = parseFloat(fld.gamma.value||'0.25');
      hidden.value = `rbf:D=${D}:gamma=${g}`;
    } else if (t === 'sigmoid') {
      const D = Math.max(1, parseInt(fld.D.value||'1024', 10));
      const s = parseFloat(fld.scale.value||'1.0');
      const c = parseFloat(fld.coef0.value||'0.0');
      hidden.value = `sigmoid:D=${D}:scale=${s}:coef0=${c}`;
    }
  }

  hidden.value = sel.value;
  toggleCustom(false); compose();

  sel.addEventListener('change', ()=>{ toggleCustom(sel.value==='custom'); compose(); });
  [fld.type, fld.D, fld.gamma, fld.scale, fld.coef0].forEach(el=>{
    el.addEventListener('input', compose);
    el.addEventListener('change', compose);
  });
})();
</script>
@endsection
