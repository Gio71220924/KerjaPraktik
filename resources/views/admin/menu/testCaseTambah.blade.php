@extends('layouts.admin')

@section('content')
@php
    $userId = Auth::id(); // Ambil user ID yang sedang login
    $latestConsultationId = DB::table("test_case_user_{$userId}")->max('case_id') ?? 0;
@endphp

<h1 class="mt-4">Add New Consultation for Id {{ $latestConsultationId + 1 }}</h1>
    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif

    <form action="{{ route('test.case.store') }}" method="POST">
        @csrf

        {{-- ====== Tambahan: Pilih Kernel untuk SVM ====== --}}
        <div class="card mb-3">
          <div class="card-body">
            <label class="form-label">SVM Kernel</label>
            <select name="svm_kernel" id="svm_kernel" class="form-select" aria-label="SVM kernel">
              <option value="sgd" selected>SGD (Linear)</option>
              <option value="rbf:D=1024:gamma=0.25">RBF — D=1024, γ=0.25</option>
              <option value="sigmoid:D=1024:scale=1.0:coef0=0.0">Sigmoid — D=1024, scale=1.0, coef0=0.0</option>
            </select>
            <small class="text-muted">Pilihan ini hanya dipakai jika kamu menekan tombol “Support Vector Machine”.</small>
          </div>
        </div>
        {{-- =============================================== --}}

        <div class="row">
            @php
                $atributCount = count($atributs); // Total atribut
                $perColumn = ceil($atributCount / 3); // Hitung jumlah atribut per kolom
            @endphp

            @for ($col = 0; $col < 3; $col++) <!-- Membagi ke dalam 3 kolom -->
                <div class="col-md-4">
                    @for ($row = $col * $perColumn; $row < min(($col + 1) * $perColumn, $atributCount); $row++)
                        @php
                            $atribut = $atributs[$row]; // Ambil atribut berdasarkan indeks
                            $values = DB::table('atribut_value')
                                ->where('atribut_id', $atribut->atribut_id)
                                ->where('user_id', Auth::id())
                                ->get();
                        @endphp

                        <div class="form-group mb-4">
                            <label for="{{ $atribut->atribut_name }}">{{ ucfirst($atribut->atribut_name) }}</label>
                            <br>
                            <label for="{{ $atribut->atribut_desc }}">{{ ucfirst($atribut->atribut_desc) }}</label>
                            <select name="{{ $atribut->atribut_id }}_{{ $atribut->atribut_name }}" class="form-control" required>
                                <option value="">Select an option</option>
                                @foreach($values as $value)
                                    <option value="{{ $value->value_id . '_' . $value->value_name }}">
                                        {{ explode('_', $value->value_name, 2)[1] ?? $value->value_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>  
            @endfor
        </div>
    
        <button type="submit" name="action_type" value="Matching Rule" class="btn btn-primary">Matching Rule</button>
        <button type="submit" name="action_type" value="Forward Chaining" class="btn btn-primary">Forward Chaining</button>
        <button type="submit" name="action_type" value="Backward Chaining" class="btn btn-primary">Backward Chaining</button>
        <button type="submit" name="action_type" value="Support Vector Machine" class="btn btn-primary">Support Vector Machine</button>
    </form>
@endsection
