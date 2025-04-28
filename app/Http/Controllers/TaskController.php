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
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{


    public function userTasksGet(Request $request)
    {
        $user = Auth::user();

        if (
            $user->can('view task') ||
            $user->can('manage task') ||
            $user->type === 'super admin' ||
            $user->type === 'company'
        ) {
            $filtersBrands = array_keys(FiltersBrands());

            $tasks = DealTask::select(
                'deal_tasks.name',
                'deal_tasks.brand_id',
                'deal_tasks.id',
                'deal_tasks.due_date',
                'deal_tasks.status',
                'deal_tasks.assigned_to'
            )
                ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
                ->join('users as brand', 'brand.id', '=', 'deal_tasks.brand_id');

            if ($user->type !== 'HR') {
                if ($user->type === 'super admin' || $user->can('level 1')) {
                    $filtersBrands[] = '3751';
                } elseif ($user->type === 'company') {
                    $tasks->where('deal_tasks.brand_id', $user->id);
                } elseif (in_array($user->type, ['Project Director', 'Project Manager']) || $user->can('level 2')) {
                    $tasks->whereIn('deal_tasks.brand_id', $filtersBrands);
                } elseif ($user->type === 'Region Manager' || ($user->can('level 3') && !empty($user->region_id))) {
                    $tasks->where('deal_tasks.region_id', $user->region_id);
                } elseif (in_array($user->type, ['Branch Manager', 'Admissions Officer', 'Marketing Officer']) || ($user->can('level 4') && !empty($user->branch_id))) {
                    $tasks->where('deal_tasks.branch_id', $user->branch_id);
                } elseif ($user->type === 'Agent') {
                    $agency = Agency::where('user_id', $user->id)->first();
                    if ($agency) {
                        $tasks->where(function ($query) use ($agency, $user) {
                            $query->where(function ($q) use ($agency) {
                                $q->where('related_type', 'agency')
                                    ->where('related_to', $agency->id);
                            })
                                ->orWhere('deal_tasks.assigned_to', $user->id)
                                ->orWhere('deal_tasks.created_by', $user->id);
                        });
                    } else {
                        $tasks->where(function ($query) use ($user) {
                            $query->where('deal_tasks.assigned_to', $user->id)
                                ->orWhere('deal_tasks.created_by', $user->id);
                        });
                    }
                } else {
                    $tasks->where('deal_tasks.assigned_to', $user->id);
                }
            } else {
                $tasks->where('deal_tasks.branch_id', $user->branch_id);
            }

            // Apply filters
            $filters = $this->TasksFilter();
            foreach ($filters as $column => $value) {
                match ($column) {
                    'subjects' => $tasks->whereIn('deal_tasks.id', $value),
                    'assigned_to' => $tasks->where('assigned_to', $value),
                    'brand_id' => $tasks->where('deal_tasks.brand_id', $value),
                    'region_id' => $tasks->where('deal_tasks.region_id', $value),
                    'branch_id' => $tasks->where('deal_tasks.branch_id', $value),
                    'due_date' => $tasks->whereDate('due_date', 'LIKE', '%' . substr($value, 0, 10) . '%'),
                    'status' => $this->filterStatus($tasks, $value),
                    'created_at_from' => $tasks->whereDate('deal_tasks.created_at', '>=', $value),
                    'created_at_to' => $tasks->whereDate('deal_tasks.created_at', '<=', $value),
                    default => null,
                };
            }

            if (!$request->filled('status')) {
                $tasks->where('status', 0);
            }

            if ($request->filled('assigned_by_me') && $request->input('assigned_by_me') == true) {
                $tasks->where('deal_tasks.created_by', $user->id);
            }

            $scorpTasks = $this->GetScorpTasks();

            $mergedResults = array_merge($tasks->pluck('deal_tasks.id')->toArray(), $scorpTasks);

            $finalQuery = DealTask::select(
                'deal_tasks.name',
                'deal_tasks.brand_id',
                'deal_tasks.id',
                'deal_tasks.due_date',
                'deal_tasks.status',
                'deal_tasks.assigned_to',
                'brandname.name as brand_name',
                'users.name as user_name'
            )
                ->whereIn('deal_tasks.id', $mergedResults)
                ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
                ->join('users as brandname', 'brandname.id', '=', 'deal_tasks.brand_id')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $g_search = $request->input('search');
                    $query->where(function ($subQuery) use ($g_search) {
                        $subQuery->where('deal_tasks.name', 'like', "%$g_search%")
                            ->orWhere('brandname.name', 'like', "%$g_search%")
                            ->orWhere('users.name', 'like', "%$g_search%")
                            ->orWhere('deal_tasks.due_date', 'like', "%$g_search%");
                    });
                })
                ->orderBy('deal_tasks.created_at', 'DESC');

            $paginatedTasks = $finalQuery->paginate($request->input('perPage', env('RESULTS_ON_PAGE', 50)));

            return response()->json([
                'status' => 'success',
                'data' => $paginatedTasks->items(),
                'current_page' => $paginatedTasks->currentPage(),
                'last_page' => $paginatedTasks->lastPage(),
                'total_records' => $paginatedTasks->total(),
                'per_page' => $paginatedTasks->perPage(),
                'message' => 'Tasks fetched successfully'
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied'
            ], 403);
        }
    }


    private function TasksFilter()
    {
        $filters = [];
        if (isset($_GET['subjects']) && !empty($_GET['subjects'])) {
            $filters['subjects'] = $_GET['subjects'];
        }

        if (isset($_GET['lead_assigned_user']) && !empty($_GET['lead_assigned_user'])) {
            $filters['assigned_to'] = $_GET['lead_assigned_user'];
        }

        if (isset($_GET['brand']) && !empty($_GET['brand'])) {
            $filters['brand_id'] = $_GET['brand'];
        }

        if (isset($_GET['region_id']) && !empty($_GET['region_id'])) {
            $filters['region_id'] = $_GET['region_id'];
        }

        if (isset($_GET['branch_id']) && !empty($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
        }

        if (isset($_GET['due_date']) && !empty($_GET['due_date'])) {
            $filters['due_date'] = $_GET['due_date'];
        }
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            if ($_GET['status'] != '0') {
                $filters['status'] = $_GET['status'];
            } else {
                $filters['status'] = 0;
            }
        }

        if (isset($_GET['created_at_from']) && !empty($_GET['created_at_from'])) {
            $filters['created_at_from'] = $_GET['created_at_from'];
        }

        if (isset($_GET['created_at_to']) && !empty($_GET['created_at_to'])) {
            $filters['created_at_to'] = $_GET['created_at_to'];
        }

        return $filters;
    }



    public function GetScorpTasks()
    {
        $brandId = 3751; // Assuming 3751 is the brand ID to filter
        // Fetching regions and branches related to the brand

        if (\Auth::user()->can('view task') || \Auth::user()->can('manage task') || \Auth::user()->type == 'super admin' || \Auth::user()->type == 'company') {
            $tasks = DealTask::select('deal_tasks.name', 'deal_tasks.brand_id', 'deal_tasks.id', 'deal_tasks.due_date', 'deal_tasks.status', 'deal_tasks.assigned_to')
                ->join('users', 'users.id', '=', 'deal_tasks.assigned_to')
                ->join('users as brand', 'brand.id', '=', 'deal_tasks.brand_id')
                ->where('deal_tasks.brand_id', $brandId)->where('deal_tasks.created_by', \Auth::user()->id);;
        }
        $filters = $this->TasksFilter();
        foreach ($filters as $column => $value) {
            if ($column === 'subjects') {
                $tasks->whereIn('deal_tasks.id', $value);
            } elseif ($column === 'assigned_to') {
                $tasks->where('assigned_to', $value);
            } elseif ($column === 'brand_id') {
                $tasks->where('deal_tasks.brand_id', $value);
            } elseif ($column === 'region_id') {
                $tasks->where('deal_tasks.region_id', $value);
            } elseif ($column === 'branch_id') {
                $tasks->where('deal_tasks.branch_id', $value);
            } elseif ($column == 'due_date') {
                $tasks->whereDate('due_date', 'LIKE', '%' . substr($value, 0, 10) . '%');
            } elseif ($column == 'status') {
                if (gettype($value) == 'array') {
                    if (in_array(2, $value)) {
                        $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
                    } else {
                        $tasks->whereIn('status', $value);
                    }
                } else {
                    if ($value == 2) {
                        $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
                    } else {
                        $tasks->where('status', $value);
                    }
                }
            } elseif ($column == 'created_at_from') {
                $tasks->whereDate('deal_tasks.created_at', '>=', $value);
            } elseif ($column == 'created_at_to') {
                $tasks->whereDate('deal_tasks.created_at', '<=', $value);
            }
        }

        if (!isset($_GET['status'])) {
            $tasks->where('deal_tasks.status', 0);
        }
        return $tasks->pluck('deal_tasks.id')->toArray();
    }

    private function filterStatus($tasks, $value)
    {
        if (is_array($value)) {
            if (in_array(2, $value)) {
                $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
            } else {
                $tasks->whereIn('status', $value);
            }
        } else {
            if ($value == 2) {
                $tasks->where('status', 0)->whereDate('deal_tasks.due_date', '<', now());
            } else {
                $tasks->where('status', $value);
            }
        }
    }


    public function createtask(Request $request)
    {
        $usr = \Auth::user();
        $employeeId = !empty(\Auth::user()->id) ? \Auth::user()->id : 0;

        // // Check if the user has permission to create a task
        // if ($usr->can('create task')) {

        // Validation rules and messages
        $rules = [
            'task_name' => 'required',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
            'assign_type' => 'required',
            'due_date' => 'required',
            'start_date' => 'required',
        ];

        $messages = [
            'brand_id.min' => 'The brand id must be required',
            'region_id.min' => 'The Region id must be required',
            'branch_id.min' => 'The branch id must be required',
            'assigned_to.min' => 'The Assigned id must be required',
        ];

        // Validate the request
        $validator = \Validator::make($request->all(), $rules, $messages);

        // Return validation error if validation fails
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        // Create a new DealTask
        $dealTask = new DealTask();
        $dealTask->deal_id = $request->related_to ?? 0;
        $dealTask->related_to = $request->related_to ?? 0;
        $dealTask->related_type = $request->related_type ?? 'task';
        $dealTask->name = $request->task_name;
        $dealTask->branch_id = $request->branch_id;
        $dealTask->region_id = $request->region_id;
        $dealTask->brand_id =  $request->brand_id;
        $dealTask->created_by = $employeeId;
        $dealTask->assigned_to = $request->assigned_to;
        $dealTask->assigned_type = $request->assign_type;
        $dealTask->due_date = $request->due_date ?? '';
        $dealTask->start_date = $request->start_date;
        $dealTask->date = $request->start_date;
        $dealTask->status = 0;
        $dealTask->remainder_date = $request->remainder_date;
        $dealTask->description = $request->description;
        $dealTask->visibility = $request->visibility;
        $dealTask->priority = 1;
        $dealTask->time = $request->remainder_time ?? '';
        $dealTask->save();

        // Add log activity (optional)
        $remarks = [
            'title' => 'Task Created',
            'message' => 'Task Created successfully'
        ];

        $related_id = '';
        $related_type = '';

        if (isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency', 'task'])) {
            $related_id = $dealTask->deal_id;
            $related_type = $dealTask->related_type;
        }

        $logData = [
            'type' => 'info',
            'note' => json_encode($remarks),
            'module_id' => $related_type == 'task' ? $dealTask->id : $related_id,
            'module_type' => $related_type,
            'notification_type' => 'Task created'
        ];
        addLogActivity($logData);

        // Notification data (optional)
        $html = '<p class="mb-0"><span class="fw-bold">
               <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important" onclick="openSidebar(\'/get-task-detail?task_id=' . $dealTask->id . '\')" data-task-id="' . $dealTask->id . '">' . $dealTask->name . '</span></span>
               Created By <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important" onclick="openSidebar(\'/users/' . \Auth::id() . '/user_detail\')">' . User::find(\Auth::id())->name . '</span></p>';

        $notificationData = [
            'type' => 'Tasks',
            'data_type' => 'Task_Created',
            'sender_id' => $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => $html,
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now()
        ];

        // Send notification if the creator is not the assigned user
        if ($dealTask->created_by !== (int)$dealTask->assigned_to) {
            addNotifications($notificationData);
        }

        // Return success response
        return response()->json([
            'status' => 'success',
            'task_id' => $dealTask->id,
            'message' => __('Task successfully created!')
        ], 201);

        // } else {
        //     // Return error response if the user does not have permission
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => __('Permission Denied.')
        //     ], 403);
        // }
    }



    public function taskUpdate(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('edit task')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validation Rules
        $rules = [
            'task_name' => 'required|string|max:255',
            'task_id' => 'required|integer|min:1',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
            'due_date' => 'required|date',
            'start_date' => 'required|date',
            'visibility' => 'required|string',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;

        $dealTask = DealTask::find($id);

        if (!$dealTask) {
            return response()->json([
                'status' => 'error',
                'message' => __('Task not found.')
            ], 404);
        }

        // Track Status Change
        $is_status_change = $dealTask->status !== $request->status;

        // Update Task Details
        $dealTask->related_to = $request->related_to;
        $dealTask->related_type = $request->related_type;
        $dealTask->name = $request->task_name;
        $dealTask->branch_id = $request->branch_id ?? $dealTask->branch_id;
        $dealTask->assigned_to = $request->assigned_to ?? $dealTask->assigned_to;
        $dealTask->brand_id = $request->brand_id ?? $dealTask->brand_id;
        $dealTask->assigned_type = $request->assign_type;
        $dealTask->region_id = $request->region_id ?? $dealTask->region_id;
        $dealTask->due_date = $request->due_date;
        $dealTask->start_date = $request->start_date;
        $dealTask->date = $request->start_date;
        $dealTask->status = $request->status ?? $dealTask->status;
        $dealTask->remainder_date = $request->remainder_date;
        $dealTask->description = $request->description;
        $dealTask->visibility = $request->visibility;
        $dealTask->priority = 1;
        $dealTask->time = $request->remainder_time ?? $dealTask->time;

        $dealTask->save();

        // Log Activity

        ActivityLog::create(
            [
                'user_id' => $user->id,
                'deal_id' => $dealTask->deal_id,
                'log_type' => 'Update Task',
                'remark' => json_encode(['title' => $dealTask->name]),
            ]
        );

        //store Activity Log
        $remarks = [
            'title' => 'Task Update',
            'message' => 'Task updated successfully'
        ];

        $module_id = isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency'])
            ? $dealTask->deal_id
            : $dealTask->id;

        $module_type = isset($dealTask->deal_id) && in_array($dealTask->related_type, ['organization', 'lead', 'deal', 'application', 'toolkit', 'agency'])
            ? $dealTask->related_type
            : 'task';

        $data = [
            'type' => 'info',
            'note' => json_encode($remarks),
            'module_id' => $module_id,
            'module_type' => $module_type,
            'notification_type' => 'Task Update'
        ];
        addLogActivity($data);

        $html = '<p class="mb-0">
    <span class="fw-bold">
       <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/get-task-detail?task_id=' . $dealTask->id . '\')"
    data-task-id="' . $dealTask->id . '">' . $dealTask->name . '</span>
     </span>
    Task Updated By <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/users/' . \Auth::id() . '/user_detail\')">
    ' . User::find(\Auth::id())->name ?? '' . '
   </p>';

        $Notification_data = [
            'type' => 'Tasks',
            'data_type' => 'Task_Updated',
            'sender_id' =>  $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => $html,
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now()
        ];
        if ($dealTask->created_by !== (int)$dealTask->assigned_to && (int)$dealTask->assigned_to !== \Auth::id()) {
            addNotifications($Notification_data);
        }

        if ($is_status_change) {
            //store Activity Log
            $remarks = [
                'title' => 'Task Update',
                'message' => 'Task status updated'
            ];

            //store Log
            $data = [
                'type' => 'info',
                'note' => json_encode($remarks),
                'module_id' => $dealTask->id,
                'module_type' => 'task',
                'notification_type' => 'Task Update'
            ];
            addLogActivity($data);
        }

        return response()->json([
            'status' => 'success',
            'task_id' => $dealTask->id,
            'message' => __('Task successfully updated!')
        ], 200);
    }


    public function ShuffleTaskOwnership(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
            'created_by' => 'required|integer|min:1',
            'assigned_to' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;
        if (!empty($id)) {

            $task = DealTask::findOrFail($id);

            $from = User::find($task->assigned_to);
            $to = User::find($request->assigned_to);

            // Ensure $from and $to are valid objects before accessing their properties
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Swaps Tasks',
                    'message' => 'Swaps From ' . ($from->name ?? '') . ' To ' . ($to->name ?? '')
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Swaps Tasks From ' . ($from->name ?? '') . ' To ' . ($to->name ?? '') . ' Successfully'
            ];

            addLogActivity($data);


            $task->assigned_to = $request->created_by;
            $task->created_by = $request->assigned_to;
            $task->due_date = Carbon::now()->addDay()->format('Y-m-d');
            $task->is_swap = '1';
            $task->save();

            return json_encode([
                'status' => 'success',
                'message' => 'Swap Tasks Successfully',
                'id' => $id,
            ]);
        }
    }



    public function getTaskDetails(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Task Details
        $taskId = $request->task_id;
        $task = DealTask::findOrFail($taskId);

        // Fetch Related Data
        $branches = Branch::get()->pluck('name', 'id');
        $users = User::get()->pluck('name', 'id');
        $stages = Stage::get()->pluck('name', 'id');
        $universities = University::get()->pluck('name', 'id');
        $organizations = User::where('type', 'organization')->orderBy('name', 'ASC')->pluck('name', 'id');
        $leads = Lead::where('branch_id', $task->branch_id)->orderBy('name', 'ASC')->pluck('name', 'id');
        $deals = Deal::where('branch_id', $task->branch_id)->orderBy('name', 'ASC')->pluck('name', 'id');
        $toolkits = University::orderBy('name', 'ASC')->pluck('name', 'id');
        $applications = DealApplication::join('deals', 'deals.id', '=', 'deal_applications.deal_id')
            ->where('deals.branch_id', $task->branch_id)
            ->orderBy('deal_applications.name', 'ASC')
            ->pluck('deal_applications.application_key', 'deal_applications.id');
        $Agency = \App\Models\Agency::find($task->related_to);

        // Fetch Discussions
        $discussions = TaskDiscussion::select('task_discussions.id', 'task_discussions.comment', 'task_discussions.created_at', 'users.name', 'users.avatar')
            ->join('users', 'task_discussions.created_by', 'users.id')
            ->where(['task_discussions.task_id' => $taskId])
            ->orderBy('task_discussions.created_at', 'DESC')
            ->get();

        // Fetch Log Activities
        $log_activities = getLogActivity($taskId, 'task');

        // Build Response Data
        $response = [
            'status' => 'success',
            'task' => $task,
            'branches' => $branches,
            'users' => $users,
            'stages' => $stages,
            'universities' => $universities,
            'organizations' => $organizations,
            'leads' => $leads,
            'deals' => $deals,
            'toolkits' => $toolkits,
            'applications' => $applications,
            'agency' => $Agency,
            'discussions' => $discussions,
            'log_activities' => $log_activities
        ];

        return response()->json($response, 200);
    }


    public function taskDiscussionStore(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
            'comment' => 'required',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;
        $usr = \Auth::user();
        $discussion = !empty($request->id) ? TaskDiscussion::find($request->id) : new TaskDiscussion();
        $discussion->fill([
            'comment'    => $request->comment,
            'task_id'    => $id,
            'created_by' => \Auth::id(),
        ])->save();
        $dealTask = DealTask::find($id);
        $discussion_comment = (strlen($text = strip_tags($discussion->comment)) > 20) ? substr($text, 0, 20) . "..." : $text;
        $html = '<p class="mb-0">
    On This Task
    <span class="fw-bold">
       <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/get-task-detail?task_id=' . $dealTask->id . '\')"
    data-task-id="' . $dealTask->id . '">' . $dealTask->name . '</span>
     </span>
     Note
     <span style="font-weight:bold;color:black !important">' . $discussion_comment . '</span>
    Created By <span style="cursor:pointer;font-weight:bold;color:#1770b4 !important"
    onclick="openSidebar(\'/users/' . \Auth::id() . '/user_detail\')">
    ' . User::find(\Auth::id())->name ?? '' . '
   </p>';

        $Notification_data = [
            'type' => 'Tasks',
            'data_type' => 'Notes_Created',
            'sender_id' =>  $dealTask->created_by,
            'receiver_id' => $dealTask->assigned_to,
            'data' => $html,
            'is_read' => 0,
            'related_id' => $dealTask->id,
            'created_by' => \Auth::id(),
            'created_at' => \Carbon\Carbon::now()
        ];
        if ($dealTask->created_by !== (int)$dealTask->assigned_to && (int)$dealTask->assigned_to !== \Auth::id()) {
            addNotifications($Notification_data);
        }

        $discussions = TaskDiscussion::select('task_discussions.id', 'task_discussions.comment', 'task_discussions.created_at', 'users.name', 'users.avatar')
            ->join('users', 'task_discussions.created_by', 'users.id')
            ->where(['task_discussions.task_id' => $id])
            ->orderBy('task_discussions.created_at', 'DESC')
            ->get()
            ->toArray();



        return response()->json([
            'status' => 'success',
            'discussions' => $discussions,
            'message' => __('Message successfully added!')
        ], 201);
    }

    public function taskDelete(Request $request)
    {

        $rules = [
            'task_id' => 'required|integer|min:1',
        ];

        // Validation
        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }
        $id = $request->task_id;
        $task = DealTask::findOrFail($id);
        $notifications = \App\Models\Notification::where('type', 'Tasks')->where('related_id', $id)->first();
        if (!empty($notifications)) {
            $notifications->delete();
        }
        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => __('Task successfully deleted!')
        ], 201);
    }


    public function downloadTasks()
    {
        if (\Auth::user()->type == 'super admin' || \Auth::user()->type == 'Admin Team' || \Auth::user()->can('level 1')) {
            $tasks = DealTask::select(['deal_tasks.*'])->join('users', 'users.id', '=', 'deal_tasks.assigned_to');

            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);

            if (\Auth::user()->type == 'company') {
                $tasks->where('deal_tasks.brand_id', \Auth::user()->id);
            } else if (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
                $tasks->whereIn('deal_tasks.brand_id', $brand_ids);
            } else if (\Auth::user()->type == 'Regional Manager' || \Auth::user()->can('level 3') && !empty(\Auth::user()->region_id)) {
                $tasks->where('deal_tasks.region_id', \Auth::user()->region_id);
            } else if (\Auth::user()->type == 'Branch Manager' || \Auth::user()->type == 'Admissions Officer' || \Auth::user()->type == 'Marketing Officer' || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
                $tasks->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            } else {
                $tasks->where('deal_tasks.assigned_to', \Auth::user()->id);
            }

            $filters = $this->TasksFilter();

            foreach ($filters as $column => $value) {
                if ($column === 'subjects') {
                    $tasks->whereIn('deal_tasks.name', $value);
                } elseif ($column === 'assigned_to') {
                    $tasks->whereIn('assigned_to', $value);
                } elseif ($column === 'created_by') {
                    $tasks->whereIn('deal_tasks.brand_id', $value);
                } elseif ($column == 'due_date') {
                    $tasks->whereDate('due_date', 'LIKE', '%' . substr($value, 0, 10) . '%');
                } elseif ($column == 'status') {
                    $tasks->where('status', $value);
                }
            }

            if (!isset($_GET['status'])) {
                $tasks->where('status', 0);
            }

            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $g_search = $_GET['search'];
                $tasks->Where('deal_tasks.name', 'like', '%' . $g_search . '%');
                $tasks->orWhere('deal_tasks.due_date', 'like', '%' . $g_search . '%');
            }

            $tasks = $tasks->orderBy('created_at', 'DESC')->get();
            $all_users = allUsers();

            // Prepare CSV Data
            $header = ['Sr.No.', 'Subject', 'Assigned to', 'Brand', 'Status'];
            $data = [];
            foreach ($tasks as $key => $task) {
                $data[] = [
                    $key + 1,
                    $task->name,
                    $all_users[$task->assigned_to] ?? '',
                    $all_users[$task->brand_id] ?? '',
                    ($task->status == 1) ? 'Completed' : 'On Going'
                ];
            }

            downloadCSV($header, $data, 'tasks.csv');
            return true;
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }
    }


    public function updateTaskStatus(Request $request, $status)
    {



        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:deal_tasks,id',
            'comment' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $id = $request->input('id');
        $dealTask = DealTask::find($id);
        if (!$dealTask || !in_array(\Auth::user()->type, ['super admin', 'Product Coordinator'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized or invalid task'
            ], 403);
        }
        $discussion = new TaskDiscussion();
        $discussion->comment = $request->comment;
        $discussion->task_id = $id;
        $discussion->created_by = \Auth::id();
        $discussion->save();


        $stageRequest = DealTask::where('id', $id)
            ->where('related_type', 'application')->first();
        if ($status == 'approve') {
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Task Approved successfully',
                    'message' => 'Task Approved successfully'
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Task Approved successfully'
            ];
            addLogActivity($data);
            $stageRequest->tasks_type_status = '1';
        } else {
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Task Rejected successfully',
                    'message' => 'Task Rejected successfully'
                ]),
                'module_id' => $id,
                'module_type' => 'task',
                'notification_type' => 'Task Rejected successfully'
            ];
            addLogActivity($data);
            $stageRequest->tasks_type_status = '2';
            $dealTask->status = 1;
            $dealTask->save();
        }
        $dealTask->save();
        $stageRequest->save();
        if (!$stageRequest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Stage request not found'
            ], 404);
        }

        $applicationId = $stageRequest->related_to;
        $dealApplication = DealApplication::find($applicationId);

        if ($dealApplication) {
            $deal = Deal::find($dealApplication->deal_id);
            if ($deal) {
                $highestStageApplication = DealApplication::where('deal_id', $dealApplication->deal_id)
                    ->orderBy('stage_id', 'desc')
                    ->first();

                if (!empty($highestStageApplication)) {
                    if ($highestStageApplication->stage_id == '0') {
                        $deal->stage_id = 0;
                    } elseif ($highestStageApplication->stage_id == '1' || $highestStageApplication->stage_id == '2') {
                        $deal->stage_id = 1;
                    } elseif ($highestStageApplication->stage_id == '3' || $highestStageApplication->stage_id == '4') {

                        $deal->stage_id = 2;
                    } elseif ($highestStageApplication->stage_id == '5' || $highestStageApplication->stage_id == '6') {

                        $deal->stage_id = 3;
                    } elseif ($highestStageApplication->stage_id == '7' || $highestStageApplication->stage_id == '8') {

                        $deal->stage_id = 4;
                    } elseif ($highestStageApplication->stage_id == '9' || $highestStageApplication->stage_id == '10') {

                        $deal->stage_id = 5;
                    } elseif ($highestStageApplication->stage_id == '11') {

                        $deal->stage_id = 6;
                    } elseif ($highestStageApplication->stage_id == '12') {

                        $deal->stage_id = 7;
                    }

                    $deal->save();
                } else {
                    $deal->stage_id = 0;
                    $deal->save();
                }
            }

            $lastStageHistory = StageHistory::where('type', 'application')
                ->where('type_id', $applicationId)
                ->latest()
                ->first();

            if ($status != 'approve') {

                //new code
                if ($stageRequest->tasks_type == 'Quality') {
                    $dealApplication->update(['stage_id' => 0]);
                    addLeadHistory([
                        'stage_id' => 0,
                        'type_id' => $applicationId,
                        'type' => 'application'
                    ]);
                } elseif ($stageRequest->tasks_type == 'Compliance') {
                    $dealApplication->update(['stage_id' => 5]);
                    addLeadHistory([
                        'stage_id' => 5,
                        'type_id' => $applicationId,
                        'type' => 'application'
                    ]);
                }
            } else {
                $dealApplication->update(['stage_id' => $stageRequest->stage_request]);
                addLeadHistory([
                    'stage_id' => $stageRequest->stage_request,
                    'type_id' => $applicationId,
                    'type' => 'application'
                ]);
            }

            // Add Log
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Stage Updated',
                    'message' => 'Application stage updated successfully.'
                ]),
                'module_id' => $applicationId,
                'module_type' => 'application',
                'notification_type' => 'application stage update'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User Task updated successfully'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Application not found'
        ], 404);
    }

    public function RejectTaskStatus(Request $request)
    {
        return $this->updateTaskStatus($request, 'reject');
    }

    public function ApprovedTaskStatus(Request $request)
    {
        return $this->updateTaskStatus($request, 'approve');
    }
}
