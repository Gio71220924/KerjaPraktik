@extends('layouts.admin')

@section('content')

    <h1 class="mt-4">Attribute Value User: {{ $username }}</h1>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('admin.menu.atributValue.tambah') }}" class="btn btn-primary mb-3">Add New</a>


    @if($atributValues->isEmpty())
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">There is no attribute value for this user.</li>
    </ol>
    @else
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Case Title</th>
                    <th>Attribute Name</th>
                    <th>Attribute Value Name</th>
                    <th>Attribute Value Desc</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($atributValues as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td> <!-- Menampilkan nomor urut -->
                        <td>{{ $kasus->case_title }}</td> <!-- Menampilkan case_title -->
                        <td>
                            @php
                                // Menghapus angka di awal yang diikuti oleh underscore (_)
                                $cleanedAtributName = preg_replace('/^\d+_/', ' ', $item->atribut->atribut_name);
                                $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
                            @endphp
                            {{ $cleanedAtributName }}
                        </td> <!-- Menampilkan atribut_name dari tabel atribut -->
                        <td>{{ $item->value_name }}</td> <!-- Menampilkan value_name dari tabel atribut_value -->
                        @php
                                // Menghapus angka di awal yang diikuti oleh underscore (_)
                                $cleanedAtributName = preg_replace('/^\d+_/', '', $item->atribut_name);
                                $cleanedAtributName = str_replace('_', ' ', $cleanedAtributName);
                            @endphp
                            {{ $cleanedAtributName }}
                        <td>{{ $item->value_desc }}</td> <!-- Menampilkan value_desc dari tabel atribut_value -->
                        <td>
                            <a href="{{ route('admin.menu.atributValue.edit', $item->value_id) }}" class="btn btn-warning btn-sm">Edit</a>
                            <form action="{{ route('admin.menu.atributValue.hapus', $item->value_id) }}" method="POST" style="display:inline;">
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