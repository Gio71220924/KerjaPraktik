@extends('layouts.admin')

@section('content')
    <h1 class="mt-4">Add New Consultation</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif

    <form action="{{ route('test.case.store') }}" method="POST">
        {{-- {{ route('inference.generate', ['user_id' => Auth::id(), 'case_num' => Auth::id()]) }} --}}
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
    
        <button type="submit" name="action_type" value="matching" class="btn btn-primary">Matching Rule</button>
        <button type="submit" name="action_type" value="fc" class="btn btn-primary">Forward Chaining</button>
        <button type="submit" name="action_type" value="bc" class="btn btn-primary">Backward Chaining</button>
    </form>
    <br>
    {{-- <form action="#">
        <button type="submit" name="action_type" value="fc" class="btn btn-primary">Forward Chaining</button>
    </form>
    <br>
    <form action="#">
        <button type="submit" name="action_type" value="bc" class="btn btn-primary">Backward Chaining</button>
    </form> --}}
@endsection
