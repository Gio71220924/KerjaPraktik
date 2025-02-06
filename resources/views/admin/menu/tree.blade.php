@extends('layouts.admin')

@section('content')
    <h1 class="mt-4">Decision Tree for User: {{ Auth::user()->username }}</h1>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @elseif (!empty($message))
        <div class="alert alert-warning">
            {{ $message }}
        </div>
    @endif

    <!-- Tombol Generate Tree -->
    <a href="{{ route('tree.generate') }}" class="btn btn-primary mb-3">Generate</a>

    {{-- Jika tree tersedia, tampilkan data --}}
    @if (!empty($tree))
        @include('partials.tree_node', ['nodes' => $tree])
    @else
        <p>Please click the button to display the Decision Tree results.</p>
    @endif
@endsection
