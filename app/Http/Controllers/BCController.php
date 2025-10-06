<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BCController extends Controller
{
    public function generateBC($user_id, $case_num) 
    {
        $user_id = Auth::id();  // Mendapatkan user_id dari user yang sedang login
        $case_num = $user_id;    // Menetapkan case_num sama dengan user_id

        $command = 'php "' . base_path('scripts/decision-tree/BC.php') . '" ' . $user_id . ' ' . $case_num;

        $output = shell_exec($command);

        return view('admin.menu.inferensi', compact('output', 'case_num'))->with('success', 'Inference updated successfully!'); 
    
    }
}
