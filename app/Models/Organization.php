<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'website',
        'linkedin',
        'facebook',
        'twitter',
        'billing_street',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'description',
        'contactemail',
        'contactphone',
        'contactjobroll',
        'contactname'
    ];


    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
