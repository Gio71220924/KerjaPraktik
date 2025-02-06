<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Aplikasi ES</title>
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.css') }}">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
    <div class="bg-white p-4 rounded shadow" style="max-width: 500px; width: 100%;">
        <h3 class="display-5 text-start mb-0">Duta Expert System</h3>
        {{-- <h3 class="text-start fw-bold mt-0 ">Duta Expert System</h3> --}}

        <!-- Display error messages -->
        @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <!-- Form login -->
        <form action="{{ route('login') }}" method="POST" class="mt-4">
            @csrf
            <div class="form-group">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
            </div>
            <div class="form-group mt-2">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-outline-dark btn-block mt-4 form-control">Login</button>
        </form>

        <p class="mt-1 text-center mb-1">or</p>

        <form action="{{ route('login.admin') }}" method="GET">
            <button type="submit" class="btn btn-dark btn-block form-control">Login as Admin</button>
        </form>

        <div class="forgot-password mt-1">
            <h>Not a member?</h>
            <a href="{{ route('registration') }}" class="text-decoration-none"> Sign up now</a>
        </div>
    </div>
</body>
</html>