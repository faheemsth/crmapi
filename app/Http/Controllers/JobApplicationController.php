<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomQuestion;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\GenerateOfferLetter;
use App\Models\InterviewSchedule;
use App\Models\Job;
use App\Models\PayslipType;
use Auth;
use App\Models\JobApplication;
use App\Models\JobApplicationNote;
use App\Models\JobOnBoard;
use App\Models\JobStage;
use App\Models\Plan;
use App\Models\SavedFilter;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;


class JobApplicationController extends Controller
{

    public function getJobApplications(Request $request)
    {
        if (\Auth::user()->can('manage job application')) {
            $stages = JobStage::orderBy('order', 'asc')
                ->with('application.jobs')
                ->where('id', '<', 6)
                ->get();

            $jobs = Job::all()->pluck('title', 'id');
            $jobs->prepend('All', '');

            $filter = [
                'start_date' => $request->start_date ?? date("Y-m-d", strtotime("-1 month")),
                'end_date' => $request->end_date ?? date("Y-m-d H:i:s", strtotime("+1 hours")),
                'job' => $request->job ?? '',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Job applications fetched successfully.',
                'data' => [
                    'stages' => $stages,
                    'jobs' => $jobs,
                    'filter' => $filter,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Permission denied.',
        ], 403);
    }

    public function getJobApplicationDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:job_applications,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!\Auth::user()->can('show job application')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.',
            ], 403);
        }

        $jobApplication = JobApplication::find($request->id);
        $notes = JobApplicationNote::where('application_id', $request->id)->get();

        $stages = JobStage::orderBy('order', 'asc')
            ->where('id', '<', 6) // Applying your filter condition
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Job application details retrieved successfully.',
            'data' => [
                'jobApplication' => $jobApplication,
                'notes' => $notes,
                'stages' => $stages,
            ],
        ]);
    }




    public function store(Request $request)
    {

        if (\Auth::user()->can('create job application')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'job' => 'required',
                    'name' => 'required',
                    'email' => 'required',
                    'phone' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            if (!empty($request->profile)) {

                $filenameWithExt = $request->file('profile')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('profile')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $dir        = 'uploads/job/profile';

                $image_path = $dir . $filenameWithExt;
                if (\File::exists($image_path)) {
                    \File::delete($image_path);
                }
                $url = '';
                $path = Utility::upload_file($request, 'profile', $fileNameToStore, $dir, []);
                if ($path['flag'] == 1) {
                    $url = $path['url'];
                } else {
                    return redirect()->back()->with('error', __($path['msg']));
                }
            }

            if (!empty($request->resume)) {


                $filenameWithExt1 = $request->file('resume')->getClientOriginalName();
                $filename1        = pathinfo($filenameWithExt1, PATHINFO_FILENAME);
                $extension1       = $request->file('resume')->getClientOriginalExtension();
                $fileNameToStore1 = $filename1 . '_' . time() . '.' . $extension1;

                $dir        = 'uploads/job/resume';

                $image_path = $dir . $filenameWithExt1;
                if (\File::exists($image_path)) {
                    \File::delete($image_path);
                }
                $url = '';
                $path = Utility::upload_file($request, 'resume', $fileNameToStore1, $dir, []);

                if ($path['flag'] == 1) {
                    $url = $path['url'];
                } else {
                    return redirect()->back()->with('error', __($path['msg']));
                }
            }
            $stages = JobStage::where('created_by', \Auth::id())->first();

            $job                  = new JobApplication();
            $job->job             = $request->job;
            $job->name            = $request->name;
            $job->email           = $request->email;
            $job->phone           = $request->phone;
            $job->profile         = !empty($request->profile) ? $fileNameToStore : '';
            $job->resume          = !empty($request->resume) ? $fileNameToStore1 : '';
            $job->cover_letter    = $request->cover_letter;
            $job->dob             = $request->dob;
            $job->gender          = $request->gender;
            $job->country         = $request->country;
            $job->state           = $request->state;
            $job->city            = $request->city;
            $job->stage           = !empty($stages) ? $stages->id : 1;
            $job->custom_question = json_encode($request->question);
            $job->created_by      = \Auth::id();
            $job->save();

            return redirect()->route('job-application.index')->with('success', __('Job application successfully created.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }

    public function show($ids)
    {

        if (\Auth::user()->can('show job application')) {
            $id             = Crypt::decrypt($ids);
            $jobApplication = JobApplication::find($id);

            $notes = JobApplicationNote::where('application_id', $id)->get();

            $stages = JobStage::where('created_by', \Auth::id())->get();

            return view('jobApplication.show', compact('jobApplication', 'notes', 'stages'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function destroy(JobApplication $jobApplication)
    {
        if (\Auth::user()->can('delete job application')) {
            $jobApplication->delete();
            return redirect()->route('job-application.index')->with('success', __('Job application successfully deleted.'));
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }
    public function archiveJobApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:job_applications,id',
            'is_archive' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!\Auth::user()->can('show job application')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied.',
            ], 403);
        }

        $jobApplication = JobApplication::find($request->id);

        $jobApplication->is_archive = !$jobApplication->is_archive;
        $jobApplication->save();

        return response()->json([
            'success' => true,
            'message' => $jobApplication->is_archive
                ? __('Job application successfully added to archive.')
                : __('Job application successfully removed from archive.'),
            'data' => $jobApplication
        ]);
    }


    public function jobBoardStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application'      => 'required|exists:job_applications,id',
            'joining_date'     => 'required|date',
            'job_type'         => 'required|string',
            'days_of_week'     => 'required|integer|gt:0',
            'salary'           => 'required|numeric|gt:0',
            'salary_type'      => 'required|string',
            'salary_duration'  => 'required|string',
            'status'           => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()
            ], 400);
        }

        // Create a new job board entry
        $jobBoard = JobOnBoard::create([
            'application'     => $request->application,
            'joining_date'    => date('Y-m-d',strtotime($request->joining_date)),
            'job_type'        => $request->job_type,
            'days_of_week'    => $request->days_of_week,
            'salary'          => $request->salary,
            'salary_type'     => $request->salary_type,
            'salary_duration' => $request->salary_duration,
            'status'          => $request->status,
            'created_by'      => auth()->id(),
        ]);

        // Delete the interview schedule if it exists
        InterviewSchedule::where('candidate', $request->application)->delete();

        return response()->json([
            'success' => true,
            'message' => __('Candidate successfully added to the job board.'),
            'data'    => $jobBoard
        ], 201);
    }

    public function order(Request $request)
    {
        if (\Auth::user()->can('move job application')) {
            $post = $request->all();
            foreach ($post['order'] as $key => $item) {
                $application        = JobApplication::where('id', '=', $item)->first();
                $application->order = $key;
                $application->stage = $post['stage_id'];
                $application->save();
            }
        } else {
            return redirect()->route('job-application.index')->with('error', __('Permission denied.'));
        }
    }




    public function rating(Request $request, $id)
    {
        $jobApplication         = JobApplication::find($id);
        $jobApplication->rating = $request->rating;
        $jobApplication->save();
    }


    public function candidate()
    {
        if (\Auth::user()->can('manage job onBoard')) {
            $archive_application = JobApplication::where('created_by', \Auth::id())->where('is_archive', 1)->get();

            return view('jobApplication.candidate', compact('archive_application'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function getjobBoardStore()
    {
        if (\Auth::user()->can('manage job onBoard')) {
            $query = JobOnBoard::with('applications.jobs')->select(
                'job_on_boards.*',
                'regions.name as region',
                'branches.name as branch',
                'users.name as brand',
                'assigned_to.name as created_user'
            )
                ->leftJoin('job_applications as ja1', 'ja1.id', '=', 'job_on_boards.application')
                ->leftJoin('jobs', 'jobs.id', '=', 'ja1.job')
                ->leftJoin('users', 'users.id', '=', 'jobs.brand_id')
                ->leftJoin('job_applications as ja2', 'ja2.id', '=', 'jobs.brand_id')
                ->leftJoin('branches', 'branches.id', '=', 'jobs.branch')
                ->leftJoin('regions', 'regions.id', '=', 'jobs.region_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'jobs.created_by');
            $jobOnBoard_query = RoleBaseTableGet($query, 'jobs.brand_id', 'jobs.region_id', 'jobs.branch', 'jobs.created_by');
            $filters = $this->jobsFilters();
            foreach ($filters as $column => $value) {
                if ($column == 'created_at') {
                    $jobOnBoard_query->whereDate('jobs.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
                } elseif ($column == 'brand') {
                    $jobOnBoard_query->where('jobs.brand_id', $value);
                } elseif ($column == 'region_id') {
                    $jobOnBoard_query->where('jobs.region_id', $value);
                } elseif ($column == 'branch_id') {
                    $jobOnBoard_query->where('jobs.branch', $value);
                }
            }
            $jobOnBoards = $jobOnBoard_query->get();
            $saved_filters = SavedFilter::where('created_by', \Auth::id())->where('module', 'jobOnBoards')->get();
            $filters = BrandsRegionsBranches();
            return response()->json([
                'success' => true,
                'data' => [
                    'jobOnBoards' => $jobOnBoards,
                    'saved_filters' => $saved_filters,
                    'filters' => $filters
                ]
            ]);
        } else {
            // Return JSON response for permission denied
            return response()->json([
                'success' => false,
                'message' => 'Permission denied.'
            ], 403);
        }
    }

    private function jobsFilters()
    {
        $filters = [];
        if (isset($_GET['name']) && !empty($_GET['name'])) {
            $filters['name'] = $_GET['name'];
        }

        if (isset($_GET['brand']) && !empty($_GET['brand'])) {
            $filters['brand'] = $_GET['brand'];
        }

        if (isset($_GET['region_id']) && !empty($_GET['region_id'])) {
            $filters['region_id'] = $_GET['region_id'];
        }

        if (isset($_GET['branch_id']) && !empty($_GET['branch_id'])) {
            $filters['branch_id'] = $_GET['branch_id'];
        }

        if (isset($_GET['lead_assigned_user']) && !empty($_GET['lead_assigned_user'])) {
            $filters['deal_assigned_user'] = $_GET['lead_assigned_user'];
        }


        if (isset($_GET['stages']) && !empty($_GET['stages'])) {
            $filters['stage_id'] = $_GET['stages'];
        }

        if (isset($_GET['users']) && !empty($_GET['users'])) {
            $filters['users'] = $_GET['users'];
        }

        if (isset($_GET['created_at_from']) && !empty($_GET['created_at_from'])) {
            $filters['created_at_from'] = $_GET['created_at_from'];
        }

        if (isset($_GET['created_at_to']) && !empty($_GET['created_at_to'])) {
            $filters['created_at_to'] = $_GET['created_at_to'];
        }
        if (isset($_GET['tag']) && !empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }

        if (isset($_GET['price']) && !empty($_GET['price'])) {
            $price = $_GET['price'];

            if (preg_match('/^(<=|>=|<|>)/', $price, $matches)) {
                $comparePrice = $matches[1]; // Get the comparison operator
                $filters['price'] = (float) substr($price, strlen($comparePrice)); // Get the price value
            } else {
                $comparePrice = '=';
                $filters['price'] = '=' . $price; // Default to '=' if no comparison operator is provided
            }
        }

        return $filters;
    }



    public function jobBoardUpdate(Request $request, $id)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'joining_date' => 'required',
                'job_type' => 'required',
                'days_of_week' => 'required',
                'salary' => 'required',
                'salary_type' => 'required',
                'salary_duration' => 'required',
                'status' => 'required',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        $jobBoard                        = JobOnBoard::find($id);
        $jobBoard->joining_date          = $request->joining_date;
        $jobBoard->job_type              = $request->job_type;
        $jobBoard->days_of_week          = $request->days_of_week;
        $jobBoard->salary                = $request->salary;
        $jobBoard->salary_type           = $request->salary_type;
        $jobBoard->salary_duration     = $request->salary_duration;
        $jobBoard->status                = $request->status;
        $jobBoard->save();


        return redirect()->route('job.on.board')->with('success', __('Job board Candidate succefully updated.'));
    }

    public function jobBoardEdit($id)
    {
        $jobOnBoard = JobOnBoard::find($id);
        $status     = JobOnBoard::$status;
        $job_type       = JobOnBoard::$job_type;
        $salary_duration = JobOnBoard::$salary_duration;
        $salary_type      = PayslipType::where('created_by', \Auth::id())->get()->pluck('name', 'id');


        return view('jobApplication.onboardEdit', compact('jobOnBoard', 'status', 'job_type', 'salary_type', 'salary_duration'));
    }

    public function jobBoardDelete($id)
    {

        $jobBoard = JobOnBoard::find($id);
        $jobBoard->delete();

        return redirect()->route('job.on.board')->with('success', __('Job onBoard successfully deleted.'));
    }

    public function jobBoardConvert($id)
    {
        $jobOnBoard = JobOnBoard::select('job_on_boards.*', 'jobs.brand_id', 'jobs.region_id', 'jobs.branch as branch_id')
            ->join('job_applications', 'job_applications.id', '=', 'job_on_boards.application')
            ->join('jobs', 'jobs.id', '=', 'job_applications.job')
            ->where('job_on_boards.id', $id)
            ->first();

        $filter = BrandsRegionsBranchesForEdit($jobOnBoard->brand_id, $jobOnBoard->region_id, $jobOnBoard->branch_id);
        $companies = $filter['brands'];
        $regions = $filter['regions'];
        $branches = $filter['branches'];

        $company_settings = Utility::settings();
        $documents        = Document::where('created_by', \Auth::id())->get();
        $branches         = Branch::where('created_by', \Auth::id())->get()->pluck('name', 'id');
        $departments      = Department::where('created_by', \Auth::id())->get()->pluck('name', 'id');
        $designations     = Designation::where('created_by', \Auth::id())->get()->pluck('name', 'id');
        $employees        = User::where('created_by', \Auth::id())->get();
        $employeesId      = \Auth::user()->employeeIdFormat($this->employeeNumber());
        $roles = Role::where('name', '!=', 'super admin')->pluck('name', 'id')->toArray();
        return view('jobApplication.convert', compact('roles', 'companies', 'regions', 'branches', 'jobOnBoard', 'employees', 'employeesId', 'departments', 'designations', 'documents', 'branches', 'company_settings'));
    }

    public function jobBoardConvertData(Request $request, $id)
    {

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'dob' => 'required',
                'gender' => 'required',
                'phone' => 'required',
                'address' => 'required',
                'email' => 'required|unique:users',
                'password' => 'required',
                'department_id' => 'required',
                'designation_id' => 'required',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->withInput()->with('error', $messages->first());
        }
        $objUser = \Auth::user();
        $employees        = User::where('type', '!=', 'client')->where('type', '!=', 'company')->where('created_by', \Auth::id())->get();

        $total_employee = $employees->count();
        $plan           = Plan::find($objUser->plan);
        $user = new User;
        $user->name = $request['name'];
        $user->email = $request['email'];
        $user->brand_id = $request->brand_id;
        $user->region_id = $request->region_id;
        $user->branch_id = $request->branch_id;
        $user->password = Hash::make($request['password']);
        $user->type = 'employee';
        $user->lang = 'en';
        $user->created_by = \Auth::id();
        $user->save();
        $user->assignRole($request->role);


        if (!empty($request->document) && !is_null($request->document)) {
            $document_implode = implode(',', array_keys($request->document));
        } else {
            $document_implode = null;
        }


        $employee = Employee::create(
            [
                'user_id' => $user->id,
                'name' => $request['name'],
                'dob' => $request['dob'],
                'gender' => $request['gender'],
                'phone' => $request['phone'],
                'address' => $request['address'],
                'email' => $request['email'],
                'password' => Hash::make($request['password']),
                'employee_id' => $this->employeeNumber(),
                'branch_id' => $request['branch_id'],
                'department_id' => $request['department_id'],
                'designation_id' => $request['designation_id'],
                'company_doj' => $request['company_doj'],
                'documents' => $document_implode,
                'account_holder_name' => $request['account_holder_name'],
                'account_number' => $request['account_number'],
                'bank_name' => $request['bank_name'],
                'bank_identifier_code' => $request['bank_identifier_code'],
                'branch_location' => $request['branch_location'],
                'tax_payer_id' => $request['tax_payer_id'],
                'created_by' => \Auth::id(),
            ]
        );

        if (!empty($employee)) {
            $JobOnBoard                      = JobOnBoard::find($id);
            $JobOnBoard->convert_to_employee = $employee->id;
            $JobOnBoard->save();
        }
        if ($request->hasFile('document')) {
            foreach ($request->document as $key => $document) {

                $filenameWithExt = $request->file('document')[$key]->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('document')[$key]->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;
                $dir             = storage_path('uploads/document/');
                $image_path      = $dir . $filenameWithExt;

                if (\File::exists($image_path)) {
                    \File::delete($image_path);
                }

                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $path              = $request->file('document')[$key]->storeAs('uploads/document/', $fileNameToStore);
                $employee_document = EmployeeDocument::create(
                    [
                        'employee_id' => $employee['employee_id'],
                        'document_id' => $key,
                        'document_value' => $fileNameToStore,
                        'created_by' => \Auth::id(),
                    ]
                );
                $employee_document->save();
            }
        }

        $setings = Utility::settings();
        if ($setings['new_user'] == 1) {
            $userArr = [
                'email' => $user->email,
                'password' => $user->password,
            ];

            $resp = Utility::sendEmailTemplate('new_user', [$user->id => $user->email], $userArr);

            return redirect()->back()->with('success', __('Application successfully converted to employee.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
        }

        return redirect()->back()->with('success', __('Application successfully converted to employee.'));
    }




    public function stageChange(Request $request)
    {
        $application        = JobApplication::where('id', '=', $request->schedule_id)->first();
        $application->stage = $request->stage;
        $application->save();


        return response()->json(
            [
                'success' => __('This candidate stage successfully changed.'),
            ],
            200
        );
    }

    public function offerletterPdf($id)
    {
        $users = \Auth::user();
        $currantLang = $users->currentLanguage();
        // $Offerletter=GenerateOfferLetter::where('lang', $currantLang)->first();
        $Offerletter = GenerateOfferLetter::where(['lang' =>   $currantLang, 'created_by' =>  \Auth::id()])->first();

        if (empty($Offerletter)) {
            return redirect()->back()->with('error', __('Offer Letter not found.'));
        }
        $job = JobApplication::find($id);
        $Onboard = JobOnBoard::find($id);
        $name = JobApplication::find($Onboard->application);
        $job_title = job::find($name->job);
        // dd($job);
        $salary = PayslipType::find($Onboard->salary_type);


        //  dd($salary->name);
        $obj = [
            'applicant_name' => $name->name,
            'app_name' => env('APP_NAME'),
            'job_title' => $job_title->title,
            'job_type' => !empty($Onboard->job_type) ? $Onboard->job_type : '',
            'start_date' => $Onboard->joining_date,
            'workplace_location' => !empty($job->jobs->branches->name) ? $job->jobs->branches->name : '',
            'days_of_week' => !empty($Onboard->days_of_week) ? $Onboard->days_of_week : '',
            'salary' => !empty($Onboard->salary) ? $Onboard->salary : '',
            'salary_type' => !empty($salary->name) ? $salary->name : '',
            'salary_duration' => !empty($Onboard->salary_duration) ? $Onboard->salary_duration : '',
            'offer_expiration_date' => !empty($Onboard->joining_date) ? $Onboard->joining_date : '',

        ];
        $Offerletter->content = GenerateOfferLetter::replaceVariable($Offerletter->content, $obj);
        return view('jobApplication.template.offerletterpdf', compact('Offerletter', 'name'));
    }
    public function offerletterDoc($id)
    {
        $users = \Auth::user();
        $currantLang = $users->currentLanguage();
        $Offerletter = GenerateOfferLetter::where(['lang' =>   $currantLang, 'created_by' =>  \Auth::id()])->first();
        if (empty($Offerletter)) {
            return redirect()->back()->with('error', __('Offer Letter not found.'));
        }
        $job = JobApplication::find($id);
        $Onboard = JobOnBoard::find($id);
        $name = JobApplication::find($Onboard->application);
        $job_title = job::find($name->job);
        // dd($job_title->title);
        $salary = PayslipType::find($Onboard->salary_type);
        // dd($Offerletter);


        //  dd($salary->name);
        $obj = [
            'applicant_name' => $name->name,
            'app_name' => env('APP_NAME'),
            'job_title' => $job_title->title,
            'job_type' => !empty($Onboard->job_type) ? $Onboard->job_type : '',
            'start_date' => $Onboard->joining_date,
            'workplace_location' => !empty($job->jobs->branches->name) ? $job->jobs->branches->name : '',
            'days_of_week' => !empty($Onboard->days_of_week) ? $Onboard->days_of_week : '',
            'salary' => !empty($Onboard->salary) ? $Onboard->salary : '',
            'salary_type' => !empty($salary->name) ? $salary->name : '',
            'salary_duration' => !empty($Onboard->salary_duration) ? $Onboard->salary_duration : '',
            'offer_expiration_date' => !empty($Onboard->joining_date) ? $Onboard->joining_date : '',

        ];
        $Offerletter->content = GenerateOfferLetter::replaceVariable($Offerletter->content, $obj);
        return view('jobApplication.template.offerletterdocx', compact('Offerletter', 'name'));
    }
}
