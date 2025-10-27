<!-- View  -->
@extends('layouts.admin')

@section('content')
<div class="container mt-4">
    <h2>Support Vector Machine (SVM)</h2>
    <hr>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('svm_summary'))
        <pre class="alert alert-info">{{ session('svm_summary') }}</pre>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($message)
        <div class="alert alert-warning">{{ $message }}</div>
    @endif

    <a href="{{ route('SVM.generate') }}" class="btn btn-primary mb-3">Generate / Train SVM</a>

    @if(!empty($svmData))
        <h5>Riwayat Training:</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Waktu (detik)</th>
                    <th>Model Path</th>
                    <th>Output</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($svmData as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->status }}</td>
                        <td>{{ number_format($item->waktu ?? 0, 6) }}</td>
                        <td class="text-break">{{ $item->model_path }}</td>
                        <td><pre class="mb-0">{{ $item->output }}</pre></td>
                        <td>{{ $item->created_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
