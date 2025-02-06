<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Kasus extends Authenticatable
{
    use HasFactory;

    // Nama tabel
    protected $table = 'kasus';

    // Primary key tabel
    protected $primaryKey = 'case_num';

    // Field yang dapat diisi (mass assignable)
    protected $fillable = [
        'case_num',
        'case_title',
        'case_desc',
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

}
