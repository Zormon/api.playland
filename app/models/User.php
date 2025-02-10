<?php

namespace App\Models;

class User extends Model {
    protected $hidden = ['id', 'password', 'created_at', 'updated_at', 'roles', 'user_id'];

    public function adulto() {
        return $this->hasOne(Adulto::class, 'user_id');
    }
}
