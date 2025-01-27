<?php

namespace App\Http\Controllers;

use Session;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Models\Label;
use App\Models\Stage;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Region;
use App\Models\Source;
use App\Models\Country;
use App\Models\Utility;
use App\Models\DealCall;
use App\Models\DealFile;
use App\Models\DealNote;
use App\Models\DealTask;
use App\Models\Pipeline;
use App\Models\UserDeal;
use App\Models\DealEmail;
use App\Models\ClientDeal;
use App\Models\University;
use App\Mail\SendDealEmail;
use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\SavedFilter;
use App\Models\Notification;
use App\Models\StageHistory;
use Illuminate\Http\Request;

use App\Models\DealDiscussion;
use App\Models\ProductService;
use App\Models\TaskDiscussion;
use App\Events\NewNotification;
use App\Models\Agency;
use App\Models\DealApplication;
use App\Models\ApplicationStage;
use App\Models\ClientPermission;
use App\Models\CompanyPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\ApplicationNote;
use App\Models\instalment;
use App\Models\LeadTag;
use App\Models\LeadStage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GeneralController extends Controller
{


    public function getDefaultFiltersData(Request $request)
    {

            $validator = Validator::make($request->all(), [
                'module' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }
            //$user_type = User::distinct()->pluck('type', 'id')->toArray();
            $access_levels = accessLevel();

            if (!empty($request->brand) || !empty($request->region_id) || !empty($request->branch_id)) {
                $filters = BrandsRegionsBranchesForEdit($request->brand, $request->region_id, $request->branch_id);
            } else {
                $filters = BrandsRegionsBranches();
            }

            $type = \Auth::user()->type;

            $saved_filters = SavedFilter::where('created_by', \Auth::user()->id)->where('module', $request->module)->get();


            return response()->json([
                'status' => 'success',
                'access_levels' => $access_levels,
                'filters' => $filters,
                'type' => $type,
                'saved_filters' => $saved_filters,
                'message' => 'Data fetched successfully'
            ]);

    }


    public function getRegionBrands(Request $request)
{

    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'type' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors()
        ], 422);
    }
    $id = $request->input('id');
    $type = $request->input('type');

    if ($type == 'branch') {
        // Fetch regions based on the brand ID
        $regions = User::where('type', 'client')->where('branch_id', $id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        // Return JSON response with regions
        return response()->json([
            'status' => 'success',
            'employees' => $regions,
        ]);
    } elseif  ($type == 'brand') {
        // Fetch regions based on the brand ID
        $regions = Region::where('brands', $id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        // Return JSON response with regions
        return response()->json([
            'status' => 'success',
            'regions' => $regions,
        ]);
    } elseif ($type == 'region') {
        // Fetch branches based on the region ID
        $branches = Branch::where('region_id', $id)->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        // Return JSON response with branches
        return response()->json([
            'status' => 'success',
            'branches' => $branches,
        ]);
    } elseif ($type == 'institute') {
        // Fetch institute details based on the ID
        $institute = University::where('id', $id)->first();

        // If institute exists, get the intake months
        if ($institute) {
            $intake_months = $institute->intake_months ?? '';
            $intake_months = explode(',', $intake_months);

            return response()->json([
                'status' => 'success',
                'intake_months' => $intake_months,
            ]);
        } else {
            return response()->json([
                'status' => 'failure',
                'message' => 'Institute not found.',
            ]);
        }
    } else {
        // Fetch region details based on the ID
        $region = Region::where('id', $id)->first();
        $brands = [];

        if ($region) {
            $ids = explode(',', $region->brands);
            $brands = User::whereIn('id', $ids)->where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

            // Return JSON response with brands
            return response()->json([
                'status' => 'success',
                'brands' => $brands,
            ]);
        } else {
            return response()->json([
                'status' => 'failure',
                'message' => 'Region not found.',
            ]);
        }
    }
}


    public function getAllBrands(Request $request)
{



        // Fetch region details based on the ID
        $region = Region::where('id', $id)->first();
        $brands = [];

        $ids = explode(',', $region->brands);
        $brands = User::where('type', 'company')->orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        if ($brands) {


            // Return JSON response with brands
            return response()->json([
                'status' => 'success',
                'brands' => $brands,
            ]);
        } else {
            return response()->json([
                'status' => 'failure',
                'message' => 'Brand not found.',
            ]);
        }

}


public function getFilterData(Request $request)
{
    // Validate incoming request parameters
    $validator = Validator::make($request->all(), [
        'brand_id' => 'required|integer|exists:users,id',
        'region_id' => 'required|integer|exists:regions,id',
        'branch_id' => 'required|integer|exists:branches,id',
        'type' => 'required|string|in:lead,tasks',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'failure',
            'errors' => $validator->errors(),
        ], 422);
    }

    // Extract validated parameters
    $brand_id = $request->input('brand_id');
    $region_id = $request->input('region_id');
    $branch_id = $request->input('branch_id');
    $type = $request->input('type');

    $data = [];

    if ($type == 'lead') {
        if (!empty($region_id) && !empty($branch_id)) {
            $leads = Lead::where('branch_id', $branch_id)
                ->where('region_id', $region_id)
                ->pluck('name', 'id')
                ->toArray();
        } else {
            $leads = Lead::where('brand_id', $brand_id)
                ->when($region_id, fn($query) => $query->where('region_id', $region_id))
                ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
                ->pluck('name', 'id')
                ->toArray();
        }

        $data = $leads;
    } elseif ($type == 'tasks') {
        if (!empty($region_id) && empty($branch_id)) {
            $tasks = DealTask::where('brand_id', $brand_id)
                ->where('region_id', $region_id)
                ->pluck('name', 'id')
                ->toArray();
        } else {
            $tasks = DealTask::where('brand_id', $brand_id)
                ->when($region_id, fn($query) => $query->where('region_id', $region_id))
                ->when($branch_id, fn($query) => $query->where('branch_id', $branch_id))
                ->pluck('name', 'id')
                ->toArray();
        }

        $data = $tasks;
    }

    return response()->json([
        'status' => 'success',
        'data' => $data,
    ]);
}



public function getFilterBranchUsers(Request $request)
{

    // Validate incoming request parameters
    $validator = Validator::make($request->all(), [
        'branch_id' => 'required|integer|exists:branches,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'failure',
            'errors' => $validator->errors(),
        ], 422);
    }
    $html = FiltersBranchUsers($request->branch_id);


    return response()->json([
        'status' => 'success',
        'data' => $html,
    ]);
}


    public function getSources()
    {
        $sources = Source::pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $sources,
        ], 200);
    }

    /**
     * Get Branches
     */
    public function getBranches()
    {
        $branches = Branch::pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $branches,
        ], 200);
    }

    /**
     * Get Stages
     */
    public function getStages()
    {
        $stages = LeadStage::all();
        return response()->json([
            'status' => 'success',
            'data' => $stages,
        ], 200);
    }

    /**
     * Get Saved Filters
     */
    public function getSavedFilters(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $user = \Auth::user(); // Authenticated user

        $savedFilters = SavedFilter::where('created_by', $user->id)
            ->where('module', $request->module)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $savedFilters,
        ], 200);
    }


public function getTags(Request $request)
{
    if (Auth::check()) {
        $user = Auth::user();
        $tags = [];

        if (in_array($user->type, ['super admin', 'Admin Team'])) {
            $tags = LeadTag::pluck('id', 'tag')->toArray();
        } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
            $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag')->toArray();
        } elseif ($user->type === 'Region Manager') {
            $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag')->toArray();
        } else {
            $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag')->toArray();
        }

        return response()->json([
            'success' => true,
            'tags' => $tags,
        ], 200);
    }

    return response()->json([
        'success' => false,
        'message' => 'Unauthorized',
    ], 401);
}





}
