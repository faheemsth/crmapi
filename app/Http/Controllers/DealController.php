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

    
}
