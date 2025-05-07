<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Stage;
use App\Models\Utility;
use App\Models\Pipeline;
use App\Models\ClientDeal;
use App\Models\University;
use App\Models\ActivityLog;
use App\Models\Agency;
use App\Models\ApplicationNote;
use App\Models\ApplicationStage;
use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Deal;
use App\Models\SavedFilter;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use App\Models\DealApplication;
use App\Models\LeadTag;
use Session;

class ApplicationsController extends Controller
{

    public function getApplications(Request $request)
{
    $usr = \Auth::user();

    if (!($usr->can('view application') || in_array($usr->type, ['super admin', 'company', 'Admin Team']) || $usr->can('level 1'))) {
        return response()->json(['status' => 'error', 'message' => __('Permission Denied.')], 403);
    }

    $perPage = (int) $request->input('num_results_on_page', env("RESULTS_ON_PAGE", 50));
    $companies = FiltersBrands();
    $brand_ids = array_keys($companies);

    $app_query = DealApplication::select('deal_applications.*')
        ->join('deals', 'deals.id', 'deal_applications.deal_id')
        ->leftJoin('leads', 'leads.is_converted', '=', 'deal_applications.deal_id')
        ->orderBy('deal_applications.created_at', 'desc');

    // Role-based filtering
    if ($usr->type == 'super admin' || $usr->type == 'Admin Team' || $usr->can('level 1')) {

        
    } else if ($usr->type == 'company') {
        $app_query->where('deals.brand_id', $usr->id);
    } elseif (in_array($usr->type, ['Project Director', 'Project Manager']) || $usr->can('level 2')) {
        $app_query->whereIn('deals.brand_id', $brand_ids);
    } elseif (($usr->type == 'Region Manager' || $usr->can('level 3')) && !empty($usr->region_id)) {
        $app_query->where('deals.region_id', $usr->region_id);
    } elseif (in_array($usr->type, ['Branch Manager', 'Admissions Officer', 'Career Consultant', 'Admissions Manager', 'Marketing Officer']) || ($usr->can('level 4') && !empty($usr->branch_id))) {
        $app_query->where('deals.branch_id', $usr->branch_id);
    } elseif ($usr->type === 'Agent') {
        $app_query->where(function ($query) use ($usr) {
            $query->where('deals.assigned_to', $usr->id)
                ->orWhere('deals.created_by', $usr->id);
        });
    } else {
        $app_query->where('deals.assigned_to', $usr->id);
    }

    // Apply filters
    $filters = $this->ApplicationFilters($request);
    foreach ($filters as $column => $value) {
        match ($column) {
            'name' => $app_query->whereIn('deal_applications.name', $value),
            'stage_id' => $app_query->whereIn('deal_applications.stage_id', $value),
            'university_id' => $app_query->whereIn('deal_applications.university_id', $value),
            'created_by' => $app_query->whereIn('deal_applications.created_by', $value),
            'brand' => $app_query->where('deals.brand_id', $value),
            'region_id' => $app_query->where('deals.region_id', $value),
            'branch_id' => $app_query->where('deals.branch_id', $value),
            'assigned_to' => $app_query->where('deals.assigned_to', $value),
            'created_at_from' => $app_query->whereDate('deal_applications.created_at', '>=', $value),
            'created_at_to' => $app_query->whereDate('deal_applications.created_at', '<=', $value),
            'tag' => $app_query->whereRaw('FIND_IN_SET(?, deal_applications.tag_ids)', [$value]),
            default => null,
        };
    }

    // Search
    if ($request->filled('search')) {
        $search = $request->input('search');
        if (strpos($search, 'APC') === 0) {
            $numericId = preg_replace('/^[A-Z]+/', '', $search);
            $app_query->where('deal_applications.id', $numericId);
        } else {
            $app_query->where(function ($query) use ($search) {
                $query->where('deal_applications.name', 'like', '%' . $search . '%')
                      ->orWhere('deal_applications.application_key', 'like', '%' . $search . '%')
                      ->orWhere('deal_applications.course', 'like', '%' . $search . '%');
            });
        }
    }

   
    // Get paginated results
    $applications = $app_query->groupBy('deal_applications.id')->paginate($perPage);
   

    return response()->json([
        'status' => 'success',
        'data' => $applications->items(),
        'current_page' => $applications->currentPage(),
        'last_page' => $applications->lastPage(),
        'total_records' => $applications->total(),
        'per_page' => $applications->perPage(),
    ]);
}
private function ApplicationFilters(Request $request)
{
    $filters = [];

    if ($request->filled('applications')) {
        $filters['name'] = $request->input('applications');
    }

    if ($request->filled('stages')) {
        $filters['stage_id'] = $request->input('stages');
    }

    if ($request->filled('created_by')) {
        $filters['created_by'] = $request->input('created_by');
    }

    if ($request->filled('universities')) {
        $filters['university_id'] = $request->input('universities');
    }

    if ($request->filled('brand')) {
        $filters['brand'] = $request->input('brand');
    }

    if ($request->filled('region_id')) {
        $filters['region_id'] = $request->input('region_id');
    }

    if ($request->filled('branch_id')) {
        $filters['branch_id'] = $request->input('branch_id');
    }

    if ($request->filled('created_at_from')) {
        $filters['created_at_from'] = $request->input('created_at_from');
    }

    if ($request->filled('created_at_to')) {
        $filters['created_at_to'] = $request->input('created_at_to');
    }

    if ($request->filled('lead_assigned_user')) {
        $filters['assigned_to'] = $request->input('lead_assigned_user');
    }

    if ($request->filled('tag')) {
        $filters['tag'] = $request->input('tag');
    }

    return $filters;
}

public function getDetailApplication(Request $request)
{
    $request->validate([
        'id' => 'required|exists:deal_applications,id',
    ]);

    $id = $request->id;

    $application = DealApplication::with([
        'city:id,name',
        'institute:id,name',
        'country:country_code,name'
    ])->where('id', $id)->first();

    if (!$application) {
        return response()->json([
            'status' => 'error',
            'message' => 'Application not found.'
        ], 404);
    }

    $stages = ApplicationStage::orderBy('id')->pluck('name', 'id')->toArray(); 
    $tags = [];

    $user = Auth::user();
    if ($user) {
        if (in_array($user->type, ['super admin', 'Admin Team'])) {
            $tags = LeadTag::pluck('id', 'tag')->toArray();
        } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
            $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag')->toArray();
        } elseif ($user->type == 'Region Manager') {
            $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag')->toArray();
        } else {
            $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag')->toArray();
        }
    }

    $stage_histories = StageHistory::where('type', 'application')
        ->where('type_id', $id)
        ->pluck('stage_id')
        ->toArray();

     
    $deposit_meta = DB::table('meta')->where([
        ['parent_id', '=', $application->id],
        ['stage_id', '=', 4]
    ])->get();

    $applied_meta = DB::table('meta')->where([
        ['parent_id', '=', $application->id],
        ['stage_id', '=', 5]
    ])->get();

    return response()->json([
        'status' => 'success',
        'data' => [
            'application' => $application,
            'stages' => $stages, 
            'tags' => $tags,
            'stage_histories' => $stage_histories, 
            'deposit_meta' => $deposit_meta,
            'applied_meta' => $applied_meta
        ]
    ]);
}



}
