<?php

namespace App\Http\Controllers;

use App\Services\SVMTrainer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SVMController extends Controller
{
    public function __construct(private readonly SVMTrainer $trainer)
    {
    }

    public function show()
    {
        $user_id = Auth::id();
        $tableName = 'svm_user_' . $user_id;

        if (!Schema::hasTable($tableName)) {
            $svmData = [];
            $message = 'SVM data not found. Please click the "Generate SVM" button.';
        } else {
            $svmDataRaw = DB::table($tableName)->get();
            if ($svmDataRaw->isEmpty()) {
                $svmData = [];
                $message = 'No SVM data available. Please generate the model first.';
            } else {
                $svmData = $svmDataRaw->toArray();
                $message = null;
            }
        }

        return view('admin.menu.svm', compact('svmData', 'message'));
    }

    public function generateSVM()
    {
        $user_id = Auth::id();
        try {
            $result = $this->trainer->trainForUser($user_id);
        } catch (RuntimeException $exception) {
            return redirect()->route('SVM.show')->with('error', $exception->getMessage());
        }

        return redirect()->route('SVM.show')->with([
            'success' => 'SVM generated successfully.',
            'svm_summary' => $result['summary'],
        ]);
    }
}
