@extends('layouts.user')

@section('content')
    <h1>Edit User and Case</h1>

    <form action="{{ route('user.update', ['user_id' => $user->user_id]) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="case_title">Case Title</label>
            <input type="text" name="case_title" id="case_title" value="{{ old('case_title', $kasus->case_title ?? '') }}" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="case_desc">Case Description</label>
            <textarea name="case_desc" id="case_desc" class="form-control" required>{{ old('case_desc', $kasus->case_desc ?? '') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
    </form>
@endsection
