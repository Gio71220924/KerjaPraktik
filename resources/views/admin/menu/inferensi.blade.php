@extends('layouts.admin')

@section('content')

@php
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

    // Ambil data dari inferensi_fc_user_{userId}
    $inferensiBC = new \App\Models\BackwardChaining();
    $inferensiBC->setTableForUser($user->user_id);
    $tableExists3 = $inferensiBC->tableExists();
    $inference3 = $tableExists3 ? $inferensiBC->getRules() : collect();

    // Gabungkan kedua hasil inferensi
    $allInference = $inference1->merge($inference2)->merge($inference3)->sortBy('case_id');

    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();

    // Ambil algoritma dari test_case_user_{userId}
    $generate = new \App\Models\Consultation();
    $generate->setTableForUser($user->user_id);
    $tableExistss = $generate->tableExists();
    
    $testCases = $tableExistss ? $generate->getRules() : collect();
    $algorithms = $testCases->pluck('algoritma', 'case_id')->toArray();
@endphp


    <h1 class="mt-4">Inferensi for User: {{ $user->username }}</h1>

    @if(isset($success))
        <div class="alert alert-success">
            {{ $success }}
        </div>
    @endif
    
    {{-- <!-- Tombol Generate Tree -->
    <a href="{{ url('/inference/' . Auth::id() . '/' . Auth::id()) }}" class="btn btn-primary">Generate Inference</a> --}}

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
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Case Title</th>
                        {{-- <th>Case Goal</th> --}}
                        <th>Rule Id</th>
                        <th>Goal</th>
                        <th>Match Value</th>
                        <th>Algortihm</th>
                        <th>Execution Time</th>
                        {{-- <th>Cocok</th> --}}
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allInference as $index => $inferensi)
                    <tr>
                        <td>{{ $inferensi->case_id }}</td>
                        <td>{{ $kasus->case_title }}</td>
                        <td>{{ $inferensi->rule_id }}</td>
                        <td>
                            @php
                                $cleanedIfPart = preg_replace('/\b\d+_/', ' ', $inferensi->rule_goal);
                                $cleanedIfPart = str_replace('_', ' ', $cleanedIfPart);
                                $cleanedIfPart = str_replace('-', ' ', $cleanedIfPart);
                                $cleanedIfPart = str_replace('=', ' =', $cleanedIfPart);
                            @endphp
                            {{ $cleanedIfPart }}
                        </td>
                        <td>{{ $inferensi->match_value }}</td>
                        <td>{{ $algorithms[$inferensi->case_id] ?? 'Unknown' }}</td>
                        <td>{{ $inferensi->waktu }}</td>
                        <td><a href="{{ url('/detail?case_id=' . $inferensi->case_id)}}" class="btn btn-primary">Detail</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
