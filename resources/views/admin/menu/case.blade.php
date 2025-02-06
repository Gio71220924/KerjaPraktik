@extends('layouts.admin')

@section('content')

    @php
        $user = Auth::user();
        $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->first();
    @endphp

    <h1 class="mt-4">Project User: {{ $user->username }}</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">This Project Name Has Been Filled When You Registered</li>
    </ol>
    
    @if (isset($success))
        <div class="alert alert-success">
            {{ $success }}
        </div>
    @elseif ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <a href="{{ route('admin.menu.case.edit') }}" class="btn btn-primary mb-3">Edit Project</a>
    
    @if ($kasus)
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th>Username</th>
                <td>{{ $user->username }}</td>
            </tr>
            <tr>
                <th>Project Name</th>
                <td>{{ $kasus->case_title }}</td>
            </tr>
            <tr>
                <th>Project Description</th>
                <td>{{ $kasus->case_desc }}</td>
            </tr>
        </table>
    </div>
    @else
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">There is no case for this user.</li>
    </ol>
    @endif         

@endsection
