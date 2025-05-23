<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'dob',
        'gender',
        'phone',
        'address',
        'email',
        'password',
        'employee_id',
        'branch_id',
        //'department_id',
        //'designation_id',
        'company_doj',
        'documents',
        'account_holder_name',
        'account_number',
        'bank_name',
        'bank_identifier_code',
        'branch_location',
        'tax_payer_id',
       // 'salary_type',
        //'salary',
        'created_by',
    ];

    public function documents()
    {
        return $this->hasMany('App\Models\EmployeeDocument', 'employee_id', 'employee_id')->get();
    }

        public function salary_type()
    {
        return $this->hasOne('App\Models\PayslipType', 'id', 'salary_type');
    }


    public function get_net_salary()
    {

        //allowance
        $allowances      = Allowance::where('employee_id', '=', $this->id)->get();
        $total_allowance = 0 ;
        foreach($allowances as $allowance)
        {
            if($allowance->type == 'fixed')
            {
                $totalAllowances  = $allowance->amount;
            }
            else
            {
                $totalAllowances  = $allowance->amount * $this->salary / 100;
            }
            $total_allowance += $totalAllowances ;
        }

        //commission
        $commissions      = Commission::where('employee_id', '=', $this->id)->get();
        $total_commission = 0;
        foreach($commissions as $commission)
        {
            if($commission->type == 'fixed')
            {
                $totalCom  = $commission->amount;
            }
            else
            {
                $totalCom  = $commission->amount * $this->salary / 100;
            }
            $total_commission += $totalCom ;
        }

        //Loan
        $loans      = Loan::where('employee_id', '=', $this->id)->get();
        $total_loan = 0;
        foreach($loans as $loan)
        {
            if($loan->type == 'fixed')
            {
                $totalloan  = $loan->amount;
            }
            else
            {
                $totalloan  = $loan->amount * $this->salary / 100;
            }
            $total_loan += $totalloan ;
        }


        //Saturation Deduction
        $saturation_deductions      = SaturationDeduction::where('employee_id', '=', $this->id)->get();
        $total_saturation_deduction = 0 ;
        foreach($saturation_deductions as $deductions)
        {
            if($deductions->type == 'fixed')
            {
                $totaldeduction  = $deductions->amount;
            }
            else
            {
                $totaldeduction  = $deductions->amount * $this->salary / 100;
            }
            $total_saturation_deduction += $totaldeduction ;
        }

        //OtherPayment
        $other_payments      = OtherPayment::where('employee_id', '=', $this->id)->get();
        $total_other_payment = 0;
        $total_other_payment = 0 ;
        foreach($other_payments as $otherPayment)
        {
            if($otherPayment->type == 'fixed')
            {
                $totalother  = $otherPayment->amount;
            }
            else
            {
                $totalother  = $otherPayment->amount * $this->salary / 100;
            }
            $total_other_payment += $totalother ;
        }

        //Overtime
        $over_times      = Overtime::where('employee_id', '=', $this->id)->get();
        $total_over_time = 0;
        foreach($over_times as $over_time)
        {
            $total_work      = $over_time->number_of_days * $over_time->hours;
            $amount          = $total_work * $over_time->rate;
            $total_over_time = $amount + $total_over_time;
        }


        //Net Salary Calculate
        $advance_salary = $total_allowance + $total_commission - $total_loan - $total_saturation_deduction + $total_other_payment + $total_over_time;

        $employee       = Employee::where('id', '=', $this->id)->first();

        $net_salary     = (!empty($employee->salary) ? $employee->salary : 0) + $advance_salary;

        return $net_salary;

    }
    public function getNetSalaryAttribute()
{
    $employeeSalary = $this->salary ?? 0;

    $allowances = Allowance::where('employee_id', $this->id)->get();
    $commissions = Commission::where('employee_id', $this->id)->get();
    $loans = Loan::where('employee_id', $this->id)->get();
    $saturation_deductions = SaturationDeduction::where('employee_id', $this->id)->get();
    $other_payments = OtherPayment::where('employee_id', $this->id)->get();
    $over_times = Overtime::where('employee_id', $this->id)->get();

    $total_allowance = $allowances->sum(fn($a) => $a->type == 'fixed' ? $a->amount : ($a->amount * $employeeSalary / 100));
    $total_commission = $commissions->sum(fn($c) => $c->type == 'fixed' ? $c->amount : ($c->amount * $employeeSalary / 100));
    $total_loan = $loans->sum(fn($l) => $l->type == 'fixed' ? $l->amount : ($l->amount * $employeeSalary / 100));
    $total_saturation_deduction = $saturation_deductions->sum(fn($d) => $d->type == 'fixed' ? $d->amount : ($d->amount * $employeeSalary / 100));
    $total_other_payment = $other_payments->sum(fn($o) => $o->type == 'fixed' ? $o->amount : ($o->amount * $employeeSalary / 100));
    $total_over_time = $over_times->sum(fn($o) => ($o->number_of_days * $o->hours * $o->rate));

    $advance_salary = $total_allowance + $total_commission - $total_loan - $total_saturation_deduction + $total_other_payment + $total_over_time;

    return $employeeSalary + $advance_salary;
}

    public static function allowance($id)
    {

        //allowance
        $allowances      = Allowance::where('employee_id', '=', $id)->get();
        $total_allowance = 0;
        foreach($allowances as $allowance)
        {
            $total_allowance = $allowance->amount + $total_allowance;
        }

        $allowance_json = json_encode($allowances);

        return $allowance_json;

    }

    public static function commission($id)
    {
        //commission
        $commissions      = Commission::where('employee_id', '=', $id)->get();
        $total_commission = 0;
        foreach($commissions as $commission)
        {
            $total_commission = $commission->amount + $total_commission;
        }
        $commission_json = json_encode($commissions);

        return $commission_json;

    }

    public static function loan($id)
    {
        //Loan
        $loans      = Loan::where('employee_id', '=', $id)->get();
        $total_loan = 0;
        foreach($loans as $loan)
        {
            $total_loan = $loan->amount + $total_loan;
        }
        $loan_json = json_encode($loans);

        return $loan_json;
    }

    public static function saturation_deduction($id)
    {
        //Saturation Deduction
        $saturation_deductions      = SaturationDeduction::where('employee_id', '=', $id)->get();
        $total_saturation_deduction = 0;
        foreach($saturation_deductions as $saturation_deduction)
        {
            $total_saturation_deduction = $saturation_deduction->amount + $total_saturation_deduction;
        }
        $saturation_deduction_json = json_encode($saturation_deductions);

        return $saturation_deduction_json;

    }

    public static function other_payment($id)
    {
        //OtherPayment
        $other_payments      = OtherPayment::where('employee_id', '=', $id)->get();
        $total_other_payment = 0;
        foreach($other_payments as $other_payment)
        {
            $total_other_payment = $other_payment->amount + $total_other_payment;
        }
        $other_payment_json = json_encode($other_payments);

        return $other_payment_json;
    }

    public static function overtime($id)
    {
        //Overtime
        $over_times      = Overtime::where('employee_id', '=', $id)->get();
        $total_over_time = 0;
        foreach($over_times as $over_time)
        {
            $total_work      = $over_time->number_of_days * $over_time->hours;
            $amount          = $total_work * $over_time->rate;
            $total_over_time = $amount + $total_over_time;
        }
        $over_time_json = json_encode($over_times);

        return $over_time_json;
    }

    public static function employee_id()
    {
        $employee = Employee::latest()->first();

        return !empty($employee) ? $employee->id + 1 : 1;
    }

    public function branch()
    {
        return $this->hasOne('App\Models\Branch', 'id', 'branch_id');
    }

    public function department()
    {
        return $this->hasOne('App\Models\Department', 'id', 'department_id');
    }

    public function designation()
    {
        return $this->hasOne('App\Models\Designation', 'id', 'designation_id');
    }

    public function salaryType()
    {
        return $this->hasOne('App\Models\PayslipType', 'id', 'salary_type');
    }

    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function paySlip()
    {
        return $this->hasOne('App\Models\PaySlip', 'id', 'employee_id');
    }


    public function present_status($employee_id, $data)
    {
        return AttendanceEmployee::where('employee_id', $employee_id)->where('date', $data)->first();
    }





}
