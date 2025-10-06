@extends('layouts.admin')

@section('content')

    @php
        use Illuminate\Support\Facades\Schema;

        $user = Auth::user();
        $generate = new \App\Models\Consultation();
        $generate->setTableForUser($user->user_id);

        // Periksa apakah tabel ada
        $tableExists = $generate->tableExists();
        $generateCase = $tableExists ? $generate->paginate(25) : collect(); // Paginasi dengan 25 baris per halaman
    
        $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();
    
        // Mendapatkan kolom-kolom dari tabel yang sesuai
        $columns = $tableExists ? Schema::getColumnListing($generate->getTable()) : [];

        // Mengambil atribut yang goal-nya bukan 'T'
        $validAtributs = DB::table('atribut')
                ->where('user_id', $user->user_id)
                ->where('goal', '!=', 'T')
                ->pluck('atribut_name', 'atribut_id'); 
        
        // Filter kolom hanya untuk atribut yang valid
        $filteredColumns = array_filter($columns, function ($column) use ($validAtributs) {
            return collect($validAtributs)->keys()->contains(explode('_', $column)[0]);
        });
    
        // Kolom yang ingin disembunyikan
        $excludeColumns = ['case_id', 'user_id', 'case_num'];

    @endphp

    <h1 class="mt-4">Consultation for User:{{ $user->username }}</h1>
 
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="d-flex gap-2">
        <a href="{{ route('test.case.create') }}" class="btn btn-primary">Add New</a>
    </div>

    <br>

    @if (!$tableExists)
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">There is no case for this user.</li>
        </ol>
    @elseif ($generateCase->isEmpty())
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item active">The table has been created but no data is available yet.</li>
        </ol>
    @else
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Id</th>
                        {{-- <th>Case Title</th> --}}
                        @foreach($filteredColumns as $column)
                            @if(!in_array($column, $excludeColumns))
                                <th>{{ (str_replace('_', ' ', preg_replace('/\b\d+_/', ' ', $column))) }}</th>
                            @endif
                        @endforeach
                        <th>Algortihm</th>
                        {{-- <th>Action</th> --}}
                    </tr>
                </thead>
                <tbody>
                    @foreach ($generateCase as $index => $row)
                        <tr>
                            <td>{{ $row->case_id }}</td>
                            {{-- <td>{{ $kasus->case_title }}</td> --}}
                            @foreach($filteredColumns as $column)
                                @if(!in_array($column, $excludeColumns))
                                    <td>
                                        @php
                                            $cleanedIfPart = preg_replace('/\b\d+_/', ' ', $row->$column);
                                            $cleanedIfPart = str_replace('_', ' ', $cleanedIfPart);
                                            $cleanedIfPart = str_replace('-', ' ', $cleanedIfPart);
                                        @endphp
                                        {{ $cleanedIfPart }}
                                    </td>
                                @endif
                            @endforeach
                            @if(in_array('algoritma', $columns))
                                <td>{{ $row->algoritma }}</td>
                            @else
                                <td>-</td> <!-- Placeholder jika kolom tidak ditemukan -->
                            @endif
                            {{-- <td>
                                <a href="{{ route('test.case.edit', $row->case_id) }}" class="btn btn-warning btn-sm">Edit</a>
                                <form action="{{ route('test.case.destroy', $row->case_id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>                         --}}
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Navigasi Pagination -->
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Table pagination">
                    <ul class="pagination pagination-sm">
                        @if($generateCase->onFirstPage())
                            <li class="page-item disabled"><span class="page-link">Previous</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $generateCase->previousPageUrl() }}">Previous</a></li>
                        @endif

                        @foreach ($generateCase->links()->elements[0] as $page => $url)
                            @if ($page == $generateCase->currentPage())
                                <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach

                        @if($generateCase->hasMorePages())
                            <li class="page-item"><a class="page-link" href="{{ $generateCase->nextPageUrl() }}">Next</a></li>
                        @else
                            <li class="page-item disabled"><span class="page-link">Next</span></li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
    @endif

@endsection
