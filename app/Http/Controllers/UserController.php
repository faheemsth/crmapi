<?php

namespace App\Http\Controllers;

use Auth;
use File;
use Session;
use App\Models\NOC;
use App\Models\Plan;
use App\Models\User;
use App\Models\Order;
use App\Models\Branch;
use App\Models\Region;
use App\Models\Utility;
use App\Models\Employee;
use App\Models\UserToDo;
use App\Models\CustomField;
use App\Models\SavedFilter;
use App\Models\UserCompany;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\JoiningLetter;
use App\Models\CompanyPermission;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Mail\AutoGeneratedPassword;
use App\Models\AdditionalAddress;
use App\Models\GenerateOfferLetter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\ExperienceCertificate;
use App\Models\Notification;
use App\Models\DealTask;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\DealApplication;
use App\Models\EmergencyContact;
use App\Models\EmployeeDocument;
use App\Models\InternalEmployeeNotes;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    public function employees(Request $request)
    {
        $user = \Auth::user();
    
        // Ensure the user has permission to manage employees
        if (\Auth::user()->can('manage employee')) {
            $excludedTypes = ['company', 'team', 'client'];
            $usersQuery = User::select('users.*');
    
            // Get company filters
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);
    
            // Apply permissions based on user levels and attributes
            if (\Auth::user()->can('level 1')) {
                // Permissions for level 1
            } elseif (\Auth::user()->type == 'company') {
                $usersQuery->where('brand_id', \Auth::user()->id);
            } elseif (\Auth::user()->can('level 2')) {
                $usersQuery->whereIn('brand_id', $brand_ids);
            } elseif (\Auth::user()->can('level 3') && !empty(\Auth::user()->region_id)) {
                $usersQuery->where('region_id', \Auth::user()->region_id);
            } elseif (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
                $usersQuery->where('branch_id', \Auth::user()->branch_id);
            } else {
                $usersQuery->where('id', \Auth::user()->id);
            }
    
            // Apply exclusion of user types
            $usersQuery->whereNotIn('type', $excludedTypes);
    
            // Fetch user data (e.g., 'name' and 'id')
            $users = $usersQuery->orderBy('users.name', 'ASC')->get(['name', 'id']);
    
            // Return response with status and data
            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        }
    
        // Return an error if the user doesn't have permission
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 403);
    }
        
    public function getEmployees(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'perPage' => 'nullable|integer|min:1',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'Name' => 'nullable|string',
            'Designation' => 'nullable|string',
            'phone' => 'nullable|string',
            'search' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = \Auth::user();
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);

        if (!$user->can('manage employee')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access'
            ], 403);
        }

        $excludedTypes = ['super admin', 'company', 'team', 'client'];

        $employeesQuery = User::select('users.*')
            ->whereNotIn('type', $excludedTypes);

        // Apply filters
        if ($request->filled('brand')) {
            $employeesQuery->where('brand_id', $request->brand);
        }
        if ($request->filled('region_id')) {
            $employeesQuery->where('region_id', $request->region_id);
        }
        if ($request->filled('branch_id')) {
            $employeesQuery->where('branch_id', $request->branch_id);
        }
        if ($request->filled('Name')) {
            $employeesQuery->where('name', 'like', '%' . $request->Name . '%');
        }
        if ($request->filled('Designation')) {
            $employeesQuery->where('type', 'like', '%' . $request->Designation . '%');
        }
        if ($request->filled('phone')) {
            $employeesQuery->where('phone', 'like', '%' . $request->phone . '%');
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $employeesQuery->where(function ($query) use ($search) {
                $query->where('users.name', 'like', "%$search%")
                    ->orWhere('users.email', 'like', "%$search%")
                    ->orWhere('users.phone', 'like', "%$search%")
                    ->orWhere('users.type', 'like', "%$search%")
                    ->orWhere(DB::raw('(SELECT name FROM branches WHERE branches.id = users.branch_id)'), 'like', "%$search%")
                    ->orWhere(DB::raw('(SELECT name FROM regions WHERE regions.id = users.region_id)'), 'like', "%$search%")
                    ->orWhere(DB::raw('(SELECT name FROM users AS brands WHERE brands.id = users.brand_id)'), 'like', "%$search%");
            });
        }

        // Apply user-specific restrictions
        if ($user->can('level 1') || $user->type === 'super admin') {
            // Level 1 permissions
        } elseif ($user->type === 'company') {
            $employeesQuery->where('brand_id', $user->id);
        } elseif ($user->can('level 2')) {
            $brandIds = array_keys(FiltersBrands());
            $employeesQuery->whereIn('brand_id', $brandIds);
        } elseif ($user->can('level 3') && $user->region_id) {
            $employeesQuery->where('region_id', $user->region_id);
        } elseif ($user->can('level 4') && $user->branch_id) {
            $employeesQuery->where('branch_id', $user->branch_id);
        } else {
            $employeesQuery->where('id', $user->id);
        }

        // Paginate results
        $employees = $employeesQuery
            ->orderBy('users.name', 'ASC')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $employees->items(),
            'current_page' => $employees->currentPage(),
            'last_page' => $employees->lastPage(),
            'total_records' => $employees->total(),
            'perPage' => $employees->perPage()
        ], 200);
    }
    public function EmployeeDetails(Request $request)
    {
        $EmployeeDetails = User::with('employee')->select(
            'users.*',
            'assignedUser.name as brand_name',
            'regions.name as region_name',
            'branches.name as branch_name'
        )
        ->leftJoin('users as assignedUser', 'assignedUser.id', '=', 'users.brand_id')
        ->leftJoin('regions', 'regions.id', '=', 'users.region_id')
        ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
        ->where('users.id', $request->id)
        ->first();

        $Employee = Employee::select('pay_slips.*', 'creater.name as created_by')
        ->leftJoin('pay_slips', 'pay_slips.employee_id', '=', 'employees.id')
        ->leftJoin('users as creater', 'creater.id', '=', 'employees.user_id') // Fixed alias reference
        ->where('employees.user_id', $request->id)
        ->get();
    
        $data=[
            'EmployeeDetails' => $EmployeeDetails,
            'pay_slips' => $Employee,
        ];
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], 200);
    }
    

    public function HrmInternalEmployeeNoteStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'lead_assigned_user' => 'required',
            'employee_notes' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $internalEmployeeNotes = InternalEmployeeNotes::create([
            'brand_id' => $request->brand_id,
            'region_id' => $request->region_id,
            'lead_branch' => $request->branch_id,
            'lead_assigned_user' => $request->lead_assigned_user,
            'notes' => $request->employee_notes,
            'created_by' => \Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Internal Employee Note successfully created.',
            'data' => $internalEmployeeNotes,
        ], 201);
    }

    public function HrmInternalEmployeeNoteDelete(Request $request)
    {
        $internalEmployeeNotes = InternalEmployeeNotes::find($request->id);

        if (!$internalEmployeeNotes) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found.',
            ], 404);
        }

        $internalEmployeeNotes->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Internal Employee Note successfully deleted.',
        ]);
    }

    public function HrmInternalEmployeeNoteUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_id' => 'required|integer|min:1',
            'region_id' => 'required|integer|min:1',
            'branch_id' => 'required|integer|min:1',
            'lead_assigned_user' => 'required',
            'employee_notes' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $internalEmployeeNotes = InternalEmployeeNotes::find($request->id);

        if (!$internalEmployeeNotes) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found.',
            ], 404);
        }

        $internalEmployeeNotes->update([
            'brand_id' => $request->brand_id,
            'region_id' => $request->region_id,
            'lead_branch' => $request->branch_id,
            'lead_assigned_user' => $request->lead_assigned_user,
            'notes' => $request->employee_notes,
            'created_by' => \Auth::id(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Internal Employee Note successfully updated.',
        ]);
    }

    public function HrmInternalEmployeeNoteGet(Request $request)
    {
        $InternalEmployeeNotes = InternalEmployeeNotes::where('lead_assigned_user', $request->id)->get();
        return response()->json([
            'status' => 'success',
            'data' => $InternalEmployeeNotes,
        ]);
    }
    
}
