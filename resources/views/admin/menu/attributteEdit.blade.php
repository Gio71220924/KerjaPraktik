@extends('layouts.admin')

@section('content')

<h1 class="mt-4">Attribute Edit</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif
    
<form action="{{ route('admin.menu.atribut.update', $atribut->atribut_id) }}" method="POST">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label for="atribut_name" class="form-label">Attribute Name</label>
        @php
            // Membersihkan atribut_name agar tampil tanpa angka dan underscore di awal, lalu mengganti "-" dengan spasi
            $cleanedAtributName = preg_replace('/^\d+_/', '', $atribut->atribut_name);
            $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
        @endphp
        <input type="text" class="form-control" id="atribut_name" name="atribut_name" value="{{ $cleanedAtributName }}" required>
        <small class="text-muted">Setiap kata yang memiliki spasi harus diganti dengan tanda underscore ( _ ).  Contoh: printer epson harus diganti dengan printer_epson.</small>
    </div>
    
    <div class="mb-3">
        <label for="goal" class="form-label">Goal</label>
        <select class="form-select" id="goal" name="goal" required>
            <option value="T" {{ $atribut->goal == 'T' ? 'selected' : '' }}>True</option>
            <option value="F" {{ $atribut->goal == 'F' ? 'selected' : '' }}>False</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="atribut_desc" class="form-label">Attribute Desc</label>
        <textarea class="form-control" id="atribut_desc" name="atribut_desc">{{ $atribut->atribut_desc }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
</form>

@endsection

