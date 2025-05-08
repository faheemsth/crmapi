<?php

namespace App\Http\Controllers;

use Session;
use Exception;
use SplFileObject;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Models\Label;
use App\Models\Stage;
use App\Models\Branch;
use App\Models\Course;
use App\Models\DealNote;
use App\Models\Region;
use App\Models\Source;
use App\Models\LeadTag;
use App\Models\Utility;
use App\Models\DealCall;
use App\Models\DealFile;
use App\Models\DealTask;
use App\Models\LeadCall;
use App\Models\LeadFile;
use App\Models\LeadNote;
use App\Models\Pipeline;
use App\Models\UserDeal;
use App\Models\UserLead;
use App\Models\DealEmail;
use App\Models\LeadEmail;
use App\Models\LeadStage;
use Illuminate\View\View;
use App\Jobs\SendEmailJob;
use App\Models\ClientDeal;
use App\Models\LeadToDeal;
use App\Models\University;
use App\Mail\SendLeadEmail;
use App\Models\LogActivity;
use App\Models\Organization;
use App\Models\StageHistory;
use Illuminate\Http\Request;
use App\Models\DealDiscussion;
use App\Models\LeadDiscussion;
use App\Models\ProductService;
use App\Models\DealApplication;
use App\Models\LeadActivityLog;
use App\Models\CompanyPermission;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\EmailSendLog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\SavedFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\Agency;
use App\Models\EmailMarkittingFileEmail;
use Illuminate\Support\Facades\Validator;






class LeadController extends Controller
{

    public function getLeads(Request $request)
    {


        

        $validator = Validator::make($request->all(), [

            'perPage' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'name' => 'nullable|string',
            'brand' => 'nullable|integer|exists:users,id',
            'region_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'stage_id' => 'nullable|array',
            'users' => 'nullable|array',
            'lead_assigned_user' => 'sometimes|nullable',
            'created_at_from' => 'nullable|date',
            'created_at_to' => 'nullable|date',
            'tag' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $usr = \Auth::user();

        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50)); // Default 50 records per page
        $page = $request->input('page', 1); // Default page 1

        $leadsQuery = Lead::select(
            'leads.id',
            'leads.name',
            'leads.brand_id',
            'leads.email',
            'leads.branch_id',
            'leads.phone',
            'leads.user_id',
            'leads.stage_id',
            'leads.tag_ids'
        )
            ->with('assignto')
            ->with('brand')
            ->with('stage')
            ->with('branch')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id');

        // Apply Filters
        if ($request->filled('Assigned')) {
            $leadsQuery->whereNotNull('leads.user_id');
        }

        if ($request->filled('Unassigned')) {
            $leadsQuery->whereNull('leads.user_id');
        }

        if ($request->filled('brand')) {
            $leadsQuery->where('leads.brand_id', $request->brand);
        }

        if ($request->filled('region_id')) {
            $leadsQuery->where('leads.region_id', $request->region_id);
        }

        if ($request->filled('branch_id')) {
            $leadsQuery->where('leads.branch_id', $request->branch_id);
        }

        if ($request->filled('stage_id')) {
            $leadsQuery->where('leads.stage_id', $request->stage_id);
        }

        if ($request->filled('tag')) {
            $leadsQuery->whereRaw('FIND_IN_SET(?, leads.tag_ids)', [$request->tag]);
        }

        if ($request->filled('lead_assigned_user')) {
            $leadsQuery->where('leads.user_id', $request->lead_assigned_user);
        }

        if ($request->filled('created_at_to')) {
            $leadsQuery->whereDate('leads.created_at', '<=', $request->created_at_to);
        }

        if ($request->filled('created_at_from')) {
            $leadsQuery->whereDate('leads.created_at', '>=', $request->created_at_from);
        }

        // User Permissions Filtering
        $userType = $usr->type;
        if ($userType === 'company') {
            $leadsQuery->where('leads.brand_id', $usr->id);
        } elseif ($userType === 'Region Manager' && $usr->region_id) {
            $leadsQuery->where('leads.region_id', $usr->region_id);
        } elseif ($userType === 'Branch Manager' && $usr->branch_id) {
            $leadsQuery->where('leads.branch_id', $usr->branch_id);
        } elseif ($userType === 'Agent') {
            $leadsQuery->where('leads.user_id', $usr->id);
        }

        // Apply Search Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $leadsQuery->where(function ($query) use ($search) {
                $query->where('leads.name', 'like', "%$search%")
                    ->orWhere('leads.email', 'like', "%$search%")
                    ->orWhere('leads.phone', 'like', "%$search%");
            });
        }
      
        // Apply Pagination
        // Apply Pagination
        $leads = $leadsQuery
            ->orderBy('leads.created_at', 'desc')->where('is_converted',0)
            ->paginate($perPage, ['*'], 'page', $page);
            
            $leadsWithTags = $leads->getCollection()->map(function ($lead) {
            $lead->tags = LeadTag::whereRaw("FIND_IN_SET(id, ?)", [$lead->tag_ids])->get();
             return $lead;
        });

        

        return response()->json([
            'status' => 'success',
            'data' => $leadsWithTags,
            'current_page' => $leads->currentPage(),
            'last_page' => $leads->lastPage(),
            'total_records' => $leads->total(),
            'per_page' => $leads->perPage()
        ], 200);
    }

    public function saveLead(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('create lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'lead_stage' => 'required',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
            'lead_phone' => 'required',
            'lead_email' => 'required',
            'lead_country' => 'required',
            'lead_city' => 'required',
            'lead_state' => 'required',
            'lead_postal_code' => 'required',
            'lead_street' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Pipeline & Stage Setup
        $pipeline = $user->default_pipeline
            ? Pipeline::find($user->default_pipeline)
            : Pipeline::first();

        $stage = LeadStage::where('pipeline_id', $pipeline->id)
            ->orderBy('order', 'asc')
            ->first();

        if (!$stage) {
            return response()->json([
                'status' => 'error',
                'message' => __('Please Create Stage for This Pipeline.')
            ], 400);
        }

        // Check for Duplicate Lead
        $leadExist = Lead::where('email', $request->lead_email)
            ->where('brand_id', $request->brand_id)
            ->where('region_id', $request->region_id)
            ->where('branch_id', $request->lead_branch)
            ->first();

        if ($leadExist) {
            return response()->json([
                'status' => 'error',
                'message' => __('Lead already exists.'),
                'lead_id' => $leadExist->id,
            ], 409);
        }

        // Create New Lead
        $lead = new Lead();
        $lead->title = $request->lead_prefix ?? '';
        $lead->name = $request->name;
        $lead->email = $request->lead_email;
        $lead->phone = $request->lead_phone;
        $lead->mobile_phone = $request->lead_phone;
        $lead->branch_id = $request->lead_branch;
        $lead->brand_id = $request->brand_id;
        $lead->region_id = $request->region_id;
        $lead->organization_id = is_string($request->lead_organization) ? 0 : $request->lead_organization;
        $lead->organization_link = $request->lead_organization_link;
        $lead->sources = $request->lead_source;
        $lead->referrer_email = $request->referrer_email;
        $lead->street = $request->lead_street;
        $lead->city = $request->lead_city;
        $lead->state = $request->lead_state;
        $lead->postal_code = $request->lead_postal_code;
        $lead->country = $request->lead_country;
        $lead->keynotes = $request->lead_description;
        $lead->tags = $request->lead_tags_list;
        $lead->stage_id = $request->lead_stage;
        $lead->subject = "{$request->lead_first_name} {$request->lead_last_name}";
        $lead->user_id = $request->lead_assigned_user;
        $lead->tag_ids = !empty($request->tag_ids) ? implode(',', $request->tag_ids) : '';
        $lead->pipeline_id = $pipeline->id;
        $lead->created_by = Session::get('auth_type_id') ?? $user->id;
        $lead->date = now()->format('Y-m-d');
        $lead->drive_link = $request->drive_link ?? '';

        $lead->save();

        // Associate Lead with User
        UserLead::create([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
        ]);

        // Add Stage History
        addLeadHistory([
            'stage_id' => $request->lead_stage,
            'type_id' => $lead->id,
            'type' => 'lead',
        ]);

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Created',
                'message' => 'Lead created successfully'
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Created'
        ]);

        // Send Notification Email (if enabled)
        if (!empty($request->lead_assigned_user)) {
            $assignedUser = User::find($request->lead_assigned_user);
            $settings = Utility::settings();

            if ($settings['lead_assigned'] == 1) {
                $emailData = [
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                    'lead_subject' => $lead->subject,
                    'lead_pipeline' => $pipeline->name,
                    'lead_stage' => $stage->name,
                ];

                $resp = Utility::sendEmailTemplate('lead_assigned', [$assignedUser->id => $assignedUser->email], $emailData);

                if (!$resp['is_success']) {
                    return response()->json([
                        'status' => 'success',
                        'lead_id' => $lead->id,
                        'message' => __('Lead successfully created!')
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'lead_id' => $lead->id,
            'message' => __('Lead successfully created!')
        ], 201);
    }

    public function updateLead(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('edit lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'name' => 'required',
            'lead_stage' => 'required',
            'lead_id' => 'required|exists:leads,id',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
            'lead_phone' => 'required',
            'lead_email' => 'required|email',
            'lead_country' => 'required',
            'lead_city' => 'required',
            'lead_state' => 'required',
            'lead_postal_code' => 'required',
            'lead_street' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Fetch Lead
        $lead_id = $request->lead_id;
        $lead = Lead::find($lead_id);

        if (!$lead) {
            return response()->json([
                'status' => 'error',
                'message' => __('Lead not found.')
            ], 404);
        }

        // Update Lead Data
        $lead->title = $request->lead_prefix ?? $lead->title;
        $lead->name = $request->name;
        $lead->email = $request->lead_email;
        $lead->phone = $request->lead_phone;
        $lead->mobile_phone = $request->lead_phone;
        $lead->branch_id = $request->lead_branch;
        $lead->brand_id = $request->brand_id;
        $lead->region_id = $request->region_id;
        $lead->organization_id = is_string($request->lead_organization) ? 0 : $request->lead_organization;
        $lead->organization_link = $request->lead_organization_link;
        $lead->sources = $request->lead_source;
        $lead->referrer_email = $request->referrer_email;
        $lead->street = $request->lead_street;
        $lead->city = $request->lead_city;
        $lead->state = $request->lead_state;
        $lead->postal_code = $request->lead_postal_code;
        $lead->country = $request->lead_country;
        $lead->keynotes = $request->lead_description;
        $lead->tags = $request->lead_tags_list;
        $lead->stage_id = $request->lead_stage;
        $lead->subject = "{$request->lead_first_name} {$request->lead_last_name}";
        $lead->user_id = $request->lead_assigned_user;
        $lead->tag_ids = !empty($request->tag_ids) ? implode(',', $request->tag_ids) : $lead->tag_ids;
        $lead->drive_link = $request->drive_link ?? $lead->drive_link;

        $lead->save();

        // Update User-Lead Association
        UserLead::updateOrCreate(
            ['lead_id' => $lead->id],
            ['user_id' => $user->id]
        );

        // Add Stage History (if stage changed)
        if ($lead->wasChanged('stage_id')) {
            addLeadHistory([
                'stage_id' => $request->lead_stage,
                'type_id' => $lead->id,
                'type' => 'lead',
            ]);
        }

        // Log Activity
        addLogActivity([
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Updated',
                'message' => 'Lead updated successfully'
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Updated'
        ]);

        // Send Notification Email (if enabled)
        if (!empty($request->lead_assigned_user)) {
            $assignedUser = User::find($request->lead_assigned_user);
            $settings = Utility::settings();

            if (isset($settings)) {
                $emailData = [
                    'lead_name' => $lead->name,
                    'lead_email' => $lead->email,
                    'lead_subject' => $lead->subject,
                    'lead_pipeline' => Pipeline::find($lead->pipeline_id)->name,
                    'lead_stage' => LeadStage::find($lead->stage_id)->name,
                ];

                $resp = Utility::sendEmailTemplate('lead_updated', [$assignedUser->id => $assignedUser->email], $emailData);

                if (!$resp['is_success']) {
                    return response()->json([
                        'status' => 'success',
                        'lead_id' => $lead->id,
                        'message' => __('Lead successfully updated!')
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'lead_id' => $lead->id,
            'message' => __('Lead successfully updated!')
        ], 200);
    }




    public function fetchColumns(Request $request)
    {

        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('create lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'leads_file' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('leads_file')) {

            $file = $request->file('leads_file');
            $extension = $file->getClientOriginalExtension();
            if ($extension == 'csv') {
                $first_row = $this->readCsvHeader($file);
            } else {
                $first_row =  $this->readExcelHeader($file);
            }

            $users = User::where('created_by', '=', \Auth::user()->creatorId())->where('type', '!=', 'client')->where('type', '!=', 'company')->where('id', '!=', \Auth::user()->id)->get()->pluck('name', 'id');

            $pipelines = Pipeline::get()->pluck('name', 'id');
            $companies = FiltersBrands();

            $filter = BrandsRegionsBranches();
            $companies = $filter['brands'];
            $regions = $filter['regions'];
            $branches = $filter['branches'];
            $employees = $filter['employees'];

            // Render the getDiscussions partial view and store the HTML in $returnHTML
            $returnHTML = ['first_row' => $first_row, 'users' => $users, 'pipelines' => $pipelines, 'companies' => $companies, 'regions' => $regions, 'branches' => $branches, 'employees' => $employees];


            return response()->json(['status' => 'success', 'data' => $returnHTML]);
        } else {
            return response()->json(['status' => 'error', 'message' => 'No CSV file uploaded']);
        }
    }


    private function readExcelHeader($file)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $first_row = [];
        foreach ($worksheet->getRowIterator() as $row) {
            $cleaned_row = [];
            foreach ($row->getCellIterator() as $cell) {
                $cellValue = $cell->getValue();
                $clean_string = preg_replace('/[^\x20-\x7E]/', '', $cellValue);
                if (empty($clean_string)) {
                    continue;
                }
                $cleaned_row[] = $clean_string;
            }
            $first_row = $cleaned_row;
            break;
        }
        return $first_row;
    }

    public function readCsvHeader($file)
    {

        // $delimater = $this->getFileDelimiter($file, 1);

        $handle = fopen($file->getPathname(), 'r');
        $first_row = [];

        while ($line = fgets($handle)) {
            // Remove BOM
            if (substr($line, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) {
                $line = substr($line, 3);
            }

            // Remove null bytes
            $clean_line = str_replace("\x00", '', $line);

            // Decode UTF-16LE
            $clean_line = utf8_encode(utf8_decode($clean_line));

            $clean_line = str_replace('??', '', $clean_line);
            $delimater = $this->getFileDelimiter($file, 1);
            $fields = explode($delimater, $clean_line);


            $fields = explode($delimater, $line);

            foreach ($fields as $field) {;
                $clean_string = preg_replace('/[^\x20-\x7E]/', '', $field);
                if (!empty($clean_string))
                    $first_row[] = $clean_string;
            }
            break;
        }
        fclose($handle);

        return $first_row;
    }


    function getFileDelimiter($file, $checkLines = 2)
    {
        $file = new SplFileObject($file);
        $delimiters = array(
            ",",
            "\t",
            ";",
            "|",
            ":"
        );
        $results = array();
        $i = 0;
        while ($file->valid() && $i <= $checkLines) {
            $line = $file->fgets();
            foreach ($delimiters as $delimiter) {
                $regExp = '/[' . $delimiter . ']/';
                $fields = preg_split($regExp, $line);
                if (count($fields) > 1) {
                    if (!empty($results[$delimiter])) {
                        $results[$delimiter]++;
                    } else {
                        $results[$delimiter] = 1;
                    }
                }
            }
            $i++;
        }
        $results = array_keys($results, max($results));
        return $results[0];
    }

    public function importCsv(Request $request)
    {

        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('create lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'leads_file' => 'required',
            'extension' => 'required',
            'brand_id' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'lead_branch' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }
        $usr = \Auth::user();

        $file = $request->file('leads_file');

        $column_arr = [];

        // Default Field Value
        if ($usr->default_pipeline) {
            $pipeline = Pipeline::where('id', '=', $usr->default_pipeline)->first();
            if (!$pipeline) {
                $pipeline = Pipeline::first();
            }
        } else {
            $pipeline = Pipeline::first();
        }

        $stage = LeadStage::where('pipeline_id', '=', $pipeline->id)->orderBy('order', 'asc')->first();

        $file = $request->file('leads_file');
        $extension = $file->getClientOriginalExtension();
        if ($extension == 'csv') {
            $response = $this->csvSheetDataSaved($request, $file, $pipeline, $stage);
        } else {
            $response =  $this->excelSheetDataSaved($request, $file, $pipeline, $stage);
        }


        if ($response = true) {
            return json_encode([
                'status' => 'success',
                'message' => 'Leads Import successfully'
            ]);
        } else {
            return json_encode([
                'status' => 'error',
                'message' => 'Permission denied.'
            ]);
        }
    }

    private function csvSheetDataSaved($request, $file, $pipeline, $stage)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $handle = fopen($file->getPathname(), 'r');
        $key = 0;

        while ($line = fgets($handle)) {

            // Remove BOM
            if (substr($line, 0, 3) == pack('CCC', 0xEF, 0xBB, 0xBF)) {
                $line = substr($line, 3);
            }

            // Remove null bytes
            $clean_line = str_replace("\x00", '', $line);

            // Decode UTF-16LE
            $clean_line = utf8_encode(utf8_decode($clean_line));

            $clean_line = str_replace('??', '', $clean_line);
            $delimater = $this->getFileDelimiter($file, 1);
            $line = explode($delimater, $clean_line);

            if ($key == 0) {
                foreach ($line as $column_key => $column) {
                    $column = preg_replace('/[^\x20-\x7E]/', '', $column);

                    if (empty($_POST['columns'][$column])) {
                        continue;
                    }

                    $column_arr[$column_key] = $_POST['columns'][$column];
                }
                $key++;
                continue;
            }

            $lead  = new Lead();
            $test = [];
            foreach ($line as $column_key => $column) {

                $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                if (!empty($column_arr[$column_key])) {
                    $test[$column_arr[$column_key]] = $column;
                    $lead->{$column_arr[$column_key]} = str_replace('"', '', $column);
                }
            }

            if (filter_var($test['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $lead_exist = Lead::where('email', $test['email'])
                    ->where('brand_id', $request->brand_id)
                    ->where('region_id', $request->region_id)
                    ->where('branch_id', $request->lead_branch)
                    ->first();

                if ($lead_exist) {
                    continue;
                }
                $lead->email = $test['email'];
            } else {
                $lead->email = 'N/A';
            }

            $lead->subject = $test['subject'] ?? 'Default Subject';

            $lead->user_id     = $request->lead_assigned_user;
            $lead->brand_id    = $request->brand_id;
            $lead->region_id   = $request->region_id;
            $lead->branch_id   = $request->lead_branch;

            $lead->pipeline_id = $pipeline->id;
            if (!isset($stage->id)) {
                return redirect()->back()->with('error', 'Please create lead stage first');
            }

            $lead->stage_id    = $stage->id;
            $lead->created_by  = $usr->id;
            $lead->date        = date('Y-m-d');


            if (!empty($test['name']) || !empty($test['email']) || !empty($test['phone']) || !empty($test['subject']) || !empty($test['notes'])) {
                $lead->save();
                //dd($test);
                if (in_array('notes', $column_arr)) {
                    $notes = new LeadNote();
                    $notes->description = str_replace('"', '', $test['notes'] ?? '') ?? '';
                    $notes->created_by = auth()->id();
                    $notes->lead_id = $lead->id;
                    $notes->save();
                }
                UserLead::create(
                    [
                        'user_id' => $usr->id,
                        'lead_id' => $lead->id,
                    ]
                );

                $usrEmail = User::find($request->lead_assigned_user);

                // Send Email
                $setings = Utility::settings();
                if ($setings['lead_assigned'] == 1) {

                    $usrEmail = User::find($request->lead_assigned_user);
                    $leadAssignArr = [
                        'lead_name'     => $lead->name,
                        'lead_email'    => $lead->email,
                        'lead_subject'  => $lead->subject,
                        'lead_pipeline' => $pipeline->name,
                        'lead_stage'    => $stage->name,

                    ];

                    $resp = Utility::sendEmailTemplate('lead_assigned', [$usrEmail->id => $usrEmail->email], $leadAssignArr);

                    //return redirect()->back()->with('success', __('Lead successfully created!') . (($resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
                }

                //Slack Notification
                // $setting  = Utility::settings($usr->id);
                // if (isset($setting['lead_notification']) && $setting['lead_notification'] == 1) {
                //     $msg = __("New Lead created by") . ' ' . $usr->name . '.';
                //     Utility::send_slack_msg($msg);
                // }

                //Telegram Notification
                // $setting  = Utility::settings($usr->id);
                // if (isset($setting['telegram_lead_notification']) && $setting['telegram_lead_notification'] == 1) {
                //     $msg = __("New Lead created by") . ' ' . $usr->name . '.';
                //     Utility::send_telegram_msg($msg);
                // }
            }
            //$lead->save();
        }

        return true;
        //return redirect()->back()->with('success', __('Lead successfully created!'));
    }



    private function excelSheetDataSaved($request, $file, $pipeline, $stage)
    {
        $usr = \Auth::user();
        $column_arr = [];
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $key = 0;

        // Extract column mapping
        foreach ($worksheet->getRowIterator() as $line) {
            if ($key == 0) {
                foreach ($line->getCellIterator() as $column_key => $column) {
                    $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                    if (empty($_POST['columns'][$column])) {
                        continue;
                    }
                    $column_arr[$column_key] = $_POST['columns'][$column];
                }
                $key++;
                continue;
            }

            $lead = new Lead();
            $test = [];

            // Assign attributes to leads, then check if the email already exists in the database or if it's a new lead.
            foreach ($line->getCellIterator() as $column_key => $column) {
                $column = preg_replace('/[^\x20-\x7E]/', '', $column);
                if (!empty($column_arr[$column_key])) {
                    $test[$column_arr[$column_key]] = $column;
                    $lead->{$column_arr[$column_key]} = $column;
                }
            }

            // Check if the lead exists
            if (filter_var($test['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $lead_exist = Lead::where('email', $lead->email)->where('brand_id', $request->brand_id)->where('region_id', $request->region_id)->where('branch_id', $request->lead_branch)->first();
                if ($lead_exist) {
                    continue;
                }
                $lead->email = in_array('email', $column_arr) ? $lead->email : '';
            } else {
                $lead->email = 'N/A';
            }
            $lead->subject = in_array('subject', $column_arr) ? $lead->subject : '';

            $lead->user_id = $request->lead_assigned_user;
            $lead->brand_id = $request->brand_id;
            $lead->region_id = $request->region_id;
            $lead->branch_id = $request->lead_branch;
            $lead->pipeline_id = $pipeline->id;

            if (!isset($stage->id)) {
                return redirect()->back()->with('error', 'Please create lead stage first');
            }

            $lead->stage_id = $stage->id;
            $lead->created_by = \Auth::user()->id;
            $lead->date = date('Y-m-d');
            if (!empty($test['name']) || !empty($test['email']) || !empty($test['phone']) || !empty($test['subject']) || !empty($test['notes'])) {
                $lead->save();

                if (in_array('notes', $column_arr)) {
                    $notes = new LeadNote();
                    $notes->description = str_replace('"', '', $test['notes']) ?? '';
                    $notes->created_by = auth()->id();
                    $notes->lead_id = $lead->id;
                    $notes->save();
                }

                UserLead::create([
                    'user_id' => $usr->id,
                    'lead_id' => $lead->id,
                ]);

                $usrEmail = User::find($request->lead_assigned_user);

                // Send Email
                $setings = Utility::settings();
                if ($setings['lead_assigned'] == 1) {
                    $leadAssignArr = [
                        'lead_name' => $lead->name,
                        'lead_email' => $lead->email,
                        'lead_subject' => $lead->subject,
                        'lead_pipeline' => $pipeline->name,
                        'lead_stage' => $stage->name,
                    ];

                    $resp = Utility::sendEmailTemplate('lead_assigned', [$usrEmail->id => $usrEmail->email], $leadAssignArr);
                }
            }
        }

        return true;
    }

    public function getLeadDetails(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Fetch Lead Details
        $lead = Lead::with('assignto')
        ->with('brand')
        ->with('stage')
        ->with('branch')
        ->with('region')
        ->with('pipeline')
        ->with('created_by')
        ->select('leads.*')
        ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')->findOrFail($request->lead_id);

        if ($lead->is_active) {
            $calendarTasks = [];
            $deal = Deal::find($lead->is_converted);

            // Calculate Lead Stage Progress
            $stages = LeadStage::where('pipeline_id', $lead->pipeline_id)->get();
            $currentStageIndex = $stages->pluck('id')->search($lead->stage_id) + 1;
            $progressPercentage = $stages->count() > 0
                ? number_format(($currentStageIndex * 100) / $stages->count())
                : 0;

            // Fetch Related Data
            $tasks = DealTask::where([
                'related_to' => $lead->id,
                'related_type' => 'lead'
            ])->orderBy('status')->get();

            // $branches = Branch::pluck('name', 'id');
            // $users = allUsers();
            $logActivities = getLogActivity($lead->id, 'lead');

            // Lead Stage History
            $stageHistories = StageHistory::where('type', 'lead')
                ->where('type_id', $lead->id)
                ->pluck('stage_id')
                ->toArray();

            // Tags Based on User Role
            $tags = [];
            if (\Auth::check()) {
                $user = \Auth::user();

                if (in_array($user->type, ['super admin', 'Admin Team'])) {
                    $tags = LeadTag::pluck('id', 'tag')->toArray();
                } elseif (in_array($user->type, ['Project Director', 'Project Manager', 'Admissions Officer'])) {
                    $tags = LeadTag::whereIn('brand_id', array_keys(FiltersBrands()))->pluck('id', 'tag')->toArray();
                } elseif ($user->type === 'Region Manager') {
                    $tags = LeadTag::where('region_id', $user->region_id)->pluck('id', 'tag')->toArray();
                } else {
                    $tags = LeadTag::where('branch_id', $user->branch_id)->pluck('id', 'tag')->toArray();
                }
            }

            // Fetch Agencies
            $agencies = Agency::pluck('organization_name', 'id');

            // Return JSON Response
            return response()->json([
                'status' => 'success',
                'data' => [
                    'lead' => $lead,
                    'deal' => $deal,
                    'stages' => $stages,
                    'current_stage_index' => $currentStageIndex,
                    'progress_percentage' => $progressPercentage,
                    'tasks' => $tasks,
                    // 'branches' => $branches,
                    // 'users' => $users,
                    'log_activities' => $logActivities,
                    'stage_histories' => $stageHistories,
                    'tags' => $tags,
                    'agencies' => $agencies
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Lead is not active.'
        ], 400);
    }

    public function getLeadDetailOnly(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Fetch Lead Details
        $lead = Lead::select('leads.*')
            ->leftJoin('lead_stages', 'leads.stage_id', '=', 'lead_stages.id')
            ->where('leads.id', $request->lead_id)
            ->first(); // Use `first` instead of `findOrFail` for a conditional check

        if ($lead) {
            return response()->json([
                'status' => 'success',
                'data' => $lead,
            ], 200);
        }

        // Return error if lead is not active or not found
        return response()->json([
            'status' => 'error',
            'message' => 'Lead is not active.'
        ], 400);
    }

    public function deleteBulkLeads(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('delete lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'ids' => 'required|string', // Expecting comma-separated IDs
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Parse and Delete Leads
        $leadIds = array_filter(explode(',', $request->ids));

        if (empty($leadIds)) {
            return response()->json([
                'status' => 'error',
                'message' => __('At least select one lead.')
            ], 400);
        }

        $deletedCount = Lead::whereIn('id', $leadIds)->delete();

        if ($deletedCount > 0) {
            // Log Activity
            addLogActivity([
                'type' => 'warning',
                'note' => json_encode([
                    'title' => 'Leads Deleted',
                    'message' => count($leadIds) . ' leads deleted successfully'
                ]),
                'module_id' => null,
                'module_type' => 'lead',
                'notification_type' => 'Leads Deleted'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Leads deleted successfully.'),
                'deleted_count' => $deletedCount
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('No leads were deleted. Please check the IDs.')
            ], 404);
        }
    }
    // ...... 
    public function updateBulkLead(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('edit lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'selectedIds' => 'required|string', // Expecting comma-separated IDs
            'brand' => 'required|exists:users,id',
            'region_id' => 'required|exists:regions,id',
            'branch_id' => 'required|exists:branches,id',
            'lead_assigned_user' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // Parse and Validate IDs
        $ids = array_filter(explode(',', $request->selectedIds));

        if (empty($ids)) {
            return response()->json([
                'status' => 'error',
                'message' => __('At least select one lead.')
            ], 400);
        }

        // Prepare Update Data
        $updateData = [];
        if (!empty($request->brand)) {
            $updateData['brand_id'] = $request->brand;
            $updateData['region_id'] = $request->region_id;
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        } elseif (!empty($request->region_id)) {
            $updateData['region_id'] = $request->region_id;
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        } elseif (!empty($request->branch_id)) {
            $updateData['branch_id'] = $request->branch_id;
            $updateData['user_id'] = $request->lead_assigned_user;
        }

        if (empty($updateData)) {
            return response()->json([
                'status' => 'error',
                'message' => __('No valid fields provided for update.')
            ], 400);
        }

        // Update Leads
        $updatedCount = Lead::whereIn('id', $ids)->update($updateData);

        if ($updatedCount > 0) {
            // Log Activity
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Leads Updated',
                    'message' => count($ids) . ' leads updated successfully'
                ]),
                'module_id' => null,
                'module_type' => 'lead',
                'notification_type' => 'Leads Updated'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('Leads updated successfully.'),
                'updated_count' => $updatedCount
            ], 200);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('No leads were updated. Please check the IDs.')
            ], 404);
        }
    }

    public function addLeadTags(Request $request)
    {
        $user = \Auth::user();

        // Check Permissions
        if (!$user->can('edit lead') && $user->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate Input
        $validator = \Validator::make($request->all(), [
            'deal_id' => 'nullable|exists:deals,id',
            'lead_id' => 'nullable|exists:leads,id',
            'selectedIds' => 'required|string', // Expecting comma-separated IDs
            'old_tag_id' => 'nullable|string',
            'new_tag_id' => 'nullable|string',
            'tagid' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        // ✅ 1. Update Tags for Deals
        if (!empty($request->deal_id)) {
            $deal = Deal::find($request->deal_id);

            if (!empty($request->old_tag_id) && !empty($request->new_tag_id)) {
                if ($request->old_tag_id == $request->new_tag_id) {
                    return response()->json([
                        'status' => 'success',
                        'message' => __('Tag updated successfully.')
                    ], 200);
                }

                $tags = explode(',', $deal->tag_ids ?? '');
                $tags = array_filter($tags, fn($tag) => $tag != $request->old_tag_id);
                $tags[] = $request->new_tag_id;

                $deal->tag_ids = implode(',', $tags);
                $deal->save();

                // ✅ Add Log Activity
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Tag Updated for Deal',
                        'message' => "Tag updated from {$request->old_tag_id} to {$request->new_tag_id} for Deal ID: {$request->deal_id}"
                    ]),
                    'module_id' => $deal->id,
                    'module_type' => 'deal',
                    'notification_type' => 'Tag Updated'
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag updated successfully.')
                ], 200);
            }
        }

        // ✅ 2. Bulk Add Tags to Leads
        if (!empty($request->selectedIds)) {
            $ids = explode(',', $request->selectedIds);
            $leads = Lead::whereIn('id', $ids)->get();

            if ($leads->count() > 0) {
                foreach ($leads as $lead) {
                    $tags = explode(',', $lead->tag_ids ?? '');
                    $tags[] = $request->tagid;
                    $lead->tag_ids = implode(',', array_unique($tags));
                    $lead->save();

                    // ✅ Add Log Activity for each Lead
                    addLogActivity([
                        'type' => 'info',
                        'note' => json_encode([
                            'title' => 'Tag Added to Lead',
                            'message' => "Tag ID: {$request->tagid} added to Lead ID: {$lead->id}"
                        ]),
                        'module_id' => $lead->id,
                        'module_type' => 'lead',
                        'notification_type' => 'Tag Added'
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag added successfully to selected leads.')
                ], 200);
            }
        }

        // ✅ 3. Update Tags for Individual Lead
        if (!empty($request->lead_id)) {
            $lead = Lead::find($request->lead_id);

            if (!empty($request->old_tag_id) && !empty($request->new_tag_id)) {
                if ($request->old_tag_id == $request->new_tag_id) {
                    return response()->json([
                        'status' => 'success',
                        'message' => __('Tag updated successfully.')
                    ], 200);
                }

                $tags = explode(',', $lead->tag_ids ?? '');
                $tags = array_filter($tags, fn($tag) => $tag != $request->old_tag_id);
                $tags[] = $request->new_tag_id;

                $lead->tag_ids = implode(',', $tags);
                $lead->save();

                // ✅ Add Log Activity
                addLogActivity([
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Tag Updated for Lead',
                        'message' => "Tag updated from {$request->old_tag_id} to {$request->new_tag_id} for Lead ID: {$lead->id}"
                    ]),
                    'module_id' => $lead->id,
                    'module_type' => 'lead',
                    'notification_type' => 'Tag Updated'
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Tag updated successfully.')
                ], 200);
            }
        }

        // Default Fallback
        return response()->json([
            'status' => 'error',
            'message' => __('Invalid request or no valid data provided.')
        ], 400);
    }

    public function convertToAdmission(Request $request)
    {
        $id=$request->id ?? '';
        $lead = Lead::findOrFail($id);
        $usr = \Auth::user();
        $user = \Auth::user();

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'client_passport' => 'required',
                'intake_month' => 'required',
                'intake_year' => 'required',
                'drive_link' => 'required',
            ]
        );
        
        if ($validator->fails()) {
            $messages = $validator->errors();
        
            return response()->json([
                'status' => 'error',
                'message' => $messages->first()
            ]);
        }
    
        // Check if lead is already converted
        if($lead->is_converted != 0) {
            return response()->json([
                        'status' => 'error',
                        'message' => 'Sorry This Lead already converted',
            ]);
        }
        // Check if passport is blocked
        $blocked_status = User::where('passport_number', $request->client_passport)
                             ->where('blocked_status', '1')
                             ->first();
    
        if(!empty($blocked_status)) {
            return response()->json([
                'status' => 'error',
                'message' => __('The passport number \'' .$request->client_passport. '\' is currently blocked and cannot proceed with the application. Please contact support for further assistance.')
            ]);
        }
    
        // Find or create client
        $client = User::where('passport_number', $request->client_passport)->first();
        
        if (!$client) {
            $validator = \Validator::make($request->all(), [
                'client_name' => 'required',
                'client_email' => 'required|email|unique:users,email',
                'client_passport' => 'required|unique:users,passport_number'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()
                ], 422);
            }

            $role = Role::findByName('client', 'web');
            $client = User::create([
                'name' => $request->client_name,
                'email' => $request->client_email,
                'password' => \Hash::make('123456789'),
                'brand_id' => $lead->brand_id,
                'branch_id' => $lead->branch_id,
                'type' => 'client',
                'lang' => 'en',
                'created_by' => $user->creatorId(),
            ]);

            $client->passport_number = $request->client_passport;
            $client->region_id = $lead->region_id;
            $client->save();
            $client->assignRole($role);
        } else {
            // Check if passport is already used in this branch
            $passport_user = User::whereRaw('REPLACE(passport_number, " ", "") = ?', [str_replace(' ', '', $request->client_passport)])
                ->where('branch_id', $lead->branch_id)
                ->first();
            
            if ($passport_user) {
                $is_exist = Deal::join('client_deals', 'client_deals.deal_id', '=', 'deals.id')
                    ->where('client_deals.client_id', $passport_user->id)
                    ->where('deals.branch_id', $lead->branch_id)
                    ->first();
    
                if ($is_exist) {
                    $branchName = Branch::find($lead->branch_id)->name ?? '';
                    return response()->json([
                        'status' => 'error',
                        'message' => "This passport is already used for the " . $branchName . " branch.",
                    ]);
                }
            }
        }
    
        // Get pipeline stage
        $stage = Stage::where('pipeline_id', $lead->pipeline_id)
            ->orderBy('id')
            ->first();
        
        if (empty($stage)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please Create Stage for This Pipeline.',
            ]);
        }
    
        // Create Deal
        $deal = new Deal();
        $deal->name = $request->name;
        $deal->price = 0;
        $deal->pipeline_id = $lead->pipeline_id;
        $deal->stage_id = $stage->id;
        $deal->sources = in_array('sources', $request->is_transfer ?? []) ? $lead->sources : '';
        $deal->products = in_array('products', $request->is_transfer ?? []) ? $lead->products : '';
        $deal->notes = in_array('notes', $request->is_transfer ?? []) ? $lead->notes : '';
        $deal->labels = $lead->labels;
        $deal->status = 'Active';
        $deal->created_by = $lead->created_by;
        $deal->branch_id = $lead->branch_id;
        $deal->region_id = $lead->region_id;
        $deal->drive_link = $request->drive_link ?? $lead->drive_link;
        $deal->university_id = $request->university_id;
        $deal->assigned_to = $lead->user_id;
        $deal->intake_month = $request->intake_month;
        $deal->intake_year = $request->intake_year;
        $deal->brand_id = $lead->brand_id;
        $deal->organization_id = is_string($lead->organization_id) ? 0 : $lead->organization_id;
        $deal->organization_link = $lead->organization_link;
        $deal->tag_ids = $lead->tag_ids;
        $deal->save();
    
        // Transfer tasks
        $tasksQuery = DealTask::query();
        $FiltersBrands = array_keys(FiltersBrands());
    
        if (\Auth::user()->type != 'HR') {
            if (\Auth::user()->type == 'super admin' || \Auth::user()->can('level 1')) {
                $FiltersBrands[] = '3751';
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type == 'company') {
                $tasksQuery->where('deal_tasks.brand_id', \Auth::user()->id);
            } elseif (\Auth::user()->type == 'Project Director' || \Auth::user()->type == 'Project Manager' || \Auth::user()->can('level 2')) {
                $tasksQuery->whereIn('deal_tasks.brand_id', $FiltersBrands);
            } elseif (\Auth::user()->type == 'Region Manager' || (\Auth::user()->can('level 3') && !empty(\Auth::user()->region_id))) {
                $tasksQuery->where('deal_tasks.region_id', \Auth::user()->region_id);
            } elseif (\Auth::user()->type == 'Branch Manager' || \Auth::user()->type == 'Admissions Officer' || 
                    \Auth::user()->type == 'Careers Consultant' || \Auth::user()->type == 'Admissions Manager' || 
                    \Auth::user()->type == 'Marketing Officer' || (\Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id))) {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            } elseif (\Auth::user()->type === 'Agent') {
                $tasksQuery->where(function ($query) {
                    $query->where('deal_tasks.assigned_to', \Auth::user()->id)
                        ->orWhere('deal_tasks.created_by', \Auth::user()->id);
                });
            } else {
                $tasksQuery->where('deal_tasks.branch_id', \Auth::user()->branch_id);
            }
        }
    
        $tasks = $tasksQuery->where('related_to', $lead->id)
                            ->where('related_type', 'lead')
                            ->orderBy('status')
                            ->get();
    
        foreach($tasks as $task) {
            $task->related_to = $deal->id;
            $task->related_type = 'deal';
            $task->save();
        }
    
        // Transfer notes
        $LeadNotes = LeadNote::where('lead_id', $lead->id)->get();
        foreach($LeadNotes as $LeadNote) {
            DealNote::create([
                'description' => $LeadNote->description,
                'created_by' => $LeadNote->created_by,
                'deal_id' => $deal->id,
            ]);
        }
    
        // Create client deal relationship
        ClientDeal::create([
            'deal_id' => $deal->id,
            'client_id' => $client->id,
        ]);
    
        // Transfer user relationships
        $leadUsers = UserLead::where('lead_id', $lead->id)->get();
        foreach ($leadUsers as $leadUser) {
            UserDeal::create([
                'user_id' => $leadUser->user_id,
                'deal_id' => $deal->id,
            ]);
        }
    
        // Transfer discussions
        if (in_array('discussion', $request->is_transfer ?? [])) {
            $discussions = LeadDiscussion::where('lead_id', $lead->id)
                                       ->where('created_by', $usr->creatorId())
                                       ->get();
            foreach ($discussions as $discussion) {
                DealDiscussion::create([
                    'deal_id' => $deal->id,
                    'comment' => $discussion->comment,
                    'created_by' => $discussion->created_by,
                ]);
            }
        }
    
        // Transfer files
        if (in_array('files', $request->is_transfer ?? [])) {
            $files = LeadFile::where('lead_id', $lead->id)->get();
            foreach ($files as $file) {
                $location = base_path() . '/storage/lead_files/' . $file->file_path;
                $new_location = base_path() . '/storage/deal_files/' . $file->file_path;
                
                if (file_exists($location) && copy($location, $new_location)) {
                    DealFile::create([
                        'deal_id' => $deal->id,
                        'file_name' => $file->file_name,
                        'file_path' => $file->file_path,
                    ]);
                }
            }
        }
    
        // Transfer calls
        if (in_array('calls', $request->is_transfer ?? [])) {
            $calls = LeadCall::where('lead_id', $lead->id)->get();
            foreach ($calls as $call) {
                DealCall::create([
                    'deal_id' => $deal->id,
                    'subject' => $call->subject,
                    'call_type' => $call->call_type,
                    'duration' => $call->duration,
                    'user_id' => $call->user_id,
                    'description' => $call->description,
                    'call_result' => $call->call_result,
                ]);
            }
        }
    
        // Transfer emails
        if (in_array('emails', $request->is_transfer ?? [])) {
            $emails = LeadEmail::where('lead_id', $lead->id)->get();
            foreach ($emails as $email) {
                DealEmail::create([
                    'deal_id' => $deal->id,
                    'to' => $email->to,
                    'subject' => $email->subject,
                    'description' => $email->description,
                ]);
            }
        }
    
        // Update lead status
        $lead->is_converted = $deal->id;
        $lead->save();
    
        // Add logs
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Converted',
                'message' => 'Lead converted successfully.'
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Converted'
        ];
        addLogActivity($data);
    
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Deal Created',
                'message' => 'Deal created successfully.'
            ]),
            'module_id' => $deal->id,
            'module_type' => 'deal',
            'notification_type' => 'Deal Created'
        ];
        addLogActivity($data);
    
        // Add stage history
        $data_for_stage_history = [
            'stage_id' => $stage->id,
            'type_id' => $deal->id,
            'type' => 'deal'
        ];
        addLeadHistory($data_for_stage_history);
    
        // Send email notification
        $pipeline = Pipeline::find($lead->pipeline_id);
        $dArr = [
            'deal_name' => $deal->name ?? '',
            'deal_pipeline' => $pipeline->name ?? '',
            'deal_stage' => $stage->name ?? '',
            'deal_status' => $deal->status ?? '',
            'deal_price' => $usr->$deal->price ?? '',
        ];
        Utility::sendEmailTemplate('Assign Deal', [$client->id => $client->email], $dArr);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Lead successfully converted',
        ]);
    }

    public function leadsLabels(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check Permission
        if (!\Auth::user()->can('edit lead')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Fetch Lead and Labels
        $lead = Lead::find($request->id);
        $labels = Label::where('pipeline_id', '=', $lead->pipeline_id)->get();
        $selected = $lead->labels();

        $selectedLabels = $selected ? $selected->pluck('name', 'id')->toArray() : [];

        // Return JSON Response
        return response()->json([
            'status' => 'success',
            'data' => [
                'lead_id' => $lead->id,
                'labels' => $labels,
                'selected_labels' => $selectedLabels
            ]
        ], 200);
    }

    public function leadLabelStore(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id',
                'labels' => 'required|array'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check Permission
        if (!\Auth::user()->can('edit lead')) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Find the Lead
        $lead = Lead::find($request->id);

        // Update Labels
        if ($request->has('labels') && is_array($request->labels)) {
            $lead->labels = implode(',', $request->labels);
        } else {
            $lead->labels = null;
        }

        $lead->save();

        // Return JSON Response
        return response()->json([
            'status' => 'success',
            'message' => __('Labels successfully updated!'),
            'data' => [
                'lead_id' => $lead->id,
                'labels' => $lead->labels
            ]
        ], 200);
    }

    public function leadsDelete(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:leads,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check Permission
        if (!\Auth::user()->can('delete lead') && \Auth::user()->type != 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Find the Lead
        $lead = Lead::find($request->id);

        // Delete related data
        LeadDiscussion::where('lead_id', '=', $lead->id)->delete();
        LeadFile::where('lead_id', '=', $lead->id)->delete();
        UserLead::where('lead_id', '=', $lead->id)->delete();
        LeadActivityLog::where('lead_id', '=', $lead->id)->delete();

        // Log the deletion
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Lead Deleted',
                'message' => 'Lead deleted successfully'
            ]),
            'module_id' => $lead->id,
            'module_type' => 'lead',
            'notification_type' => 'Lead Deleted'
        ];
        addLogActivity($data);

        // Delete the Lead
        $lead->delete();

        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => __('Lead successfully deleted!')
        ], 200);
    }

    public function updateLeadStage(Request $request)
    {
        // Validate the Request Data
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'stage_id' => 'required|exists:lead_stages,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        $lead_id = $request->lead_id;
        $stage_id = $request->stage_id;

        // Get the current stage of the lead
        $from_stage = Lead::where('id', $lead_id)->first()->stage_id;
        $to_stage = $stage_id;
        $stages = LeadStage::pluck('name', 'id')->toArray();

        // Update the lead's stage
        Lead::where('id', $lead_id)->update(['stage_id' => $stage_id]);

        // Log the old stage history
        $data_for_stage_history_old = [
            'stage_id' => $from_stage,
            'type_id' => $lead_id,
            'type' => 'lead'
        ];
        addLeadHistory($data_for_stage_history_old);

        // Log the new stage history
        $data_for_stage_history = [
            'stage_id' => $stage_id,
            'type_id' => $lead_id,
            'type' => 'lead'
        ];
        addLeadHistory($data_for_stage_history);

        // Log the stage update action
        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Stage Updated',
                'message' => 'Lead stage has been updated successfully from ' . $stages[$from_stage] . ' to ' . $stages[$to_stage] . '.'
            ]),
            'module_id' => $lead_id,
            'module_type' => 'lead',
            'notification_type' => 'Stage Updated'
        ];
        addLogActivity($data);

        // Return Success Response
        return response()->json([
            'status' => 'success',
            'message' => 'Lead stage updated successfully.'
        ], 200);
    }

    public function LeadOrgnizationUpdate(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'agency_id' => 'required|exists:agencies,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Find the lead by ID
        $lead = Lead::find($request->lead_id);

        if ($lead) {
            // Update the organization link
            $lead->organization_link = $request->agency_id;
            $lead->save();

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead updated successfully.'
            ], 200);
        } else {
            // Return error response if the lead is not found
            return response()->json([
                'status' => 'error',
                'message' => 'Lead not found'
            ], 404);
        }
    }


    public function LeadDriveLinkUpdate(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make(
            $request->all(),
            [
                'lead_id' => 'required|exists:leads,id',
                'drive_link' => 'required|url'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Find the lead by ID
        $lead = Lead::find($request->lead_id);

        if ($lead) {
            // Save the previous drive link for logging purposes
            $previous_drive_link = $lead->drive_link;

            // Update the drive link
            $lead->drive_link = $request->drive_link;
            $lead->save();

            // Log activity for the drive link update
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Lead Drive Link Updated',
                    'message' => 'Lead drive link has been updated from ' . $previous_drive_link . ' to ' . $lead->drive_link
                ]),
                'module_id' => $lead->id,
                'module_type' => 'lead',
                'notification_type' => 'Drive Link Updated'
            ];
            addLogActivity($data);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Lead updated successfully.'
            ], 200);
        } else {
            // Return error response if the lead is not found
            return response()->json([
                'status' => 'error',
                'message' => 'Lead not found'
            ], 404);
        }
    }


    public function notesCreateOrUpdate(Request $request)
    {
        // Validate the input
        $validator = Validator::make(
            $request->all(),
            [
                'description' => 'required|string',
                'lead_id' => 'required|exists:leads,id',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 400);
        }

        $lead_id = $request->lead_id;

        if ($request->note_id) {
            // Update existing note
            $note = LeadNote::find($request->note_id);

            if ($note) {
                $note->description = $request->description;
                $note->save();

                // Log activity
                $data = [
                    'type' => 'info',
                    'note' => json_encode([
                        'title' => 'Lead Notes Updated',
                        'message' => 'Lead notes updated successfully'
                    ]),
                    'module_id' => $lead_id,
                    'module_type' => 'lead',
                    'notification_type' => 'Lead Notes Updated'
                ];
                addLogActivity($data);

                return response()->json([
                    'status' => 'success',
                    'message' => __('Notes updated successfully')
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Note not found'
                ], 404);
            }
        } else {
            // Create new note
            $note = new LeadNote;
            $note->description = $request->description;
            $note->created_by = Session::get('auth_type_id') ?: \Auth::user()->id;
            $note->lead_id = $lead_id;
            $note->save();

            // Log activity
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Notes Created',
                    'message' => 'Notes created successfully'
                ]),
                'module_id' => $lead_id,
                'module_type' => 'lead',
                'notification_type' => 'Notes Created'
            ];
            addLogActivity($data);

            return response()->json([
                'status' => 'success',
                'message' => __('Notes added successfully')
            ]);
        }
    }
}
