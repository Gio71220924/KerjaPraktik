<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Atribut extends Authenticatable
{
    use HasFactory;

    // Nama tabel
    protected $table = 'atribut';

    // Primary key tabel
    protected $primaryKey = 'atribut_id';

    // Field yang dapat diisi (mass assignable)
    protected $fillable = [
        'atribut_name',
        'goal',
        'atribut_desc',
        'case_num',
        'user_id',
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    // Accessor untuk goal enum (opsional)
    public function getGoalAttribute($value)
    {
        return $value === 'T' ? 'True' : 'False';
    }

    
}
