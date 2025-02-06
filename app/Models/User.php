<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    // Nama tabel
    protected $table = 'user';

    // Primary key tabel
    protected $primaryKey = 'user_id';

    // Field yang dapat diisi (mass assignable)
    protected $fillable = [
        'username',
        'password',
        'active',
        'role'
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    // Jika ada enum untuk active, bisa dibuat accessor untuk mempermudah akses enum
    public function getActiveAttribute($value)
    {
        return $value === 'T' ? 'Active' : 'Inactive';
    }

    // Jika kamu ingin menyembunyikan password saat diakses dalam response
    protected $hidden = [
        'password',
    ];
    
}
