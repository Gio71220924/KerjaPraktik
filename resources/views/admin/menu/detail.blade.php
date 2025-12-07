@extends('layouts.admin')

@section('content')

@php
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Support\Facades\DB;

    $user = Auth::user();
    $selectedCaseId = request('case_id'); // Ambil case_id dari URL request

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

    // Gabungkan data inferensi dan filter berdasarkan case_id yang dipilih
    $allInference = $inference1->merge($inference2)->merge($inference3);
    $selectedInference = $allInference->where('case_id', $selectedCaseId);

    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // Ambil algoritma dari test_case_user_{userId}
    $generate = new \App\Models\Consultation();
    $generate->setTableForUser($user->user_id);
    $tableExistss = $generate->tableExists();
    $testCases = $tableExistss ? $generate->getRules()->where('case_id', $selectedCaseId) : collect();

    $algorithms = $testCases->pluck('algoritma', 'case_id')->toArray();

    // Mendapatkan kolom dari tabel yang sesuai
    $columns = $tableExistss ? Schema::getColumnListing($generate->getTable()) : [];

    // Mengambil atribut yang goal-nya bukan 'T'
    $validAtributs = DB::table('atribut')
            ->where('user_id', $user->user_id)
            ->where('goal', '!=', 'T')
            ->pluck('atribut_name', 'atribut_id'); 

    // Filter kolom hanya untuk atribut yang valid
    $filteredColumns = array_filter($columns, function ($column) use ($validAtributs, $columns) {
        return in_array($column, $columns) && collect($validAtributs)->keys()->contains(explode('_', $column)[0]);
    });

    // Kolom yang ingin disembunyikan
    $excludeColumns = ['case_id', 'user_id', 'case_num', 'algoritma'];
@endphp

<h1 class="mt-4">Detail Inferensi for Id {{ $selectedCaseId }}</h1>

@if ($selectedInference->isEmpty())
    <p class="alert alert-warning">You have no detail for Id {{ $selectedCaseId }}</p>
@else
    <div class="table-responsive">
        <table class="table table-bordered mb-0">
            <tbody>
            @foreach ($selectedInference as $detail)
                <tr>
                    <th>Id</th>
                    <td>{{ $detail->case_id }}</td>
                </tr>
                <tr>
                    <th>Rule Id</th>
                    <td>{{ $detail->rule_id }}</td>
                </tr>
                <tr>
                    <th>Match Value</th>
                    <td>{{ number_format((float) $detail->match_value, 4, '.', '') }}</td>
                </tr>

                <tr>
                    <th colspan="2">Your Consultation</th>
                </tr>

                @foreach ($testCases as $index => $row)
                    @if ($testCases->count() > 1)
                        <tr>
                            <th colspan="2">Consultation {{ $index + 1 }}</th>
                        </tr>
                    @endif

                    @foreach ($filteredColumns as $column)
                        @if (!in_array($column, $excludeColumns))
                            @php
                                $label = str_replace('_', ' ', preg_replace('/\b\d+_/', ' ', $column));
                                $label = preg_replace('/\s+/', ' ', trim($label));
                                $value = preg_replace('/\b\d+_/', ' ', $row->$column);
                                $value = str_replace(['_', '-'], ' ', $value);
                                $value = preg_replace('/\s+/', ' ', trim($value));
                            @endphp
                            <tr>
                                <th>{{ $label }}</th>
                                <td>{{ $value }}</td>
                            </tr>
                        @endif
                    @endforeach
                @endforeach

                <tr>
                    <th colspan="2">Your Consultation Goal</th>
                </tr>

                @php
                    $goal = str_replace(['_', '-', '='], [' ', ' ', ' ='], preg_replace('/\b\d+_/', ' ', $detail->rule_goal));
                    $algorithmName = $algorithms[$detail->case_id] ?? 'Unknown';
                    $algorithmName = ucwords(str_replace(['_', '-'], ' ', $algorithmName));
                @endphp

                <tr>
                    <th>Goal</th>
                    <td>{{ $goal }}</td>
                </tr>
                <tr>
                    <th>Algorithm</th>
                    <td>
                        @php
                            $algo = $algorithmName;
                            $rg   = strtolower((string)($detail->rule_goal ?? ''));
                            if (!$algo) {
                                if (str_contains($rg, 'forward')) {
                                    $algo = 'Forward Chaining';
                                } elseif (str_contains($rg, 'backward')) {
                                    $algo = 'Backward Chaining';
                                } elseif (str_contains($rg, 'matching')) {
                                    $algo = 'Matching Rule';
                                } elseif (str_contains($rg, 'kernel=')) {
                                    $algo = 'Support Vector Machine';
                                }
                            }
                        @endphp
                        {{ $algo ?? 'Unknown' }}
                    </td>
                </tr>
                <tr>
                    <th>Execution Time</th>
                    <td>
                        @php
                            $timeVal = $detail->waktu ?? $detail->exec_time ?? null;
                        @endphp
                        {{ $timeVal !== null ? $timeVal : '-' }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<a href="{{ url('/inference') }}" class="btn btn-secondary">Back</a>

@endsection
