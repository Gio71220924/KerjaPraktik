<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DecisionTreeController extends Controller
{
    public function showTree()
    {
        $user_id = Auth::id();
        $tableName = 'tree_user_' . $user_id;

        // Cek apakah tabel ada
        if (!Schema::hasTable($tableName)) {
            // Tabel tidak tersedia
            $tree = [];
            $message = 'Tree data not found. Please click the Generate Decision Tree button.';
        } else {
            // Ambil data dari tabel
            $treeData = DB::table($tableName)->get();

            if ($treeData->isEmpty()) {
                // Tabel ada tapi kosong
                $tree = [];
                $message = 'Tree data not found. Please click the Generate Decision Tree button.';
            } else {
                // Proses data menjadi tree
                $treeArray = $treeData->toArray();

                $buildTree = function ($parentId = 0) use (&$treeArray, &$buildTree) {
                    $branch = [];
                    foreach ($treeArray as $item) {
                        if ($item->parent_node == $parentId) {
                            $item->children = $buildTree($item->tree_id);
                            $branch[] = $item;
                        }
                    }
                    return $branch;
                };

                $tree = $buildTree(0);
                $message = null; // Tidak ada pesan jika data tersedia
            }
        }

        return view('admin.menu.tree', compact('tree', 'message'));
    }

    public function generateTree()
    {
        $user_id = Auth::id();
        $case_num = $user_id;

        // Jalankan script untuk menghasilkan tree
        $command = 'php "' . base_path('scripts/decision-tree/c45.php') . '" ' . $user_id . ' ' . $case_num;
        shell_exec($command);

        // Redirect kembali ke halaman tree
        return redirect()->route('tree.show')->with('success', 'Decision Tree successfully generated.');
    }
}
