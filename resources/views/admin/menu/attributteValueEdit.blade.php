@extends('layouts.admin')

@section('content')

<h1 class="mt-4">Attribute Value Edit</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif

<form action="{{ route('admin.menu.atributValue.update', $atribut->value_id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label for="atribut_id" class="form-label">Attribute Name</label>
        <select name="atribut_id" class="form-control" required>
            @foreach($atributs as $item)
                @php
                    // Membersihkan atribut_name agar tampil tanpa angka dan underscore di awal, lalu mengganti "-" dengan spasi
                    $cleanedAtributName = preg_replace('/^\d+_/', ' ', $item->atribut_name);
                    $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
                @endphp
                <option value="{{ $item->atribut_id }}" {{ $atribut->atribut_id == $item->atribut_id ? 'selected' : '' }}>
                    {{ $cleanedAtributName }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label for="value_name" class="form-label">Attribute Value Name</label>
        <input type="text" class="form-control" id="value_name" name="value_name" value="{{ $atribut->value_name }}" required>
    </div>
    <div class="mb-3">
        <label for="value_desc" class="form-label">Attribute Value Desc</label>
        <textarea class="form-control" id="value_desc" name="value_desc">{{ $atribut->value_desc }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>

@endsection
