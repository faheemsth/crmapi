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
use App\Models\City;
use App\Models\instalment;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\LeadTag;
use App\Models\LeadStage;
use App\Models\LogActivity;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            return  FiltersBranchUsersFORTASK($id);
        } elseif ($type == 'brand') {
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
















    public function getMultiRegionBrands(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'brand_ids' => 'required|array',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $id = $request->input('brand_ids');
        // print_r($id);
        // die;
        $type = $request->input('type');

        if ($type == 'branch') {
            return  FiltersBranchUsersFORTASK($id);
        } elseif ($type == 'brand') {
            // Fetch regions based on the brand ID
            $regions = Region::with('brand')
                ->whereHas('brand', function ($query) use ($id) {
                    $query->whereIn('id', $id);
                })
                ->orderBy('name', 'ASC')
                ->get()
                ->mapWithKeys(function ($region) {
                    $key = $region->id; // Use the region's ID as the key
                    $value = $region->name . ($region->brand ? '-' . $region->brand->name : ''); // Concatenate region name with brand name
                    return [$key => $value];
                })
                ->toArray();

            // Return JSON response with regions
            return response()->json([
                'status' => 'success',
                'regions' => $regions,
            ]);
        } elseif ($type == 'region') {
            // Fetch branches based on the region ID
            $branches = Branch::with(['brand', 'region']) // Load related brand and region
                ->whereHas('region', function ($query) use ($id) { // Correctly use the 'region' relationship
                    $query->whereIn('id', $id);
                })
                ->orderBy('name', 'ASC')
                ->get()
                ->mapWithKeys(function ($branch) {
                    $key = $branch->id; // Use the branch's ID as the key
                    $value = $branch->name 
                            . ($branch->brand ? '-' . $branch->brand->name : '') 
                            . ($branch->region ? '-' . $branch->region->name : ''); // Safely concatenate brand and region names
                    return [$key => $value];
                })
                ->toArray();

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


    public function getAllProjectDirectors(Request $request)
    {




        $ProjectDirector = User::where('type', 'Project Director')->pluck('name', 'id')->toArray();

        if ($ProjectDirector) {


            // Return JSON response with brands
            return response()->json([
                'status' => 'success',
                'data' => $ProjectDirector,
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

    public function getJobCategories()
    {
        $categories = JobCategory::get()->pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $categories,
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
    public function getRolesPluck()
    {
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $roles = Role::whereNotIn('name', $excludedTypes)->get()->unique('name')->pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $roles,
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
                $tags = LeadTag::pluck('tag', 'id')->toArray();
            } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('tag', 'id')->toArray();
            } elseif ($user->type === 'Region Manager') {
                $tags = LeadTag::where('region_id', $user->region_id)->pluck('tag', 'id')->toArray();
            } else {
                $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('tag', 'id')->toArray();
            }

            return response()->json([
                'status' => 'success',
                'data' => $tags,
            ], 200);
        }

        return response()->json([
            'status' => 'false',
            'message' => 'Unauthorized',
        ], 401);
    }
    public function FilterSave(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'filter_name' => 'required|string|max:255',
            'url' => 'required|url',
            'module' => 'required|string|max:255',
            'count' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Save the filter data
        try {
            $filter = new SavedFilter();
            $filter->filter_name = $request->filter_name;
            $filter->url = $request->url;
            $filter->module = $request->module;
            $filter->count = $request->count;
            $filter->created_by = auth()->id(); // Use the authenticated user's ID
            $filter->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Filter saved successfully.',
                'data' => $filter,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failure',
                'message' => 'An error occurred while saving the filter.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function UpdateFilterSave(Request $request)
    {
        // Validate incoming request parameters
        $validator = Validator::make($request->all(), [
            'filter_name' => 'required|string|max:255',
            'url' => 'required|url',
            'module' => 'required|string|max:255',
            'count' => 'required|integer|min:0',
            'FilterID' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failure',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Save the filter data
        try {
            $filter = SavedFilter::find($request->FilterID) ?? new SavedFilter();
            $filter->filter_name = $request->filter_name;
            $filter->url = $request->url;
            $filter->module = $request->module;
            $filter->count = $request->count;
            $filter->created_by = auth()->id(); // Use the authenticated user's ID
            $filter->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Filter Update successfully.',
                'data' => $filter,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failure',
                'message' => 'An error occurred while saving the filter.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function Country()
    {
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'id')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Country,
        ], 200);

    }

    public function CountryByCode()
    {
        $Country = Country::orderBy('name', 'ASC')->pluck('name', 'country_code')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $Country,
        ], 200);

    }

    public function getLogActivity_old(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'type' => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => __('Validation Error.'),
            'errors' => $validator->errors(),
        ], 422);
    }

    // Fetch log activity records
    if( $request->type=='user'){
            $logs = LogActivity::with('createdBy:id,name')->where('created_by', $request->id) 
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get();
    }else{
        $logs = LogActivity::with('createdBy:id,name')->where('module_id', $request->id)
                ->where('module_type', $request->type)
                ->orderBy('created_at', 'desc')
                ->limit(500)
                ->get();
    }
    

    return response()->json([
        'status' => true,
        'message' => 'Log activities fetched successfully.',
        'data' => $logs
    ]);
}

public function getLogActivity(Request $request)
{
    // Validate input
    $validator = Validator::make($request->all(), [
        'id' => 'required|integer',
        'type' => 'required|string|max:100',
        'perPage' => 'sometimes|integer|min:1|max:500',
        'page' => 'sometimes|integer|min:1',
        'date' => 'sometimes|string', // Accept as string to parse flexibly
        'search' => 'sometimes|string|max:255',
        'module_type' => 'sometimes|string|max:100',
        'logtype' => 'sometimes|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => __('Validation Error.'),
            'errors' => $validator->errors(),
        ], 422);
    }

    // Determine pagination size
    $num_results_on_page = env("RESULTS_ON_PAGE", 50);
    if ($request->has('perPage')) {
        $num_results_on_page = (int)$request->get('perPage');
    }

    // Base query
    $query = LogActivity::with('createdBy:id,name')
        ->orderBy('created_at', 'desc');

    if ($request->type === 'user') {
        $query->where('created_by', $request->id);
    } else {
        $query->where('module_id', $request->id) ;
    }

    // Apply start_date filter
    if ($request->filled('date')) {
        $rawDate = $request->date;

        try {
            $parsedDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
            $query->whereDate('created_at', $parsedDate);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid start_date format. Please use a valid date.',
                'errors' => [
                    'start_date' => ['The start_date could not be parsed.']
                ]
            ], 422);
        }
    }

    // module_type filter
    if ($request->filled('type') && $request->type === 'user') {
        $query->where('module_type', $request->type);
    }

    // type filter
    if ($request->filled('logtype')) {
        $query->where('type', $request->logtype);
    }

    // search filter
    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('note', 'like', '%' . $search . '%')
              ->orWhereHas('createdBy', function ($subQuery) use ($search) {
                  $subQuery->where('name', 'like', '%' . $search . '%');
              });
        });
    }

    // Total record count before pagination
    $total_records = $query->count();

    // Paginate
    $logs = $query->paginate($num_results_on_page);

    return response()->json([
        'status' => true,
        'message' => 'Log activities fetched successfully.',
        'data' => $logs->items(),
        'total_records' => $total_records,
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
        'per_page' => $logs->perPage(),
    ]);
}

public function getDistinctModuleTypes(Request $request)
{
   

    // Base query
    $query = LogActivity::query();

    

    // Get distinct module types
    $distinctModuleTypes = $query
        ->select('module_type')
        ->distinct()
        ->orderBy('module_type')
        ->pluck('module_type')
        ->toArray();

    return response()->json([
        'status' => true,
        'message' => 'Distinct module types fetched successfully.',
        'data' => $distinctModuleTypes,
    ]);
}


public function DeleteSavedFilter(Request $request)
{
    // Validate the Request Data
    $validator = Validator::make(
        $request->all(),
        [
            'id' => 'required|exists:saved_filters,id',
        ]
    );

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 400);
    }

    // Attempt to Find the Log Entry
    $SavedFilter = SavedFilter::find($request->id);

    if ($SavedFilter) {
        $SavedFilter->delete();
        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => __('Filter successfully deleted!'),
        ], 200);
    } else {
        return response()->json([
            'status' => 'error',
            'message' => __('Filter Not Found'),
        ], 404); // Using 404 for "not found" is more appropriate
    }


}

public function UniversityByCountryCode(Request $request)
{
        $request->validate([
            'country' => 'required|string',
        ]);
        try {
            $country = $request->get('country');
            $country_code = Country::where('country_code', $country)->first();
            if ($country_code) {
                $universities = University::where('uni_status', '0')
                    ->whereRaw("FIND_IN_SET(?, country)", [$country_code->name])
                    ->pluck('name', 'id')
                    ->toArray();
                $universities = $universities;
            } else {
                $universities = [''];
            }
            return response()->json([
                'status' => "success",
                'data' => $universities,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => "success",
                'message' => $e->getMessage(),
            ], 500);
        }
}

public function GetBranchByType()
{
    ini_set('memory_limit', '256M');
    $type = $_POST['type'] ?? null;
    $BranchId = Auth::user()->type == 'super admin' ? 0 : Auth::user()->branch_id;
    if (!$type) {
        return json_encode([
            'status' => 'error',
            'message' => 'Type and Branch ID are required.',
        ]);
    }
    try {
        switch ($type) {
            case 'lead':
                $leadsQuery = \App\Models\Lead::query();
                $userType = \Auth::user()->type;

                if ($userType === 'company') {
                    $leadsQuery->where('brand_id', \Auth::user()->id);
                } elseif ($userType === 'Region Manager' && !empty(\Auth::user()->region_id)) {
                    $leadsQuery->where('region_id', \Auth::user()->region_id);
                } elseif ($userType === 'Branch Manager' && !empty(\Auth::user()->branch_id)) {
                    $leadsQuery->where('branch_id', \Auth::user()->branch_id);
                } elseif ($userType === 'Agent') {
                    $leadsQuery->where('user_id', \Auth::user()->id);
                }

                // Ensure data exists in the query result
                $data = $leadsQuery->select('id', 'name')->pluck('name', 'id')->toArray();
                break;
            case 'organization':
                $data = User::where('type', 'organization')->pluck('name', 'id')->toArray();
                break;

            case 'deal':
                $data = Deal::where('branch_id', $BranchId)->pluck('name', 'id')->toArray();
                break;

            case 'application':
                $data = DealApplication::join('deals', 'deals.id', '=', 'deal_applications.deal_id')
                    ->where('deals.branch_id', $BranchId)
                    ->pluck('deal_applications.name', 'deal_applications.id')
                    ->toArray();
                break;

            case 'toolkit':
                $data = University::pluck('name', 'id')->toArray();
                break;

            case 'agency':
                $data = User::join('agencies', 'agencies.user_id', '=', 'users.id')
                    ->where('approved_status', 2)
                    ->pluck('agencies.organization_name', 'agencies.id')
                    ->toArray();
                break;

            default:
                $data = User::where('branch_id', $BranchId)
                    ->where('type', 'organization')
                    ->pluck('name', 'id')
                    ->toArray();
                break;
        }

        return response()->json([
            'status' => "success",
            'data' => $data,
        ], 200);
    } catch (\Exception $e) {
        return json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

 public function leadsrequireddata(Request $request)
    {
        // Validate input
        $stages = LeadStage::pluck('name', 'id');

        // Get organizations that are not companies
        $organizations = User::where('type', 'organization')->pluck('name', 'id');
        $sources = Source::pluck('name', 'id');

        // Get approved agencies
        $agencies = User::join('agencies', 'agencies.user_id', '=', 'users.id')
            ->where('approved_status', '2')
            ->pluck('agencies.organization_name', 'agencies.id');

        $tags = [];

            if (Auth::check()) {
                $user = Auth::user();

                if (in_array($user->type, ['super admin', 'Admin Team'])) {
                    $tags = LeadTag::pluck('tag', 'id');
                } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                    $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('tag', 'id');
                } elseif (in_array($user->type, ['Region Manager'])) {
                    $tags = LeadTag::where('region_id', $user->region_id)->pluck('tag', 'id');
                } else {
                    $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('tag', 'id');
                }
            }

        // Fetch countries
        $countries = countries();

        // Return the response
        return response()->json([
            'status' => "success",
            'data' => [
                'stages' => $stages,
                'organizations' => $organizations,
                'sources' => $sources,
                'agencies' => $agencies,
                'countries' => $countries,
                'tags' => $tags,
            ]
        ]);
    }

    public function getCitiesOnCode(Request $request)
    {
        $countryCode = $request->input('code');
        $cities = City::where('country_code', $countryCode)->pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $cities
            
        ]);
    }


    public function DealTagPluck()
    {
        $LeadTag = LeadTag::pluck('id', 'tag')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $LeadTag
        ]);
    }

    public function DealStagPluck()
    {
        $Stage = Stage::pluck('name', 'id')->toArray();
        return response()->json([
            'status' => 'success',
            'data' => $Stage
        ]);
    }

    public function ApplicationStagPluck()
    {
        $stages = ApplicationStage::pluck('name', 'id');
        return response()->json([
            'status' => 'success',
            'data' => $stages
        ]);
    }

    public function convertToBase64(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid URL provided',
                'errors' => $validator->errors()
            ], 422);
        }

        $imageUrl = $request->input('image_url');
        
        try {
            // Fetch the image with timeout and proper headers
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'image/*'
                ])
                ->get($imageUrl);
            
            if ($response->successful()) {
                $mimeType = $this->detectMimeType($response->body());
                
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'base64' => 'data:' . $mimeType . ';base64,' . base64_encode($response->body()),
                        'mime_type' => $mimeType,
                        'size' => strlen($response->body())
                    ]
                ]);
            }
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch image. Server responded with status: ' . $response->status()
            ], 400);
            
        } catch (\Exception $e) {
            Log::error("Image conversion error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => "Image conversion error: " . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detect MIME type from binary data
     */
    private function detectMimeType($binaryData)
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($binaryData);
    }

     public function getemailTags(Request $request)
    {
        $type = $request->type;

        $tags = DB::table('email_tags')
            ->where('type', 'universal')
            ->orWhere('type', $type)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tags
        ]);
    }
   public function getemailTagstype(Request $request)
{
    $tags = DB::table('email_tags')
        ->where('type', '!=', 'universal')
        ->distinct()
        ->pluck('type'); // returns a flat array like ["employee", "leave", "attendance"]

    return response()->json([
        'success' => true,
        'data' => $tags
    ]);
}

 public function totalSummary(Request $request)
    {

         $validator = Validator::make($request->all(), [ 
                'user_id' => 'required|exists:users,id', 
                'type' => 'required|string|in:week,month', 
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], 422);
            }

            $employeeId = $request->input('user_id');
            $type = $request->input('type', 'week');

        if (!$employeeId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found.'
            ], 404);
        }

        $query = DB::table('attendance_employees')
            ->where('employee_id', $employeeId);

        // Filter by date range
        if ($type === 'week') {
            $query->whereBetween('date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString()
            ]);
        } elseif ($type === 'month') {
            $query->whereYear('date', now()->year)
                ->whereMonth('date', now()->month);
        }

        $records = $query->get();

        // Status counts
        $present = $records->where('status', 'Present')->count();
        $absent = $records->where('status', 'Absent')->count();
        $leave = $records->where('status', 'Leave')->count();
        $holiday = $records->where('status', 'Holiday')->count();
        $on_time_rate = $records->filter(function ($record) {
            return $record->status === 'Present' &&
                $record->clock_in &&
                $record->shift_start &&
                strtotime($record->clock_in) <= strtotime($record->shift_start);
        })->count();
        $total = $records->count();

        // Working days = All except holidays
        $workingDays = $present + $absent + $leave;

        // Total working time in seconds (only for Present days)
        $totalSeconds = 0;
        foreach ($records as $record) {
            if ($record->status === 'Present' && $record->clock_in && $record->clock_out) {
                $clockIn = strtotime($record->clock_in);
                $clockOut = strtotime($record->clock_out);
                $diff = $clockOut - $clockIn;

                if ($diff > 0) {
                    $totalSeconds += $diff;
                }
            }
        }

        // Total working hours (HH:MM)
        $totalHours = floor($totalSeconds / 3600);
        $totalMinutes = floor(($totalSeconds % 3600) / 60);
        $totalWorking = sprintf('%02d:%02d', $totalHours, $totalMinutes);

        // Average working time = totalSeconds / workingDays
        $avgSeconds = $workingDays > 0 ? intval($totalSeconds / $workingDays) : 0;
        $avgHours = floor($avgSeconds / 3600);
        $avgMinutes = floor(($avgSeconds % 3600) / 60);
        $avgWorking = sprintf('%02d:%02d', $avgHours, $avgMinutes);

        return response()->json([
            'present' => $present,
            'absent' => $absent,
            'leave' => $leave,
            'holiday' => $holiday,
            'total_days' => $total,
            'working_days' => $workingDays,
            'total_working_hours' => $totalWorking,
            'average_working_hours' => $avgWorking,
            'on_time_rate' => $present > 0 ? round(($on_time_rate / $present) * 100, 2) : 0,
        ]);
    }

 
    public function saveSystemSettings(Request $request)
    {
         

        $user = Auth::user();

        if (!$user->can('manage company settings')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        $post = $request->except(['_token']);

        

        $settings = Utility::settings();

        // Capture original settings for logging
        $originalData = $settings;

        // Track changes
        $changes = [];
        $updatedFields = [];
       // dd($post);
        foreach ($post as $key => $data) {
            if (array_key_exists($key, $settings)) {
                    if ($settings[$key] != $data) {
                        $changes[$key] = [
                            'old' => $settings[$key],
                            'new' => $data
                        ];
                        $updatedFields[] = $key;
                    }
                 }

                DB::insert(
                    'insert into settings (`value`, `name`,`created_by`,`created_at`,`updated_at`) 
                     values (?, ?, ?, ?, ?) 
                     ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                    [
                        $data,
                        $key,
                        $user->creatorId(),
                        now(),
                        now(),
                    ]
                );
           
        }

        // 🔹 Log only if changes exist
        if (!empty($changes)) {
            $typeoflog = ' system settings';
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $user->name. ucfirst($typeoflog) . ' updated ' ,
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $user->creatorId(),
                'module_type' => 'settings',
                'notification_type' => ucfirst($typeoflog) . ' Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Settings successfully updated.'),
            'updated_fields' => $updatedFields
        ], 200);
    }

    public function fetchSystemSettings(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('manage company settings')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Fetch settings for current company/creator
        $settings = DB::table('settings')
            ->pluck('value', 'name') // returns key-value pair
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ], 200);
    }

 

}
