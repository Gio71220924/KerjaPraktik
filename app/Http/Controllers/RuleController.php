<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RuleController extends Controller
{
    public function generateRule($user_id, $case_num)
    {
        $user_id = Auth::id();  // Mendapatkan user_id dari user yang sedang login
        $case_num = $user_id;    // Menetapkan case_num sama dengan user_id

        $command = 'php "' . base_path('scripts/decision-tree/rule.php') . '" ' . $user_id . ' ' . $case_num;

        $output = shell_exec($command);

        return view('admin.menu.rule', compact('output', 'case_num'))->with('success', 'Rule updated successfully!'); 
    }
}
