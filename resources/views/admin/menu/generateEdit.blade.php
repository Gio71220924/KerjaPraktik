@extends('layouts.admin')

@section('content')
<h1 class="mt-4">Edit Case</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif
    
<form action="{{ route('generate.case.update', $case->case_id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row">
        @foreach ($atributs as $atribut)
            @php
                $kolom_name = "{$atribut->atribut_id}_{$atribut->atribut_name}";
                $current_value = $case->$kolom_name ?? null;
                $values = DB::table('atribut_value')
                    ->where('atribut_id', $atribut->atribut_id)
                    ->where('user_id', Auth::id())
                    ->get();
            @endphp

            <div class="form-group col-md-6">
                <label for="{{ $kolom_name }}">{{ ucfirst($atribut->atribut_name) }}</label>
                <select name="{{ $kolom_name }}" id="{{ $kolom_name }}" class="form-control" required>
                    <option value="">Select an option</option>
                    @foreach ($values as $value)
                        <option value="{{ $value->value_id . '_' . $value->value_name }}" 
                            {{ $current_value == ($value->value_id . '_' . $value->value_name) ? 'selected' : '' }}>
                            {{ explode('_', $value->value_name, 2)[1] ?? $value->value_name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endforeach
    </div>

    <button type="submit" class="btn btn-primary mt-4">Update</button>
</form>
@endsection
