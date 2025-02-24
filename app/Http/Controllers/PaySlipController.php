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
    public function index()
    {
        // Check user permissions
        if (!$this->canManagePaySlips()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Fetch employees and prepare month/year options
        $payslips = PaySlip::with('employees', 'employee.salaryType')
            ->where('created_by', Auth::id())
            ->get();

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

    private function getEligibleEmployees($formattedMonthYear, $existingPayslips)
    {
        return Employee::where('company_doj', '<=', date($formattedMonthYear . '-t'))
            ->whereNotIn('id', $existingPayslips)
            ->whereNotNull('salary')
            ->whereNotNull('salary_type')
            ->get();
    }

    private function generatePayslips($employees, $formattedMonthYear)
    {
        foreach ($employees as $employee) {
            PaySlip::firstOrCreate([
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
        $employee = Employee::where('user_id',$id)->first();
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
        // Retrieve the payslip based on employee ID and month
        $payslip = PaySlip::where('employee_id', $request->id)->first();

        if (!$payslip) {
            return response()->json([
                'error' => 'Payslip not found for the specified employee and month.'
            ], 404);
        }

        // Retrieve the employee data
        $employee = Employee::find($payslip->employee_id);

        if (!$employee) {
            return response()->json([
                'error' => 'Employee not found.'
            ], 404);
        }
        $payslipDetail = Utility::employeePayslipDetail($request->id);
        return response()->json([
            'payslip' => $payslip,
            'employee' => $employee,
            'payslipDetail' => $payslipDetail
        ]);
    }
    
}
