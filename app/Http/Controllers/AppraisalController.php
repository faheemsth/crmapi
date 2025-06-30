<?php

namespace App\Http\Controllers;

use App\Models\Appraisal;
use App\Models\appraisalremark;
use App\Models\Branch;
use App\Models\Competencies;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Indicator;
use App\Models\LogActivity;
use App\Models\Performance_Type;
use App\Models\PerformanceType;
use App\Models\SavedFilter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class AppraisalController extends Controller
{

    public function getAppraisals(Request $request)
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'employee_id' => 'nullable|integer|exists:users,id',
            'created_at' => 'nullable|date',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!$user->can('manage appraisal')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 403);
        }

        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        // Build the query
        $appraisalQuery = Appraisal::select(
            'appraisals.*',
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'assigned_to.name as created_user',
            'branches.name as branch_id',
        )
            ->with('employees','appraisalRemarks')
            ->leftJoin('users', 'users.id', '=', 'appraisals.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'appraisals.branch')
            ->leftJoin('regions', 'regions.id', '=', 'appraisals.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'appraisals.created_by');

        // Apply role-based filtering
        $appraisalQuery = RoleBaseTableGet($appraisalQuery, 'appraisals.brand_id', 'appraisals.region_id', 'appraisals.branch', 'appraisals.created_by');

        // Apply filters
        if ($request->filled('brand')) {
            $appraisalQuery->where('appraisals.brand_id', $request->brand);
        }

        if ($request->filled('region_id')) {
            $appraisalQuery->where('appraisals.region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $appraisalQuery->where('appraisals.branch', $request->branch_id);
        }

        if ($request->filled('employee_id')) {
            $appraisalQuery->where('appraisals.employee', $request->employee_id);
        }

        if ($request->filled('created_at')) {
            $appraisalQuery->whereDate('appraisals.created_at', '=', $request->created_at);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $appraisalQuery->where(function ($query) use ($search) {
            $query->where('assigned_to.name', 'like', "%$search%") // Ensure correct alias
            ->orWhere('users.name', 'like', "%$search%")
            ->orWhere('branches.name', 'like', "%$search%")
            ->orWhere('regions.name', 'like', "%$search%");
            });
        }

        // Fetch paginated appraisals
        $appraisals = $appraisalQuery
            ->orderBy('appraisals.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Get competency count
        $userType = Role::where('name', $user->type)->first();
        $competencyCount = Competencies::where('type', $userType->id)->count();
        $competencyCount = $competencyCount == 0 ? 1 : $competencyCount;

        // Get summary data for active/inactive appraisals

        // Return JSON response
        return response()->json([
            'status' => 'success',
            'data' => $appraisals->items(),
            'current_page' => $appraisals->currentPage(),
            'last_page' => $appraisals->lastPage(),
            'total_records' => $appraisals->total(),
            'per_page' => $appraisals->perPage(),
            'competency_count' => $competencyCount,
            'message' => __('Appraisals retrieved successfully'),
        ]);
    }

    public function addApraisal(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('create appraisal')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 403);
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'lead_branch' => 'required|integer|min:1',
            'lead_assigned_user' => 'required|integer|min:1',
            'appraisal_date' => 'required|date',
            'rating' => 'nullable|array',
            'admission_rate' => 'nullable|numeric',
            'application_rate' => 'nullable|numeric',
            'deposit_rate' => 'nullable|numeric',
            'visa_rate' => 'nullable|numeric',
            'competencyRemarks' => 'nullable|array',
            'competencyRemarks.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Check for duplicate appraisal
        $existingAppraisal = Appraisal::where('employee', $request->lead_assigned_user)
            ->where('appraisal_date', $request->appraisal_date)
            ->first();

        if ($existingAppraisal) {
            $employee = User::find($existingAppraisal->employee);
            return response()->json([
                'status' => 'error',
                'message' => __('Related to :user on :date, an appraisal already exists.', [
                    'user' => $employee->name ?? 'User',
                    'date' => $request->appraisal_date,
                ]),
            ], 422);
        }

        // Create a new Appraisal instance
        $appraisal = new Appraisal();
        $appraisal->brand_id = $request->brand_id;
        $appraisal->region_id = $request->region_id;
        $appraisal->branch = $request->lead_branch;
        $appraisal->employee = $request->lead_assigned_user;
        $appraisal->appraisal_date = $request->appraisal_date;
        $appraisal->rating = json_encode($request->rating, true);
        $appraisal->remark = $request->remark;
        $appraisal->admission_rate = $request->admission_rate ?: 0;
        $appraisal->admission_remarks = $request->admission_remarks;
        $appraisal->application_rate = $request->application_rate ?: 0;
        $appraisal->application_remarks = $request->application_remarks;
        $appraisal->deposit_rate = $request->deposit_rate ?: 0;
        $appraisal->deposit_remarks = $request->deposit_remarks;
        $appraisal->visa_rate = $request->visa_rate ?: 0; // Ensure visa_rate is a numeric value
        $appraisal->visa_remarks = $request->visa_remarks;
        $appraisal->created_by = $request->emp_id ?? Auth::id();
        $appraisal->save();

        // Insert competency remarks if provided
        if (!empty($request->competencyRemarks) && is_array($request->competencyRemarks)) {
            foreach ($request->competencyRemarks as $competencyId => $remark) {
                if (!empty($competencyId) && !empty($remark)) {
                    AppraisalRemark::create([
                        'appraisal_id' => $appraisal->id,
                        'competencies_id' => $competencyId,
                        'remarks' => $remark,
                    ]);
                } else {
                    logger()->warning("Missing competency ID or remark", [
                        'competencyId' => $competencyId,
                        'remark' => $remark,
                    ]);
                }
            }
        }

            //  ========== add ============
        $user = User::find($appraisal->employee);
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $user->name. ' appraisalt created',
                'message' => $user->name. ' appraisal created'
            ]),
            'module_id' => $appraisal->id,
            'module_type' => 'appraisal',
            'notification_type' => 'appraisal Created',
        ]);

          addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $user->name. ' appraisal created',
                'message' => $user->name. ' appraisal created'
            ]),
            'module_id' => $appraisal->employee,
            'module_type' => 'employeeprofile',
            'notification_type' => 'Appraisal Created',
        ]);


        return response()->json([
            'status' => 'success',
            'message' => __('Appraisal successfully created.'),
            'data' => $appraisal,
        ], 201);
    }


    public function updateAppraisal(Request $request)
    {
        $user = Auth::user();

        if (!$user->can('edit appraisal')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied'),
            ], 403);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:appraisals,id',
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'lead_branch' => 'required|integer|min:1',
            'lead_assigned_user' => 'required|integer|min:1',
            'appraisal_date' => 'required|date',
            'rating' => 'nullable|array',
            'admission_rate' => 'nullable|numeric',
            'application_rate' => 'nullable|numeric',
            'deposit_rate' => 'nullable|numeric',
            'visa_rate' => 'nullable|numeric',
            'competencyRemarks' => 'nullable|array',
            'competencyRemarks.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Retrieve the Appraisal
        $appraisal = Appraisal::findOrFail($request->id);

        $originalData = $appraisal->toArray();

        // Update appraisal details
        $appraisal->brand_id = $request->brand_id;
        $appraisal->region_id = $request->region_id;
        $appraisal->branch = $request->lead_branch;
        $appraisal->employee = $request->lead_assigned_user;
        $appraisal->appraisal_date = $request->appraisal_date;
        $appraisal->remark = $request->remark;
        $appraisal->admission_rate = $request->admission_rate;
        $appraisal->admission_remarks = $request->admission_remarks;
        $appraisal->application_rate = $request->application_rate;
        $appraisal->application_remarks = $request->application_remarks;
        $appraisal->deposit_rate = $request->deposit_rate;
        $appraisal->deposit_remarks = $request->deposit_remarks;
        $appraisal->visa_rate = $request->visa_rate;
        $appraisal->visa_remarks = $request->visa_remarks;
        $appraisal->status = $request->save_type === 'Submit' ? 2 : 1;
        $appraisal->save();

        // Handle competency remarks
        if (!empty($request->competencyRemarks) && is_array($request->competencyRemarks)) {
            foreach ($request->competencyRemarks as $competencyId => $remarks) {
                AppraisalRemark::updateOrCreate(
                    ['appraisal_id' => $appraisal->id, 'competencies_id' => $competencyId],
                    ['remarks' => $remarks]
                );
            }
        }

        // Log activity if appraisal is submitted
         // ============ edit ============

           // Log changed fields only
        $changes = [];
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($appraisal->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $appraisal->$field
                ];
                $updatedFields[] = $field;
            }
        }
        $user = User::find($appraisal->employee);
           
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name . ' appraisal updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $appraisal->id,
                    'module_type' => 'appraisal',
                    'notification_type' => 'appraisal Updated'
                ]);
            }

             
        if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $user->name . ' appraisal updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                        'changes' => $changes
                    ]),
                    'module_id' => $appraisal->employee,
                    'module_type' => 'employeeprofile',
                    'notification_type' => 'appraisal Updated'
                ]);
            }



        return response()->json([
            'status' => 'success',
            'message' => __('Appraisal successfully updated.'),
            'data' => $appraisal,
        ]);
    }


    public function deleteAppraisal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:appraisals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        if (!Auth::user()->can('delete appraisal')) {
            return response()->json(['status' => 'error', 'message' => __('Permission denied.')], 403);
        }

        $appraisal = Appraisal::find($request->id);
        if (!$appraisal) {
            return response()->json(['status' => 'error', 'message' => 'Appraisal not found.'], 404);
        }



        // Delete related Appraisal Remarks
        AppraisalRemark::where('appraisal_id', $appraisal->id)->delete();

        // Delete the Appraisal
        $appraisal->delete();

            //    =================== delete ===========

            $user = User::find($appraisal->employee); 
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name . ' appraisal deleted ',
                        'message' => $user->name . ' appraisal deleted '
                    ]),
                    'module_id' => $appraisal->id,
                    'module_type' => 'appraisal',
                    'notification_type' => 'appraisal deleted'
                ]);
            

                
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $user->name . ' appraisal deleted ',
                        'message' => $user->name . ' appraisal deleted '
                    ]),
                    'module_id' => $user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => 'appraisal deleted'
                ]);
            

        return response()->json(['status' => 'success', 'message' => 'Appraisal and related remarks successfully deleted.']);
    }

    public function appraisalDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:appraisals,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $appraisal = Appraisal::select(
            'appraisals.*',
            'regions.name as region',
            'branches.name as branch',
            'users.name as brand',
            'assigned_to.name as created_user',
            'branches.id as branch_id',
            'assigned_to.id as created_id',
        )
            ->with('employees')
            ->leftJoin('users', 'users.id', '=', 'appraisals.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'appraisals.branch')
            ->leftJoin('regions', 'regions.id', '=', 'appraisals.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'appraisals.employee')
            ->where('appraisals.id', $request->id)
            ->first();

        if (!$appraisal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data Not Found'
            ], 404);
        }

        $user = User::find($appraisal->employee);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found'
            ], 404);
        }

        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $performance_types = Role::whereNotIn('name', $excludedTypes)->where('name', $user->type)->get();

        $employee = Employee::where('user_id', $appraisal->employee)->first();
        $user_type = Role::where('name', $user->type)->first();
        $indicator = Indicator::where('designation', $user_type->id)->first();

        $rating = !empty($appraisal->rating) ? json_decode($appraisal->rating, true) : [];
        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : [];

        return response()->json([
            'status' => 'success',

            'data' => $appraisal,
            'performance_types' => $performance_types,
            'ratings' => $ratings,
            'rating' => $rating,
            'user' => $user,
            'employee' => $employee,

        ]);
    }

    public function fetchperformance(Request $request)
    {
        $userget = User::find($request->employee);
        $user_type = Role::where('name', $userget->type)->first();
        $indicator = Indicator::where('designation', $user_type->id)->first();
        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : [];
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $performance_types = Role::whereNotIn('name', $excludedTypes)->where('name', $userget->type)->get();

        // Add competencies to each performance type
        foreach ($performance_types as $performance_type) {
            $performance_type->competencies = Competencies::whereRaw("FIND_IN_SET(?, type)", [$performance_type->id])->get();
        }

        return response()->json([
            'status' => "success",
            'userget' => $userget,
            'performance_types' => $performance_types,
            'ratings' => $ratings,
        ]);
    }

    public function fetchperformanceedit(Request $request)
    {
        // Fetch appraisal data
        $appraisal = Appraisal::with('appraisalRemarks')->find($request->appraisal);

        // Fetch user data
        $userget = User::find($request->employee);

        // Fetch user role
        $user_type = Role::where('name', $userget->type)->first();

        // Fetch indicator data
        $indicator = Indicator::where('designation', $user_type->id)->first();

        // Exclude certain roles
        $excludedTypes = ['super admin', 'company', 'team', 'client'];
        $performance_types = Role::whereNotIn('name', $excludedTypes)
            ->where('name', $userget->type)
            ->get();

        // Decode ratings
        $ratings = !empty($indicator) ? json_decode($indicator->rating, true) : [];
        $rating = !empty($appraisal) ? json_decode($appraisal->rating, true) : [];

        // Add competencies to each performance type
        foreach ($performance_types as $performance_type) {
            $performance_type->competencies = Competencies::whereRaw("FIND_IN_SET(?, type)", [$performance_type->id])->get();
        }

        // Return JSON response
        return response()->json([
            'status' => "success",
            'userget' => $userget,
            'performance_types' => $performance_types,
            'ratings' => $ratings,
            'rating' => $rating,
            'appraisal' => $appraisal,
        ]);
    }
}
