<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PaySlip;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaySlipController extends Controller
{
    public function index(Request $request)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);
        // Fetch employees and prepare month/year options
        $jobsQuery = PaySlip::select(
            'pay_slips.*',
            'brand.name as brandname',   // Renamed for better readability
            'regions.name as regionname', // Renamed for better readability
            'branches.name as branchname' // Renamed for better readability
        )
        ->with([
            'employees',
            'employee.salaryType' // Assuming `employee` is a relationship; corrected casing
        ])
        ->leftJoin('employees', 'employees.id', '=', 'pay_slips.employee_id')
        ->leftJoin('users', 'users.id', '=', 'employees.user_id') // Corrected column relation
        ->leftJoin('users as brand', 'brand.id', '=', 'users.brand_id') // Referring to employees' brand_id
        ->leftJoin('regions', 'regions.id', '=', 'users.region_id')
        ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
        ->where('pay_slips.created_by', Auth::id()); // Filtering for the authenticated user



            if ($request->filled('brand')) {
                $jobsQuery->where('users.brand_id', $request->brand);
            }

            if ($request->filled('region_id')) {
                $jobsQuery->where('users.region_id', $request->region_id);
            }

            if ($request->filled('branch_id')) {
                $jobsQuery->where('users.branch_id', $request->branch_id);
            }

            if ($request->filled('employee_id')) {
                $jobsQuery->where('users.id', $request->employee_id);
            }

            if ($request->filled('created_at')) {
                $jobsQuery->whereDate('users.created_at', '=', $request->created_at);
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $jobsQuery->where(function ($query) use ($search) {
                    $query->where('brand.name', 'like', "%$search%")
                        ->orWhere('regions.name', 'like', "%$search%")
                        ->orWhere('branches.name', 'like', "%$search%")
                        ->orWhere('users.name', 'like', "%$search%")
                        ->orWhere('pay_slips.net_payble', 'like', "%$search%");
                });
            }

            //
            $payslips = $jobsQuery
            ->orderBy('pay_slips.created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'status' => 'success',
                'data' => $payslips->items(),
                'current_page' => $payslips->currentPage(),
                'last_page' => $payslips->lastPage(),
                'total_records' => $payslips->total(),
                'per_page' => $payslips->perPage(),
                'message' => __('Payslips retrieved successfully'),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $payslips,
        ], 201);
    }

    public function store(Request $request)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request input
        $validator = Validator::make($request->all(), [
            'month' => 'required|string|size:2|in:' . implode(',', array_keys($this->getMonths())),
            'year' => 'required|string|size:4|regex:/^\d{4}$/',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        $formattedMonthYear = $request->year . '-' . $request->month;

        // Check for existing payslips
        $existingPayslips = $this->getExistingPayslips($formattedMonthYear);

        // Get eligible employees
        $eligibleEmployees = $this->getEligibleEmployees($formattedMonthYear, $existingPayslips);

        if ($eligibleEmployees->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Payslips have already been created.')
            ], 400);
        }

        // Generate payslips and send notifications
        $this->generatePayslips($eligibleEmployees, $formattedMonthYear);
        $this->sendNotifications($formattedMonthYear);

        // Fetch and return generated payslips
        $payslips = $this->fetchPayslips($formattedMonthYear);
        return response()->json([
            'status' => 'success',
            'message' => __('Payslips successfully created.'),
            'data' => $payslips,
        ], 201);
    }

    public function destroy($id)
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find and delete payslip
        $payslip = PaySlip::where('id', $id)
            ->where('created_by', Auth::id())
            ->first();

        if (!$payslip) {
            return response()->json([
                'status' => 'error',
                'message' => "Payslip not found.",
            ], 404);
        }

         //    =================== delete ===========

            $employee = Employee::find($payslip->employee_id);  
            $typeoflog = 'payslip';
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $employee->user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $employee->user->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $payslip->id,
                    'module_type' => 'payslip',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);
            

                
                addLogActivity([
                    'type' => 'warning',
                    'note' => json_encode([
                        'title' => $employee->user->name .  ' '.$typeoflog.'  deleted ',
                        'message' => $employee->user->name .  ' '.$typeoflog.'  deleted ' 
                    ]),
                    'module_id' => $employee->user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' =>  ' '.$typeoflog.'  deleted'
                ]);
            

        $payslip->delete();
        return response()->json([
            'status' => 'success',
            'message' => "Payslip deleted.",
        ], 200);
    }

    public function searchJson(Request $request)
    {
        // Validate request input
        $validator = Validator::make($request->all(), [
            'datePicker' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()->first()
            ], 422);
        }

        // Fetch payslips for the given month
        $payslips = $this->fetchPayslips($request->datePicker);
        return response()->json([
            'status' => 'success',
            'data' => $payslips,
        ], 201);
    }

    // Helper methods

    private function canManagePaySlips()
    {
        return Auth::user()->can('manage pay slip');
    }

    private function errorResponse($message, $statusCode)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], $statusCode);
    }

    private function getMonths()
    {
        return [
            '01' => 'JAN',
            '02' => 'FEB',
            '03' => 'MAR',
            '04' => 'APR',
            '05' => 'MAY',
            '06' => 'JUN',
            '07' => 'JUL',
            '08' => 'AUG',
            '09' => 'SEP',
            '10' => 'OCT',
            '11' => 'NOV',
            '12' => 'DEC',
        ];
    }

    private function getYears()
    {
        return range(date('Y'), date('Y') - 9);
    }

    private function getExistingPayslips($formattedMonthYear)
    {
        return PaySlip::where('salary_month', $formattedMonthYear)
            ->where('created_by', Auth::id())
            ->pluck('employee_id');
    }

    // private function getEligibleEmployees($formattedMonthYear, $existingPayslips)
    // {
    //     return Employee::where('company_doj', '<=', date($formattedMonthYear . '-t'))
    //         ->whereNotIn('id', $existingPayslips)
    //         ->whereNotNull('salary')
    //         ->whereNotNull('salary_type')
    //         ->get();
    // }

    private function getEligibleEmployees($formattedMonthYear, $existingPayslips)
    {
    $excludedTypes = ['super admin', 'company', 'team', 'client'];

    // Get the base user query
    $usersQuery = User::whereNotIn('type', $excludedTypes);

    // Apply filters from request
    if (!empty(request('brand'))) {
        $usersQuery->where('brand_id', request('brand'));
    }
    if (!empty(request('region_id'))) {
        $usersQuery->where('region_id', request('region_id'));
    }
    if (!empty(request('branch_id'))) {
        $usersQuery->where('branch_id', request('branch_id'));
    }
    if (!empty(request('Name'))) {
        $usersQuery->where('name', 'like', '%' . request('Name') . '%');
    }
    if (!empty(request('Designation'))) {
        $usersQuery->where('type', 'like', '%' . request('Designation') . '%');
    }
    if (!empty(request('phone'))) {
        $usersQuery->where('phone', 'like', '%' . request('phone') . '%');
    }

    // Apply user type-specific filtering
    $user = \Auth::user();
    if ($user->type == 'super admin') {
        // No additional filtering
    } elseif ($user->type == 'company') {
        $usersQuery->where('brand_id', $user->id);
    } else {
        $usersQuery->where('brand_id', $user->brand_id);
    }

    // Get the filtered user IDs
    $userIds = $usersQuery->pluck('id');

    // Fetch employees with conditions and related users
    return Employee::where('company_doj', '<=', now()->endOfMonth())
        ->whereNotIn('id', $existingPayslips)
        ->whereNotNull('salary')
        ->whereNotNull('salary_type')
        ->whereIn('user_id', $userIds)
        ->with('user') // Load related user data
        ->get();

    }

    private function generatePayslips($employees, $formattedMonthYear)
    {
        foreach ($employees as $employee) {
          $payslip =  PaySlip::firstOrCreate([
                'employee_id' => $employee->id,
                'salary_month' => $formattedMonthYear,
                'created_by' => Auth::id(),
            ], [
                'net_payble' => $employee->get_net_salary(),
                'status' => 0,
                'basic_salary' => $employee->salary ?? 0,
                'allowance' => Employee::allowance($employee->id),
                'commission' => Employee::commission($employee->id),
                'loan' => Employee::loan($employee->id),
                'saturation_deduction' => Employee::saturation_deduction($employee->id),
                'other_payment' => Employee::other_payment($employee->id),
                'overtime' => Employee::overtime($employee->id),
            ]);

                 //  ========== add ============
                $user = User::find($employee->user_id);
                $typeoflog = 'payslip';
                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.' created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $payslip->id,
                    'module_type' => 'employee',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

                addLogActivity([
                    'type' => 'success',
                    'note' => json_encode([
                        'title' => $user->name. ' '.$typeoflog.'  created',
                        'message' => $user->name. ' '.$typeoflog.'  created'
                    ]),
                    'module_id' => $user->id,
                    'module_type' => 'employeeprofile',
                    'notification_type' => ' '.$typeoflog.'  Created',
                ]);

        }
    }

    private function sendNotifications($formattedMonthYear)
    {
        $settings = Utility::settings(Auth::id());

        if (!empty($settings['payslip_notification'])) {
            Utility::send_slack_msg("Payslip generated for $formattedMonthYear.");
        }

        if (!empty($settings['telegram_payslip_notification'])) {
            Utility::send_telegram_msg("Payslip generated for $formattedMonthYear.");
        }
    }

    private function fetchPayslips($formattedMonthYear)
    {
        return PaySlip::with('employees', 'employee.salaryType')
            ->where('salary_month', $formattedMonthYear)
            ->where('created_by', Auth::id())
            ->get();
    }


    public function updateEmployeeSalary(Request $request, $id)
    {
        // Validate the request data
        $validator = \Validator::make($request->all(), [
            'salary_type' => 'required|integer', // Ensure it's an integer
            'salary' => 'required|numeric',      // Ensure it's numeric
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        // Find the employee or return a 404 error if not found
        $employee = Employee::where('id',$id)->first();
        if (!$employee) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found.',
            ], 404);
        }

        // Update the employee's salary details
        $employee->salary_type = $request->salary_type;
        $employee->salary = $request->salary;
        $employee->save();

        // Return a success response
        return response()->json([
            'status' => 'success',
            'message' => 'Employee salary updated successfully.',
            'data' => $employee,
        ], 200);
    }

    public function Payslip_fetch(Request $request)
    {
        // Validate the request
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        try {
            // Retrieve the employee ID from the user
            $userId = User::findOrFail($request->id)->employee->id;
            if (!$userId) {
                return response()->json([
                    'error' => 'User not found for the specified employee.'
                ], 404);
            }
            // Retrieve the payslip based on employee ID
            $payslip = PaySlip::where('employee_id', $userId)->first();

            if (!$payslip) {
                return response()->json([
                    'error' => 'Payslip not found for the specified employee.'
                ], 404);
            }

            // Retrieve the employee data
            $employee = Employee::find($payslip->employee_id);

            if (!$employee) {
                return response()->json([
                    'error' => 'Employee not found.'
                ], 404);
            }

            // Retrieve payslip details using a utility function
            $payslipDetail = Utility::employeePayslipDetail($userId);

            return response()->json([
                'payslip' => $payslip,
                'employee' => $employee,
                'payslipDetail' => $payslipDetail
            ]);

        } catch (\Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'error' => 'An error occurred while fetching the payslip.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

}
