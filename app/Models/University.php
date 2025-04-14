<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class University extends Model
{
    use HasFactory;
    protected $appends = ['country_code', 'meta_data' ];
    protected $hidden = ['universityMeta'];
    protected $with = ['createdBy']; // Always eager load this relationship

    public function course()
    {
        return $this->hasOne(Course::class);
    }

    public function getCountryCodeAttribute()
    {
        $ct = Country::where('name', $this->country)->first();
        return strtolower($ct->country_code ?? '');
    }

    public function getMetaDataAttribute()
    {
        $metas = new \stdClass();

        foreach ($this->universityMeta as $data) {
            $key = $data->meta_key;
            $value = $data->meta_value;
            $decodedValue = json_decode($value);
            $metas->$key = json_last_error() === JSON_ERROR_NONE ? $decodedValue : $value;
        }

        return $metas;
    }

    public function universityMeta()
    {
        return $this->hasMany(UniversityMeta::class, 'university_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by'); // Ensure 'created_by' matches your DB column
    }


}
