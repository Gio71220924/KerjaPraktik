@extends('layouts.admin')

@section('content')

    @php
        $user = Auth::user();
        $ruleModel = new \App\Models\Rule();
        $ruleModel->setTableForUser($user->user_id);

        // Periksa apakah tabel ada
        $tableExists = $ruleModel->tableExists();
        $rules = $tableExists ? $ruleModel->getRules() : collect();

        // Pagination sederhana di level view
        $perPage = 10;
        $page    = max((int) request()->input('page', 1), 1);
        $rulesPaged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rules->slice(($page - 1) * $perPage, $perPage),
            $rules->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
        
        $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();
    @endphp

    <h1 class="mt-4">Rule User: {{ $user->username }}</h1>

    @if(isset($success))
        <div class="alert alert-success">
            {{ $success }}
        </div>
    @endif

    <a href="{{ url('/rule/' . Auth::id() . '/' . Auth::id()) }}" class="btn btn-primary">Generate Rule</a>

    <br>


    <br>

    @if (!$tableExists)
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">There is no rule for this user.</li>
        </ol>
    @elseif ($rules->isEmpty())
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">There is no rule for this user.</li>
        </ol>
    @else
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Case Title</th>
                            <th>Rule If</th>
                            <th>Rule Then</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rulesPaged as $index => $rule )
                        <tr>
                            <td>{{ ($rulesPaged->firstItem() ?? 0) + $index }}</td>
                            <td>{{ $kasus->case_title }}</td>
                            <td>
                                @php
                                    $cleanedIfPart = preg_replace('/\b\d+_/', ' ', $rule->if_part);
                                    $cleanedIfPart = str_replace('_', ' ', $cleanedIfPart);
                                    $cleanedIfPart = str_replace('-', ' ', $cleanedIfPart);
                                    $cleanedIfPart = str_replace('=', ' =', $cleanedIfPart);
                                @endphp
                                {{ $cleanedIfPart }}
                            </td>
                            <td>
                                @php
                                    $cleanedIfPart = preg_replace('/\b\d+_/', ' ', $rule->then_part);
                                    $cleanedIfPart = str_replace('_', ' ', $cleanedIfPart);
                                    
                                    $cleanedIfPart = str_replace('-', ' ', $cleanedIfPart);
                                    $cleanedIfPart = str_replace('=', ' =', $cleanedIfPart);
                                @endphp
                                {{ $cleanedIfPart }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-3">
                    {{ $rulesPaged->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    @endif

@endsection
