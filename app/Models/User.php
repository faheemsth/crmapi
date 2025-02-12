<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;


use Carbon\Carbon;
use App\Models\CompanyPermission;
use App\Models\DealTask;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;



    protected $appends = ['profile'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'avatar',
        'branch_id',
        'lang',
        'mode',
        'delete_status',
        'plan',
        'plan_expire_date',
        'requested_plan',
        'last_login_at',
        'created_by',
        'region_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public $settings;


    public function creatorId()
    {
        if ($this->type == 'team' || $this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }


    public function companyPermissions()
    {
        //return CompanyPermission::where('permitted_company_id', '=', $this->creatorId())->first();

        return $this->hasMany(CompanyPermission::class, 'user_id', 'id');
    }

    public function organization($id)
    {
       // dd($id);
       return Organization::where('user_id', $id)->first();
    }

    public function organizationLeadContactsList($id)
    {
        $contacts = Lead::join('client_deals as cd', 'leads.is_converted', '=', 'cd.deal_id')
                ->join('users', 'users.id', '=', 'cd.client_id')
                ->where('leads.organization_id', '=', $id)
                ->get();
            return $contacts;
    }


    public function organizationLeadContacts($id)
    {
        $count = Lead::join('client_deals as cd', 'leads.is_converted', '=', 'cd.deal_id')
                ->where('leads.organization_id', '=', $id)
                ->groupBy('leads.organization_id')
                ->count();
            return $count;
    }

    public function organizationLeadFiles($id)
    {
        $count = Lead::join('lead_files', 'leads.id', '=', 'lead_files.lead_id')
                ->where('leads.organization_id', '=', $id)
                ->groupBy('leads.organization_id')
                ->count();
            return $count;
    }


    public function organizationLeadNotes($id)
    {
        $count = OrganizationNote::where('organization_id', $id)->count();
        return $count;
    }

    public function organizationLeadTasks($id)
    {
        $count = DealTask::where('related_to', $id)->count();
        return $count;
    }

    public function organizationLeadNotesList($id)
    {
        $count = OrganizationNote::where('organization_id', $id)->orderBy('created_at', 'DESC')->get();
        return $count;
    }


    public function organizationLeadDiscussions($id)
    {
        $count = OrganizationDiscussion::join('users', 'organization_discussions.created_by', 'users.id')->where(['organization_discussions.organization_id' => $id])->orderBy('organization_discussions.created_at', 'DESC')->count();
        return $count;
    }


    public function organizationOpportunity($id)
    {
        $count = Lead::join('client_deals', 'client_deals.client_id', '=', 'leads.is_converted')->join('deal_applications', 'deal_applications.deal_id', '=', 'client_deals.deal_id')->where('leads.organization_id', $id)->groupBy('leads.organization_id')->count();
        return $count;
    }

    public function organizationTasks($id)
    {
        $count = DealTask::where('organization_id', $id)->groupBy('organization_id')->count();
        return $count;
    }

    public function organizationTasksList($id)
    {
        $count = DealTask::where('organization_id', $id)->get();
        return $count;
    }

    public function organizationOpportunitiesList($id)
    {
        $result = Lead::join('client_deals', 'client_deals.client_id', '=', 'leads.is_converted')->join('deal_applications', 'deal_applications.deal_id', '=', 'client_deals.deal_id')->where('leads.organization_id', $id)->groupBy('leads.organization_id')->get();
        return $result;
    }


    public function organizationActivitiesList($id)
    {
        $list = ActivityLog::join('client_deals', 'client_deals.deal_id', '=', 'activity_logs.deal_id')
        ->join('leads', 'leads.is_converted', '=', 'client_deals.client_id')
        ->get();
        return $list;
    }


    // public function countCompany()
    // {
    //     return User::where('type', '=', 'company')->where('created_by', '=', $this->creatorId())->count();
    // }

    public function getProfileAttribute()
    {

        if (!empty($this->avatar) && \Storage::exists($this->avatar)) {
            return $this->attributes['avatar'] = asset(\Storage::url($this->avatar));
        } else {
            return $this->attributes['avatar'] = asset(\Storage::url('avatar.png'));
        }
    }

    public function authId()
    {
        return $this->id;
    }



    public function ownerId()
    {
        if ($this->type == 'team' || $this->type == 'company' || $this->type == 'super admin') {
            return $this->id;
        } else {
            return $this->created_by;
        }
    }

    public function deals()
    {
        return $this->belongsToMany('App\Models\Deal', 'user_deals', 'user_id', 'deal_id');
    }

    public function leads()
    {
        return $this->belongsToMany('App\Models\Lead', 'user_leads', 'user_id', 'lead_id');
    }

    public function clientDeals()
    {
        return $this->belongsToMany('App\Models\Deal', 'client_deals', 'client_id', 'deal_id');
    }

    public function employee()
    {
        return $this->hasOne('App\Models\Employee', 'user_id', 'id');
    }
}
