@extends('layouts.admin')

@section('content')

<h1 class="mt-4">Add Attribute</h1>
    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif
<form action="{{ route('admin.menu.atribut.store') }}" method="POST">
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
        <label for="atribut_name" class="form-label">Attribute Name</label>
        <input type="text" class="form-control" id="atribut_name" name="atribut_name" required>
        <small class="text-muted">Any word that has spaces must be replaced with an underscore ( _ ). Ex: printer epson must be replaced with printer_epson.</small>
    </div>
    <div class="mb-3">
        <label for="goal" class="form-label">Goal</label>
        <select class="form-select" id="goal" name="goal" required>
            <option value="T">True</option>
            <option value="F">False</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="atribut_desc" class="form-label">Attribute+ Desc</label>
        <textarea class="form-control" id="atribut_desc" name="atribut_desc"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit</button>
</form>

@endsection
