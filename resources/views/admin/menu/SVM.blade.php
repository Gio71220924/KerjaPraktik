<!-- View  -->

<!-- @extends('layouts.admin')

@section ('content')
    <div class="container-fluid px-4">
        <h1 class="mt-4">Support Vector Machine</h1>
        <br><br>
        <div class="card mb-4">
            <div class="card-body">
                Support Vector Machine (SVM) adalah algoritma pembelajaran mesin yang digunakan untuk klasifikasi dan regresi. SVM bekerja dengan mencari hyperplane optimal yang memisahkan data ke dalam kelas-kelas yang berbeda. Hyperplane ini dipilih sedemikian rupa sehingga jarak antara hyperplane dan titik data terdekat dari masing-masing kelas (disebut margin) adalah maksimal. Dengan demikian, SVM berusaha untuk menciptakan batas keputusan yang paling jelas antara kelas-kelas tersebut.
            </div>
        </div>
    </div>
@endsection -->


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
