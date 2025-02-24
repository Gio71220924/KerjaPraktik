@extends('layouts.admin')

@section('content')

    @php
    $user = Auth::user();
    $inferensi = new \App\Models\ForwardChaining();
    $inferensi->setTableForUser($user->user_id);

    // Periksa apakah tabel ada
    $tableExists = $inferensi->tableExists();
    $inference = $tableExists ? $inferensi->getRules() : collect();

    $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();
    @endphp

    <h1 class="mt-4">Inferensi Forward Chaining for User: {{ $user->username }}</h1>

    @if(isset($success))
        <div class="alert alert-success">
            {{ $success }}
        </div>
    @endif
    
    <!-- Tombol Generate Tree -->
    <a href="{{ url('/forwardChaining/' . Auth::id() . '/' . Auth::id()) }}" class="btn btn-primary">Generate Inference</a>

    <br>
    <br>

    @if (!$tableExists)
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">There is no inference for this user.</li>
    </ol>
    @elseif ($inference->isEmpty())
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">There is no inference for this user.</li>
        </ol>
    @else
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Case Title</th>
                        {{-- <th>Case Goal</th> --}}
                        <th>Rule Id</th>
                        <th>Rule Goal</th>
                        <th>Match Value</th>
                        {{-- <th>Cocok</th> --}}
                    </tr>
                </thead>
                <tbody>
                    @foreach ($inference as $index => $inferensi )
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $kasus->case_title }}</td>
                        {{-- <td>
                            @php
                                $cleanedIfPart = preg_replace('/\b\d+_/', '', $inferensi->case_goal);
                            @endphp
                            {{ $cleanedIfPart }}
                        </td> --}}
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
                        {{-- <td>{{ $inferensi->cocok }}</td> --}}
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
