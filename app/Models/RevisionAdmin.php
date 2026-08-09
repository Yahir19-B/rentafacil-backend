<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionAdmin extends Model
{
    //
    protected $fillable = [
        'moderacion_id',
        'admin_id',
        'decision',
        'comentario'
    ];
    public function moderacion() {
        return $this->belongsTo(Moderacion::class);
    }
    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
