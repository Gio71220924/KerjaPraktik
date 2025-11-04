<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InferenceController extends Controller
{
    public function generateInference($user_id, $case_num)
    {
        $user_id  = Auth::user()->user_id;  // ← konsisten
        $case_num = $user_id;

        $command = 'php "' . base_path('scripts/decision-tree/matching_rule.php') . '" ' . $user_id . ' ' . $case_num;
        $output = shell_exec($command);

        return view('admin.menu.inferensi', compact('output', 'case_num'))->with('success', 'Inference updated successfully!'); 
    }

    public function generate($user_id, $case_num)
    {
        $user_id  = Auth::user()->user_id;  // ← konsisten
        $case_num = $user_id;

        $command = 'php "' . base_path('scripts/decision-tree/matching_rule.php') . '" ' . $user_id . ' ' . $case_num;
        $output = shell_exec($command);

        return view('admin.menu.inferensi', compact('output', 'case_num'))->with('success', 'Inference updated successfully!');
    }
}
