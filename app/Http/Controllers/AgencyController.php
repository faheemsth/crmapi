<?php

namespace App\Http\Controllers;
use Session;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\OrganizationType;
use Illuminate\Support\Facades\Validator;
use App\Models\City;
use App\Models\AgencyNote;
use Illuminate\Validation\Rule;
class AgencyController extends Controller
{
    private function organizationsFilter(Request $request)
    {
        $filters = [];
        $fields = ['organization_name' => 'organization_name', 'organization_email' => 'organization_email', 'billing_country' => 'billing_country', 'billing_country' => 'billing_country'];

        foreach ($fields as $queryKey => $filterKey) {
            if ($request->has($queryKey) && !empty($request->input($queryKey))) {
                $filters[$filterKey] = $request->input($queryKey);
            }
        }

        return $filters;
    }

    public function index(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'num_results_on_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'organization_name' => 'nullable|string',
            'agencyemail' => 'nullable|string',
            'agencyphone' => 'nullable|string',
            'country' => 'nullable|string',
            'city' => 'nullable|string',
            'brand_id' => 'nullable|integer|exists:brands,id',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Default pagination settings
        $perPage = $request->input('perPage', env("RESULTS_ON_PAGE", 50));
        $page = $request->input('page', 1);
    
        // Check user permissions
        $user = \Auth::user();
        if ($user->type !== 'super admin' && !$user->can('Manage Agency')) {
            return response()->json(['error' => __('Permission Denied.')], 403);
        }
    
        $orgQuery = Agency::query();
    
        // Apply filters from request
        $filters = $this->organizationsFilter($request);
        foreach ($filters as $column => $value) {
            $orgQuery->where("agencies.$column", 'LIKE', '%' . $value . '%');
        }
    
        // Global search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $orgQuery->where(function ($query) use ($search) {
                $query->where('agencies.organization_name', 'LIKE', "%$search%")
                    ->orWhere('agencies.phone', 'LIKE', "%$search%")
                    ->orWhere('agencies.organization_email', 'LIKE', "%$search%")
                    ->orWhere('agencies.billing_country', 'LIKE', "%$search%")
                    ->orWhere('agencies.city', 'LIKE', "%$search%");
            });
        }
    
        // Fetch paginated results
        $organizations = $orgQuery
            ->orderBy('id', 'ASC')
            ->paginate($perPage, ['*'], 'page', $page);
    
        // Fetch cities if a country filter is applied
        $cities = [];
        if ($request->filled('country')) {
            $country = \App\Models\Country::where('name', $request->input('country'))->first();
        }

        return response()->json([
            'status' => 'success',
            'data' => $organizations->items(),
            'current_page' => $organizations->currentPage(),
            'last_page' => $organizations->lastPage(),
            'total_records' => $organizations->total(),
            'perPage' => $organizations->perPage()
        ], 200);
    }
    
    public function storeagency(Request $request)
    {
        if (\Auth::user()->type == 'super admin' || \Auth::user()->can('Create Agency')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'organization_name' => 'required|unique:agencies,organization_name',
                    'organization_email' => 'required|unique:agencies,organization_email',
                    'phone' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return json_encode([
                    'status' => 'error',
                    'message' => $messages->first()
                ]);
            }
            $Agency = new Agency;
            $Agency->type = 'Agency';
            $Agency->phone = $request->phone;
            $Agency->organization_name =  $request->organization_name;
            $Agency->organization_email =  $request->organization_email;
            $Agency->website = $request->website;
            $Agency->linkedin = $request->linkedin;
            $Agency->facebook = $request->facebook;
            $Agency->twitter = $request->twitter;
            $Agency->billing_street = $request->billing_street;
            $Agency->contactname = $request->contactname;
            $Agency->contactemail = $request->contactemail;
            $Agency->contactphone = $request->contactphone;
            $Agency->contactjobroll = $request->contactjobroll;
            $Agency->billing_country = $request->billing_country ?? '';
            $Agency->description = $request->description;
            $Agency->user_id = \Auth::id();
            $Agency->city = $request->city ?? '';
            $Agency->c_address = $request->c_address;
            $Agency->save();
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Agency Created',
                    'message' => 'Agency created successfully'
                ]),
                'module_id' => $Agency->id,
                'module_type' => 'agency',
                'notification_type' => 'Agency Created'
            ];
            addLogActivity($data);
            return response()->json([
                'status' => 'success',
                'message' => 'Agency created successfully!.',
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ]);
        }
    }

    public function GetAgencyDetail(Request $request)
    {
        $org_query = Agency::find($request->id);

        return response()->json([
           'status' => 'success',
           'data' => $org_query,
        ]);
    }
    public function updateAgency(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:agencies,id',
                'organization_name' => [
                    'required',
                    Rule::unique('agencies')->ignore($request->id),
                ],
                'organization_email' => [
                    'required',
                    'email',
                    Rule::unique('agencies')->ignore($request->id),
                ],
                'phone' => 'required',
            ]
        );
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422); // Added proper HTTP status code for validation errors
        }
    
        $agency = Agency::find($request->id);
        if (!$agency) {
            return response()->json([
                'status' => 'error',
                'message' => 'Agency not found.',
            ], 404); // Added proper HTTP status code for not found
        }
    
        // Check authorization before proceeding with update
        if (!(\Auth::user()->type == 'super admin' || \Auth::user()->can('Edit Agency'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.',
            ], 403); // Added proper HTTP status code for forbidden
        }
    
        // Update agency fields
        $agency->type = 'Agency';
        $agency->phone = $request->phone;
        $agency->organization_name =  $request->organization_name;
        $agency->organization_email =  $request->organization_email;
        $agency->website = $request->website;
        $agency->linkedin = $request->linkedin;
        $agency->facebook = $request->facebook;
        $agency->twitter = $request->twitter;
        $agency->billing_street = $request->billing_street;
        $agency->contactname = $request->contactname;
        $agency->contactemail = $request->contactemail;
        $agency->contactphone = $request->contactphone;
        $agency->contactjobroll = $request->contactjobroll;
        $agency->billing_country = $request->billing_country ?? '';
        $agency->description = $request->description;
        $agency->user_id = \Auth::id();
        $agency->city = $request->city ?? '';
        $agency->c_address = $request->c_address;
        $agency->save();
    
       
    
            // Log activity
            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Organization Updated',
                    'message' => 'Organization updated successfully',
                ]),
                'module_id' => $agency->id,
                'module_type' => 'agency',
                'notification_type' => 'Organization Updated',
            ];
            addLogActivity($data);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Agency updated successfully!',
        ]);
    }


    public function destroy($id)
    {

        if (\Auth::user()->type == 'super admin' || \Auth::user()->can('Delete Agency')) {
            
            $org_data =  Agency::find($id);

            if (!empty($org_data)){
                $org_data->delete();
                return redirect()->route('agency.index')->with('success', __('Organization successfully deleted!'));
            }else{
                return response()->json(['error' => __('Data Not Found')], 401);
            }
            
    
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    public function deleteBulkAgency(Request $request)
    {
        if ($request->ids != null) {
            $Agencies = Agency::whereIn('id', explode(',', $request->ids))->get();
            foreach($Agencies as $Agency){
               User::where('id', $Agency->user_id)->where('type', '=', 'agency')->delete();
               $Agency->delete();
            }
            return redirect()->route('agency.index')->with('success', 'Agency deleted successfully');
        } else {
            return redirect()->route('agency.index')->with('error', 'Atleast select 1 organization.');
        }
    }


    public function notesStore(Request $request)
    {


        $validator = \Validator::make(
            $request->all(),
            [
                // 'title' => 'required',
                'description' => 'required'
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return json_encode([
                'status' => 'error',
                'message' =>  $messages->first()
            ]);
        }
        $id = $request->id;

        if ($request->note_id != null && $request->note_id != '') {
            $note = AgencyNote::where('id', $request->note_id)->first();
            // $note->title = $request->input('title');
            $note->description = $request->input('description');
            $note->update();

            $data = [
                'type' => 'info',
                'note' => json_encode([
                    'title' => 'Agency Notes Updated',
                    'message' => 'Agency notes updated successfully'
                ]),
                'module_id' => $request->id,
                'module_type' => 'agency',
                'notification_type' => 'Agency Notes Updated'
            ];
            addLogActivity($data);


            $notesQuery = AgencyNote::where('agency_id', $id);

            if(\Auth::user()->type != 'super admin' && \Auth::user()->type != 'Project Director' && \Auth::user()->type != 'Project Manager') {
                    $notesQuery->where('created_by', \Auth::user()->id);
            }
            $notes = $notesQuery->orderBy('created_at', 'DESC')->get();
            $html = view('leads.getNotes', compact('notes'))->render();

            return json_encode([
                'status' => 'success',
                'html' => $html,
                'message' =>  __('Notes updated successfully')
            ]);
        }
        $note = new AgencyNote;
        // $note->title = $request->input('title');
        $note->description = $request->input('description');
        $session_id = Session::get('auth_type_id');
        if ($session_id != null) {
            $note->created_by  = $session_id;
        } else {
            $note->created_by  = \Auth::user()->id;
        }
        $note->agency_id = $id;
        $note->save();


        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Notes created',
                'message' => 'Noted created successfully'
            ]),
            'module_id' => $id,
            'module_type' => 'agency',
            'notification_type' => 'Notes created'
        ];
        addLogActivity($data);


        $notesQuery = AgencyNote::where('agency_id', $id);

        if(\Auth::user()->type != 'super admin' && \Auth::user()->type != 'Project Director' && \Auth::user()->type != 'Project Manager') {
                $notesQuery->where('created_by', \Auth::user()->id);
        }
        $notes = $notesQuery->orderBy('created_at', 'DESC')->get();

        $html = view('leads.getNotes', compact('notes'))->render();

        return json_encode([
            'status' => 'success',
            'html' => $html,
            'message' =>  __('Notes added successfully')
        ]);

        //return redirect()->back()->with('success', __('Notes added successfully'));
    }

    public function UpdateFromAgencyNoteForm(Request $request)
    {
        $note = AgencyNote::where('id', $request->id)->first();

        $html = view('agency.getNotesForm', compact('note'))->render();

        return json_encode([
            'status' => 'success',
            'html' => $html,
            'message' =>  __('Notes added successfully')
        ]);
    }


    public function notesEdit($id)
    {
        $note = AgencyNote::where('id', $id)->first();
        return view('leads.notes_edit', compact('note'));
    }

    public function notesUpdate(Request $request, $id)
    {


        $validator = \Validator::make(
            $request->all(),
            [
                // 'title' => 'required',
                'description' => 'required'
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return json_encode([
                'status' => 'error',
                'message' =>  $messages->first()
            ]);
        }

        $note = AgencyNote::where('id', $request->note_id)->first();
        $note->title = $request->input('title');
        $note->description = $request->input('description');
        $note->update();

        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Agency Notes Updated',
                'message' => 'Agency notes updated successfully'
            ]),
            'module_id' => $request->id,
            'module_type' => 'agency',
            'notification_type' => 'Agency Notes Updated'
        ];
        addLogActivity($data);


        $notes = AgencyNote::where('agency_id', $id)->orderBy('created_at', 'DESC')->get();
        $html = view('agency.getNotes', compact('notes'))->render();

        return json_encode([
            'status' => 'success',
            'html' => $html,
            'message' =>  __('Notes updated successfully')
        ]);
    }


    public function notesDelete(Request $request, $id)
    {

        $note = AgencyNote::where('id', $id)->first();
        $note->delete();

        $notesQuery = AgencyNote::where('agency_id', $request->lead_id);
        if(\Auth::user()->type != 'super admin' && \Auth::user()->type != 'Project Director' && \Auth::user()->type != 'Project Manager') {
                $notesQuery->where('created_by', \Auth::user()->id);
        }
        $notes = $notesQuery->orderBy('created_at', 'DESC')->get();
        $html = view('leads.getNotes', compact('notes'))->render();


        $data = [
            'type' => 'info',
            'note' => json_encode([
                'title' => 'Agency Notes Deleted',
                'message' => 'Agency notes deleted successfully'
            ]),
            'module_id' => $request->lead_id,
            'module_type' => 'agency',
            'notification_type' => 'Agency Notes Deleted'
        ];
        addLogActivity($data);


        return json_encode([
            'status' => 'success',
            'html' => $html,
            'message' =>  __('Notes deleted successfully')
        ]);
    }

}
