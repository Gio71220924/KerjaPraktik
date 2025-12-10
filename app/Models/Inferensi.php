<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Inferensi extends Model
{
    // Primary key tabel
    protected $primaryKey = 'inf_id';

    // Field yang dapat diisi (mass assignable)
    protected $fillable = [
        'case_goal',
        'rule_id',
        'rule_goal',
        'match_value',
        'cocok',
        'waktu',
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    protected $table;

    public function setTableForUser($userId)
    {
        $this->table = 'inferensi_user_' . $userId;
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
            // Ambil data secukupnya supaya tampilan tidak kehabisan memori
            return DB::table($this->table)
                ->orderByDesc('inf_id')
                ->limit(500)
                ->get();
        } else {
            // Return empty collection if the table doesn't exist
            return collect([]);
        }
    }

}
