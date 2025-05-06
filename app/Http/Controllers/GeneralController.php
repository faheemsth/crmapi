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

    public function getLogActivity(Request $request)
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
    $logs = LogActivity::where('module_id', $request->id)
                ->where('module_type', $request->type)
                ->orderBy('created_at', 'desc')
                ->get();

    return response()->json([
        'status' => true,
        'message' => 'Log activities fetched successfully.',
        'data' => $logs
    ]);
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
    $type = $_POST['type'] ?? null;
    $BranchId = Auth::user()->type == 'super admin' ? null : Auth::user()->id;
    if (!$type) {
        return json_encode([
            'status' => 'error',
            'message' => 'Type and Branch ID are required.',
        ]);
    }
    try {
        switch ($type) {
            case 'lead':
                $data = \App\Models\Lead::where('branch_id', $BranchId)->pluck('name', 'id')->toArray();
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
}
