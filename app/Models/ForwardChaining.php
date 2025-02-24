<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ForwardChaining extends Model
{
    protected $primaryKey = 'inf_id';

    protected $fillable = [
        'case_goal',
        'rule_id',
        'rule_goal',
        'match_value',
        'cocok',
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    protected $table;

    public function setTableForUser($userId)
    {
        $this->table = 'inferensi_fc_user_' . $userId;
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
