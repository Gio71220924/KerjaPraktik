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

    <a href="{{ route('SVM.generate') }}" class="btn btn-primary mb-3">Generate / Train SVM</a>

    @if(!empty($svmData))
        <h5>Riwayat Training:</h5>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Durasi (detik)</th>
                    <th>Jumlah Data</th>
                    <th>Lokasi Model</th>
                    <th>Output</th>
                    <th>Dibuat Pada</th>
                </tr>
            </thead>
            <tbody>
                @foreach($svmData as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>{{ $item->status }}</td>
                        <td>{{ isset($item->duration) ? number_format($item->duration, 4) : '-' }}</td>
                        <td>{{ $item->row_count ?? '-' }}</td>
                        <td>
                            @if(!empty($item->model_path))
                                <code>{{ $item->model_path }}</code>
                            @else
                                -
                            @endif
                        </td>
                        <td><pre class="mb-0">{{ $item->output }}</pre></td>
                        <td>{{ $item->created_at }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
