<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Utility;
use App\Models\Trainer;
use App\Models\SavedFilter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class LeaveController extends Controller
{
    public function getLeaves(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage leave')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage'    => 'nullable|integer|min:1',
            'page'       => 'nullable|integer|min:1',
            'search'     => 'nullable|string',
            'brand_id'   => 'nullable|integer|exists:users,id',
            'region_id'  => 'nullable|integer|exists:regions,id',
            'branch_id'  => 'nullable|integer|exists:branches,id',
            'created_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Base query with necessary joins
        $query = Leave::select(
            'leaves.*',
            'users.name as username',
            'branches.name as branch_name',
            'brands.name as brand_name',
            'regions.name as region_name'
        )
            ->with(['brand', 'branch', 'region', 'created_by', 'leaveType', 'employees','User'])
            ->leftJoin('employees', 'employees.id', '=', 'leaves.employee_id')
            ->leftJoin('users', 'users.id', '=', 'employees.user_id')
            ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
            ->leftJoin('users as brands', 'brands.id', '=', 'leaves.brand_id')
            ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id');

        // Apply role-based filtering
        $query = RoleBaseTableGet($query, 'leaves.brand_id', 'leaves.region_id', 'leaves.branch_id', 'leaves.created_by');

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('users.name', 'like', "%$search%")
                    ->orWhere('employees.name', 'like', "%$search%")
                    ->orWhere('brands.name', 'like', "%$search%")
                    ->orWhere('regions.name', 'like', "%$search%")
                    ->orWhere('branches.name', 'like', "%$search%");
            });
        }

        // Apply additional filters
        if ($request->filled('brand_id')) {
            $query->where('leaves.brand_id', $request->brand_id);
        }
        if ($request->filled('region_id')) {
            $query->where('leaves.region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('leaves.branch_id', $request->branch_id);
        }
        if (
            $request->filled('employee_id') &&
            $request->employee_id !== 'undefined'
        ) {
            $query->where('leaves.employee_id', $request->employee_id);

        }
        if ($request->filled('created_at')) {
            $query->whereDate('leaves.created_at', substr($request->created_at, 0, 10));
        }

        // Apply sorting and pagination
        $leaves = $query->orderBy('leaves.created_at', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $leaves->items(),
            'current_page' => $leaves->currentPage(),
            'last_page' => $leaves->lastPage(),
            'total_records' => $leaves->total(),
            'per_page' => $leaves->perPage(),
        ], 200);
    }





    public function addLeave(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create leave')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'brand_id'           => 'required|integer|exists:users,id',
            'region_id'          => 'required|integer|exists:regions,id',
            'lead_branch'        => 'required|integer|exists:branches,id',
            'lead_assigned_user' => 'required|integer|exists:users,id',
            'leave_type_id'      => 'required|integer|exists:leave_types,id',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'leave_reason'       => 'required|string',
            'remark'             => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Create and save leave record
        $leave = new Leave();
        $leave->employee_id = $request->lead_assigned_user;
        $leave->brand_id = $request->brand_id;
        $leave->region_id = $request->region_id;
        $leave->branch_id = $request->lead_branch;
        $leave->leave_type_id = $request->leave_type_id;
        $leave->applied_on = now()->format('Y-m-d');
        $leave->start_date = $request->start_date;
        $leave->end_date = $request->end_date;
        $leave->total_leave_days = 0;
        $leave->leave_reason = $request->leave_reason;
        $leave->remark = $request->remark;
        $leave->status = 'Pending';
        $leave->created_by = Auth::id();
        $leave->save();

        // Log Activity
        // addLogActivity([
        //     'type' => 'info',
        //     'note' => json_encode([
        //         'title' => 'Leave Created',
        //         'message' => 'Leave record created successfully'
        //     ]),
        //     'module_id' => $leave->id,
        //     'module_type' => 'leave',
        //     'notification_type' => 'Leave Created'
        // ]);

          addLogActivity([
            'type' => 'success',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  created',
                'message' => $leave->user->name. 'leave  created'
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'leave created',
        ]);
    addLogActivity([
            'type' => 'success',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  created',
                'message' => $leave->user->name. 'leave  created'
            ]),
              'module_id' => $leave->employee_id,
            'module_type' => 'employeeprofile',
            'notification_type' => 'leave created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave successfully created.',
            'data' => $leave
        ], 201);
    }

    public function show(Leave $leave)
    {
        return redirect()->route('leave.index');
    }

    public function updateLeave(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit leave')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'id'           => 'required|exists:leaves,id',
            'brand_id'           => 'required|integer|exists:users,id',
            'region_id'          => 'required|integer|exists:regions,id',
            'lead_branch'        => 'required|integer|exists:branches,id',
            'lead_assigned_user' => 'required|integer|exists:users,id',
            'leave_type_id'      => 'required|integer|exists:leave_types,id',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'leave_reason'       => 'required|string',
            'remark'             => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Leave Record
        $leave = Leave::where('id', $request->id)->first();

        if (!$leave) {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave record not found.'
            ], 404);
        }

         $originalData = $leave->toArray();

        // Update Leave Record
        $leave->employee_id = $request->lead_assigned_user ?? $leave->employee_id;
        $leave->brand_id = $request->brand_id ?? $leave->brand_id;
        $leave->region_id = $request->region_id ?? $leave->region_id;
        $leave->branch_id = $request->lead_branch ?? $leave->branch_id;
        $leave->leave_type_id = $request->leave_type_id ?? $leave->leave_type_id;
        $leave->start_date = $request->start_date ?? $leave->start_date;
        $leave->end_date = $request->end_date ?? $leave->end_date;
        $leave->leave_reason = $request->leave_reason ?? $leave->leave_reason;
        $leave->remark = $request->remark ?? $leave->remark;

        $leave->save();

        // Log Activity

        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($leave->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $leave->$field
                ];
                $updatedFields[] = $field;
            }
        }
      
          addLogActivity([
            'type' => 'info',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  updated',
                 'message' => 'Fields updated: ' . implode(', ', $updatedFields)
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'leave updated',
        ]);
          addLogActivity([
            'type' => 'info',
              'note' => json_encode([
                'title' => $leave->user->name. ' leave  updated',
                 'message' => 'Fields updated: ' . implode(', ', $updatedFields)
            ]),
            'module_id' => $leave->employee_id,
            'module_type' => 'employeeprofile',
            'notification_type' => 'leave updated',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave successfully updated.',
            'data' => $leave
        ], 200);
    }


    public function deleteLeave(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:leaves,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if the user has permission
        if (!\Auth::user()->can('delete leave')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find the Leave record
        $leave = Leave::with('User')->find($request->id);



        // Log the deletion activity
        $logData = [
            'type' => 'warning',
            'note' => json_encode([
               'title' => $leave->user->name. ' leave  deleted',
                'message' => $leave->user->name. ' leave  deleted'
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'Leave deleted'
        ];
        addLogActivity($logData);
  // Log the deletion activity
        $logData = [
            'type' => 'warning',
            'note' => json_encode([
               'title' => $leave->user->name. ' leave  deleted',
                'message' => $leave->user->name. ' leave  deleted'
            ]),
              'module_id' => $leave->employee_id,
            'module_type' => 'employeeprofile',
            'notification_type' => 'Leave Deleted'
        ];
        addLogActivity($logData);

        // Delete the record
        $leave->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('Leave successfully deleted.')
        ], 200);
    }


    public function action($id)
    {
        $leave     = Leave::find($id);
        $employee  = Employee::find($leave->employee_id);
        $leavetype = LeaveType::find($leave->leave_type_id);

        return view('leave.action', compact('employee', 'leavetype', 'leave'));
    }

    public function changeLeaveStatus(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'leave_id' => 'required|exists:leaves,id',
            'status'   => 'required|string|in:Pending,Approval,Rejected,Approved'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Leave Record
        $leave = Leave::where('id', $request->leave_id)->first();

        if (!$leave) {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave record not found.'
            ], 404);
        }

        // Update Status
        $leave->status = $request->status;

        // If status is 'Approval', calculate total leave days and update status to 'Approved'
        if ($request->status == 'Approval') {

        $attendanceRecords = \App\Models\AttendanceEmployee::whereBetween('date', [$leave->start_date, $leave->end_date])
        ->where('employee_id', $leave->employee_id)
        ->get();

        foreach ($attendanceRecords as $leaveData) {
            $leaveData->status = 'Leave';
            $leaveData->save();
        }

            $startDate = new \DateTime($leave->start_date);
            $endDate = new \DateTime($leave->end_date);
            $leave->total_leave_days = $startDate->diff($endDate)->days;
            $leave->status = 'Approved';
        }

        $leave->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Leave Status Updated',
                'message' => "Leave status changed to {$leave->status}"
            ]),
            'module_id' => $leave->id,
            'module_type' => 'leave',
            'notification_type' => 'Leave Status Updated'
        ]);

        // Send Email if enabled
        $settings = Utility::settings();
        if (!empty($leave->employee_id) && isset($settings['leave_status']) && $settings['leave_status'] == 1) {
            $employee = Employee::where('id', $leave->employee_id)->first();

            if ($employee) {
                $actionArr = [
                    'leave_name' => $employee->name ?? '',
                    'leave_status' => $leave->status,
                    'leave_reason' => $leave->leave_reason,
                    'leave_start_date' => $leave->start_date,
                    'leave_end_date' => $leave->end_date,
                    'total_leave_days' => $leave->total_leave_days
                ];

                $emailResponse = Utility::sendEmailTemplate('leave_action_sent', [$employee->id => $employee->email], $actionArr);

                if (!$emailResponse['is_success'] && !empty($emailResponse['error'])) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Leave status successfully updated, but email failed to send.',
                        'email_error' => $emailResponse['error'],
                        'data' => $leave
                    ], 200);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Leave status successfully updated.',
            'data' => $leave
        ], 200);
    }



    public function jsoncount(Request $request)
    {

        // $leave_counts = LeaveType::select(\DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave, leave_types.title, leave_types.days,leave_types.id'))
        //                          ->leftjoin('leaves', function ($join) use ($request){
        //     $join->on('leaves.leave_type_id', '=', 'leave_types.id');
        //     $join->where('leaves.employee_id', '=', $request->employee_id);
        // }
        // )->groupBy('leaves.leave_type_id')->get();

        $leave_counts=[];
        $leave_types = LeaveType::where('created_by',\Auth::id())->get();
        foreach ($leave_types as  $type) {
            $counts=Leave::select(\DB::raw('COALESCE(SUM(leaves.total_leave_days),0) AS total_leave'))->where('leave_type_id',$type->id)->groupBy('leaves.leave_type_id')->where('employee_id',$request->employee_id)->first();

            $leave_count['total_leave']=!empty($counts)?$counts['total_leave']:0;
            $leave_count['title']=$type->title;
            $leave_count['days']=$type->days;
            $leave_count['id']=$type->id;
            $leave_counts[]=$leave_count;
        }


        return $leave_counts;

    }

    public function Hrmleave()
    {
        $user = \Auth::user();
        if ($user->type!='HR' && $user->type!='super admin' && $user->type!='Project Manager') {
            echo 'access Denied';
                exit();
                die();
        }
        // Build the leads query
        if(isset($_GET['emp_id'])){
            $userId = $_GET['emp_id'];
         }else{
             $userId = \Auth::id();
         }
        $Employee = Employee::where('user_id',$userId)->first();
        $AuthUser = User::find($userId);
        if(empty($Employee)){
            $leaves=[];
            $leaveFrequency=[];
            $leaveDetails=[];
            return view('hrmhome.leave', compact('leaves','AuthUser','leaveFrequency','leaveDetails'));
        }
        $Leave_query = Leave::select(
                'regions.name as region',
                'branches.name as branch',
                'users.name as brand',
                'leaves.id',
                'leaves.brand_id',
                'leaves.branch_id',
                'leaves.created_by',
                'leaves.start_date',
                'leaves.created_at',
                'leaves.leave_reason',
                'leaves.end_date',
                'leaves.applied_on',
                'leaves.leave_type_id',
                'leaves.total_leave_days',
                'leaves.status',
                )
                ->leftJoin('users', 'users.id', '=', 'leaves.brand_id')
                ->leftJoin('users as leavedPerson', 'users.id', '=', 'leaves.employee_id')
                ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
                ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'leaves.created_by')
                ->leftJoin('lead_tags as tag', 'tag.lead_id', '=', 'leaves.id');
        $Leave_query->where('leaves.employee_id',$Employee->id);
        $leaves=$Leave_query->get();
        $leaves = Leave::where('start_date', '>=', Carbon::now()->subMonths(12))
        ->select('start_date', 'end_date')->where('leaves.employee_id',$Employee->id);
         $leaves=$Leave_query->get();
         $leaveFrequency = [
            'Sun' => 0,
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0,
        ];
        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date)->addDay();
            foreach ($startDate->toPeriod($endDate, '1 day') as $date) {
                $weekday = $date->format('D'); // Get weekday abbreviation (Sun, Mon, etc.)
                $leaveFrequency[$weekday]++;
            }
        }
        $leaveTypes = LeaveType::all();
        $leaveDetails = [];
        foreach ($leaveTypes as $type) {
            $allowance = $type->days;
            $leave_balance_check = Leave::where('leave_type_id', $type->id)->where('start_date', '>=', Carbon::now()->subMonths(12))
                        ->select('start_date', 'end_date')->where('leaves.employee_id',$Employee->id)->get();
            $usedLeaves = $leave_balance_check->where('status', 'Approved')->sum('total_leave_days');
            $plannedLeaves = $leave_balance_check->where('status', 'Pending')->sum('total_leave_days');
            $balance = $allowance - $usedLeaves;
            $available = $balance - $plannedLeaves;
            $leaveDetails[] = [
                'leave_type' => $type->title, // Title from LeaveType table
                'allowance' => $allowance,
                'balance' => $balance,
                'planned' => $plannedLeaves,
                'available' => $available,
                'units' => 'Days',
                    ];
            }
        return view('hrmhome.leave', compact('leaves','AuthUser','leaveFrequency','leaveDetails'));

    }

    public function HrmEmployee1()
    {
        return view('hrmhome.employee1');

    }


    public function Balance(Request $request)
    {
        $user = \Auth::user();

        // Authorization check
        // if (!in_array($user->type, ['HR', 'super admin', 'Project Manager'])) {
        //     return response()->json(['error' => 'Access Denied'], 403);
        // }

        // Fetch the employee ID or use authenticated user ID
        if(isset($request->emp_id)){
            $userId = $request->emp_id;
         }else{
             $userId = \Auth::id();
         }
         $employee = Employee::where('user_id',$userId)->first();
         $authUser = User::find($userId);

        if (empty($employee)) {
            return response()->json([
                'leaves' => [],
                'authUser' => $authUser,
                'leaveFrequency' => [],
                'leaveDetails' => []
            ]);
        }

        // Build the leaves query
        $leaveQuery = Leave::select(
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'leaves.id',
            'leaves.brand_id',
            'leaves.branch_id',
            'leaves.created_by',
            'leaves.start_date',
            'leaves.created_at',
            'leaves.leave_reason',
            'leaves.end_date',
            'leaves.applied_on',
            'leaves.leave_type_id',
            'leaves.total_leave_days',
            'leaves.status'
        )
            ->leftJoin('users', 'users.id', '=', 'leaves.brand_id')
            ->leftJoin('users as leavedPerson', 'users.id', '=', 'leaves.employee_id')
            ->leftJoin('branches', 'branches.id', '=', 'leaves.branch_id')
            ->leftJoin('regions', 'regions.id', '=', 'leaves.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'leaves.created_by')
            ->where('leaves.employee_id', $userId);

        $leaves = $leaveQuery->get();

        // Calculate leave frequency
        $leaveFrequency = [
            'Sun' => 0,
            'Mon' => 0,
            'Tue' => 0,
            'Wed' => 0,
            'Thu' => 0,
            'Fri' => 0,
            'Sat' => 0,
        ];

        foreach ($leaves as $leave) {
            $startDate = Carbon::parse($leave->start_date);
            $endDate = Carbon::parse($leave->end_date)->addDay();
            foreach ($startDate->toPeriod($endDate, '1 day') as $date) {
                $weekday = $date->format('D'); // Get weekday abbreviation
                $leaveFrequency[$weekday]++;
            }
        }

        // Fetch leave details
        $leaveTypes = LeaveType::all();
        $leaveDetails = [];
        foreach ($leaveTypes as $type) {
            $allowance = $type->days;

            $leaveBalanceCheck = Leave::where('leave_type_id', $type->id)
                ->where('start_date', '>=', Carbon::now()->subMonths(12))
                ->where('leaves.employee_id', $userId)
                ->get();

            // $usedLeaves = $leaveBalanceCheck->where('status', 'Approved')->sum('total_leave_days');
            $usedLeaves = $leaveBalanceCheck
                ->filter(fn($leave) => $leave->status === 'Approved' && Carbon::parse($leave->start_date)->lte(Carbon::today()))
                ->sum('total_leave_days');


            $plannedLeaves = $leaveBalanceCheck->where('status', 'Approved')
            ->filter(function ($leave) {
                return Carbon::parse($leave->start_date)->gt(Carbon::today());
            })
            ->sum('total_leave_days');





            $balance = $usedLeaves;

            $available = ($allowance - $usedLeaves) - $plannedLeaves;

            $leaveDetails[] = [
                'leave_type' => $type->title,
                'allowance' => $allowance,
                'balance' => $balance,
                'planned' => $plannedLeaves,
                'available' => $available,
                'units' => 'Days',
            ];
        }
     $data=['leaves' => $leaves,
            'authUser' => $authUser,
            'leaveFrequency' => $leaveFrequency,
            'leaveDetails' => $leaveDetails,
           ];
        // Return JSON responsest
        return response()->json([
            'status' => "success",
            'data' => $leaveDetails
        ]);
    }

}
