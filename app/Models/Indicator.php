<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $fillable = [
        'branch',
        'designation',
        'customer_experience',
        'marketing',
        'administration',
        'professionalism',
        'integrity',
        'attendance',
        'created_by',
        'created_user',
        'rating',
    ];

    public static $technical = [
        'None',
        'Beginner',
        'Intermediate',
        'Advanced',
        'Expert / Leader',
    ];

    public static $organizational = [
        'None',
        'Beginner',
        'Intermediate',
        'Advanced',
    ];

    public function branches()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
    }

    public function departments()
    {
        return $this->hasOne('App\Models\Department', 'id', 'department');
    }

    public function designations()
    {
        return $this->hasOne('App\Models\Designation', 'id', 'designation');
    }

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_user');
    }
    public function branch()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch');
    }

    public function brand()
    {
        return $this->hasOne('App\Models\User', 'id', 'brand_id');
    }

    public function region()
    {
        return $this->hasOne('App\Models\Region', 'id', 'region_id');
    }

    public function created_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'created_by');
    }

    public function updated_by()
    {
        return $this->hasOne('App\Models\User', 'id', 'updated_by');
    }
}
