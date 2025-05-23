<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStage extends Model
{
    protected $fillable = [
        'title',
        'order',
        'created_by',
    ];

    public function applications($filter)
    {
        $application = JobApplication::where('is_archive', 0)->where('stage', $this->id);
        if(!empty($filter)){
            $application->where('created_at', '>=', $filter['start_date']);
            $application->where('created_at', '<=', $filter['end_date']);

            if(!empty($filter['job']))
            {
                $application->where('job', $filter['job']);
            }
        }


        $application = $application->orderBy('order')->get();

        return $application;
    }
    public function application()
    {
        return $this->hasMany('App\Models\JobApplication', 'stage', 'id');
    }
}
