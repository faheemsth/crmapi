<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEmployee;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\IpRestrict;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class AttendanceEmployeeController extends Controller
{
    public function getAttendances(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage attendance')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'perPage'   => 'nullable|integer|min:1',
            'page'      => 'nullable|integer|min:1',
            'search'    => 'nullable|string',
            'brand_id'  => 'nullable|integer|exists:brands,id',
            'region_id' => 'nullable|integer|exists:regions,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'type'      => 'nullable|in:monthly,daily',
            'month'     => 'nullable|date_format:Y-m',
            'date'      => 'nullable|date_format:Y-m-d',
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
        $query = AttendanceEmployee::with(['employee', 'employee.user']);
        if(isset($request->emp_id)){
            $employee=Employee::where('id',$request->emp_id)->first();
            $query->where('employee_id',$employee->id ?? 1);
         }
        // Apply role-based filtering
       // $query = RoleBaseTableGet($query, 'employees.brand_id', 'employees.region_id', 'employees.branch_id', 'employees.created_by');

        $user = Auth::user();

        if (!$user->can('level1')) {
            // Fetch attendance for a single employee
            // $empId = $user->employee->id ?? 0;
            // $query->where('employee_id', $empId);
        } else {
            // Filter based on selected brand, region, and branch
            $employeeQuery = Employee::select('employees.id')->join('users', 'users.id', '=', 'employees.user_id');

            if ($request->filled('brand_id')) {
                $employeeQuery->where('users.brand_id', $request->brand_id);
            }
            if ($request->filled('region_id')) {
                $employeeQuery->where('users.region_id', $request->region_id);
            }
            if ($request->filled('branch_id')) {
                $employeeQuery->where('users.branch_id', $request->branch_id);
            }

            $employeeIds = $employeeQuery->pluck('id');
            $query->whereIn('employee_id', $employeeIds);
        }

        // Apply attendance filters (daily/monthly)
        if ($request->type === 'monthly' && $request->filled('month')) {
            $start_date = Carbon::parse($request->month)->startOfMonth()->toDateString();
            $end_date = Carbon::parse($request->month)->endOfMonth()->toDateString();
            $query->whereBetween('date', [$start_date, $end_date]);
        } elseif ($request->type === 'daily' && $request->filled('date')) {
            $query->whereDate('date', $request->date);
        } else {
            $start_date = Carbon::now()->startOfMonth()->toDateString();
            $end_date = Carbon::now()->endOfMonth()->toDateString();
            $query->whereBetween('date', [$start_date, $end_date]);
        }

        // Apply sorting and pagination
        $attendanceRecords = $query->orderBy('date', 'DESC')
            ->paginate($perPage, ['*'], 'page', $page);

        // Return the paginated data
        return response()->json([
            'status' => 'success',
            'data' => $attendanceRecords->items(),
            'current_page' => $attendanceRecords->currentPage(),
            'last_page' => $attendanceRecords->lastPage(),
            'total_records' => $attendanceRecords->total(),
            'per_page' => $attendanceRecords->perPage(),
        ], 200);
    }




    public function addAttendance(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('create attendance')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'date'        => 'required|date',
            'clock_in'    => 'required|date_format:H:i',
            'clock_out'   => 'required|date_format:H:i|after:clock_in',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Check if attendance already exists for the given employee and date
        $existingAttendance = AttendanceEmployee::where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->where('clock_out', '00:00:00')
            ->exists();

        if ($existingAttendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee attendance already created.'
            ], 409);
        }

        // Get company start and end time
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');
        $date = date("Y-m-d");

        // Calculate late time
        $totalLateSeconds = strtotime($request->clock_in) - strtotime($date . ' ' . $startTime);
        $late = gmdate('H:i:s', max(0, $totalLateSeconds));

        // Calculate early leaving
        $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($request->clock_out);
        $earlyLeaving = gmdate('H:i:s', max(0, $totalEarlyLeavingSeconds));

        // Calculate overtime
        $overtime = (strtotime($request->clock_out) > strtotime($date . ' ' . $endTime))
            ? gmdate('H:i:s', strtotime($request->clock_out) - strtotime($date . ' ' . $endTime))
            : '00:00:00';

        // Create and save attendance record
        $attendance = new AttendanceEmployee();
        $attendance->employee_id = $request->employee_id;
        $attendance->date = $request->date;
        $attendance->status = 'Present';
        $attendance->clock_in = $request->clock_in . ':00';
        $attendance->clock_out = $request->clock_out . ':00';
        $attendance->late = $late;
        $attendance->early_leaving = $earlyLeaving;
        $attendance->overtime = $overtime;
        $attendance->total_rest = '00:00:00';
        $attendance->created_by = Auth::user()->creatorId();
        $attendance->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Attendance Created',
                'message' => 'Employee attendance record created successfully'
            ]),
            'module_id' => $attendance->id,
            'module_type' => 'attendance',
            'notification_type' => 'Attendance Created'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee attendance successfully created.',
            'data' => $attendance
        ], 201);
    }


    public function updateAttendance(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('edit attendance')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'attendance_id' => 'required|exists:attendance_employees,id',
            'employee_id'   => 'nullable|integer|exists:employees,id',
            'date'          => 'nullable|date',
            'clock_in'      => 'nullable|date_format:H:i',
            'clock_out'     => 'nullable|date_format:H:i|after:clock_in',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Fetch Attendance Record
        $attendance = AttendanceEmployee::where('id', $request->attendance_id)->first();

        if (!$attendance) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance record not found.'
            ], 404);
        }

        // Preserve existing values if not provided
        $attendance->employee_id = $request->employee_id ?? $attendance->employee_id;
        $attendance->date = $request->date ?? $attendance->date;
        $attendance->clock_in = $request->clock_in ? $request->clock_in . ':00' : $attendance->clock_in;
        $attendance->clock_out = $request->clock_out ? $request->clock_out . ':00' : $attendance->clock_out;

        // Get company start and end time
        $startTime = Utility::getValByName('company_start_time');
        $endTime = Utility::getValByName('company_end_time');
        $date = $attendance->date;

        // Calculate late time
        $totalLateSeconds = strtotime($attendance->clock_in) - strtotime($date . ' ' . $startTime);
        $attendance->late = gmdate('H:i:s', max(0, $totalLateSeconds));

        // Calculate early leaving
        $totalEarlyLeavingSeconds = strtotime($date . ' ' . $endTime) - strtotime($attendance->clock_out);
        $attendance->early_leaving = gmdate('H:i:s', max(0, $totalEarlyLeavingSeconds));

        // Calculate overtime
        $attendance->overtime = (strtotime($attendance->clock_out) > strtotime($date . ' ' . $endTime))
            ? gmdate('H:i:s', strtotime($attendance->clock_out) - strtotime($date . ' ' . $endTime))
            : '00:00:00';

        $attendance->save();

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Attendance Updated',
                'message' => 'Employee attendance record updated successfully'
            ]),
            'module_id' => $attendance->id,
            'module_type' => 'attendance',
            'notification_type' => 'Attendance Updated'
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee attendance successfully updated.',
            'data' => $attendance
        ], 200);
    }




    public function deleteAttendance(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:attendance_employees,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if the user has permission
        if (!\Auth::user()->can('delete attendance')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ], 403);
        }

        // Find the Attendance record
        $attendance = AttendanceEmployee::find($request->id);

        // Log the deletion activity
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Attendance Deleted',
                'message' => 'An attendance record was deleted successfully.'
            ]),
            'module_id' => $attendance->id,
            'module_type' => 'attendance',
            'notification_type' => 'Attendance Deleted'
        ];
        addLogActivity($logData);

        // Delete the record
        $attendance->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('Attendance successfully deleted.')
        ], 200);
    }


    public function attendance(Request $request)
    {
        $settings = Utility::settings();

        $employeeId      = !empty(\Auth::user()->employee) ? \Auth::user()->employee->id : 0;
        $todayAttendance = AttendanceEmployee::where('employee_id', '=', $employeeId)->where('date', date('Y-m-d'))->first();
        if (empty($todayAttendance)) {

            $startTime = Utility::getValByName('company_start_time');
            $endTime   = Utility::getValByName('company_end_time');

            $attendance = AttendanceEmployee::orderBy('id', 'desc')->where('employee_id', '=', $employeeId)->where('clock_out', '=', '00:00:00')->first();

            if ($attendance != null) {
                $attendance            = AttendanceEmployee::find($attendance->id);
                $attendance->clock_out = $endTime;
                $attendance->save();
            }

            $date = date("Y-m-d");
            $time = date("H:i:s");

            //late
            $totalLateSeconds = time() - strtotime($date . $startTime);
            $hours            = floor($totalLateSeconds / 3600);
            $mins             = floor($totalLateSeconds / 60 % 60);
            $secs             = floor($totalLateSeconds % 60);
            $late             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

            $checkDb = AttendanceEmployee::where('employee_id', '=', \Auth::user()->id)->get()->toArray();


            if (empty($checkDb)) {
                $employeeAttendance                = new AttendanceEmployee();
                $employeeAttendance->employee_id   = $employeeId;
                $employeeAttendance->date          = $date;
                $employeeAttendance->status        = 'Present';
                $employeeAttendance->clock_in      = $time;
                $employeeAttendance->clock_out     = '00:00:00';
                $employeeAttendance->late          = $late;
                $employeeAttendance->early_leaving = '00:00:00';
                $employeeAttendance->overtime      = '00:00:00';
                $employeeAttendance->total_rest    = '00:00:00';
                $employeeAttendance->created_by    = \Auth::user()->id;

                $employeeAttendance->save();

                return redirect()->back()->with('success', __('Employee Successfully Clock In.'));
            }
            foreach ($checkDb as $check) {

                $employeeAttendance                = new AttendanceEmployee();
                $employeeAttendance->employee_id   = $employeeId;
                $employeeAttendance->date          = $date;
                $employeeAttendance->status        = 'Present';
                $employeeAttendance->clock_in      = $time;
                $employeeAttendance->clock_out     = '00:00:00';
                $employeeAttendance->late          = $late;
                $employeeAttendance->early_leaving = '00:00:00';
                $employeeAttendance->overtime      = '00:00:00';
                $employeeAttendance->total_rest    = '00:00:00';
                $employeeAttendance->created_by    = \Auth::user()->id;

                $employeeAttendance->save();

                return redirect()->back()->with('success', __('Employee Successfully Clock In.'));
            }
        } else {
            return redirect()->back()->with('error', __('Employee are not allow multiple time clock in & clock for every day.'));
        }
    }

    public function bulkAttendance(Request $request)
    {
        if (\Auth::user()->can('create attendance')) {

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('Select Branch', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('Select Department', '');

            $employees = [];
            if (!empty($request->branch) && !empty($request->department)) {
                $employees = Employee::where('created_by', \Auth::user()->creatorId())->where('branch_id', $request->branch)->where('department_id', $request->department)->get();
            } else {
                $employees = Employee::where('created_by', \Auth::user()->creatorId())->where('branch_id', 1)->where('department_id', 1)->get();
            }


            return view('attendance.bulk', compact('employees', 'branch', 'department'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function bulkAttendanceData(Request $request)
    {

        if (\Auth::user()->can('create attendance')) {
            if (!empty($request->branch) && !empty($request->department)) {
                $startTime = Utility::getValByName('company_start_time');
                $endTime   = Utility::getValByName('company_end_time');
                $date      = $request->date;

                $employees = $request->employee_id;
                $atte      = [];

                if (!empty($employees)) {
                    foreach ($employees as $employee) {
                        $present = 'present-' . $employee;
                        $in      = 'in-' . $employee;
                        $out     = 'out-' . $employee;
                        $atte[]  = $present;
                        if ($request->$present == 'on') {

                            $in  = date("H:i:s", strtotime($request->$in));
                            $out = date("H:i:s", strtotime($request->$out));

                            $totalLateSeconds = strtotime($in) - strtotime($startTime);

                            $hours = floor($totalLateSeconds / 3600);
                            $mins  = floor($totalLateSeconds / 60 % 60);
                            $secs  = floor($totalLateSeconds % 60);
                            $late  = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                            //early Leaving
                            $totalEarlyLeavingSeconds = strtotime($endTime) - strtotime($out);
                            $hours                    = floor($totalEarlyLeavingSeconds / 3600);
                            $mins                     = floor($totalEarlyLeavingSeconds / 60 % 60);
                            $secs                     = floor($totalEarlyLeavingSeconds % 60);
                            $earlyLeaving             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);

                            if (strtotime($out) > strtotime($endTime)) {
                                //Overtime
                                $totalOvertimeSeconds = strtotime($out) - strtotime($endTime);
                                $hours                = floor($totalOvertimeSeconds / 3600);
                                $mins                 = floor($totalOvertimeSeconds / 60 % 60);
                                $secs                 = floor($totalOvertimeSeconds % 60);
                                $overtime             = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                            } else {
                                $overtime = '00:00:00';
                            }


                            $attendance = AttendanceEmployee::where('employee_id', '=', $employee)->where('date', '=', $request->date)->first();

                            if (!empty($attendance)) {
                                $employeeAttendance = $attendance;
                            } else {
                                $employeeAttendance              = new AttendanceEmployee();
                                $employeeAttendance->employee_id = $employee;
                                $employeeAttendance->created_by  = \Auth::user()->creatorId();
                            }


                            $employeeAttendance->date          = $request->date;
                            $employeeAttendance->status        = 'Present';
                            $employeeAttendance->clock_in      = $in;
                            $employeeAttendance->clock_out     = $out;
                            $employeeAttendance->late          = $late;
                            $employeeAttendance->early_leaving = ($earlyLeaving > 0) ? $earlyLeaving : '00:00:00';
                            $employeeAttendance->overtime      = $overtime;
                            $employeeAttendance->total_rest    = '00:00:00';
                            $employeeAttendance->save();
                        } else {
                            $attendance = AttendanceEmployee::where('employee_id', '=', $employee)->where('date', '=', $request->date)->first();

                            if (!empty($attendance)) {
                                $employeeAttendance = $attendance;
                            } else {
                                $employeeAttendance              = new AttendanceEmployee();
                                $employeeAttendance->employee_id = $employee;
                                $employeeAttendance->created_by  = \Auth::user()->creatorId();
                            }

                            $employeeAttendance->status        = 'Leave';
                            $employeeAttendance->date          = $request->date;
                            $employeeAttendance->clock_in      = '00:00:00';
                            $employeeAttendance->clock_out     = '00:00:00';
                            $employeeAttendance->late          = '00:00:00';
                            $employeeAttendance->early_leaving = '00:00:00';
                            $employeeAttendance->overtime      = '00:00:00';
                            $employeeAttendance->total_rest    = '00:00:00';
                            $employeeAttendance->save();
                        }
                    }
                } else {
                    return redirect()->back()->with('error', __('Employee not found.'));
                }


                return redirect()->back()->with('success', __('Employee attendance successfully created.'));
            } else {
                return redirect()->back()->with('error', __('Branch & department field required.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function HrmAttendance(Request $request)
    {

        if (isset($_GET['emp_id'])) {
            $userId = $_GET['emp_id'];
        } else {
            $userId = \Auth::id();
        }
        $user = \Auth::user();

        if ($user->type != 'HR' && $user->type != 'super admin' && $user->type != 'Project Manager') {
            echo 'access Denied';
            exit();
            die();
        }

        if (\Auth::user()->can('manage attendance')) {

            $filters = BrandsRegionsBranches();

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('Select Branch', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('Select Department', '');

            if (!\Auth::user()->can('level 1')) {

                $attendanceEmployee = AttendanceEmployee::where('employee_id', $userId);


                if ($request->type == 'monthly' && !empty($request->month)) {
                    $month = date('m', strtotime($request->month));
                    $year  = date('Y', strtotime($request->month));

                    $start_date = date($year . '-' . $month . '-01');
                    $end_date   = date($year . '-' . $month . '-t');

                    $attendanceEmployee->whereBetween(
                        'date',
                        [
                            $start_date,
                            $end_date,
                        ]
                    );
                } elseif ($request->type == 'daily' && !empty($request->date)) {
                    $attendanceEmployee->where('date', $request->date);
                } else {
                    $month      = date('m');
                    $year       = date('Y');
                    $start_date = date($year . '-' . $month . '-01');
                    $end_date   = date($year . '-' . $month . '-t');

                    $attendanceEmployee->whereBetween(
                        'date',
                        [
                            $start_date,
                            $end_date,
                        ]
                    );
                }
                $attendanceEmployee = $attendanceEmployee->get();
            } else {


                $attendanceEmployee = AttendanceEmployee::where('employee_id', $userId);

                if ($request->type == 'monthly' && !empty($request->month)) {
                    $month = date('m', strtotime($request->month));
                    $year  = date('Y', strtotime($request->month));

                    $start_date = date($year . '-' . $month . '-01');
                    $end_date   = date($year . '-' . $month . '-t');

                    $attendanceEmployee->whereBetween(
                        'date',
                        [
                            $start_date,
                            $end_date,
                        ]
                    );
                } elseif ($request->type == 'daily' && !empty($request->date)) {
                    $attendanceEmployee->where('date', $request->date);
                } else {
                    $month      = date('m');
                    $year       = date('Y');
                    $start_date = date($year . '-' . $month . '-01');
                    $end_date   = date($year . '-' . $month . '-t');

                    $attendanceEmployee->whereBetween(
                        'date',
                        [
                            $start_date,
                            $end_date,
                        ]
                    );
                }
                $attendanceEmployee = $attendanceEmployee->get();
            }
            $attendanceUserEmployee = User::find(!empty($request->emp_id) ? $request->emp_id : \Auth::id());

            if (isset($_GET['emp_id'])) {
                $AuthUser = User::find($_GET['emp_id']);
            } else {
                $AuthUser = \Auth::user();
            }

            return view('hrmhome.attendance', compact('AuthUser', 'attendanceUserEmployee', 'attendanceEmployee', 'branch', 'department', 'filters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }





















    public function HrmAttendanceDetail(Request $request)
    {


        $enventgetdate = Carbon::createFromFormat('l, d/m/Y', $request->date)->format('d-m-Y');

        $user = \Auth::user();

        if ($user->type != 'HR' && $user->type != 'super admin' && $user->type != 'Project Manager') {
            echo 'access Denied';
            exit();
            die();
        }

        if (\Auth::user()->can('manage attendance')) {

            $filters = BrandsRegionsBranches();

            $branch = Branch::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $branch->prepend('Select Branch', '');

            $department = Department::where('created_by', \Auth::user()->creatorId())->get()->pluck('name', 'id');
            $department->prepend('Select Department', '');

            $attendanceEmployee = AttendanceEmployee::where('date', $enventgetdate)->where('employee_id', $request->userId)->first();
        }

        $eventvalue = $request->eventvalue;
        $html = view('hrmhome.attendanceDetail', compact('eventvalue', 'enventgetdate', 'attendanceEmployee', 'branch', 'department', 'filters'))->render();

        return json_encode([
            'status' => 'success',
            'html' => $html
        ]);
    }
}
