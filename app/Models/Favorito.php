<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    //
    protected $fillable = [
        'user_id',
        'propiedad_id'
    ];
    public function user() {
        return $this->belongsTo(User::class);
    }
    public function propiedad() {
        return $this->belongsTo(Propiedad::class);
    }
}
