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
    public function getAdmission(Request $request)
{
    $user = Auth::user();

    if (!($user->can('view deal') || $user->can('manage deal') || in_array($user->type, ['super admin', 'company', 'Admin Team']))) {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission Denied.',
        ], 403);
    }

    $query = Deal::query();

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
    if ($request->filled('lead_id')) {
        $query->where('lead_id', $request->get('lead_id'));
    }

    if ($request->filled('brand_id')) {
        $query->whereHas('lead', function ($q) use ($request) {
            $q->where('brand_id', $request->get('brand_id'));
        });
    }

    if ($request->filled('branch_id')) {
        $query->whereHas('lead', function ($q) use ($request) {
            $q->where('branch_id', $request->get('branch_id'));
        });
    }

    if ($request->filled('assigned_to')) {
        $query->whereHas('lead', function ($q) use ($request) {
            $q->where('assigned_to', $request->get('assigned_to'));
        });
    }

    if ($request->filled('created_at_from')) {
        $query->whereDate('created_at', '>=', $request->get('created_at_from'));
    }

    if ($request->filled('created_at_to')) {
        $query->whereDate('created_at', '<=', $request->get('created_at_to'));
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

    $deals = $query->orderByDesc('id')->paginate($request->get('per_page', 10));

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
            'lead' => $lead,
            'stage_histories' => $stageHistories, 
            'client_deal' => $clientDeal,
            'notes' => $notes,
            'discussions' => $discussions,
            'tags' => $tags,
        ]
    ]);
}


public function getMoveApplicationPluck(Request $request)
{
    
    $validator = \Validator::make($request->all(), [
        'passport_number' => 'required|string',
        'id' => 'required|integer|exists:deal_applications,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors(),
        ]);
    }

    if (auth()->user()->type === 'super admin' || auth()->user()->type === 'Admin Team') {

        $admissions = \DB::table('deals')
            ->leftJoin('client_deals', 'client_deals.deal_id', '=', 'deals.id')
            ->leftJoin('users as clientUser', 'clientUser.id', '=', 'client_deals.client_id')
            ->leftJoin('users as brandUser', 'brandUser.id', '=', 'deals.brand_id')
            ->leftJoin('regions', 'regions.id', '=', 'deals.region_id')
            ->leftJoin('branches', 'branches.id', '=', 'deals.branch_id')
            ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'deals.assigned_to')
            ->where('clientUser.passport_number', $request->passport_number)
            ->select(
                'deals.id',
                'deals.name',
                'brandUser.name as brandName',
                'regions.name as RegionName',
                'branches.name as branchName',
                'assignedUser.name as assignedName'
            )
            ->get();

        $pluckFormatted = $admissions->mapWithKeys(function ($admission) {
            $label = $admission->name . '-' . $admission->brandName . '-' . $admission->RegionName . '-' . $admission->branchName . '-' . $admission->assignedName;
            return [$admission->id => $label];
        });

        return response()->json([
            'status' => true,
            'data' => $pluckFormatted
        ]);
    }

    return response()->json([
        'status' => false,
        'message' => __('Permission Denied.')
    ], 403);
}

public function moveApplicationsave(Request $request)
{
    if (!in_array(\Auth::user()->type, ['super admin', 'Admin Team'])) {
        return response()->json([
            'status' => 'error',
            'message' => 'Permission Denied.',
        ]);
    }

    $validator = \Validator::make($request->all(), [
        'id' => 'required|exists:deal_applications,id',
        'deal_id' => 'required|exists:deals,id',
        'old_deal_id' => 'required|exists:deals,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'message' => $validator->errors(),
        ]);
    }

    if ($request->deal_id == $request->old_deal_id) {
        return response()->json([
            'status' => 'error',
            'message' => 'The selected deal already contains this application.',
        ]);
    }

    $oldApplication = DealApplication::where('id', $request->id)->first();

    if (!$oldApplication) {
        return response()->json([
            'status' => 'error',
            'message' => 'Original application not found.',
        ]);
    }

    // Duplicate Application
    $newApplication = new DealApplication();
    $newApplication->application_key = $oldApplication->application_key;
    $newApplication->university_id = $oldApplication->university_id;
    $newApplication->deal_id = $request->deal_id;
    $newApplication->course = $oldApplication->course;
    $newApplication->stage_id = $oldApplication->stage_id;
    $newApplication->name = $oldApplication->name;
    $newApplication->intake = $oldApplication->intake;
    $newApplication->external_app_id = $oldApplication->external_app_id;
    $newApplication->status = $oldApplication->status;
    $newApplication->created_by = $oldApplication->created_by;
    $newApplication->brand_id = $oldApplication->brand_id;
    $newApplication->created_at = $oldApplication->created_at;
    $newApplication->updated_at = $oldApplication->updated_at;
    $newApplication->save();

    // Clone notes
    $notes = ApplicationNote::where('application_id', $request->id)->get();
    foreach ($notes as $note) {
        $newNote = new ApplicationNote();
        $newNote->title = $note->title;
        $newNote->description = $note->description;
        $newNote->application_id = $newApplication->id;
        $newNote->created_by = $note->created_by;
        $newNote->created_at = $note->created_at;
        $newNote->updated_at = $note->updated_at;
        $newNote->save();
    }

    // Clone tasks
    $tasks = DealTask::where(['related_to' => $request->id, 'related_type' => 'application'])->get();
    foreach ($tasks as $task) {
        $newTask = new DealTask();
        $newTask->deal_id = $newApplication->id;
        $newTask->name = $task->name;
        $newTask->date = $task->date;
        $newTask->time = $task->time;
        $newTask->priority = $task->priority;
        $newTask->status = 1;
        $newTask->organization_id = $task->organization_id;
        $newTask->assigned_to = $task->assigned_to;
        $newTask->assigned_type = $task->assigned_type;
        $newTask->related_type = $task->related_type;
        $newTask->related_to = $newApplication->id;
        $newTask->branch_id = $task->branch_id;
        $newTask->due_date = $task->due_date;
        $newTask->start_date = $task->start_date;
        $newTask->remainder_date = $task->remainder_date;
        $newTask->description = $task->description;
        $newTask->visibility = $task->visibility;
        $newTask->deal_stage_id = $task->deal_stage_id;
        $newTask->created_by = $task->created_by;
        $newTask->brand_id = $task->brand_id;
        $newTask->region_id = $task->region_id;
        $newTask->created_at = $task->created_at;
        $newTask->updated_at = $task->updated_at;
        $newTask->save();
    }

    // Update stages
    $this->updateDealStageByDealId($request->deal_id);
    $this->updateDealStageByDealId($request->old_deal_id);

    // Delete old application
    $oldApplication->delete();

    // Compare old and new application fields
    $differences = [];
    $fieldsToCheck = [
        'application_key', 'university_id', 'deal_id', 'course',
        'stage_id', 'name', 'intake', 'external_app_id',
        'status', 'created_by', 'brand_id', 'created_at', 'updated_at'
    ];

    foreach ($fieldsToCheck as $field) {
        $oldValue = $oldApplication->$field;
        $newValue = $newApplication->$field;

        if ($oldValue != $newValue) {
            $differences[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }
    }

    // Activity Log
    addLogActivity([
        'type' => 'info',
        'note' => json_encode([
            'title' => 'Application Moved',
            'message' => 'Application moved to another deal successfully.',
            'differences' => $differences,
        ]),
        'module_id' => $newApplication->id,
        'module_type' => 'application',
        'notification_type' => 'Application Moved',
    ]);

    return response()->json([
        'status' => 'success',
        'app_id' => $newApplication->id,
        'message' => __('Application moved successfully.'),
    ]);
}

private function updateDealStageByDealId($dealId)
{
    $latestApplication = DealApplication::where('deal_id', $dealId)
        ->orderByDesc('stage_id')
        ->first();

    $deal = Deal::find($dealId);

    if (!$deal || !$latestApplication) return;

    $stage_id = $latestApplication->stage_id;

    $stageMap = [
        0 => 0,
        1 => 1, 2 => 1,
        3 => 2, 4 => 2,
        5 => 3, 6 => 3,
        7 => 4, 8 => 4,
        9 => 5, 10 => 5,
        11 => 6,
        12 => 7,
    ];

    $deal->stage_id = $stageMap[$stage_id] ?? 0;
    $deal->save();
}


    
}
