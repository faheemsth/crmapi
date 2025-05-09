<?php

namespace App\Http\Controllers;

use Session;
use Illuminate\Support\Facades\Auth;
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
use App\Models\Institute;
use App\Models\LeadTag;
use App\Models\Meta;
use App\Models\TaskTag;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Validator;

class DealController extends Controller
{

    private function dealFilters()
    {
        $filters = [];
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $filters['name'] = $_POST['name'];
        }

        if (isset($_POST['brand_id']) && !empty($_POST['brand_id'])) {
            $filters['brand_id'] = $_POST['brand_id'];
        }

        if (isset($_POST['region_id']) && !empty($_POST['region_id'])) {
            $filters['region_id'] = $_POST['region_id'];
        }

        if (isset($_POST['branch_id']) && !empty($_POST['branch_id'])) {
            $filters['branch_id'] = $_POST['branch_id'];
        }

        if (isset($_POST['lead_assigned_user']) && !empty($_POST['lead_assigned_user'])) {
            $filters['deal_assigned_user'] = $_POST['lead_assigned_user'];
        }


        if (isset($_POST['stages']) && !empty($_POST['stages'])) {
            $filters['stage_id'] = $_POST['stages'];
        }

        if (isset($_POST['users']) && !empty($_POST['users'])) {
            $filters['users'] = $_POST['users'];
        }

        if (isset($_POST['created_at_from']) && !empty($_POST['created_at_from'])) {
            $filters['created_at_from'] = $_POST['created_at_from'];
        }

        if (isset($_POST['created_at_to']) && !empty($_POST['created_at_to'])) {
            $filters['created_at_to'] = $_POST['created_at_to'];
        }
        if (isset($_POST['tag']) && !empty($_POST['tag'])) {
            $filters['tag'] = $_POST['tag'];
        }
        return $filters;
    }

    public function getAdmission(Request $request)
    {
        $user = Auth::user();

        if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }

        $query = Deal::select(
            'deals.id',
            'deals.name',
            'deals.stage_id',
            'deals.tag_ids',
            'deals.assigned_to',
            'deals.intake_month',
            'deals.intake_year',
            'sources.name as sources',
            'assignedUser.name as assigName',
            'clientUser.passport_number as passport',
        )->distinct()
            ->leftJoin('user_deals', 'user_deals.deal_id', '=', 'deals.id')
            ->leftJoin('sources', 'sources.id', '=', 'deals.sources')
            ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
            ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
            ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
            ->leftJoin('leads', 'leads.is_converted', '=', 'deals.id');

        // Permissions logic
        if (in_array($user->type, ['super admin', 'Admin Team']) || $user->can('level 1')) {
            // No filters applied
        } elseif ($user->type == 'company') {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('brand_id', $user->id);
            });
        } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->whereIn('brand_id', array_keys(FiltersBrands()));
            });
        } elseif ($user->type == 'Region Manager' || ($user->can('level 3') && $user->region_id)) {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('region_id', $user->region_id);
            });
        } elseif (in_array($user->type, ['Branch Manager', 'Admissions Officer', 'Career Consultant', 'Admissions Manager', 'Marketing Officer']) || ($user->can('level 4') && $user->branch_id)) {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id);
            });
        } elseif ($user->type == 'Agent') {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
            });
        } else {
            $query->whereHas('lead', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            });
        }

        // Filters
        $filters = $this->dealFilters();
        foreach ($filters as $column => $value) {
            if ($column === 'name') {
                $query->where('deals.name', 'like', "%{$value}%");
            } elseif ($column === 'stage_id') {
                $query->where('deals.stage_id', $value);
            } elseif ($column == 'users') {
                $query->whereIn('deals.created_by', $value);
            } elseif ($column == 'created_at') {
                $query->whereDate('deals.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
            } elseif ($column == 'brand') {
                $query->where('deals.brand_id', $value);
            } elseif ($column == 'region_id') {
                $query->where('deals.region_id', $value);
            } elseif ($column == 'branch_id') {
                $query->where('deals.branch_id', $value);
            } elseif ($column == 'deal_assigned_user') {
                $query->where('deals.assigned_to', $value);
            } else if ($column == 'created_at_from') {
                $query->whereDate('deals.created_at', '>=', $value);
            } else if ($column == 'created_at_to') {
                $query->whereDate('deals.created_at', '<=', $value);
            } else if ($column == 'tag') {
                $query->whereRaw('FIND_IN_SET(?, deals.tag_ids)', [$value]);
            }
        }

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('deal_title', 'like', "%{$search}%")
                ->orWhere('deal_value', 'like', "%{$search}%")
                ->orWhereHas('lead', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
        }
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);



        $deals = $query
        ->orderByDesc('deals.id')
        ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $deals->items(),
            'current_page' => $deals->currentPage(),
            'last_page' => $deals->lastPage(),
            'total_records' => $deals->total(),
            'per_page' => $deals->perPage()
        ]);
    }
    public function getAdmissionDetails(Request $request)
    {

        $user = Auth::user();

        if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'deal_id' => 'required|exists:deals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $deal = Deal::where('id', $request->deal_id)->first();
        if (!$deal->is_active) {
            return response()->json(['status' => 'error', 'message' => 'Permission Denied.'], 403);
        }

    
        $stages = Stage::orderBy('id')->pluck('name', 'id');
        $appStages = ApplicationStage::orderBy('id')->pluck('name', 'id');

        $clientDeal = ClientDeal::where('deal_id', $deal->id)->first();
        $clientUser = User::find($clientDeal->client_id);
        $applications = DealApplication::where('deal_id', $deal->id)->get();
        $tasks = DealTask::where(['related_to' => $deal->id, 'related_type' => 'deal'])->orderBy('status')->get();
        $lead = Lead::where('is_converted', $deal->id)->first();
        $stageHistories = StageHistory::where('type', 'deal')->where('type_id', $deal->id)->pluck('stage_id');

        $discussions = DealDiscussion::select('deal_discussions.id', 'deal_discussions.comment', 'deal_discussions.created_at', 'users.name', 'users.avatar')
            ->join('users', 'deal_discussions.created_by', '=', 'users.id')
            ->where('deal_discussions.deal_id', $deal->id)
            ->orderBy('deal_discussions.created_by', 'DESC')
            ->get();

        $notesQuery = DealNote::select('deal_notes.*')
            ->join('deals', 'deals.id', '=', 'deal_notes.deal_id')
            ->where('deal_notes.deal_id', $deal->id);

        $user = auth()->user();

        if ($user->can('level 2') || $user->can('level 3') || $user->can('level 4')) {
            $notesQuery->whereIn('deals.brand_id', array_keys(FiltersBrands()));
        } else {
            $notesQuery->where('deal_notes.created_by', $user->id);
        }

        $notes = $notesQuery->orderBy('created_at', 'DESC')->get();

        // Tags by role
        if (in_array($user->type, ['super admin', 'Admin Team'])) {
            $tags = LeadTag::pluck('id', 'tag');
        } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
            $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag');
        } elseif ($user->type === 'Region Manager') {
            $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag');
        } else {
            $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag');
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'admission' => $deal, 
                'stages' => $stages,
                'application_stages' => $appStages,
                'applications' => $applications,
                'tasks' => $tasks,
                'clientUser' => $clientUser,
                'lead' => $lead,
                'stage_histories' => $stageHistories, 
                'client_deal' => $clientDeal,
                'notes' => $notes,
                'discussions' => $discussions,
                'tags' => $tags,
            ]
        ]);
    }

    

    public function UpdateAdmissionDetails(Request $request)
    {
        $id = $request->id ?? '';
        $deal = Deal::findOrFail($id);
        
        // Get the first user associated with this deal (if any)
        $user_who_have_password = User::whereIn('id', function($query) use ($id) {
            $query->select('client_id')->from('client_deals')->where('deal_id', $id);
        })->first();

        if (\Auth::user()->can('edit deal') || \Auth::user()->type == 'super admin') {
            if (\Auth::user()->can('edit deal') || $deal->created_by == \Auth::user()->ownerId() || \Auth::user()->type == 'super admin') {
                // Prepare validation rules
                $validationRules = [
                    'name' => 'required',
                    'intake_month' => 'required',
                    'intake_year' => 'required',
                    'brand_id' => 'required|gt:0',
                    'region_id' => 'required|gt:0',
                    'lead_branch' => 'required|gt:0',
                    'lead_assigned_user' => 'required|gt:0',
                    'pipeline_id' => 'required',
                ];

                // Add passport_number validation only if user exists
                if ($user_who_have_password) {
                    $validationRules['passport_number'] = ['required', 'unique:users,passport_number,' . $user_who_have_password->id];
                }

                $validator = \Validator::make($request->all(), $validationRules);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => $validator->errors()
                    ], 422);
                }

                $usr = \Auth::user();
                $deal->name  = $request->name;
                $deal->category = $request->input('category');
                $deal->university_id = $request->input('university_id');
                $deal->organization_id = $request->input('organization_id');
                $deal->phone = $request->input('lead_phone');
                $deal->brand_id = $request->input('brand_id');
                $deal->region_id = $request->input('region_id');
                $deal->branch_id = $request->input('lead_branch');
                $deal->assigned_to = $request->input('lead_assigned_user');
                
                if (isset($request->lead_branch)) {
                    $deal->branch_id = $request->input('lead_branch');
                }
                $deal->intake_month = $request->input('intake_month');
                $deal->intake_year = $request->input('intake_year');
                $deal->price = 0;
                $deal->pipeline_id = $request->input('pipeline_id');
                $deal->description = $request->input('deal_description');
                $deal->status      = 'Active';
                $deal->created_by  = $usr->ownerId();
                $deal->save();

                // Update passport number if user exists
                if ($user_who_have_password) {
                    $user_who_have_password->passport_number = $request->passport_number;
                    $user_who_have_password->save();
                }

                // Handle lead update or creation
                $lead = Lead::where('is_converted', $id)->first();
                if(!empty($lead)) {
                    if (!empty($request->lead_email)) {
                        $lead->email = $request->lead_email;
                    }
                
                    if (!empty($request->lead_phone)) {
                        $lead->phone = $request->full_number;
                    }
                
                    $lead->save();  
                } else {
                    $lead = new Lead();
                    $lead->title       = $request->name;
                    $lead->name        = $request->name;
                    $lead->email       = $request->lead_email ?? '--';
                    $lead->phone       = $request->full_number ?? '--';
                    $lead->mobile_phone = $request->full_number ?? '--';
                    $lead->branch_id      = $request->lead_branch;
                    $lead->brand_id      = $request->brand_id;
                    $lead->region_id      = $request->region_id;
                    $lead->organization_id = "--";
                    $lead->organization_link = "--";
                    $lead->sources = "--";
                    $lead->referrer_email = $request->lead_email ?? '--';
                    $lead->street = "--";
                    $lead->city = "--";
                    $lead->state = "--";
                    $lead->postal_code = "--";
                    $lead->country = "--";
                    $lead->keynotes = "--";
                    $lead->tags = "--";
                    $lead->stage_id    = "1";
                    $lead->subject     = $request->name;
                    $lead->user_id     = $deal->assigned_to;
                    $lead->tag_ids     = "";
                    $lead->pipeline_id = "1";
                    $lead->created_by  = $deal->created_by;
                    $lead->date        = date('Y-m-d');
                    $lead->drive_link = "";
                    $lead->is_converted = $deal->id;
                    $lead->save();
                }

                $data = [
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Deal Updated',
                        'message' => 'Deal updated successfully.'
                    ]),
                    'module_id' => $deal->id,
                    'module_type' => 'deal',
                    'notification_type' => 'Deal Updated'
                ];
                addLogActivity($data);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Deal successfully updated!')
                ]);
            } else {
                 return response()->json([
                    'status' => 'error',
                    'message' => __('Permission Denied.')
                ]);
            }
        } else {
             return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ]);
        }
    }
}
