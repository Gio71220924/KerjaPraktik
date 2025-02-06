<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Kasus;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('user.menu.user', compact('users'));
    }
    
    // Controller (UserController.php) - Method index
    public function active($id)
    {
        $user = User::findOrFail($id);
        $user->active = 'T';
        $user->save();

        return redirect()->route('user.menu.user')->with('success', 'User has been activated!');
    }

    // Menonaktifkan user
    public function inactive($id)
    {
        $user = User::findOrFail($id);
        $user->active = 'F';
        $user->save();

        return redirect()->route('user.menu.user')->with('success', 'User has been inactivated!');
    }
    
}
