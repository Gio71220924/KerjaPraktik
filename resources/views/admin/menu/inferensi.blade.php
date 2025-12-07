{{-- resources/views/admin/menu/inferensi.blade.php --}}
@extends('layouts.admin')

@section('content')

@php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();

    /**
     * Set metadata umum per-baris: source, rank algoritma, timestamp dibuat, dan display id unik.
     */
    $setCommon = function($r, string $src) {
        // sumber
        $r->_source = $src; // 'user' | 'fc' | 'bc'

        // rank algoritma untuk tie-breaker
        $algoRank = 1;
        if ($src === 'fc') $algoRank = 2;
        elseif ($src === 'bc') $algoRank = 3;
        $r->_algo_rank = $algoRank;

        // normalisasi timestamp "dibuat" (fallback beberapa nama kolom)
        $r->_created = $r->created_at
            ?? ($r->createdAt ?? null)
            ?? ($r->created ?? null)
            ?? ($r->tanggal ?? null)
            ?? ($r->ts ?? null);

        $r->_ts = $r->_created ? @strtotime((string)$r->_created) : null;

        // display id unik untuk UI (hindari tabrakan antar tabel)
        $lid = $r->inf_id ?? $r->id ?? $r->case_id ?? null;

        // deteksi SVM di sumber "user"
        $isSvm = false;
        $ruleId   = strtoupper((string)($r->rule_id ?? ''));
        $ruleGoal = (string)($r->rule_goal ?? $r->goal ?? '');
        if ($src === 'user' && ($ruleId === 'SVM' || stripos($ruleGoal, 'kernel=') !== false)) {
            $isSvm = true;
        }

        if ($src === 'fc')      { $prefix = 'FC-'; }
        elseif ($src === 'bc')  { $prefix = 'BC-'; }
        else                    { $prefix = $isSvm ? 'SVM-' : 'MR-'; }

        $r->_disp_id = $lid !== null ? ($prefix . $lid) : ($prefix . '?');

        return $r;
    };

    // === Ambil data dari 3 sumber: inferensi_user, inferensi_fc_user, inferensi_bc_user ===
    $mdlInf = new \App\Models\Inferensi();
    $mdlInf->setTableForUser($user->user_id);
    $t1Exists = $mdlInf->tableExists();
    $rows1    = $t1Exists
        ? $mdlInf->getRules()->map(function($r) use ($setCommon){ return $setCommon($r, 'user'); })
        : collect();

    $mdlFC = new \App\Models\ForwardChaining();
    $mdlFC->setTableForUser($user->user_id);
    $t2Exists = $mdlFC->tableExists();
    $rows2    = $t2Exists
        ? $mdlFC->getRules()->map(function($r) use ($setCommon){ return $setCommon($r, 'fc'); })
        : collect();

    $mdlBC = new \App\Models\BackwardChaining();
    $mdlBC->setTableForUser($user->user_id);
    $t3Exists = $mdlBC->tableExists();
    $rows3    = $t3Exists
        ? $mdlBC->getRules()->map(function($r) use ($setCommon){ return $setCommon($r, 'bc'); })
        : collect();

    // === Gabung & urutkan: paling dulu dibuat (created_at), lalu tie-breaker algo_rank, lalu id lokal (stabil)
    $all = $rows1->merge($rows2)->merge($rows3)
        ->sortBy(function($r) {
            // ts: kalau null, dorong ke belakang dengan angka besar (biar yg ada timestamp muncul duluan)
            $ts = is_int($r->_ts) ? $r->_ts : 9_999_999_999_999;

            // stabilkan id lokal untuk tie-breaker terakhir
            $rid = (string)($r->inf_id ?? $r->id ?? '');
            $ridKey = (ctype_digit($rid) && $rid !== '')
                ? str_pad($rid, 20, '0', STR_PAD_LEFT)
                : 'Z' . $rid;

            // format string biar sort leksikografis vs numerik
            return sprintf('%013d-%02d-%s', $ts, (int)($r->_algo_rank ?? 9), $ridKey);
        })
        ->values();

    // Case title (opsional global; fallback ke row jika ada per-baris)
    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // === Label algoritma untuk tampilan
    $renderAlgo = function($row) {
        $src = $row->_source ?? 'user';
        if ($src === 'fc') return 'Forward Chaining';
        if ($src === 'bc') return 'Backward Chaining';

        $ruleId   = strtoupper((string)($row->rule_id ?? ''));
        $ruleGoal = (string)($row->rule_goal ?? $row->goal ?? '');
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

    // Pagination (client-side on the merged collection)
    $perPage = 10;
    $page    = max((int) request()->input('page', 1), 1);
    $paged   = new \Illuminate\Pagination\LengthAwarePaginator(
        $all->slice(($page - 1) * $perPage, $perPage),
        $all->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    // Util: buang prefix angka_ (e.g., "202_Olahraga" -> "Olahraga")
    $stripNumPrefix = function(string $s){
        return preg_replace('/^\s*\d+_/', '', trim($s));
    };

    // Formatter rule_goal / goal
    $formatRuleGoal = function($row) use ($stripNumPrefix) {
        $ruleId = strtoupper((string)($row->rule_id ?? ''));
        $raw    = (string)($row->rule_goal ?? $row->goal ?? '');

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

        $text = preg_replace('/\b\d+_/', ' ', $raw);
        $text = str_replace(['_', '-'], ' ', $text);
        $text = str_replace('=', ' =', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    };
@endphp

<h1 class="mt-4">Inferensi - {{ $user->username }}</h1>

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
<div class="mb-3 d-flex flex-wrap gap-2">
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
            <th style="width:120px;">Id</th>
            <th style="min-width:220px;">Case Title</th>
            <th style="width:120px;">Rule Id</th>
            <th>Goal / Rule Goal</th>
            <th style="width:140px;">Match Value</th>
            <th style="min-width:180px;">Algorithm</th>
            <th style="width:180px;">Execution Time (s)</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($paged as $row)
            @php
                // Id tampil unik dengan prefix MR-/SVM-/FC-/BC-
                $dispId = $row->_disp_id ?? '-';

                // Case title (fallback ke global jika tak ada per-baris)
                $caseTitle = $row->case_title ?? ($kasus->case_title ?? '-');

                // Rule Id (apa adanya)
                $ruleId = (string)($row->rule_id ?? '');

                // Goal/Rule Goal (dirapikan)
                $goalText = $formatRuleGoal($row);

                // Match value (fallback ke 'score' untuk SVM jika ada)
                $mv = isset($row->match_value) ? (float)$row->match_value : (isset($row->score) ? (float)$row->score : 0.0);
                $mvFmt = number_format($mv, 4);

                // Algoritma (label)
                $algo = $renderAlgo($row);

                // Waktu eksekusi (fallback beberapa kolom)
                $sec = isset($row->waktu) ? (float)$row->waktu : (isset($row->exec_time) ? (float)$row->exec_time : 0.0);
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
                <a href="{{ url('/detail?case_id=' . urlencode((string)$cidForLink)) }}" class="btn btn-primary btn-sm">Detail</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
 +    <div class="mt-3">                                                                                                                              
      {{ $paged->onEachSide(1)->links('pagination::bootstrap-5') }}                                                                                 
    </div>                                                                                                                                          
  </div>                                                                                                                                            
@endif  

@endsection
