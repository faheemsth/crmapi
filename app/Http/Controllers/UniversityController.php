<?php

namespace App\Http\Controllers;
use App\Models\Country;
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
                    $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
                    $page = $request->input('page', 1);

                    // Build query
                    $query = University::query();

                    // Filters
                    if ($request->filled('name')) {
                        $query->where('name', 'like', '%' . $request->name . '%');
                    }

                    if ($request->filled('city')) {
                        $query->where('campuses', 'like', '%' . $request->city . '%');
                    }

                    if ($request->filled('country')) {
                        if ($request->country === 'Europe') {
                            $query->whereIn('country', $europeanCountries);
                        } else {
                            $country = Country::find($request->country);
                            if (!empty($country)) {
                                $query->where('country', 'like', '%' . $country->name . '%');
                            } else {
                                $query->where('country', 'like', '%' . $request->country . '%');
                            }
                        }
                    }

                    if ($request->filled('rank_id')) {
                        $query->where('rank_id', 'like', '%' . $request->rank_id . '%');
                    }

            if ($request->filled('intake_months')) {
                $query->where(function($subQuery) use ($request) {
                    foreach ($request->intake_months as $month) {
                        $subQuery->orWhereRaw("FIND_IN_SET(?, intake_months)", [trim($month)]);
                    }
                });
            }




                    // Retrieve paginated data

                    $universities = $query->orderBy('rank_id', 'DESC')->orderBy('name', 'ASC')
                    ->paginate($perPage, ['*'], 'page', $page);

                    // University statistics grouped by country
                    $universityStatsByCountries = University::selectRaw('count(id) as total_universities, country')
                        ->groupBy('country')
                        ->get();

                    $statuses = [];
                    foreach ($universityStatsByCountries as $u) {
                        $statuses[$u->country] = array(
                                'country_code'=>$u->country_code,
                                'count'=>$u->total_universities
                        );
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
                            'number_of_tiles' => 5,
                            'statuses' => $sortedStatuses,
                            'universities' => $universities->items(),
                            'current_page' => $universities->currentPage(),
                            'last_page' => $universities->lastPage(),
                            'total_records' => $universities->total(),
                            'per_page' => $universities->perPage(),
                        ]
                    ]);
    }
    public function getPublicUniversities(Request $request)
    {
        $universities = University::select([
            'id', 
            'name', 
            'country', 
            'commission', 
            'notes',
            'created_by',
            'rank_id',
            'level_id',
            'payment_type_id',
            'pay_out_id'
        ])
        ->with([
            'createdBy:id,name',
            'rank:id,name',
            'ToolkitLevel:id,name',
            'PaymentType:id,name',
            'InstallmentPayOut:id,name'
        ])
        ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'University list retrieved successfully.',
            'data' => [
                'universities' => $universities,
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
            'territory' => 'required|array|min:1',
            'territory.*' => 'required|string',
            'company_id' => 'nullable|exists:users,id',
           // 'rank_id' => 'required|exists:university_ranks,id',
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
        $university->status = '1';
        $university->name = $request->name;
        $university->company_id = 1;
        $university->country = implode(',', $request->country);
        $university->city = $request->city;
        $university->campuses = $request->campuses;
      //  $university->rank_id = $request->rank_id;
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
            'type' => 'success',
            'note' => json_encode([
                'title' => $university->name. '  created',
                'message' => $university->name. '  created'
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
           // 'rank_id' => 'required|exists:university_ranks,id', // 
            'name' => 'required|max:200',
            'country' => 'required|array|min:1',
            'country.*' => 'required|string|max:200',
            'city' => 'nullable|max:200',
            'months' => 'required|array|min:1',
            'months.*' => 'required|string',
            'territory' => 'required|array|min:1',
            'territory.*' => 'required|string',
            'company_id' => 'nullable|exists:users,id',
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
        // $university->campuses = $request->city;
       // $university->rank_id = $request->rank_id;
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
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
            if ($university->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $university->name . ' updated ',
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
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

    
   public function updateUniversitiesByKey(Request $request)
        {
            // Validate request
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:universities,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], 422);
            }

            // Permission check
            if (!Auth::user()->can('edit university')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Permission Denied.'
                ], 403);
            }

            // Get university
            $university = University::findOrFail($request->id);
            $originalData = $university->toArray();

            // Exclude ID from update
            $metaData = $request->except('id');

            // Update each field dynamically
            foreach ($metaData as $key => $newValue) {

                // Handle array fields
                if (in_array($key, ['country', 'territory'])) {
                    $newValue = implode(',', (array) $newValue);
                }

                if ($key === 'months') {
                    $key = 'intake_months';
                    $newValue = implode(',', (array) $newValue);
                }

                // Assign only if column exists in table
                if (\Schema::hasColumn('universities', $key)) {
                    $university->$key = $newValue;
                }
            }

            // Save updated university
            $university->save();

            // Detect changes
            $changes = [];
            $updatedFields = [];
            foreach ($originalData as $field => $oldValue) {
                if (in_array($field, ['created_at', 'updated_at'])) {
                    continue;
                }
                if ($university->$field != $oldValue) {
                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $university->$field
                    ];
                    $updatedFields[] = $field;
                }
            }

            // Log changes if any
            if (!empty($changes)) {
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => $university->name . ' updated',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
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
         $updatedFields = [];
        foreach ($originalData as $field => $oldValue) {
             if (in_array($field, ['created_at', 'updated_at', 'rank', 'created_by'])) {
                    continue;
                }
            if ($university->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $university->$field
                ];

                 $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                 'note' => json_encode([
                        'title' => $university->name . ' updated ',
                        'message' => 'Fields updated: ' . implode(', ', $updatedFields),
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

        // // Log the deletion
        // $logData = [
        //     'type' => 'info',
        //     'note' => json_encode([
        //         'title' => 'University Deleted',
        //         'message' => 'University deleted successfully.',
        //         'changes' => $university
        //     ]),
        //     'module_id' => $university->id,
        //     'module_type' => 'university',
        //     'notification_type' => 'University Deleted'
        // ];
        // addLogActivity($logData);

          addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $university->name. '  deleted',
                'message' => $university->name. '  deleted',
                'changes' => $university
            ]),
            'module_id' => $university->id,
            'module_type' => 'university',
            'notification_type' => 'University deleted',
        ]);

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
        $applications = []; //DealApplication::where('university_id', $id)->get();

        // related admissions
        $deals = []; //Deal::where('university_id', $id)->get();

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


    // public function getIntakeMonthByUniversity()
    // {
    //     $id = $_POST['id'];
    //     $university = University::where('id', $id)->first();
    //     $courses = Course::where('university_id', $id)->get();
    
    //     $monthNames = [
    //         'JAN' => 'JAN',
    //         'FEB' => 'FEB',
    //         'MAR' => 'MAR',
    //         'APR' => 'APR',
    //         'MAY' => 'MAY',
    //         'JUN' => 'JUN',
    //         'JUL' => 'JUL',
    //         'AUG' => 'AUG',
    //         'SEP' => 'SEP',
    //         'OCT' => 'OCT',
    //         'NOV' => 'NOV',
    //         'DEC' => 'DEC'
    //     ];
        
    //     // Process intake months
    //     $intake_months = [];
    //     if ($university && $university->intake_months) {
    //         $intake_months = collect(explode(',', $university->intake_months))
    //             ->map(function ($monthAbbr) use ($monthNames) {
    //                 $trimmed = trim($monthAbbr);
    //                 return $monthNames[$trimmed] ?? $trimmed;
    //             })
    //             ->filter()
    //             ->unique()
    //             ->values()
    //             ->toArray();
    //     }
    
    //     // Process courses
    //     $course_options = [];
    //     foreach ($courses as $course) {
    //         $course_options[] = [
    //             $course->id => $course->name . ' - ' . $course->campus . ' - ' . $course->intake_month . ' - ' . $course->intakeYear . ' (' . $course->duration . ')'
    //         ];
    //     }
    
    //     // Process intake years
    //     $intake_years = [];
    //     $years = intakeYear() ?? [];
    //     foreach ($years as $year) {
    //         $intake_years[] = [
    //             $year => $year
    //         ];
    //     }
    
    //     return [
    //         'status' => 'success',
    //         'university_status' => $university->status ?? null,
    //         'intake_months' => $intake_months,
    //         'courses' => $course_options,
    //         'intake_years' => $intake_years
    //     ];
    // }
    public function getIntakeMonthByUniversity()
     {
            $id = $_POST['id'];
            $university = University::where('id', $id)->first();
            $courses = Course::where('university_id', $id)->get();

            $monthNames = [
                'JAN' => 'JAN',
                'FEB' => 'FEB',
                'MAR' => 'MAR',
                'APR' => 'APR',
                'MAY' => 'MAY',
                'JUN' => 'JUN',
                'JUL' => 'JUL',
                'AUG' => 'AUG',
                'SEP' => 'SEP',
                'OCT' => 'OCT',
                'NOV' => 'NOV',
                'DEC' => 'DEC'
            ];
            
            // Process intake months - already in pluck-like format
            $intake_months = [];
            if ($university && $university->intake_months) {
                $intake_months = collect(explode(',', $university->intake_months))
                    ->mapWithKeys(function ($monthAbbr) use ($monthNames) {
                        $trimmed = trim($monthAbbr);
                        $value = $monthNames[$trimmed] ?? $trimmed;
                        return [$value => $value];
                    })
                    ->filter()
                    ->unique()
                    ->toArray();
            }

            // Process courses in pluck-like format
            $course_options = $courses->mapWithKeys(function ($course) {
                $label = $course->name . ' - ' . $course->campus . ' - ' . $course->intake_month . ' - ' . $course->intakeYear . ' (' . $course->duration . ')';
                return [$course->id => $label];
            })->toArray();

            // Process intake years in pluck-like format
            $intake_years = collect(intakeYear() ?? [])->mapWithKeys(function ($year) {
                return [$year => $year];
            })->toArray();

            return [
                'status' => 'success',
                'university_status' => $university->status ?? null,
                'intake_months' => $intake_months,
                'courses' => $course_options,
                'intake_years' => $intake_years
            ];
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
    $university->uni_status = $request->status;
    $university->save();

    // Log activity
   $statusText = $request->status == 1 ? 'Active' : 'Inactive';

    $logData = [
        'type' => 'info',
        'note' => json_encode([
            'title' => $university->name . ' status updated to ' . $statusText,
            'message' => $university->name . ' status updated to ' . $statusText,
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
 


    $statusText = $request->status == 1 ? 'active' : 'inactive';

    $logData = [
        'type' => 'info',
        'note' => json_encode([
            'title' => $university->name . ' MOI status updated to ' . $statusText,
            'message' => $university->name . ' MOI status updated to ' . $statusText,
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

public function pluckInstitutes(Request $request)
{
    $University = University::pluck('name', 'id')->toArray();
    return response()->json([
        'status' => 'success',
        'data' => $University
        
    ]);
}


public function get_course_campus()
{
    $id = $_POST['id'];
    // Fetch campus details
    $campus = Course::where('id', $id)
        ->whereNotNull('campus')
        ->pluck('campus')
        ->flatMap(function ($campusString) {
            return array_map('trim', explode(',', $campusString));
        })
        ->first();

    if (empty($campus)) {
        return response()->json([
            'status' => 'success',
            'campus' => [],
            'intake_month' => [],
            'intake_year' => null,
        ]);
    }

    // Fetch intake month details

    $intake_month = Course::where('id', $id)
    ->whereNotNull('intake_month')
    ->pluck('intake_month')
    ->flatMap(function ($campusString) {
        return array_map('trim', explode(',', $campusString));
    })
    ->map(function ($month) {
        // Convert full month name to 3-letter abbreviation
        return substr(strtoupper($month), 0, 3);
    })
    ->toArray();

    // Then you can compare with your monthNames array
    $monthNames = [
        'JAN' => 'JAN',
        'FEB' => 'FEB',
        'MAR' => 'MAR',
        'APR' => 'APR',
        'MAY' => 'MAY',
        'JUN' => 'JUN',
        'JUL' => 'JUL',
        'AUG' => 'AUG',
        'SEP' => 'SEP',
        'OCT' => 'OCT',
        'NOV' => 'NOV',
        'DEC' => 'DEC'
    ];

    // Filter only valid month abbreviations
    $validMonths = array_filter($intake_month, function ($month) use ($monthNames) {
        return isset($monthNames[$month]);
    });

    // If you need unique values
    $validMonths = implode(',', array_unique($validMonths));

    // Fetch intake year
    $campusfetch = Course::find($id);
    $intake_year = $campusfetch ? $campusfetch->intakeYear : null;

    return response()->json([
        'status' => 'success',
        'campus' => $campus,
        'intake_month' => $validMonths,
        'intake_year' => $intake_year,
    ]);
}
}
