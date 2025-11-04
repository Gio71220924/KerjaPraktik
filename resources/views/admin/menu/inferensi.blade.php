{{-- resources/views/admin/menu/inferensi.blade.php --}}
@extends('layouts.admin')

@section('content')

@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\Schema;

    $user = Auth::user();

    // === Ambil data dari 3 sumber: inferensi_user, inferensi_fc_user, inferensi_bc_user ===
    $mdlInf = new \App\Models\Inferensi();
    $mdlInf->setTableForUser($user->user_id);
    $t1Exists = $mdlInf->tableExists();
    $rows1    = $t1Exists ? $mdlInf->getRules() : collect();

    $mdlFC = new \App\Models\ForwardChaining();
    $mdlFC->setTableForUser($user->user_id);
    $t2Exists = $mdlFC->tableExists();
    $rows2    = $t2Exists ? $mdlFC->getRules() : collect();

    $mdlBC = new \App\Models\BackwardChaining();
    $mdlBC->setTableForUser($user->user_id);
    $t3Exists = $mdlBC->tableExists();
    $rows3    = $t3Exists ? $mdlBC->getRules() : collect();

    // === Gabung semua hasil ===
    $all = $rows1->merge($rows2)->merge($rows3)
                 ->sortBy(fn($r) => sprintf('%020s-%020s', $r->case_id ?? '', $r->inf_id ?? 0))
                 ->values();

    // Case title (opsional)
    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // Ambil algoritma dari test_case_user_{userId} untuk mapping label kolom "Algorithm"
    $generate   = new \App\Models\Consultation();
    $generate->setTableForUser($user->user_id);
    $tcExists   = $generate->tableExists();
    $testCases  = $tcExists ? $generate->getRules() : collect();
    $algorithms = $testCases->pluck('algoritma', 'case_id')->toArray();

    // Helper render algoritma (fallback SVM jika rule_id = 'SVM')
    $renderAlgo = function($row) use ($algorithms) {
        $cid = $row->case_id ?? null;
        if ($cid !== null && isset($algorithms[$cid]) && $algorithms[$cid] !== '') {
            return $algorithms[$cid];
        }
        if (isset($row->rule_id) && strtoupper((string)$row->rule_id) === 'SVM') {
            return 'Support Vector Machine';
        }
        return 'Unknown';
    };

    // Ringkasan jumlah per sumber (untuk badge)
    $cInfer = $rows1->count();
    $cFC    = $rows2->count();
    $cBC    = $rows3->count();
    $cAll   = $all->count();
@endphp

<h1 class="mt-4">Inferensi — {{ $user->username }}</h1>

{{-- Alert hasil aksi --}}
@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger" style="white-space:pre-wrap">{{ session('error') }}</div>
@endif

{{-- Panel diagnostik SVM (jika ada) --}}
@if(session('svm_diag'))
  <div class="alert alert-secondary mt-2">
    <details open>
      <summary><strong>Diagnostics</strong> (klik untuk sembunyikan)</summary>
      <pre class="mt-2" style="white-space:pre-wrap">{{ session('svm_diag') }}</pre>
    </details>
  </div>
@endif

{{-- Ringkasan jumlah --}}
<div class="mb-3 d-flex flex-wrap gap-2">
  <span class="badge text-bg-primary">Total: {{ $cAll }}</span>
  <span class="badge text-bg-success">inferensi_user: {{ $cInfer }}</span>
  <span class="badge text-bg-info">inferensi_fc_user: {{ $cFC }}</span>
  <span class="badge text-bg-warning">inferensi_bc_user: {{ $cBC }}</span>
</div>

{{-- Tombol kecil utilitas --}}
<div class="mb-3 d-flex gap-2">
  <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Refresh</a>
  @if ($tcExists)
    <a href="{{ route('test.case.form') }}" class="btn btn-sm btn-outline-primary">Lihat Test Case</a>
  @endif
</div>

@if (!$t1Exists && !$t2Exists && !$t3Exists)
  <ol class="breadcrumb mb-4"><li class="breadcrumb-item active">Belum ada tabel inferensi untuk user ini.</li></ol>
@elseif ($all->isEmpty())
  <ol class="breadcrumb mb-4"><li class="breadcrumb-item active">Belum ada data inferensi.</li></ol>
@else
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:90px;">Id</th>
            <th style="min-width:220px;">Case Title</th>
            <th style="width:110px;">Rule Id</th>
            <th>Goal / Rule Goal</th>
            <th style="width:140px;">Match Value</th>
            <th style="min-width:180px;">Algorithm</th>
            <th style="width:180px;">Execution Time (s)</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($all as $row)
            @php
                // Id tampil: pakai inf_id jika ada, kalau tidak fallback case_id
                $dispId = $row->inf_id ?? $row->case_id ?? '-';

                // Case title fallback
                $caseTitle = $kasus->case_title ?? '-';

                // Rule Id
                $ruleId = $row->rule_id ?? '';

                // Rule goal tampil:
                $text = (string)($row->rule_goal ?? '');
                if (strtoupper((string)$ruleId) !== 'SVM') {
                    // Rapikan tampilan (buang prefix angka_, ganti _/- jadi spasi, rapikan '=')
                    $text = preg_replace('/\b\d+_/', ' ', $text);
                    $text = str_replace(['_', '-'], ' ', $text);
                    $text = str_replace('=', ' =', $text);
                    $text = preg_replace('/\s+/', ' ', $text);
                }

                // Match value (DECIMAL(5,4))
                $mv = isset($row->match_value) ? (float)$row->match_value : 0.0;
                $mvFmt = number_format($mv, 4);

                // Algoritma
                $algo = $renderAlgo($row);

                // Waktu eksekusi (DECIMAL(16,14))
                $sec = isset($row->waktu) ? (float)$row->waktu : 0.0;
                $secFmt = number_format($sec, 6);
            @endphp
            <tr>
              <td>{{ $dispId }}</td>
              <td>{{ $caseTitle }}</td>
              <td>{{ $ruleId }}</td>
              <td>{{ $text }}</td>
              <td>{{ $mvFmt }}</td>
              <td>{{ $algo }}</td>
              <td>{{ $secFmt }}</td>
              <td>
                <a href="{{ url('/detail?case_id=' . urlencode($row->case_id ?? '')) }}" class="btn btn-primary btn-sm">Detail</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@endsection
