<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competencies extends Model
{
    protected $fillable = [
        'name',
        'type',
        'created_by',
    ];
    protected $appends = ['role_names'];


    public function performance()
    {
        return $this->hasOne('Spatie\Permission\Models\Role', 'id', 'type');
    }
    public function getRoleNamesAttribute()
    {
        $ids = explode(',', $this->type);
        $roles = \Spatie\Permission\Models\Role::whereIn('id', $ids)->pluck('name')->toArray();
        return implode(', ', $roles);

    }
}