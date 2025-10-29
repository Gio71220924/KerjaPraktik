{{-- resources/views/svm/index.blade.php --}}
@extends('layouts.admin')

@section('content')
<div class="container mt-4">
  <h2>Support Vector Machine (SVM)</h2>
  <hr>

  {{-- Flash messages --}}
  @if(session('svm_ok'))
    <div class="alert alert-success"><pre class="mb-0">{{ session('svm_ok') }}</pre></div>
  @endif
  @if(session('svm_err'))
    <div class="alert alert-danger"><pre class="mb-0">{{ session('svm_err') }}</pre></div>
  @endif

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @isset($message)
    <div class="alert alert-warning">{{ $message }}</div>
  @endisset

  {{-- Form Train SVM --}}
  <form action="{{ route('SVM.generate') }}" method="POST" class="mb-4" id="svmTrainForm">
    @csrf
    @php
      $uid = Auth::id();
      // Jika kamu punya case_id terakhir, bisa pakai itu. Default pakai user_id.
      $caseNumDefault = $uid;
    @endphp
    <input type="hidden" name="user_id" value="{{ $uid }}">
    <input type="hidden" name="case_num" value="{{ $caseNumDefault }}">
    <input type="hidden" name="kernel" id="kernelInput" value="sgd">

    <div class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Pilih Kernel</label>
        <select id="kernelSelect" class="form-select" required>
          <option value="sgd" selected>SGD (Linear)</option>
          <option value="rbf:D=1024:gamma=0.25">RBF — D=1024, γ=0.25</option>
          <option value="sigmoid:D=1024:scale=1.0:coef0=0.0">Sigmoid — D=1024, scale=1.0, coef0=0.0</option>
          <option value="custom">Custom…</option>
        </select>
        <small class="text-muted">Format internal: <code>sgd</code>, <code>rbf:D=...:gamma=...</code>, <code>sigmoid:D=...:scale=...:coef0=...</code></small>
      </div>

      {{-- Panel Custom --}}
      <div class="col-12"></div>
      <div class="col-md-3 d-none" id="customTypeWrap">
        <label class="form-label">Tipe Custom</label>
        <select id="customType" class="form-select">
          <option value="rbf" selected>rbf</option>
          <option value="sigmoid">sigmoid</option>
          <option value="sgd">sgd</option>
        </select>
      </div>

      <div class="col-md-2 d-none" id="customDWrap">
        <label class="form-label">D (dimensi)</label>
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

      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">Train SVM</button>
      </div>
    </div>
  </form>

  {{-- Riwayat Training --}}
  @if(!empty($svmData) && count($svmData) > 0)
    <h5>Riwayat Training:</h5>
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">ID</th>
            <th style="width:120px;">Status</th>
            <th>Output</th>
            <th style="width:200px;">Tanggal</th>
          </tr>
        </thead>
        <tbody>
          @foreach($svmData as $item)
            <tr>
              <td>{{ $item->id }}</td>
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

{{-- JS kecil untuk compose kernel --}}
<script>
(function(){
  const sel = document.getElementById('kernelSelect');
  const inputKernel = document.getElementById('kernelInput');

  const customTypeWrap  = document.getElementById('customTypeWrap');
  const customDWrap     = document.getElementById('customDWrap');
  const customGammaWrap = document.getElementById('customGammaWrap');
  const customScaleWrap = document.getElementById('customScaleWrap');
  const customCoef0Wrap = document.getElementById('customCoef0Wrap');

  const customType  = document.getElementById('customType');
  const customD     = document.getElementById('customD');
  const customGamma = document.getElementById('customGamma');
  const customScale = document.getElementById('customScale');
  const customCoef0 = document.getElementById('customCoef0');

  function toggleCustom(show) {
    [customTypeWrap, customDWrap].forEach(el => el.classList.toggle('d-none', !show));
    // RBF fields
    customGammaWrap.classList.toggle('d-none', !show || customType.value !== 'rbf');
    // Sigmoid fields
    const showSig = show && customType.value === 'sigmoid';
    customScaleWrap.classList.toggle('d-none', !showSig);
    customCoef0Wrap.classList.toggle('d-none', !showSig);
  }

  function composeKernel() {
    if (sel.value !== 'custom') {
      inputKernel.value = sel.value;
      return;
    }
    const t = customType.value;
    if (t === 'sgd') {
      inputKernel.value = 'sgd';
    } else if (t === 'rbf') {
      const D = Math.max(1, parseInt(customD.value||'1024', 10));
      const g = parseFloat(customGamma.value||'0.25');
      inputKernel.value = `rbf:D=${D}:gamma=${g}`;
    } else if (t === 'sigmoid') {
      const D = Math.max(1, parseInt(customD.value||'1024', 10));
      const s = parseFloat(customScale.value||'1.0');
      const c = parseFloat(customCoef0.value||'0.0');
      inputKernel.value = `sigmoid:D=${D}:scale=${s}:coef0=${c}`;
    }
  }

  // init
  inputKernel.value = sel.value;
  toggleCustom(false);
  composeKernel();

  // events
  sel.addEventListener('change', () => {
    const isCustom = sel.value === 'custom';
    toggleCustom(isCustom);
    composeKernel();
  });
  [customType, customD, customGamma, customScale, customCoef0].forEach(el => {
    el.addEventListener('input', composeKernel);
    el.addEventListener('change', composeKernel);
  });
})();
</script>
@endsection
