@extends('layouts.admin')

@section('content')
    <h1 class="mt-4">Add New Case</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif
    
    <form action="{{ route('generate.case.store') }}" method="POST">
        @csrf
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
        
        <br>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
