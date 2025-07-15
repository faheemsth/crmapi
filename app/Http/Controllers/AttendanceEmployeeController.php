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
use Illuminate\Pagination\LengthAwarePaginator;

class AttendanceEmployeeController extends Controller
{
  
    public function viewAttendance(Request $request)
    {

          $validator = Validator::make($request->all(), [
            'emp_id' => 'nullable|integer|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ], 422);  // 422 Unprocessable Entity status
        }
        if($request->emp_id){
            $employee = User::find($request->emp_id);
            if(!$employee){
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);  // 404 Not Found status
            }
        }else{
            $employee = Auth::user();
        }
 
        $employeeId = $employee->id;
        $branchId = $employee->branch_id;

        $branch = Branch::find($branchId);
        $timezone = $branch->timezone ?? 'Asia/Karachi';

        if ($branch->timezone == '') {
            return response()->json([
                'success' => false,
                'message' => 'The branch timezone is not configured. Please contact the administrator for assistance.',
                'errors' => 'The branch timezone is not configured. Please contact the administrator for assistance.'
            ], 422);  // 422 Unprocessable Entity status
        }
        //date_default_timezone_set( $timezone);

        $startDate = date('Y-m-d', strtotime($request->input('start_date', now()->startOfMonth())));
        $endDate = date('Y-m-d', strtotime($request->input('end_date', now())));

        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        $today = now()->format('Y-m-d');

        $attendanceRecords = AttendanceEmployee::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $branch = Branch::find($branchId);
        $timezone = $branch->timezone ?? 'Asia/Karachi';

        $timeInBranch = Carbon::now($timezone);
        $timeInUTC = Carbon::now('UTC');
        $timezoneDifference = ($timeInBranch->getOffset() - $timeInUTC->getOffset()) / 3600;

        $attendanceData = [];

        foreach ($period as $date) {
            $formattedDate = $date->format('Y-m-d');
            $dayOfWeek = $date->format('l');

            if ($formattedDate > $today) continue;

            $attendance = $attendanceRecords->firstWhere('date', $formattedDate);

            if (!$attendance) {
                $attendanceData[] = [
                    'date' => $formattedDate,
                    'status' => in_array($dayOfWeek, [ 'Sunday']) ? 'Holiday' : 'Absent',
                    'clock_in' => null,
                    'clock_out' => null,
                    'late' => null,
                    'early_punch_out' => null,
                    'hours_worked' => null,
                    'early_check_out_reason' => null,
                ];
            } else {
                $clockIn = Carbon::parse($attendance->clock_in)->addHours($timezoneDifference);
                if ($attendance->clock_out !== '00:00:00') {
                    $clockOut = Carbon::parse($attendance->clock_out)->addHours($timezoneDifference);
                } else {
                    $clockOut = Carbon::parse($attendance->clock_out)->addHours(0);
                }


                $hoursWorked = $clockOut->diff($clockIn);
                $hoursWorkedFormatted = $hoursWorked->format('%H:%I:%S');

                $earlyPunchOut = $clockOut->diffInHours($clockIn) < $branch->shift_time ? 'Yes' : 'No';
                $status = $earlyPunchOut === 'Yes' ? 'Early Punch Out' : 'Present';

                $attendanceData[] = [
                    'date' => $formattedDate,
                    'status' => $status,
                    'clock_in' => $clockIn->format('H:i:s'),
                    'clock_out' => $clockOut->format('H:i:s'),
                    'late' => $attendance->late,
                    'early_punch_out' => $earlyPunchOut,
                    'hours_worked' => $hoursWorkedFormatted,
                    'early_check_out_reason' => $attendance->earlyCheckOutReason ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $attendanceData,
        ], 200);
    }

//old
    // public function getAttendances(Request $request)
    // {
    //     try {
    //         // Permission check
    //         if (!Auth::user()->can('manage attendance')) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => __('Permission denied.')
    //             ], 403);
    //         }

    //         // Validate request parameters
    //         $validator = Validator::make($request->all(), [
    //             'perPage' => 'nullable|integer|min:1',
    //             'page' => 'nullable|integer|min:1',
    //             'search' => 'nullable|string',
    //             'brand_id' => 'nullable|integer|exists:brands,id',
    //             'region_id' => 'nullable|integer|exists:regions,id',
    //             'branch_id' => 'nullable|integer|exists:branches,id',
    //             'type' => 'nullable|in:monthly,daily',
    //             'month' => 'nullable|date_format:Y-m',
    //             'date' => 'nullable|date_format:Y-m-d',
    //             'download_csv' => 'nullable|boolean',
    //             'status' => 'nullable|integer|in:1,2,3,4',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'errors' => $validator->errors()
    //             ], 422);
    //         }

    //         $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
    //         $page = $request->input('page', 1);

    //         // Retrieve employees
    //        $employees = Employee::with(['user'])
    //             ->when($request->filled('brand_id'), function ($query) use ($request) {
    //                 $query->whereHas('user', fn($q) => $q->where('brand_id', $request->brand_id));
    //             })
    //             ->when($request->filled('emp_id'), function ($query) use ($request) {
    //                 $query->whereHas('user', function ($q) use ($request) {
    //                     $q->where('id', $request->emp_id);
    //                 });
    //             }) // ✅ Properly closed here
    //             ->when($request->filled('tag_id'), function ($query) use ($request) {
                   
    //                 $query->whereHas('user', function ($q) use ($request) {
    //                     $q->where('tag_ids', $request->tag_id);
    //                 });

                    
    //             })
    //             ->get();

                

    //         $data = [];

    //         foreach ($employees as $employee) {
    //             if (!$employee->user) {
    //                 continue; // Skip if the user relationship is missing
    //             }

    //             $attendanceRange = AttendanceEmployee::where('employee_id', $employee->user_id)
    //                 ->selectRaw('MIN(date) as first_date, MAX(date) as last_date')
    //                 ->first();

    //             if (!$attendanceRange || !$attendanceRange->first_date) {
    //                 continue;
    //             }

    //             // Determine the start date
    //             if ($request->filled('start_date')) {
    //                 $startDate = Carbon::parse($request->start_date);
    //             } else {
    //                 $startDate = Carbon::parse($attendanceRange->first_date);
    //             }

    //             // Determine the end date
    //             if ($request->filled('end_date')) {
    //                 $endDate = Carbon::parse($request->end_date);
    //             } else {
    //                 $endDate = now()->endOfMonth();
    //             }

    //             $existingAttendances = AttendanceEmployee::where('employee_id', $employee->user_id)
    //                 ->whereBetween('date', [$startDate, $endDate])
    //                 ->get()
    //                 ->keyBy('date');

    //             while ($startDate <= $endDate) {
    //                 $dateStr = $startDate->format('Y-m-d');
    //                 $attendance = $existingAttendances[$dateStr] ?? null;

    //                 $shiftDurationInSeconds = $employee?->user?->branch?->shift_time * 60 * 60;

    //                 $data[] = [
    //                     'employee_id' => $employee->user_id,
    //                     'employee_name' => $employee->user->name,
    //                     'brand_id' => $employee->user->brand_id,
    //                     'region_id' => $employee->user->region_id,
    //                     'branch_id' => $employee->user->branch_id,
    //                     'date' => $dateStr,
    //                     'clock_in' => $attendance?->clock_in ?? "00:00:00",
    //                     'earlyCheckOutReason' => $attendance?->earlyCheckOutReason ?? null,
    //                     'clock_out' => $attendance?->clock_out ?? "00:00:00",
    //                     'worked_hours' => $attendance && $attendance->clock_in && $attendance->clock_out
    //                         ? gmdate("H:i:s", Carbon::parse($attendance->clock_out)->diffInSeconds(Carbon::parse($attendance->clock_in)))
    //                         : "00:00:00",
    //                     'status' => $attendance && $attendance->clock_in && $attendance->clock_out
    //                         ? (Carbon::parse($attendance->clock_out)->diffInSeconds(Carbon::parse($attendance->clock_in)) < $shiftDurationInSeconds
    //                             ? 'Early Leaving'
    //                             : 'Present')
    //                         : 'Absent',
    //                     'late' => $attendance?->late ?? "00:00:00",
    //                     'early_leaving' => $attendance?->early_leaving ?? "00:00:00",
    //                     'overtime' => $attendance?->overtime ?? "00:00:00",
    //                 ];

    //                 $startDate->addDay();
    //             }
    //         }
    //         // Sort data in descending order by date
    //         usort($data, function ($a, $b) {
    //             return strcmp($b['date'], $a['date']);
    //         });
    //         // usort($data, function ($a, $b) {
    //         //     return strcmp($a['date'], $b['date']);
    //         // });


    //         // Apply filters
    //         if ($request->filled('brand')) {
    //             $data = array_filter($data, fn($record) => $record['brand_id'] == $request->brand);
    //         }

    //         if ($request->filled('region_id')) {
    //             $data = array_filter($data, fn($record) => $record['region_id'] == $request->region_id);
    //         }

    //         if ($request->filled('branch_id')) {
    //             $data = array_filter($data, fn($record) => $record['branch_id'] == $request->branch_id);
    //         }

    //         if ($request->filled('date')) {
    //             $data = array_filter($data, fn($record) => $record['date'] == $request->date);
    //         }

    //         if ($request->filled('search')) {
    //             $search = $request->input('search');
    //             $data = array_filter($data, fn($record) => str_contains(strtolower($record['employee_name']), strtolower($search)));
    //         }

    //         if ($request->filled('status')) {
    //             $statusMap = [1 => 'Present', 2 => 'Absent', 3 => 'Early Leaving'];
    //             if ($request->status != "4") {
    //                 $statusFilter = $statusMap[$request->status] ?? 'Present';
    //                 $data = array_filter($data, fn($record) => $record['status'] === $statusFilter);
    //             }
    //         } else {
    //             $statusFilter = 'Present';
    //             $data = array_filter($data, fn($record) => $record['status'] === $statusFilter);
    //         }

    //         if ($request->input('download_csv')) {
    //             $csvFileName = 'Attendance_' . time() . '.csv';
    //             $headers = [
    //                 'Content-Type' => 'text/csv',
    //                 'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
    //             ];
    //             $callback = function () use ($data) {
    //                 $file = fopen('php://output', 'w');
    //                 fputcsv($file, [
    //                     'ID',
    //                     'Employee',
    //                     'Date',
    //                     'Status',
    //                     'Clock In',
    //                     'Clock Out',
    //                     'Late',
    //                     'Early Leaving',
    //                     'Overtime',
    //                 ]);
    //                 foreach ($data as $row) {
    //                     fputcsv($file, array_values($row));
    //                 }
    //                 fclose($file);
    //             };
    //             return response()->stream($callback, 200, $headers);
    //         }

    //         // Paginate data
    //         $offset = ($page - 1) * $perPage;
    //         $paginatedData = array_slice($data, $offset, $perPage);

    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $paginatedData,
    //             'current_page' => $page,
    //             'last_page' => ceil(count($data) / $perPage),
    //             'total_records' => count($data),
    //             'per_page' => $perPage,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }



    public function getAttendances(Request $request)
    {
        try {
            if (!Auth::user()->can('manage attendance')) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('Permission denied.')
                ], 403);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'perPage' => 'nullable|integer|min:1',
                'page' => 'nullable|integer|min:1',
                'search' => 'nullable|string',
                'brand_id' => 'nullable|integer|exists:users,id',
                'region_id' => 'nullable|integer|exists:regions,id',
                'branch_id' => 'nullable|integer|exists:branches,id',
                'type' => 'nullable|in:monthly,daily',
                'month' => 'nullable|date_format:Y-m',
                'date' => 'nullable|date_format:Y-m-d',
                'download_csv' => 'nullable|boolean',
                'status' => 'nullable|integer|in:1,2,3,4',
                'tag_ids' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
            $page = $request->input('page', 1);

            $tagIds = $request->filled('tag_ids') ? explode(',', $request->tag_ids) : [];

            // Paginate employees
            $employeeQuery = Employee::with(['user.branch'])
                    ->when($request->filled('brand_id'), fn($q) =>
                        $q->whereHas('user', fn($uq) => $uq->where('brand_id', $request->brand_id)))
                    ->when($request->filled('region_id'), fn($q) =>
                        $q->whereHas('user', fn($uq) => $uq->where('region_id', $request->region_id)))
                    ->when($request->filled('branch_id'), fn($q) =>
                        $q->whereHas('user', fn($uq) => $uq->where('branch_id', $request->branch_id)))
                    ->when($request->filled('emp_id'), fn($q) =>
                        $q->whereHas('user', fn($uq) => $uq->where('id', $request->emp_id)))
                    ->when(!empty($tagIds), function ($q) use ($tagIds) {
                        $q->whereHas('user', function ($uq) use ($tagIds) {
                            $uq->where(function($innerQuery) use ($tagIds) {
                                foreach ($tagIds as $tagId) {
                                    $innerQuery->orWhereRaw("FIND_IN_SET(?, tag_ids)", [$tagId]);
                                }
                            });
                        });
                    })
                    ->when($request->filled('search'), function ($q) use ($request) {
                        $q->whereHas('user', function ($uq) use ($request) {
                            $uq->where('name', 'like', '%' . $request->search . '%');
                        });
                    });

            
            

            $employees = $employeeQuery->paginate($perPage, ['*'], 'page', $page);

            

            // Define date range
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date) : now()->endOfMonth();

            // Limit range to 31 days for performance
            if ($endDate->diffInDays($startDate) > 31) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Date range too large. Limit to 31 days.'
                ], 422);
            }

            $data = [];

            foreach ($employees as $employee) {
                $user = $employee->user;
                if (!$user) continue;

                $shiftSeconds = ($user->branch->shift_time ?? 0) * 3600;

                $attendances = AttendanceEmployee::where('employee_id', $user->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get()
                    ->keyBy('date');

                $current = $startDate->copy();
                $today = Carbon::today(); // Get current date
                while ($current <= $endDate) {
                    if ($current > $today) {
                            break;
                        }
                    $date = $current->format('Y-m-d');
                    $attendance = $attendances[$date] ?? null;

                    $clockIn = $attendance?->clock_in ?? '00:00:00';
                    $clockOut = $attendance?->clock_out ?? '00:00:00';

                    $workedSeconds = ($attendance && $attendance->clock_in && $attendance->clock_out)
                        ? Carbon::parse($clockOut)->diffInSeconds(Carbon::parse($clockIn))
                        : 0;

                    $data[] = [
                        'employee_id' => $user->id,
                        'employee_name' => $user->name,
                        'brand_id' => $user->brand_id,
                        'region_id' => $user->region_id,
                        'branch_id' => $user->branch_id,
                        'date' => $date,
                        'clock_in' => $clockIn,
                        'earlyCheckOutReason' => $attendance?->earlyCheckOutReason,
                        'clock_out' => $clockOut,
                        'worked_hours' => gmdate('H:i:s', $workedSeconds),
                        'status' => $workedSeconds === 0 ? 'Absent' : ($workedSeconds < $shiftSeconds ? 'Early Leaving' : 'Present'),
                        'late' => $attendance?->late ?? "00:00:00",
                        'early_leaving' => $attendance?->early_leaving ?? "00:00:00",
                        'overtime' => $attendance?->overtime ?? "00:00:00",
                    ];

                    $current->addDay();
                }
            }

            // Sort by date desc
            usort($data, fn($a, $b) => strcmp($b['date'], $a['date']));

            // Filter status if required
            if ($request->filled('status') && $request->status != 4) {
                $map = [1 => 'Present', 2 => 'Absent', 3 => 'Early Leaving'];
                $status = $map[$request->status] ?? 'Present';
                $data = array_filter($data, fn($d) => $d['status'] === $status);
            }else{
               $data = array_filter($data, fn($d) => $d['status'] === 'Present'); 
            }

            // // Filter search
            // if ($request->filled('search')) {
            //     $search = strtolower($request->search);
            //     $data = array_filter($data, fn($d) => str_contains(strtolower($d['employee_name']), $search));
            // }

            // If CSV download
            if ($request->input('download_csv')) {
                $filename = 'Attendance_' . now()->timestamp . '.csv';
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ];
                return response()->stream(function () use ($data) {
                    $f = fopen('php://output', 'w');
                    fputcsv($f, ['ID', 'Employee', 'Date', 'Status', 'Clock In', 'Clock Out', 'Late', 'Early Leaving', 'Overtime']);
                    foreach ($data as $row) {
                        fputcsv($f, array_values($row));
                    }
                    fclose($f);
                }, 200, $headers);
            }

            $perPage = $request->input('perPage', env('RESULTS_ON_PAGE', 50));
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($data, $offset, $perPage);

            return response()->json([
                'status' => 'success',
                'data' => $paginatedData,
                'current_page' => $page,
                'last_page' => ceil(count($data) / $perPage),
                'total_records' => count($data),
                'per_page' => $perPage,
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
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
