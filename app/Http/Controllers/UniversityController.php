<?php

namespace App\Http\Controllers;
use Auth;
use App\Models\Course;
use App\Models\CourseDuration;
use App\Models\CourseLevel;
use App\Models\Deal;
use App\Models\DealApplication;
use App\Models\InstituteCategory;
use App\Models\Stage;
use App\Models\University;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UniversityController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getUniversities(Request $request)
    {
        // Check permission
        if (!Auth::user()->type == 'super admin' && !Gate::check('show university') && !Gate::check('manage university')) {
            return response()->json([
                'status' => false,
                'message' => __('Permission Denied.'),
            ], 403);
        }

        // European countries for filtering
        $europeanCountries = [
            "Albania", "Andorra", "Armenia", "Austria", "Azerbaijan",
            "Belarus", "Belgium", "Bosnia and Herzegovina", "Bulgaria",
            "Croatia", "Cyprus", "Czech Republic", "Denmark", "Estonia",
            "Finland", "France", "Georgia", "Germany", "Greece",
            "Hungary", "Iceland", "Ireland", "Italy", "Kazakhstan",
            "Kosovo", "Latvia", "Liechtenstein", "Lithuania",
            "Luxembourg", "Malta", "Moldova", "Monaco", "Montenegro",
            "Netherlands", "North Macedonia", "Norway", "Poland",
            "Portugal", "Romania", "Russia", "San Marino", "Serbia",
            "Slovakia", "Slovenia", "Spain", "Sweden", "Switzerland",
            "Turkey", "Ukraine", "Vatican City"
        ];

        // Pagination control
        $perPage = $request->get('num_results_on_page', env('RESULTS_ON_PAGE', 50));
        $page = $request->get('page', 1);
        $start = ($page - 1) * $perPage;

        // Build query
        $query = University::query();

        // Filters
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->filled('country')) {
            if ($request->country === 'Europe') {
                $query->whereIn('country', $europeanCountries);
            } else {
                $query->where('country', 'like', '%' . $request->country . '%');
            }
        }

        if ($request->filled('city')) {
            $query->where('campuses', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('note')) {
            $query->where('note', 'like', '%' . $request->note . '%');
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', 'like', '%' . $request->created_by . '%');
        }

        // Retrieve paginated data
        $universities = $query
            ->skip($start)
            ->take($perPage)
            ->orderBy('id', 'desc')
            ->get();

        // University statistics grouped by country
        $universityStatsByCountries = University::selectRaw('count(id) as total_universities, country')
            ->groupBy('country')
            ->get();

        $statuses = [];
        foreach ($universityStatsByCountries as $u) {
            $statuses[$u->country] = $u->total_universities;
        }

        $customOrder = [
            "United States", "Canada", "United Kingdom", "Australia",
            "United Arab Emirates", "Hungary", "Ireland", "Malta",
            "Poland", "Germany", "Holand", "China", "Malaysia",
            "Turkey", "Samoa,Djibouti"
        ];

        // Reorder statuses
        $sortedStatuses = [];
        foreach ($customOrder as $country) {
            if (isset($statuses[$country])) {
                $sortedStatuses[$country] = $statuses[$country];
            }
        }

        // Final response
        return response()->json([
            'status' => 'success',
            'message' => 'University list retrieved successfully.',
            'data' => [
                'universities' => $universities,
                'statuses' => $sortedStatuses,
                'total_records' => $query->count()
            ]
        ]);
    }


    public function download(){

        $universities = University::when(!empty($_GET['name']), function ($query) {
            return $query->where('name', 'like', '%' . $_GET['name'] . '%');
        })
        ->when(!empty($_GET['country']), function ($query) {
            return $query->where('country', 'like', '%' . $_GET['country'] . '%');
        })

        ->when(!empty($_GET['city']), function ($query) {
            return $query->where('city', 'like', '%' . $_GET['city'] . '%');
        })

        ->when(!empty($_GET['note']), function ($query) {
            return $query->where('note', 'like', '%' . $_GET['note'] . '%');
        })

        ->when(!empty($_GET['created_by']), function ($query) {
            return $query->where('created_by', 'like', '%' . $_GET['created_by'] . '%');
        })

        ->get();

        $users = User::get()->pluck('name', 'id');
        $universityStatsByCountries = University::selectRaw('count(id) as total_universities, country')
            ->groupBy('country')
            ->get();


        //dd($universities);

        $header = [
            'Sr.No.',
            'Institutes',
            'Campuse',
            'Intake Months',
            'Territory',
            'Band',
            'Resource',
            'Application Method'
        ];

        $data = [];
        foreach($universities as $key => $university){
            $data[] = [
                $key+1,
                $university->name,
                $university->campuses,
                $university->intake_months,
                $university->territory,
                $users[$university->company_id] ?? '',
                $university->resource_drive_link,
                $university->application_method_drive_link
            ];
        }
        downloadCSV($header, $data, 'toolkit.csv');
        return;
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
        if (\Auth::user()->can('create university')) {

            //getting countries
            $countries = countries();

            //months
            $months = months();

            //getting companies
            $companies = FiltersBrands();

            $categories = InstituteCategory::pluck('name', 'id')->prepend('Select Category', 0);

            $data = [
                'countries' => $countries,
                'companies' => $companies,
                'months'  => $months,
                'categories' => $categories
            ];

            return view('university.create', $data);
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function addUniversities(Request $request)
    {
        if (!Auth::user()->can('create university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:200',
            'country' => 'required|array|min:1',
            'country.*' => 'required|string|max:200',
            'city' => 'nullable|max:200',
            'months' => 'required|array|min:1',
            'months.*' => 'required|string',
            'territory' => 'nullable|array|min:1',
            'territory.*' => 'nullable|string',
            'company_id' => 'nullable|exists:users,id',
            'rank_id' => 'required|exists:university_ranks,id',
            'phone' => 'nullable|max:20',
            'note' => 'nullable|string',
            'institution_link' => 'nullable|string',
            'resource_drive_link' => 'nullable|string',
            'application_method_drive_link' => 'nullable|string',
            'category_id' => 'nullable|exists:institute_categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $university = new University();
        $university->name = $request->name;
        $university->company_id = 0;
        $university->country = implode(',', $request->country);
        $university->city = $request->city;
        $university->campuses = $request->campuses;
        $university->rank_id = $request->rank_id;
        $university->phone = $request->phone;
        $university->institution_link = $request->institution_link;
        $university->note = $request->note;
        $university->intake_months = implode(',', $request->months);
        if($request->territory!=''){
            $university->territory = implode(',', $request->territory);
        }

        $university->company_id = $request->company_id;
        $university->resource_drive_link = $request->resource_drive_link;
        $university->application_method_drive_link = $request->application_method_drive_link;
        $university->institute_category_id = $request->category_id;
        $university->created_by = Auth::user()->id;
        $university->save();

        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'University Created',
                'message' => 'University Created successfully'
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'University created successfully.',
            'data' => $university
        ]);
    }


    public function updateUniversities(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'rank_id' => 'required|exists:university_ranks,id',
            'name' => 'required|max:200',
            'country' => 'required|array|min:1',
            'country.*' => 'required|string|max:200',
            'city' => 'required|max:200',
            'months' => 'required|array|min:1',
            'months.*' => 'required|string',
            'territory' => 'required|array|min:1',
            'territory.*' => 'required|string',
            'company_id' => 'required|exists:users,id',
            'phone' => 'nullable|max:20',
            'note' => 'nullable|string',
            'institution_link' => 'nullable|string',
            'resource_drive_link' => 'nullable|string',
            'application_method_drive_link' => 'nullable|string',
            'category_id' => 'nullable|exists:institute_categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        if (!Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }

        $university = University::where('id', $request->id)->first();
        $originalData = $university->toArray();

        // Update fields
        $university->name = $request->name;
        $university->country = implode(',', $request->country);
        $university->city = $request->city;
        $university->campuses = $request->city;
        $university->rank_id = $request->rank_id;
        $university->phone = $request->phone;
        $university->institution_link = $request->institution_link;
        $university->note = $request->note;
        $university->intake_months = implode(',', $request->months);
        $university->territory = implode(',', $request->territory);
        $university->company_id = $request->company_id;
        $university->resource_drive_link = $request->resource_drive_link;
        $university->application_method_drive_link = $request->application_method_drive_link;
        $university->institute_category_id = $request->category_id;
        $university->save();

        // Log changed fields only
        $changes = [];
        foreach ($originalData as $field => $oldValue) {
            if ($university->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'University Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $university->id,
                'module_type' => 'university',
                'notification_type' => 'University Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University updated successfully.',
            'data' => $university
        ]);
    }


    public function updateAboutUniversity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
            'territory' => 'required|array|min:1',
            'agency' => 'required|string',
            'territory.*' => 'required|string',
            'campuses' => 'required|array|min:1',
            'campuses.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        if (!Auth::user()->can('edit university')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }

        $university = University::where('id', $request->id)->first();
        $originalData = $university->toArray();

        // Update fields
        $university->territory = implode(',', $request->territory);
        $university->campuses = implode(',', $request->campuses);
        $university->agency = $request->agency;
        $university->save();

        // Log changed fields only
        $changes = [];
        foreach ($originalData as $field => $oldValue) {
            if ($university->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field
                ];
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'University Updated',
                    'message' => 'Fields updated successfully',
                    'changes' => $changes
                ]),
                'module_id' => $university->id,
                'module_type' => 'university',
                'notification_type' => 'University Updated'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'University about updated successfully.',
            'data' => $university
        ]);
    }

    public function deleteUniversities(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Check permission
        if (!\Auth::user()->can('delete university')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Find the university
        $university = University::find($request->id);

        // Log the deletion
        $logData = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'University Deleted',
                'message' => 'University deleted successfully.',
                'changes' => $university
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University Deleted'
        ];
        addLogActivity($logData);

        // Delete the record
        $university->delete();

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => __('University successfully deleted!')
        ], 200);
    }


    public function universityDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $id =    $request->id;
        $university = University::findOrFail($id);

        // related applications
        $applications = DealApplication::where('university_id', $id)->get();

        // related admissions
        $deals = Deal::where('university_id', $id)->get();

        $stages = Stage::pluck('name', 'id')->toArray();
        $organizations = User::where('type', 'organization')->pluck('name', 'id')->toArray();


        $course = Course::where('university_id', $id);

        if (\Auth::user()->type == 'super admin') {
            $courses = $course->get();
        } else {
            $courses = $course->where('created_by', \Auth::user()->id)->get();
        }

        return response()->json([
            'status' => 'success',
            'university' => $university,
            'applications' => $applications,
            'deals' => $deals,
            'stages' => $stages,
            'organizations' => $organizations,
            'courses' => $courses
        ]);
    }


    public function getIntakeMonths()
    {
        $id = $_GET['id'];
        $university = University::where('id', $id)->first();
        $courses = Course::where('university_id', $id)->get();
        $intake_html = '';
        $course_html = '';
        $intake_year_html= '';

        $monthNames = [
            'JAN' => 'January',
            'FEB' => 'February',
            'MAR' => 'March',
            'APR' => 'April',
            'MAY' => 'May',
            'JUN' => 'June',
            'JUL' => 'July',
            'AUG' => 'August',
            'SEP' => 'September',
            'OCT' => 'October',
            'NOV' => 'November',
            'DEC' => 'December'
        ];

        $intake_month = University::where('id', $id)
            ->whereNotNull('intake_months')
            ->pluck('intake_months')
            ->flatMap(function ($campusString) use ($monthNames) {
                return array_map(function ($monthAbbr) use ($monthNames) {
                    return $monthNames[trim($monthAbbr)] ?? $monthAbbr;
                }, explode(',', $campusString));
            })
            ->toArray();

        $intake_html = '<select name="intake_month" class="form form-control select2 validationSideColor" id="intake_month" ' . ($university->status == '1' ? 'disabled' : '') . '>';
        $intake_html .= '<option value="">Select Month</option>';
        if (!empty($intake_month)) {
            foreach ($intake_month as $intake) {
                $intake_html .= '<option value="' . $intake . '"> ' . $intake . ' </option>';
            }
        }
        $intake_html .= '</select>';

        $course_html = '<select name="course" class="form form-control select2 validationSideColor" id="course_id">';
        $course_html .= '<option value="">Select Course</option>';
        if (!empty($courses)) {
            foreach ($courses as $key => $course) {
                $course_html .= '<option value="' . $course->id.'"> ' . $course->name . ' - ' . $course->campus . ' - ' . $course->intake_month . ' - ' . $course->intakeYear . ' (' . $course->duration . ')</option>';
            }
        }
        $course_html .= '</select>';



        $intake_year_html = '<select name="intakeYear" class="form form-control select2 validationSideColor" id="intakeYear" ' . ($university->status == '1' ? 'disabled' : '') . '>';
        $intake_year_html .= '<option value="">Select Year</option>';

        // Check if $campusfetch is not null before accessing intakeYear
        if (!empty(intakeYear())) {
            foreach (intakeYear() as $intakeYear) {
                $intake_year_html .= '<option value="' . $intakeYear . '"> ' . $intakeYear . ' </option>';
            }
        }
        $intake_year_html .= '</select>';

        return json_encode([
            'status' => 'success',
            'university_status' => $university->status,
            'intake_html' => $intake_html,
            'course_html' => $course_html,
            'intake_year_html' => $intake_year_html
        ]);
    }

    public function SaveToggleCourse(Request $request)
    {
        $University = University::find($request->id);
        if ($University) {
            $University->status = $request->status;
            $University->save();

            return json_encode([
                'status' => 'success',
                'message' => 'Courses successfully updated!',
            ]);
        }
    }

    public function updateUniversityStatus(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        'university_id' => 'required|exists:universities,id',
        'status' => 'required|in:0,1' // Assuming 0 or 1 as status values
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 400);
    }

    // Check permission
    if (!\Auth::user()->can('edit university')) {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 403);
    }

    // Find university
    $university = University::find($request->university_id);

    if (!$university) {
        return response()->json([
            'status' => 'error',
            'message' => __('University not found.')
        ], 404);
    }

    // Update status
    $university->status = $request->status;
    $university->save();

    // Log activity
    $logData = [
        'type' => 'info',
        'note' => json_encode([
            'title' => 'University Status Updated',
            'message' => 'University status changed to ' . $request->status,
        ]),
        'module_id' => $university->id,
        'module_type' => 'university',
        'notification_type' => 'University Updated'
    ];
    addLogActivity($logData);

    // Success response
    return response()->json([
        'status' => 'success',
        'message' => __('University successfully updated!')
    ], 200);
}

    public function updateUniversityCourseStatus(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        'university_id' => 'required|exists:universities,id',
        'status' => 'required|in:0,1' // Assuming 0 or 1 as status values
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 400);
    }

    // Check permission
    if (!\Auth::user()->can('edit university')) {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 403);
    }

    // Find university
    $university = University::find($request->university_id);

    if (!$university) {
        return response()->json([
            'status' => 'error',
            'message' => __('University not found.')
        ], 404);
    }

    // Update status
    $university->uni_status = $request->status;
    $university->save();

    // Log activity
    $logData = [
        'type' => 'info',
        'note' => json_encode([
            'title' => 'University course Status Updated',
            'message' => 'University status changed to ' . $request->status,
        ]),
        'module_id' => $university->id,
        'module_type' => 'university',
        'notification_type' => 'University Updated'
    ];
    addLogActivity($logData);

    // Success response
    return response()->json([
        'status' => 'success',
        'message' => __('University successfully updated!')
    ], 200);
}
    public function updateUniversityMOIStatus(Request $request)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        'university_id' => 'required|exists:universities,id',
        'status' => 'required|in:0,1' // Assuming 0 or 1 as status values
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => 'error',
            'errors' => $validator->errors(),
        ], 400);
    }

    // Check permission
    if (!\Auth::user()->can('edit university')) {
        return response()->json([
            'status' => 'error',
            'message' => __('Permission Denied.')
        ], 403);
    }

    // Find university
    $university = University::find($request->university_id);

    if (!$university) {
        return response()->json([
            'status' => 'error',
            'message' => __('University not found.')
        ], 404);
    }

    // Update status
    $university->moi_status = $request->status;
    $university->save();

    // Log activity
    $logData = [
        'type' => 'info',
        'note' => json_encode([
            'title' => 'University course Status Updated',
            'message' => 'University status changed to ' . $request->status,
        ]),
        'module_id' => $university->id,
        'module_type' => 'university',
        'notification_type' => 'University Updated'
    ];
    addLogActivity($logData);

    // Success response
    return response()->json([
        'status' => 'success',
        'message' => __('University successfully updated!')
    ], 200);
}

}
