<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Rule extends Model
{
    // Primary key tabel
    protected $primaryKey = 'rule_id';

    // Field yang dapat diisi (mass assignable)
    protected $fillable = [
        'if_part',
        'then_part',
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    protected $table;

    public function setTableForUser($userId)
    {
        $this->table = 'rule_user_' . $userId;
        return $this;
    }

    /**
     * Check if the table exists in the database.
     */
    public function tableExists()
    {
        return DB::getSchemaBuilder()->hasTable($this->table);
    }

    public function getRules()
    {
        if ($this->tableExists()) {
            return DB::table($this->table)->get();
        } else {
            // Return empty collection if the table doesn't exist
            return collect([]);
        }
    }

}
