@extends('layouts.user')

@section('content')

    @php
        $users = \App\Models\User::all(); 
        $cases = \App\Models\Kasus::all(); 
    @endphp

    <h1 class="mt-4">Shell Expert System User List</h1>
    <br>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Username</th>
                    <th>Case Title</th>
                    <th>Case Description</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $index => $user)
                    @php
                        $kasus = $cases->where('case_num', $user->user_id)->first();
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $kasus ? $kasus->case_title : 'No Case Found' }}</td>
                        <td>{{ $kasus ? $kasus->case_desc : 'No Case Found' }}</td>
                        <td>{{ $user->active }}</td>
                        <td>
                            @if ($user->active == 'Inactive')
                                <form action="{{ route('user.active', $user->user_id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Are you sure to activate this user?')">Activate</button>
                                </form>
                            @else
                                <form action="{{ route('user.inactive', $user->user_id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure to inactivate this user?')">Inactivate</button>
                                </form>
                            @endif
                        </td>
                        {{-- <td>
                            <a href="#" class="btn btn-warning btn-sm">Active</a>

                            <form action="#" method="GET" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure to inactive this user?')">Inctive</button>
                            </form>
                            
                            
                        </td> --}}
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endsection
