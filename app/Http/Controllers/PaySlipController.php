<?php

namespace App\Http\Controllers;
use App\Models\Employee;
use App\Models\PaySlip;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;

class PaySlipController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage pay slip')) {
            $employees = Employee::all();

            $month = [
                '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR', '05' => 'MAY',
                '06' => 'JUN', '07' => 'JUL', '08' => 'AUG', '09' => 'SEP', '10' => 'OCT',
                '11' => 'NOV', '12' => 'DEC',
            ];

            $year = range(date('Y'), date('Y') - 9);

            return response()->json([
                'employees' => $employees,
                'months' => $month,
                'years' => $year,
            ]);
        }

        return response()->json(['error' => 'Permission denied.'], 403);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required',
            'year' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $formattedMonthYear = $request->year . '-' . $request->month;

        $existingPayslips = PaySlip::where('salary_month', $formattedMonthYear)
            ->where('created_by', Auth::id())
            ->pluck('employee_id');

        $eligibleEmployees = $this->getEligibleEmployees($formattedMonthYear, $existingPayslips);

        if ($eligibleEmployees->isEmpty()) {
            return response()->json(['error' => 'Please set employee salary.'], 400);
        }

        $this->generatePayslips($eligibleEmployees, $formattedMonthYear);
        $this->sendNotifications($formattedMonthYear);

        return response()->json(['success' => 'Payslip successfully created.'], 201);
    }

    private function getEligibleEmployees($formattedMonthYear, $existingPayslips)
    {
        $query = Employee::where('company_doj', '<=', date($formattedMonthYear . '-t'))
            ->whereNotIn('id', $existingPayslips)
            ->whereNotNull('salary')
            ->whereNotNull('salary_type');

        // Apply additional filters if required
        return $query->get();
    }

    private function generatePayslips($employees, $formattedMonthYear)
    {
        foreach ($employees as $employee) {
            $payslip = PaySlip::firstOrNew([
                'employee_id' => $employee->id,
                'salary_month' => $formattedMonthYear,
                'created_by' => Auth::id(),
            ]);

            if (!$payslip->exists) {
                $payslip->fill([
                $payslip->net_payble = $employee->get_net_salary(),
                $payslip->status = 0,
                $payslip->basic_salary = $employee->salary ?? 0,
                $payslip->allowance = Employee::allowance($employee->id),
                $payslip->commission = Employee::commission($employee->id),
                $payslip->loan = Employee::loan($employee->id),
                $payslip->saturation_deduction = Employee::saturation_deduction($employee->id),
                $payslip->other_payment = Employee::other_payment($employee->id),
                $payslip->overtime = Employee::overtime($employee->id),
                $payslip->save(),
                ]);
                $payslip->save();
            }
        }
    }

    private function sendNotifications($formattedMonthYear)
    {
        $setting = Utility::settings(Auth::id());

        if (!empty($setting['payslip_notification'])) {
            Utility::send_slack_msg("Payslip generated for $formattedMonthYear.");
        }

        if (!empty($setting['telegram_payslip_notification'])) {
            Utility::send_telegram_msg("Payslip generated for $formattedMonthYear.");
        }
    }

    public function destroy($id)
    {
        $payslip = PaySlip::find($id);
        if ($payslip) {
            $payslip->delete();
            return response()->json(['success' => 'Payslip deleted.']);
        }
        return response()->json(['error' => 'Payslip not found.'], 404);
    }

    public function searchJson(Request $request)
    {
        $formattedMonthYear = $request->datePicker;
        $payslips = PaySlip::with('employee', 'employee.salaryType')
            ->where('salary_month', $formattedMonthYear)
            ->where('created_by', Auth::id())
            ->get();

        return response()->json($payslips);
    }
}
