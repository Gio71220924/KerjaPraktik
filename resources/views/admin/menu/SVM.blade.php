<!-- View  -->
@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h2>Support Vector Machine (SVM)</h2>
    <hr>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($message)
        <div class="alert alert-warning">{{ $message }}</div>
    @endif

    <a href="{{ route('SVM.generate') }}" class="btn btn-primary mb-3">Train SVM</a>

    @if(!empty($svmData))
        <h5>Riwayat Training:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Output</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($svmData as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->status }}</td>
                        <td><pre>{{ $item->output }}</pre></td>
                        <td>{{ $item->created_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
