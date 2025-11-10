{{-- resources/views/admin/menu/inferensi.blade.php --}}
@extends('layouts.admin')

@section('content')

@php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();

    // === Ambil data dari 3 sumber: inferensi_user, inferensi_fc_user, inferensi_bc_user ===
    $mdlInf = new \App\Models\Inferensi();
    $mdlInf->setTableForUser($user->user_id);
    $t1Exists = $mdlInf->tableExists();
    $rows1    = $t1Exists ? $mdlInf->getRules()->map(function($r){ $r->_source = 'user'; return $r; }) : collect();

    $mdlFC = new \App\Models\ForwardChaining();
    $mdlFC->setTableForUser($user->user_id);
    $t2Exists = $mdlFC->tableExists();
    $rows2    = $t2Exists ? $mdlFC->getRules()->map(function($r){ $r->_source = 'fc'; return $r; }) : collect();

    $mdlBC = new \App\Models\BackwardChaining();
    $mdlBC->setTableForUser($user->user_id);
    $t3Exists = $mdlBC->tableExists();
    $rows3    = $t3Exists ? $mdlBC->getRules()->map(function($r){ $r->_source = 'bc'; return $r; }) : collect();

    // === Gabung & urutkan stabil (numerik/ULID) ===
    $all = $rows1->merge($rows2)->merge($rows3)
        ->sortBy(function($r){
            $cid = (string)($r->case_id ?? '');
            $k1  = (ctype_digit($cid) && $cid !== '')
                ? str_pad($cid, 20, '0', STR_PAD_LEFT)
                : 'Z' . $cid;

            $rid = (string)($r->inf_id ?? $r->id ?? '');
            $k2  = (ctype_digit($rid) && $rid !== '')
                ? str_pad($rid, 20, '0', STR_PAD_LEFT)
                : 'Z' . $rid;

            return $k1.'-'.$k2;
        })
        ->values();

    // Case title (opsional)
    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // === Label algoritma berdasar sumber, bukan test_case_user ===
    $renderAlgo = function($row) {
        $src = $row->_source ?? 'user';
        if ($src === 'fc') return 'Forward Chaining';
        if ($src === 'bc') return 'Backward Chaining';

        // Sumber: inferensi_user
        $ruleId   = strtoupper((string)($row->rule_id ?? ''));
        $ruleGoal = (string)($row->rule_goal ?? '');
        if ($ruleId === 'SVM' || stripos($ruleGoal, 'kernel=') !== false) {
            return 'Support Vector Machine';
        }
        return 'Matching Rule';
    };

    // Ringkasan jumlah per sumber (untuk badge)
    $cInfer = $rows1->count();
    $cFC    = $rows2->count();
    $cBC    = $rows3->count();
    $cAll   = $all->count();

    // Util: buang prefix angka_ (e.g., "202_Olahraga" -> "Olahraga")
    $stripNumPrefix = function(string $s){
        return preg_replace('/^\s*\d+_/', '', trim($s));
    };

    // Formatter rule_goal:
    // - SVM: ambil sebelum "| kernel=...", pecah "LHS = RHS", buang prefix angka di kiri/kanan.
    // - Non-SVM: rapikan massal (hapus "123_", ganti _/- jadi spasi, rapikan '=').
    $formatRuleGoal = function($row) use ($stripNumPrefix) {
        $ruleId = strtoupper((string)($row->rule_id ?? ''));
        $raw    = (string)($row->rule_goal ?? '');

        $isSvm = ($ruleId === 'SVM') || (stripos($raw, 'kernel=') !== false);
        if ($isSvm) {
            $main = preg_replace('/\s*\|\s*kernel\s*=.*$/i', '', $raw);
            if (strpos($main, '=') !== false) {
                [$lhs, $rhs] = array_map('trim', explode('=', $main, 2));
                $lhs = $stripNumPrefix($lhs);
                $rhs = $stripNumPrefix($rhs);
                return $lhs . ' = ' . $rhs;
            }
            return $stripNumPrefix($main);
        }

        // Non-SVM
        $text = preg_replace('/\b\d+_/', ' ', $raw);
        $text = str_replace(['_', '-'], ' ', $text);
        $text = str_replace('=', ' =', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    };
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
  <a href="{{ route('test.case.form') }}" class="btn btn-sm btn-outline-primary">Lihat Test Case</a>
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
                // Id tampil: prioritas inf_id, lalu id (tabel generic), terakhir case_id
                $dispId = $row->inf_id
                    ?? ($row->id ?? null)
                    ?? ($row->case_id ?? '-');

                // Case title
                $caseTitle = $kasus->case_title ?? '-';

                // Rule Id (apa adanya)
                $ruleId = (string)($row->rule_id ?? '');

                // Goal/Rule Goal (dirapikan)
                $goalText = $formatRuleGoal($row);

                // Match value
                $mv = isset($row->match_value) ? (float)$row->match_value : 0.0;
                $mvFmt = number_format($mv, 4);

                // Algoritma (label) — berdasarkan sumber
                $algo = $renderAlgo($row);

                // Waktu eksekusi
                $sec = isset($row->waktu) ? (float)$row->waktu : 0.0;
                $secFmt = number_format($sec, 6);

                $cidForLink = $row->case_id ?? '';
            @endphp
            <tr>
              <td>{{ $dispId }}</td>
              <td>{{ $caseTitle }}</td>
              <td>{{ $ruleId }}</td>
              <td>{{ $goalText }}</td>
              <td>{{ $mvFmt }}</td>
              <td>{{ $algo }}</td>
              <td>{{ $secFmt }}</td>
              <td>
                <a href="{{ url('/detail?case_id=' . urlencode($cidForLink)) }}" class="btn btn-primary btn-sm">Detail</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@endsection
