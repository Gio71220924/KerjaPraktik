@extends('layouts.admin')

@section ('content')
    <div class="container-fluid px-4">
        <h1 class="mt-4">Support Vector Machine</h1>
        <ol class="breadcrumb mb-4">
            <li class="breadcrumb-item"><a href="{{ url('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item active">Support Vector Machine</li>
        </ol>
        <div class="card mb-4">
            <div class="card-body">
                Support Vector Machine (SVM) adalah algoritma pembelajaran mesin yang digunakan untuk klasifikasi dan regresi. SVM bekerja dengan mencari hyperplane optimal yang memisahkan data ke dalam kelas-kelas yang berbeda. Hyperplane ini dipilih sedemikian rupa sehingga jarak antara hyperplane dan titik data terdekat dari masing-masing kelas (disebut margin) adalah maksimal. Dengan demikian, SVM berusaha untuk menciptakan batas keputusan yang paling jelas antara kelas-kelas tersebut.
            </div>
        </div>
    </div>
@endsection