<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaseUser extends Model
{
    protected $primaryKey = 'case_id';
    public $timestamps = false;

    /**
     * Set table name dynamically.
     */
    public function setTableForUser($userId)
    {
        $this->table = 'case_user_' . $userId;
        return $this;
    }

    /**
     * Check if the table exists.
     */
    public function tableExists()
    {
        return Schema::hasTable($this->table);
    }

    public function getRules()
    {
        return DB::table($this->table)->get(); // Ambil semua data dari tabel
    }
    
    // /**
    //  * Create table dynamically with attributes as columns.
    //  */
    // public function createTableForUser($userId)
    // {
    //     $tableName = 'case_user_' . $userId;
        
    //     if (!Schema::hasTable($tableName)) {
    //         Schema::create($tableName, function ($table) use ($userId) {
    //             $table->increments('case_id');
    //             $table->integer('user_id');
    //             $table->integer('case_num');

    //             // Ambil atribut untuk user_id ini
    //             $attributes = DB::table('atribut')->where('user_id', $userId)->get();

    //             foreach ($attributes as $attribute) {
    //                 $table->string($attribute->atribut_name);
    //             }
    //         });
    //     }
    // }
}
