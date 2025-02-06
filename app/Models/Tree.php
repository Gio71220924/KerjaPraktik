<?php

namespace App\Models;

use Illuminate\Container\Attributes\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

class Tree extends Model
{
    protected $table;

    /**
     * Set tabel tree untuk user tertentu.
     */
    public function setTableForUser($user_id)
    {
        $this->table = "tree_user_{$user_id}";
    }

    /**
     * Periksa apakah tabel tree ada.
     */
    public function tableExists()
    {
        return Schema::hasTable($this->table);
    }

    /**
     * Ambil data tree dari tabel.
     */
    public function getRules()
    {
        if (!$this->tableExists()) {
            return collect();
        }
        return DB::table($this->table)->get();
    }

    /**
     * Insert node baru ke dalam tree.
     */
    public function insertNode($parentNode, $atributName, $valueName, $goal)
    {
        DB::table($this->table)->insert([
            'parent_node' => $parentNode,
            'atribut_name' => $atributName,
            'value_name' => $valueName,
            'goal' => $goal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
