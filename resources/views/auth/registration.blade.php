<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Registration ES System</title>
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.css') }}">
</head>
<body class="d-flex justify-content-center align-items-center vh-100 bg-light">
    <div class="bg-white p-4 rounded shadow" style="max-width: 500px; width: 100%;">
        <h3 class="display-5 text-start mb-0">Create an Account</h3>

        {{-- Display error messages --}}
        @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

         {{-- Display success message --}}
         {{-- @if (session('success'))
         <div class="alert alert-success">
             {{ session('success') }}
         </div>
         @endif --}}

        <!-- Form Regis -->
        <form action="{{ route('registration') }}" method="POST" class="mt-4">
            @csrf
            <div class="form-group">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
            </div>
            <div class="form-group mt-2">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group mt-2">
                <input type="text" class="form-control" id="case_title" name="case_title" placeholder="Project Title" required>
            </div>
            <div class="form-group mt-2">
                <input type="text" class="form-control" id="case_desc" name="case_desc" placeholder="Project Deskripsi" required>
            </div>
            <button type="submit" class="btn btn-dark btn-block mt-4 form-control">Sign Up</button>
        </form>

        <div class="forgot-password mt-1">
            <h>Already have an account?</h>
            <a href="{{ route('login') }}" class="text-decoration-none"> Login here</a>
        </div>
    </div>
</body>
</html>