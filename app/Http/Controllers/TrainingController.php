<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\SavedFilter;
use App\Models\Trainer;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class TrainingController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage training')) {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);

            // Build the leads query
            $Trainer_query = Training::select('trainings.id', 'regions.name as region', 'branches.name as branch', 'users.name as brand', 'trainings.id', 'trainings.training_cost', 'trainings.created_by', 'trainings.status', 'trainings.training_type', 'trainings.trainer', 'assigned_to.name as assignName')
                ->leftJoin('users', 'users.id', '=', 'trainings.brand_id')
                ->leftJoin('branches', 'branches.id', '=', 'trainings.branch_id')
                ->leftJoin('regions', 'regions.id', '=', 'trainings.region_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'trainings.employee');

            // Apply user type-based filtering
            $userType = \Auth::user()->type;
            if (in_array($userType, ['super admin', 'Admin Team']) || \Auth::user()->can('level 1')) {
                // No additional filtering needed
            } elseif ($userType === 'company') {
                $Trainer_query->where('trainings.brand_id', \Auth::user()->id);
            } elseif (in_array($userType, ['Project Director', 'Project Manager']) || \Auth::user()->can('level 2')) {
                $Trainer_query->whereIn('trainings.brand_id', $brand_ids);
            } elseif (($userType === 'Region Manager' || \Auth::user()->can('level 3')) && !empty(\Auth::user()->region_id)) {
                $Trainer_query->where('trainings.region_id', \Auth::user()->region_id);
            } elseif (($userType === 'Branch Manager' || in_array($userType, ['Admissions Officer', 'Admissions Manager', 'Marketing Officer'])) || \Auth::user()->can('level 4') && !empty(\Auth::user()->branch_id)) {
                $Trainer_query->where('trainings.branch_id', \Auth::user()->branch_id);
            } else {
                $Trainer_query->where('trainings.created_by', \Auth::user()->id);
            }

            $filters = $this->TraningFilters();

            foreach ($filters as $column => $value) {
                if ($column == 'created_at') {
                    $Trainer_query->whereDate('trainings.created_at', 'LIKE', '%' . substr($value, 0, 10) . '%');
                } elseif ($column == 'brand') {
                    $Trainer_query->where('trainings.brand_id', $value);
                } elseif ($column == 'region_id') {
                    $Trainer_query->where('trainings.region_id', $value);
                } elseif ($column == 'branch_id') {
                    $Trainer_query->where('trainings.branch_id', $value);
                }
            }

            $trainings = $Trainer_query->get();
            $status    = Training::$Status;
            $saved_filters = SavedFilter::where('created_by', \Auth::id())->where('module', 'leads')->get();
            $filters = BrandsRegionsBranches();
            return view('training.index', compact('filters', 'trainings', 'status', 'saved_filters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    private function TraningFilters()
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


    public function create()
    {
        if (\Auth::user()->can('create training')) {
            $trainingTypes = TrainingType::where('created_by', \Auth::id())->get()->pluck('name', 'id');
            $trainers      = GetTrainers();
            $options       = Training::$options;
            // for all Brands Regions Branches
            $filter = BrandsRegionsBranches();
            $companies = $filter['brands'];
            $regions = $filter['regions'];
            $branches = $filter['branches'];
            $employees = $filter['employees'];
            return view('training.create', compact('employees', 'regions', 'companies', 'branches', 'trainingTypes', 'trainers', 'employees', 'options'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create training')) {

            $validator = \Validator::make(
                $request->all(),
                [
                    'brand_id' => 'required|numeric|min:1',
                    'region_id' => 'required|numeric|min:1',
                    'lead_branch' => 'required|numeric|min:1',
                    'training_type' => 'required',
                    'training_cost' => 'required',
                    'lead_assigned_user' => 'required',
                    'start_date' => 'required',
                    'end_date' => 'required',
                ]
            );
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ]);
            }

            $training                 = new Training();
            $training->brand_id         = $request->brand_id;
            $training->region_id      = $request->region_id;
            $training->branch_id         = $request->lead_branch;
            $training->trainer_option = $request->trainer_option;
            $training->training_type  = $request->training_type;
            $training->trainer        = $request->trainer;
            $training->training_cost  = $request->training_cost;
            $training->employee       = $request->lead_assigned_user;
            $training->start_date     = $request->start_date;
            $training->end_date       = $request->end_date;
            $training->description    = $request->description;
            $training->created_by     = \Auth::id();
            $training->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Training created successfully.',
                'id' => $training->id
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ]);
        }
    }


    public function TrainingShow(Request $request)
    {
        $training = Training::select('trainings.id', 'regions.name as region', 'branches.name as branch', 'users.name as brand', 'trainings.id', 'trainings.training_cost', 'trainings.created_by', 'trainings.status', 'trainings.training_type', 'trainings.trainer', 'assigned_to.name as assignName', 'trainings.created_at', 'trainings.updated_at')
            ->leftJoin('users', 'users.id', '=', 'trainings.brand_id')
            ->leftJoin('branches', 'branches.id', '=', 'trainings.branch_id')
            ->leftJoin('regions', 'regions.id', '=', 'trainings.region_id')
            ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'trainings.employee')
            ->where('trainings.id', $request->id)->first();

        $status    = Training::$Status;

        $html = view('training.show', compact('training', 'status'))->render();

        return json_encode([
            'status' => 'success',
            'html' => $html
        ]);
    }


    public function edit(Training $training)
    {
        if (\Auth::user()->can('create training')) {
            $filter = BrandsRegionsBranchesForEdit($training->brand_id, $training->region_id, $training->branch_id);
            $companies = $filter['brands'];
            $regions = $filter['regions'];
            $branches = $filter['branches'];
            $employees = $filter['employees'];
            $trainingTypes = TrainingType::where('created_by', \Auth::id())->get()->pluck('name', 'id');
            $trainers      = GetTrainers();
            $options       = Training::$options;

            return view('training.edit', compact('branches', 'companies', 'regions', 'branches', 'trainingTypes', 'trainers', 'employees', 'options', 'training'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function update(Request $request, Training $training)
    {
        if (\Auth::user()->can('edit training')) {

            $validator = \Validator::make(
                $request->all(),
                [
                    'brand_id' => 'required|numeric|min:1',
                    'region_id' => 'required|numeric|min:1',
                    'lead_branch' => 'required|numeric|min:1',
                    'training_type' => 'required',
                    'training_cost' => 'required',
                    'lead_assigned_user' => 'required',
                    'start_date' => 'required',
                    'end_date' => 'required',
                ]
            );
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validator->errors()->first()
                ]);
            }

            $training->brand_id         = $request->brand_id;
            $training->region_id      = $request->region_id;
            $training->branch_id         = $request->lead_branch;
            $training->trainer_option = $request->trainer_option;
            $training->training_type  = $request->training_type;
            $training->trainer        = $request->trainer;
            $training->training_cost  = $request->training_cost;
            $training->employee       = $request->lead_assigned_user;
            $training->start_date     = $request->start_date;
            $training->end_date       = $request->end_date;
            $training->description    = $request->description;
            $training->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Training created successfully.',
                'id' => $training->id
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission denied.')
            ]);
        }
    }


    public function destroy(Training $training)
    {
        if (\Auth::user()->can('delete training')) {
            if ($training->created_by == \Auth::id()) {
                $training->delete();

                return redirect()->route('training.index')->with('success', __('Training successfully deleted.'));
            } else {
                return redirect()->back()->with('error', __('Permission denied.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function updateStatus(Request $request)
    {
        $training              = Training::find($request->id);
        $training->performance = $request->performance;
        $training->status      = $request->status;
        $training->remarks     = $request->remarks;
        $training->save();

        return redirect()->route('training.index')->with('success', __('Training status successfully updated.'));
    }

    public function HrmTraining()
    {
        if (isset($_GET['emp_id'])) {
            $userId = $_GET['emp_id'];
        } else {
            $userId = \Auth::id();
        }

        $AuthUser = User::find($userId);
        if (\Auth::user()->can('manage training')) {
            $companies = FiltersBrands();
            $brand_ids = array_keys($companies);

            // Build the leads query
            $Trainer_query = Training::select('trainings.id', 'regions.name as region', 'branches.name as branch', 'users.name as brand', 'trainings.id', 'trainings.training_cost', 'trainings.created_by', 'trainings.status', 'trainings.training_type', 'trainings.trainer', 'assigned_to.name as assignName')
                ->leftJoin('users', 'users.id', '=', 'trainings.brand_id')
                ->leftJoin('branches', 'branches.id', '=', 'trainings.branch_id')
                ->leftJoin('regions', 'regions.id', '=', 'trainings.region_id')
                ->leftJoin('users as assigned_to', 'assigned_to.id', '=', 'trainings.employee');

            $Trainer_query->where('trainings.employee', $userId);

            $trainings = $Trainer_query->get();
            $status    = Training::$Status;
            $saved_filters = SavedFilter::where('created_by', \Auth::id())->where('module', 'leads')->get();
            $filters = BrandsRegionsBranches();

            return view('hrmhome.training', compact('AuthUser', 'filters', 'trainings', 'status', 'saved_filters'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function HrmTraningCreate(Request $request)
    {
        if (\Auth::user()->can('create training')) {

            $trainingTypes = TrainingType::where('created_by', $request->emp_id)->get()->pluck('name', 'id');
            $trainers      = GetTrainers();
            $options       = Training::$options;
            // for all Brands Regions Branches
            $users = User::find($request->emp_id);
            $filter = BrandsRegionsBranchesForEdit($users->brand_id, $users->region_id, $users->branch_id);
            $companies = $filter['brands'];
            $regions = $filter['regions'];
            $branches = $filter['branches'];
            $employees = $filter['employees'];
            return view('hrmhome.HrmTraningCreate', compact('users', 'employees', 'regions', 'companies', 'branches', 'trainingTypes', 'trainers', 'employees', 'options'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
