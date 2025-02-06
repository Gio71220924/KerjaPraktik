@extends('layouts.admin')

@section('content')

<h1 class="mt-4">Add Attribute Value</h1>
    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif
<form action="{{ route('admin.menu.atributValue.store') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="case_title" class="form-label">Case Title</label>
        <select class="form-select" id="case_title" name="case_num" required>
            @foreach ($cases as $case)
                <option value="{{ $case->case_num }}">{{ $case->case_title }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label for="atribut_id" class="form-label">Attribute Name</label>
        <select class="form-select" id="atribut_id" name="atribut_id" required>
            @foreach ($atributs as $atribut)
                @php
                    // Membersihkan atribut_name agar tampil tanpa angka dan underscore di awal, lalu mengganti "-" dengan spasi
                    $cleanedAtributName = preg_replace('/^\d+_/', ' ', $atribut->atribut_name);
                    $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
                @endphp
                <option value="{{ $atribut->atribut_id }}">{{ $cleanedAtributName }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label for="value_name" class="form-label">Attribute Value Name</label>
        <input type="text" class="form-control" id="value_name" name="value_name" required>
    </div>
    <div class="mb-3">
        <label for="value_desc" class="form-label">Attribute Value Desc</label>
        <textarea class="form-control" id="value_desc" name="value_desc"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>

@endsection
