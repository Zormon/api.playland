<?php

namespace App\Models;

class User extends Model {
    protected $hidden = ['password', 'created_at', 'updated_at', 'roles', 'user_id'];

    protected $fillable = ['loginid', 'password', 'roles'];

    public function adulto() {
        return $this->hasOne(Adulto::class, 'user_id');
    }
}
