{{-- resources/views/admin/menu/inferensi.blade.php --}}
@extends('layouts.admin')

@section('content')

@php
    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();

    // Ambil data dari inferensi_user_{userId}
    $inferensi = new \App\Models\Inferensi();
    $inferensi->setTableForUser($user->user_id);
    $tableExists1 = $inferensi->tableExists();
    $inference1 = $tableExists1 ? $inferensi->getRules() : collect();

    // Ambil data dari inferensi_fc_user_{userId}
    $inferensiFC = new \App\Models\ForwardChaining();
    $inferensiFC->setTableForUser($user->user_id);
    $tableExists2 = $inferensiFC->tableExists();
    $inference2 = $tableExists2 ? $inferensiFC->getRules() : collect();

    // Ambil data dari inferensi_bc_user_{userId}
    $inferensiBC = new \App\Models\BackwardChaining();
    $inferensiBC->setTableForUser($user->user_id);
    $tableExists3 = $inferensiBC->tableExists();
    $inference3 = $tableExists3 ? $inferensiBC->getRules() : collect();

    // Gabungkan semua hasil inferensi
    $allInference = $inference1->merge($inference2)->merge($inference3)->sortBy('case_id');

    // Case title (optional)
    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // Ambil algoritma dari test_case_user_{userId} untuk mapping (Matching Rule/FC/BC)
    $generate = new \App\Models\Consultation();
    $generate->setTableForUser($user->user_id);
    $tableExistss = $generate->tableExists();
    $testCases = $tableExistss ? $generate->getRules() : collect();
    $algorithms = $testCases->pluck('algoritma', 'case_id')->toArray();

    // Helper: render algoritma dengan fallback SVM
    $renderAlgo = function($row) use ($algorithms) {
        // Jika sudah ada mapping dari test_case_user, pakai itu
        if (isset($algorithms[$row->case_id]) && $algorithms[$row->case_id] !== null && $algorithms[$row->case_id] !== '') {
            return $algorithms[$row->case_id];
        }
        // Fallback: kalau baris ini dari SVMController (rule_id = 'SVM'), tampilkan "Support Vector Machine"
        if (isset($row->rule_id) && strtoupper((string)$row->rule_id) === 'SVM') {
            return 'Support Vector Machine';
        }
        // Default
        return 'Unknown';
    };
@endphp

<h1 class="mt-4">Inferensi for User: {{ $user->username }}</h1>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<br>

@if (!$tableExists1 && !$tableExists2 && !$tableExists3)
  <ol class="breadcrumb mb-4">
      <li class="breadcrumb-item active">There is no inference for this user.</li>
  </ol>
@elseif ($allInference->isEmpty())
  <ol class="breadcrumb mb-4">
      <li class="breadcrumb-item active">There is no inference for this user.</li>
  </ol>
@else
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">Id</th>
            <th style="min-width:220px;">Case Title</th>
            <th style="width:100px;">Rule Id</th>
            <th>Goal</th>
            <th style="width:140px;">Match Value</th>
            <th style="min-width:180px;">Algorithm</th>
            <th style="width:180px;">Execution Time (s)</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($allInference as $index => $row)
            <tr>
              {{-- Id di tabel inferensi adalah inf_id (auto increment) pada strukturmu --}}
              <td>{{ $row->inf_id ?? $row->case_id }}</td>

              {{-- Case Title (fallback '-' jika kosong) --}}
              <td>{{ $kasus->case_title ?? '-' }}</td>

              {{-- Rule Id (SVM akan berisi 'SVM') --}}
              <td>{{ $row->rule_id }}</td>

              {{-- Goal (rule_goal) --}}
              <td>
                @php
                  $text = (string)($row->rule_goal ?? '');
                  // Untuk SVM: biarkan apa adanya (agar "kernel=..." tetap terlihat)
                  if (strtoupper((string)$row->rule_id) !== 'SVM') {
                      // Bersihkan angka_id dan underscore/dash untuk tampilan rules lain
                      $text = preg_replace('/\b\d+_/', ' ', $text);
                      $text = str_replace(['_', '-'], ' ', $text);
                      $text = str_replace('=', ' =', $text);
                  }
                @endphp
                {{ $text }}
              </td>

              {{-- Match Value (format 4 desimal sesuai DECIMAL(5,4)) --}}
              <td>
                @php
                  $mv = isset($row->match_value) ? (float)$row->match_value : 0;
                @endphp
                {{ number_format($mv, 4) }}
              </td>

              {{-- Algorithm: mapping dari test_case_user, fallback SVM --}}
              <td>{{ $renderAlgo($row) }}</td>

              {{-- Execution Time --}}
              <td>
                @php
                  // kolom 'waktu' di strukturmu DECIMAL(16,14)
                  $sec = isset($row->waktu) ? (float)$row->waktu : 0;
                @endphp
                {{ number_format($sec, 6) }}
              </td>

              {{-- Detail --}}
              <td>
                <a href="{{ url('/detail?case_id=' . urlencode($row->case_id)) }}" class="btn btn-primary btn-sm">Detail</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@endsection
