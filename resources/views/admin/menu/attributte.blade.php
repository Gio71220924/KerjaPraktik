@extends('layouts.admin')

@section('content')
    <h1 class="mt-4">Attribute User: {{ $username }}</h1>
    
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
        </div>
    @endif

    <a href="{{ route('admin.menu.atribut.tambah') }}" class="btn btn-primary mb-3">Add New</a>
    
    @if($atribut->isEmpty())
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">There is no attribute for this user.</li>
    </ol>
    @else
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Case Title</th>
                    <th>Attribute Name</th>
                    <th>Goal</th>
                    <th>Attribute Desc</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($atribut as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td> <!-- Menambahkan nomor urut -->
                        <td>{{ $kasus->case_title }}</td> <!-- Menampilkan case_title -->
                        <td>
                            @php
                                // Menghapus angka di awal yang diikuti oleh underscore (_)
                                $cleanedAtributName = preg_replace('/^\d+_/', '', $item->atribut_name);
                                $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
                            @endphp
                            {{ $cleanedAtributName }}
                        </td>                        
                        <td>{{ $item->goal }}</td>
                        <td>{{ $item->atribut_desc }}</td>
                        <td>
                            <a href="{{ route('admin.menu.atribut.edit', $item->atribut_id) }}" class="btn btn-warning btn-sm">Edit</a>
                            <form action="{{ route('admin.menu.atribut.hapus', $item->atribut_id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
                    

@endsection