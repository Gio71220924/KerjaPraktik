@extends('layouts.admin')

@section('content')

    <h1 class="mt-4">Edit Project {{ $user->username }}</h1>

    <form action="{{ route('admin.menu.case.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card-body">
            <div class="mb-3">
                <label for="case_title" class="form-label">Project Name</label>
                <input type="text" class="form-control" id="case_title" name="case_title" value="{{ old('case_title', $kasus->case_title) }}" required>
            </div>
            <div class="mb-3">
                <label for="case_desc" class="form-label">Project Description</label>
                <textarea class="form-control" id="case_desc" name="case_desc" rows="4">{{ old('case_desc', $kasus->case_desc) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

@endsection
