@extends('layouts.admin')

@section('content')
<div class="container mt-4">
  <h2>Support Vector Machine (SVM)</h2>
  <hr>

  {{-- Distribusi kelas di case_user (data training) --}}
  @if(isset($classStats) && count($classStats) > 0)
    @php
      $totalCases = $classStats->sum('total');
    @endphp
    <div class="card mb-3">
      <div class="card-header fw-semibold">Distribusi Data Training (case_user_{{ Auth::id() }})</div>
      <div class="card-body">
        <ul class="mb-0">
          @foreach($classStats as $row)
            @php
              $p = $totalCases > 0 ? ($row->total / $totalCases) * 100 : 0;
            @endphp
            <li>
              {{ $row->label }} &mdash;
              {{ $row->total }} data ({{ number_format($p, 1) }}%)
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif

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
      $predict   = $meta['predict']      ?? [];
      $predConf  = $predict['confidence'] ?? null;
      $predLabel = $predict['label']      ?? null;
      $threshold = $meta['threshold']      ?? null;
      $hyper     = $meta['hyperparams']    ?? [];
    @endphp
    <div class="alert alert-info">
      <strong>Hasil predict SVM (Generate &amp; Predict):</strong>
      <ul class="mb-0">
        <li>Jumlah data: latih = {{ $samples['train'] ?? '?' }}, uji = {{ $samples['test'] ?? '?' }} (total = {{ $samples['total'] ?? '?' }})</li>
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
        @php $top = $predict['top'] ?? []; @endphp
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
        <li>Hyperparameter: epochs = {{ $hyper['epochs'] ?? '?' }}, lambda = {{ $hyper['lambda'] ?? '?' }}, eta0 = {{ $hyper['eta0'] ?? '?' }}, test_ratio = {{ $hyper['test_ratio'] ?? '0.2' }}</li>
      </ul>

      {{-- Visualisasi sederhana setelah Generate & Predict --}}
      @php
        $confPercent = $predConf !== null ? max(0, min(100, $predConf * 100)) : null;
      @endphp
      @if($confPercent !== null || (is_array($top) && count($top) > 0))
        <hr>
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
            <option value="rbf:D=1024:gamma=0.25">RBF — D=1024, γ=0.25</option>
            <option value="sigmoid:D=1024:scale=1.0:coef0=0.0">Sigmoid — D=1024, scale=1.0, coef0=0.0</option>
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
              <option value="rbf:D=1024:gamma=0.25">RBF — D=1024, γ=0.25</option>
              <option value="sigmoid:D=1024:scale=1.0:coef0=0.0">Sigmoid — D=1024, scale=1.0, coef0=0.0</option>
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
            <input type="number" min="1" step="1" id="customD" class="form-control" value="1024">
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
