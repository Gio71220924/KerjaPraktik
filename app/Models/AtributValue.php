<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;


class AtributValue extends Authenticatable
{
    use HasFactory;

    protected $table = 'atribut_value'; // Sesuaikan dengan nama tabel di database
    protected $primaryKey = 'value_id'; // Sesuaikan dengan primary key tabel

    protected $fillable = [
        'atribut_id', 
        'value_name', 
        'value_desc', 
        'user_id', 
        'case_num'
    ];

    // Menonaktifkan timestamps (created_at, updated_at)
    public $timestamps = false;

    // Relasi ke model Atribut
    public function atribut()
    {
        return $this->belongsTo(Atribut::class, 'atribut_id', );
    }
}
