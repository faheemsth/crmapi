<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id','document_id','document_value','created_by'
    ];
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee_id');
    }
}
