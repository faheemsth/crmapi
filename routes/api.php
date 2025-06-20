<?php

use App\Http\Controllers\AgencyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginRegisterController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AllowanceController;
use App\Http\Controllers\AllowanceOptionController;
use App\Http\Controllers\ApplicationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaySlipController;
use App\Http\Controllers\AppraisalController;
use App\Http\Controllers\AttendanceEmployeeController;
use App\Http\Controllers\AwardTypeController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommissionsController;
use App\Http\Controllers\CompetenciesController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\CustomQuestionController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DeductionController;
use App\Http\Controllers\DeductionOptionController;
use App\Http\Controllers\InterviewScheduleController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\PayslipTypeController;
use App\Http\Controllers\TrainingTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GoalTrackingController;
use App\Http\Controllers\GoalTypeController;
use App\Http\Controllers\IndicatorController;
use App\Http\Controllers\InstituteCategoryController;
use App\Http\Controllers\InstituteController;
use App\Http\Controllers\JobCategoryController;
use App\Http\Controllers\JobStageController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\LoanOptionController;
use App\Http\Controllers\OtherPaymentController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MoiAcceptedController;
use App\Http\Controllers\PerformanceTypeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SetSalaryController;
use App\Http\Controllers\TerminationTypeController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\UniversityMetaController;
use App\Http\Controllers\UniversityRankController;
use App\Http\Controllers\UniversityRuleController;
use App\Http\Controllers\UserController;
use App\Models\InterviewSchedule;
use App\Models\JobCategory;
use App\Models\TaskFile;
use App\Models\TrainingType;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes of authtication
Route::controller(LoginRegisterController::class)->group(function () {
    Route::post('/register', 'register');
    Route::post('/registerAgent', 'registerAgent');
    Route::post('/login', 'login');
    Route::post('/validateEmpId', 'validateEmpId');
    Route::post('/encryptDataEmpId', 'encryptDataEmpId');
    Route::post('/googlelogin', 'googlelogin');
    Route::post('/changePassword', 'changePassword');
});

//Route::get('/appMeta', [ProductController::class, 'appMeta']);
Route::post('/jobRequirement', [JobController::class, 'jobRequirement']);
Route::post('/jobApplyData', [JobController::class, 'jobApplyData']);


// Protected routes of product and logout
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/userDetail', [LoginRegisterController::class, 'userDetail']);
    Route::post('/getProfileData', [UserController::class, 'getProfileData']);
    Route::post('/updateUserStatus', [UserController::class, 'updateUserStatus']);
    Route::post('/logout', [LoginRegisterController::class, 'logout']);
    Route::get('/agentRequestGet', [AgentController::class, 'agentRequestGet']);
    Route::post('/agentRequestPost', [AgentController::class, 'agentRequestPost']);
    Route::post('/userTasksGet', [TaskController::class, 'userTasksGet']);
    Route::post('/createtask', [TaskController::class, 'createtask']);
    Route::post('/taskUpdate', [TaskController::class, 'taskUpdate']);
    Route::post('/updateTaskStatus', [TaskController::class, 'updateTaskStatus']);
    Route::post('/ShuffleTaskOwnership', [TaskController::class, 'ShuffleTaskOwnership']);
    Route::post('/getTaskDetails', [TaskController::class, 'getTaskDetails']);
    Route::post('/TaskDetails', [TaskController::class, 'TaskDetails']);
    
    Route::post('/taskDiscussionStore', [TaskController::class, 'taskDiscussionStore']);
    Route::post('/taskDiscussionUpdate', [TaskController::class, 'taskDiscussionUpdate']);
    Route::post('/taskDiscussionDelete', [TaskController::class, 'taskDiscussionDelete']);
    Route::post('/GetTaskDiscussion', [TaskController::class, 'GetTaskDiscussion']);

    Route::post('/taskDelete', [TaskController::class, 'taskDelete']);
    Route::get('/downloadTasks', [TaskController::class, 'downloadTasks']);
    Route::post('/ApprovedTaskStatus', [TaskController::class, 'ApprovedTaskStatus']);
    Route::post('/GetTaskByRelatedToRelatedType', [TaskController::class, 'GetTaskByRelatedToRelatedType']);

    

    // Leads start here
    Route::post('/getLeads', [LeadController::class, 'getLeads']);
    Route::post('/fetchColumns', [LeadController::class, 'fetchColumns']);
    Route::post('/importCsv', [LeadController::class, 'importCsv']);
    Route::post('/getLeadDetails', [LeadController::class, 'getLeadDetails']);
    Route::post('/getLeadDetailOnly', [LeadController::class, 'getLeadDetailOnly']);
    Route::post('/saveLead', [LeadController::class, 'saveLead']);
    Route::post('/updateLead', [LeadController::class, 'updateLead']);
    Route::post('/deleteBulkLeads', [LeadController::class, 'deleteBulkLeads']);
    Route::post('/updateBulkLead', [LeadController::class, 'updateBulkLead']);
    Route::post('/addLeadTags', [LeadController::class, 'addLeadTags']);
    Route::post('/convertToAdmission', [LeadController::class, 'convertToAdmission']);
    Route::post('/leadsLabels', [LeadController::class, 'leadsLabels']);
    Route::post('/leadLabelStore', [LeadController::class, 'leadLabelStore']);
    Route::post('/leadsDelete', [LeadController::class, 'leadsDelete']);
    Route::post('/updateLeadStage', [LeadController::class, 'updateLeadStage']);
    Route::post('/StageHistory', [LeadController::class, 'StageHistory']);
    Route::post('/LeadOrgnizationUpdate', [LeadController::class, 'LeadOrgnizationUpdate']);
    Route::post('/LeadDriveLinkUpdate', [LeadController::class, 'LeadDriveLinkUpdate']);
    Route::post('/CreateOrUpdateLeadNotes', [LeadController::class, 'CreateOrUpdateLeadNotes']);
    Route::post('/GetLeadNotes', [LeadController::class, 'GetLeadNotes']);
    Route::post('/DeleteLeadNotes', [LeadController::class, 'DeleteLeadNotes']);



    // user/brands
    Route::post('/getBrands', [UserController::class, 'getBrands']);
    Route::post('/addBrand', [UserController::class, 'addBrand']);
    Route::post('/updateBrand', [UserController::class, 'updateBrand']);
    Route::post('/deleteBrand', [UserController::class, 'deleteBrand']);
    Route::post('/brandDetail', [UserController::class, 'brandDetail']);

    // emergency/contact
    Route::post('/emergency-contact', [UserController::class, 'EmergencyContactPost']);
    Route::put('/emergency-contact', [UserController::class, 'EmergencyContactUpdate']);
    Route::delete('/emergency-contact', [UserController::class, 'EmergencyContactDelete']);

    // additional/address
    Route::post('/additional-address', [UserController::class, 'AdditionalAddressPost']);
    Route::post('/additional-address-update', [UserController::class, 'AdditionalAddressUpdate']);
    Route::post('/additional-address-delete', [UserController::class, 'AdditionalAddressDelete']);
    Route::post('/getAdditionalAddresses/{userId}', [UserController::class, 'getAdditionalAddresses']);


    // UserEmployeeFileUpdate
    Route::post('/UserEmployeeFileUpdate', [UserController::class, 'UserEmployeeFileUpdate']);
    Route::post('/employeeFileAttachments', [UserController::class, 'employeeFileAttachments']);


    // trainers
    Route::post('/getTrainers', [TrainerController::class, 'getTrainers']);
    Route::post('/Trainers', [TrainerController::class, 'Trainers']);
    Route::post('/addTrainer', [TrainerController::class, 'addTrainer']);
    Route::post('/updateTrainer', [TrainerController::class, 'updateTrainer']);
    Route::post('/deleteTrainer', [TrainerController::class, 'deleteTrainer']);
    Route::post('/trainerDetail', [TrainerController::class, 'trainerDetail']);

    // Training
    Route::post('/getTraining', [TrainingController::class, 'getTraining']);
    Route::post('/addTraining', [TrainingController::class, 'addTraining']);
    Route::post('/updateTraining', [TrainingController::class, 'updateTraining']);
    Route::post('/deleteTraining', [TrainingController::class, 'deleteTraining']);
    Route::post('/TrainingDetail', [TrainingController::class, 'TrainingDetail']);

    // Training
    Route::post('/getIndicators', [IndicatorController::class, 'getIndicators']);
    Route::post('/addIndicator', [IndicatorController::class, 'addIndicator']);
    Route::post('/updateIndicator', [IndicatorController::class, 'updateIndicator']);
    Route::post('/deleteIndicator', [IndicatorController::class, 'deleteIndicator']);
    Route::post('/indicatorDetail', [IndicatorController::class, 'indicatorDetail']);

    // Apraisals
    Route::post('/getAppraisals', [AppraisalController::class, 'getAppraisals']);
    Route::post('/addApraisal', [AppraisalController::class, 'addApraisal']);
    Route::post('/updateAppraisal', [AppraisalController::class, 'updateAppraisal']);
    Route::post('/deleteAppraisal', [AppraisalController::class, 'deleteAppraisal']);
    Route::post('/appraisalDetails', [AppraisalController::class, 'appraisalDetails']);
    Route::post('/fetchperformance', [AppraisalController::class, 'fetchperformance']);
    Route::post('/fetchperformanceedit', [AppraisalController::class, 'fetchperformanceedit']);

    // SetSalaries
    Route::post('/getSetSalaries', [SetSalaryController::class, 'getSetSalaries']);

    // Leaves
    Route::post('/getLeaves', [LeaveController::class, 'getLeaves']);
    Route::post('/addLeave', [LeaveController::class, 'addLeave']);
    Route::post('/updateLeave', [LeaveController::class, 'updateLeave']);
    Route::post('/deleteLeave', [LeaveController::class, 'deleteLeave']);
    Route::post('/Balance', [LeaveController::class, 'Balance']);
    Route::post('/changeLeaveStatus', [LeaveController::class, 'changeLeaveStatus']);
    // Leaves
    Route::post('/getAttendances', [AttendanceEmployeeController::class, 'getAttendances']);
    Route::post('/addAttendance', [AttendanceEmployeeController::class, 'addAttendance']);
    Route::post('/updateAttendance', [AttendanceEmployeeController::class, 'updateAttendance']);
    Route::post('/deleteAttendance', [AttendanceEmployeeController::class, 'deleteAttendance']);

    // AllowanceController
    Route::post('/getAllowances', [AllowanceController::class, 'getAllowances']);
    Route::post('/addEmpoyeeAllowance', [AllowanceController::class, 'addEmpoyeeAllowance']);
    Route::post('/updateEmployeeAllowance', [AllowanceController::class, 'updateEmployeeAllowance']);
    Route::post('/deleteEmployeeAllownce', [AllowanceController::class, 'deleteEmployeeAllownce']);


    Route::post('/getDeductions', [DeductionController::class, 'getDeductions']);
    Route::post('/addEmployeeDeduction', [DeductionController::class, 'addEmployeeDeduction']);
    Route::post('/updateEmployeeDeduction', [DeductionController::class, 'updateEmployeeDeduction']);
    Route::post('/deleteEmployeeDeduction', [DeductionController::class, 'deleteEmployeeDeduction']);

    Route::post('/getOvertimes', [OvertimeController::class, 'getOvertimes']);
    Route::post('/addEmployeeOvertime', [OvertimeController::class, 'addEmployeeOvertime']);
    Route::post('/updateEmployeeOvertime', [OvertimeController::class, 'updateEmployeeOvertime']);
    Route::post('/deleteEmployeeOvertime', [OvertimeController::class, 'deleteEmployeeOvertime']);

    Route::post('/getLoans', [LoanController::class, 'getLoans']);
    Route::post('/addEmployeeLoan', [LoanController::class, 'addEmployeeLoan']);
    Route::post('/updateEmployeeLoan', [LoanController::class, 'updateEmployeeLoan']);
    Route::post('/deleteEmployeeLoan', [LoanController::class, 'deleteEmployeeLoan']);


    // CommissionsController
    Route::post('/getCommissions', [CommissionsController::class, 'getCommissions']);
    Route::post('/addEmpoyeeCommissions', [CommissionsController::class, 'addEmpoyeeCommissions']);
    Route::post('/updateEmployeeCommissions', [CommissionsController::class, 'updateEmployeeCommissions']);
    Route::post('/deleteEmployeeCommissions', [CommissionsController::class, 'deleteEmployeeCommissions']);

    // OtherPaymentController
    Route::post('/getOtherPayments', [OtherPaymentController::class, 'getOtherPayments']);
    Route::post('/addEmpoyeeOtherPayment', [OtherPaymentController::class, 'addEmpoyeeOtherPayment']);
    Route::post('/updateEmployeeOtherPayment', [OtherPaymentController::class, 'updateEmployeeOtherPayment']);
    Route::post('/deleteEmployeeOtherPayment', [OtherPaymentController::class, 'deleteEmployeeOtherPayment']);

    // agency
    Route::post('/getagency', [AgencyController::class, 'index']);
    Route::post('/storeagency', [AgencyController::class, 'storeagency']);
    Route::post('/updateagency', [AgencyController::class, 'updateagency']);
    Route::post('/GetAgencyDetail', [AgencyController::class, 'GetAgencyDetail']);

    // Apraisals
    Route::post('/getGoalTrackings', [GoalTrackingController::class, 'getGoalTrackings']);
    Route::post('/addGoalTracking', [GoalTrackingController::class, 'addGoalTracking']);
    Route::post('/updateGoalTracking', [GoalTrackingController::class, 'updateGoalTracking']);
    Route::post('/deleteGoalTracking', [GoalTrackingController::class, 'deleteGoalTracking']);
    Route::post('/goalTrackingDetail', [GoalTrackingController::class, 'goalTrackingDetail']);

    Route::resource('leavetype', LeaveTypeController::class);
    Route::post('/leavetype-pluck', [LeaveTypeController::class, 'plucktitle']);


    // user/employees
    Route::post('/getEmployees', [UserController::class, 'getEmployees']);
    Route::get('/employees', [UserController::class, 'employees']);
    Route::get('/Pluck_All_Users', [UserController::class, 'Pluck_All_Users']);

    Route::get('/get/employee/Details', [UserController::class, 'EmployeeDetails']);
    Route::post('/createEmployee', [UserController::class, 'createEmployee']);

    // Hrm Internal Employee Note Create
    Route::post('/EmployeeNoteGet', [UserController::class, 'HrmInternalEmployeeNoteGet']);
    Route::post('/EmployeeNoteUpdate', [UserController::class, 'HrmInternalEmployeeNoteUpdate']);
    Route::post('/EmployeeNoteStore', [UserController::class, 'HrmInternalEmployeeNoteStore']);
    Route::post('/EmployeeNoteDelete', [UserController::class, 'HrmInternalEmployeeNoteDelete']);

    //organization 
    Route::post('getorganization', [OrganizationController::class, 'getorganization']);
    Route::post('organizationstore', [OrganizationController::class, 'organizationstore']);
    Route::post('organizationupdate', [OrganizationController::class, 'organizationupdate']);
    
    Route::post('organizationshow', [OrganizationController::class, 'organizationshow']);

    // Branches
    Route::post('/getRegions', [RegionController::class, 'getRegions']);
    Route::post('/addRegion', [RegionController::class, 'addRegion']);
    Route::post('/updateRegion', [RegionController::class, 'updateRegion']);
    Route::post('/deleteRegion', [RegionController::class, 'deleteRegion']);
    Route::post('/deleteBulkRegions', [RegionController::class, 'deleteBulkRegions']);
    Route::post('/regionDetail', [RegionController::class, 'regionDetail']);

    // Branches
    Route::post('/branchDetail', [BranchController::class, 'branchDetail']);
    Route::post('/getBrancheslist', [BranchController::class, 'getBranches']);
    Route::post('/addBranch', [BranchController::class, 'addBranch']);
    Route::post('/updateBranch', [BranchController::class, 'updateBranch']);
    Route::post('/deleteBranch', [BranchController::class, 'deleteBranch']);

    // Training type
    Route::post('/addTrainingType', [TrainingTypeController::class, 'addTrainingType']);

    Route::get('/TrainingTypes', [TrainingTypeController::class, 'TrainingTypes']);
    Route::get('/getTrainingTypes', [TrainingTypeController::class, 'getTrainingTypes']);
    Route::post('/updateTrainType', [TrainingTypeController::class, 'updateTrainType']);
    Route::post('/deleteTrainingType', [TrainingTypeController::class, 'deleteTrainingType']);

    // Department
    Route::post('/addDepartment', [DepartmentController::class, 'addDepartment']);
    Route::get('/getDepartments', [DepartmentController::class, 'getDepartments']);
    Route::post('/updateDepartment', [DepartmentController::class, 'updateDepartment']);
    Route::post('/deleteDepartment', [DepartmentController::class, 'deleteDepartment']);
    Route::post('/departmentsPluck', [DepartmentController::class, 'departmentsPluck']);



    // AllowanceOption
    Route::post('/addAllowanceOption', [AllowanceOptionController::class, 'addAllowanceOption']);
    Route::get('/getAllowanceOptions', [AllowanceOptionController::class, 'getAllowanceOptions']);
    Route::post('/updateAllowanceOption', [AllowanceOptionController::class, 'updateAllowanceOption']);
    Route::post('/deleteAllowanceOption', [AllowanceOptionController::class, 'deleteAllowanceOption']);
    Route::get('/pluckAllowanceOptions', [AllowanceOptionController::class, 'pluckAllowanceOptions']);

    // LoanOption
    Route::post('/addLoanOption', [LoanOptionController::class, 'addLoanOption']);
    Route::get('/getLoanOptions', [LoanOptionController::class, 'getLoanOptions']);
    Route::get('/pluckLoanOption', [LoanOptionController::class, 'pluckLoanOption']);
    Route::post('/updateLoanOption', [LoanOptionController::class, 'updateLoanOption']);
    Route::post('/deleteLoanOption', [LoanOptionController::class, 'deleteLoanOption']);

    // DeductionOption
    Route::post('/addDeductionOption', [DeductionOptionController::class, 'addDeductionOption']);
    Route::get('/getDeductionOptions', [DeductionOptionController::class, 'getDeductionOptions']);
    Route::get('/pluckDeductionOption', [DeductionOptionController::class, 'pluckDeductionOption']);
    Route::post('/updateDeductionOption', [DeductionOptionController::class, 'updateDeductionOption']);
    Route::post('/deleteDeductionOption', [DeductionOptionController::class, 'deleteDeductionOption']);

    // GoalType
    Route::post('/addGoalType', [GoalTypeController::class, 'addGoalType']);
    Route::get('/getGoalTypes', [GoalTypeController::class, 'getGoalTypes']);
    Route::get('/pluckGoalTypes', [GoalTypeController::class, 'pluckGoalTypes']);
    Route::post('/updateGoalType', [GoalTypeController::class, 'updateGoalType']);
    Route::post('/deleteGoalType', [GoalTypeController::class, 'deleteGoalType']);

    // UniversityRank
    Route::post('/addUniversityRank', [UniversityRankController::class, 'addUniversityRank']);
    Route::get('/getUniversityRanks', [UniversityRankController::class, 'getUniversityRanks']);
    Route::get('/pluckUniversityRanks', [UniversityRankController::class, 'pluckUniversityRanks']);
    Route::post('/updateUniversityRank', [UniversityRankController::class, 'updateUniversityRank']);
    Route::post('/deleteUniversityRank', [UniversityRankController::class, 'deleteUniversityRank']);

    // AwardType
    Route::post('/addAwardType', [AwardTypeController::class, 'addAwardType']);
    Route::get('/getAwardTypes', [AwardTypeController::class, 'getAwardTypes']);
    Route::post('/updateAwardType', [AwardTypeController::class, 'updateAwardType']);
    Route::post('/deleteAwardType', [AwardTypeController::class, 'deleteAwardType']);

    // TerminationType
    Route::post('/addTerminationType', [TerminationTypeController::class, 'addTerminationType']);
    Route::get('/getTerminationTypes', [TerminationTypeController::class, 'getTerminationTypes']);
    Route::post('/updateTerminationType', [TerminationTypeController::class, 'updateTerminationType']);
    Route::post('/deleteTerminationType', [TerminationTypeController::class, 'deleteTerminationType']);

    // JobCategory
    Route::post('/addJobCategory', [JobCategoryController::class, 'addJobCategory']);
    Route::get('/getJobCategorieslist', [JobCategoryController::class, 'getJobCategories']);
    Route::post('/updateJobCategory', [JobCategoryController::class, 'updateJobCategory']);
    Route::post('/deleteJobCategory', [JobCategoryController::class, 'deleteJobCategory']);

    // JobStage
    Route::post('/addJobStage', [JobStageController::class, 'addJobStage']);
    Route::get('/getJobStages', [JobStageController::class, 'getJobStages']);
    Route::get('/PluckJobStages', [JobStageController::class, 'PluckJobStages']);
    Route::post('/updateJobStage', [JobStageController::class, 'updateJobStage']);
    Route::post('/deleteJobStage', [JobStageController::class, 'deleteJobStage']);


    // PerformanceType
    Route::post('/addPerformanceType', [PerformanceTypeController::class, 'addPerformanceType']);
    Route::get('/getPerformanceTypes', [PerformanceTypeController::class, 'getPerformanceTypes']);
    Route::post('/updatePerformanceType', [PerformanceTypeController::class, 'updatePerformanceType']);
    Route::post('/deletePerformanceType', [PerformanceTypeController::class, 'deletePerformanceType']);


    // Competency
    Route::post('/addCompetency', [CompetenciesController::class, 'addCompetency']);
    Route::get('/getCompetencies', [CompetenciesController::class, 'getCompetencies']);
    Route::post('/updateCompetency', [CompetenciesController::class, 'updateCompetency']);
    Route::post('/deleteCompetency', [CompetenciesController::class, 'deleteCompetency']);
    Route::post('/getCompetenciesByType', [CompetenciesController::class, 'getCompetenciesByType']);

    // Branches
    Route::post('/getInterviews', [InterviewScheduleController::class, 'getInterviews']);
    Route::post('/showInterviews', [InterviewScheduleController::class, 'show']);
    Route::post('/updateInterviews', [InterviewScheduleController::class, 'update']);
    Route::post('/deleteInterviews', [InterviewScheduleController::class, 'destroy']);
    Route::post('/addInterveiw', [InterviewScheduleController::class, 'addInterveiw']);

    // Courses
    Route::post('/getCourses', [CourseController::class, 'getCourses']);
    Route::post('/showCourses', [CourseController::class, 'show']);
    Route::post('/updateCourses', [CourseController::class, 'updateCourses']);
    Route::post('/deleteCourse', [CourseController::class, 'deleteCourse']);
    Route::post('/addCourses', [CourseController::class, 'addCourses']);
    Route::post('/getCourseDetail', [CourseController::class, 'getCourseDetail']);
    Route::post('/pluckCourse', [CourseController::class, 'pluckCourse']);

    //  Job Applications
    Route::post('/candidate', [JobApplicationController::class, 'candidate']);
    Route::post('/getJobApplications', [JobApplicationController::class, 'getJobApplications']);
    Route::post('/getJobApplicationDetails', [JobApplicationController::class, 'getJobApplicationDetails']);
    Route::post('/archiveJobApplication', [JobApplicationController::class, 'archiveJobApplication']);
    Route::post('/getarchiveJobApplication', [JobApplicationController::class, 'getarchiveJobApplication']);
    Route::post('/jobBoardStore', [JobApplicationController::class, 'jobBoardStore']);
    Route::post('/getJobBoardDetail', [JobApplicationController::class, 'getJobBoardDetail']);
    Route::post('/deleteJobOnBoard', [JobApplicationController::class, 'deleteJobOnBoard']);
    Route::post('/addJobApplicationSkill', [JobApplicationController::class, 'addJobApplicationSkill']);
    Route::post('/addJobApplicationNote', [JobApplicationController::class, 'addJobApplicationNote']);
    Route::post('/deleteJobApplicationNote', [JobApplicationController::class, 'deleteJobApplicationNote']);
    Route::post('/getjobBoardStore', [JobApplicationController::class, 'getjobBoardStore']);
    Route::post('archiveApplication', [JobApplicationController::class, 'archiveApplication']);
    Route::post('/job-board/update', [JobApplicationController::class, 'jobBoardUpdate']);
    // Jobs
    Route::post('/getJobs', [JobController::class, 'getJobs']);
    Route::post('/PluckJobs', [JobController::class, 'PluckJobs']);
    Route::post('/createJob', [JobController::class, 'createJob']);
    Route::post('/getJobDetails', [JobController::class, 'getJobDetails']);
    Route::post('/updateJob', [JobController::class, 'updateJob']);
    Route::post('/updateJobStatus', [JobController::class, 'updateJobStatus']);
    Route::post('/deleteJob', [JobController::class, 'deleteJob']);

    // Custom Question
    Route::post('/getQuestions', [CustomQuestionController::class, 'getQuestions']);
    Route::post('/createQuestion', [CustomQuestionController::class, 'createQuestion']);
    Route::post('/updateQuestion', [CustomQuestionController::class, 'updateQuestion']);
    Route::post('/deleteQuestion', [CustomQuestionController::class, 'deleteQuestion']);


    // Pay slip

    Route::get('payslips/', [PaySlipController::class, 'index']);
    Route::get('payslips/{id}', [PaySlipController::class, 'show']);
    Route::post('payslips/', [PaySlipController::class, 'store']);
    Route::post('payslips-show', [PaySlipController::class, 'searchJson']);
    Route::match(['put', 'patch'], 'payslips/{id}', [PaySlipController::class, 'update']);
    Route::delete('payslips/{id}', [PaySlipController::class, 'destroy']);
    Route::post('Payslip_fetch', [PaySlipController::class, 'Payslip_fetch']);


    //
    Route::post('updateEmployeeSalary/{id}', [PaySlipController::class, 'updateEmployeeSalary']);

    // Pay slip Type
    Route::get('/payslip-types', [PayslipTypeController::class, 'index'])->middleware('can:manage payslip type');
    Route::get('/pluckPayslip', [PayslipTypeController::class, 'pluckPayslip'])->middleware('can:manage payslip type');
    Route::post('/payslip-store', [PayslipTypeController::class, 'store'])->middleware('can:create payslip type');
    Route::get('/payslip-get/{id}', [PayslipTypeController::class, 'show'])->middleware('can:manage payslip type');
    Route::put('/payslip-update/{id}', [PayslipTypeController::class, 'update'])->middleware('can:edit payslip type');
    Route::post('/payslip-delete/{id}', [PayslipTypeController::class, 'destroy'])->middleware('can:delete payslip type');

    Route::post('/instituteDetail', [InstituteController::class, 'instituteDetail']);
    Route::post('/getInstitutes', [InstituteController::class, 'getInstitutes']);
    Route::post('/pluckInstitute', [InstituteController::class, 'pluckInstitutes']);
    Route::post('/addInstitute', [InstituteController::class, 'addInstitute']);
    Route::post('/updateInstitute', [InstituteController::class, 'updateInstitute']);
    Route::post('/deleteInstitute', [InstituteController::class, 'deleteInstitute']);

    Route::post('/getUniversities', [UniversityController::class, 'getUniversities']);
    Route::post('/pluckUniversities', [UniversityController::class, 'pluckInstitutes']);
    Route::post('/addUniversities', [UniversityController::class, 'addUniversities']);
    Route::post('/updateUniversities', [UniversityController::class, 'updateUniversities']);
    Route::post('/deleteUniversities', [UniversityController::class, 'deleteUniversities']);
    Route::post('/universityDetail', [UniversityController::class, 'universityDetail']);
    Route::post('/addUpdateUniversityMeta', [UniversityMetaController::class, 'storeOrUpdateMetas']);
    Route::post('/getUniversityMeta', [UniversityMetaController::class, 'getUniversityMeta']);
    Route::post('/updateUniversityStatus', [UniversityController::class, 'updateUniversityStatus']);
    Route::post('/updateUniversityCourseStatus', [UniversityController::class, 'updateUniversityCourseStatus']);
    Route::post('/updateUniversityMOIStatus', [UniversityController::class, 'updateUniversityMOIStatus']);
    Route::post('/updateAboutUniversity', [UniversityController::class, 'updateAboutUniversity']);
    Route::post('/getIntakeMonthByUniversity', [UniversityController::class, 'getIntakeMonthByUniversity']);
    Route::post('/get_course_campus', [UniversityController::class, 'get_course_campus']);

     //   Institute Category
     Route::post('/addInstituteCategory', [InstituteCategoryController::class, 'addInstituteCategory']);
     Route::get('/getInstituteCategoryPluck', [InstituteCategoryController::class, 'getInstituteCategoryPluck']);
     Route::get('/getInstituteCategories', [InstituteCategoryController::class, 'getInstituteCategories']);
     Route::post('/updateInstituteCategory', [InstituteCategoryController::class, 'updateInstituteCategory']);
     Route::post('/deleteInstituteCategory', [InstituteCategoryController::class, 'deleteInstituteCategory']);


     //     University Rules
     Route::post('/getClients', [ClientController::class, 'getClients']);
     Route::post('/clientDetail', [ClientController::class, 'clientDetail']);
     Route::post('/updateClient', [ClientController::class, 'updateClient']);

     //     University Rules
     Route::post('/addUniversityRule', [UniversityRuleController::class, 'addUniversityRule']);
     Route::post('/getUniversityRules', [UniversityRuleController::class, 'getUniversityRules']);
     Route::post('/updateUniversityRule', [UniversityRuleController::class, 'updateUniversityRule']);
     Route::post('/deleteUniversityRule', [UniversityRuleController::class, 'deleteUniversityRule']);
     Route::post('/updateUniversityRulePosition', [UniversityRuleController::class, 'updateUniversityRulePosition']);


     //     adminission
     Route::post('/getAdmission', [DealController::class, 'getAdmission']);
     Route::post('/getAdmissionDetails', [DealController::class, 'getAdmissionDetails']);
     Route::post('/getMoveApplicationPluck', [DealController::class, 'getMoveApplicationPluck']);
     Route::post('/moveApplicationsave', [DealController::class, 'moveApplicationsave']);

     


     //     application
     Route::post('/getApplications', [ApplicationsController::class, 'getApplications']);
     Route::post('/getDetailApplication', [ApplicationsController::class, 'getDetailApplication']);
     Route::post('/updateApplication', [ApplicationsController::class, 'updateApplication']);
     Route::post('/storeApplication', [ApplicationsController::class, 'storeApplication']);
     Route::post('/deleteApplication', [ApplicationsController::class, 'deleteApplication']);
     Route::post('/updateApplicationStage', [ApplicationsController::class, 'updateApplicationStage']);
     
     Route::post('/application_request_save_deposite', [ApplicationsController::class, 'application_request_save_deposite']);

     Route::post('/applicationAppliedStage', [ApplicationsController::class, 'applicationAppliedStage']);
     Route::post('/saveApplicationDepositRequest', [ApplicationsController::class, 'saveApplicationDepositRequest']);
     Route::post('/applicationNotesStore', [ApplicationsController::class, 'applicationNotesStore']);
     Route::post('/getApplicationNotes', [ApplicationsController::class, 'getApplicationNotes']);

     //     University Rules
     Route::post('/addMOIInstitutes', [MoiAcceptedController::class, 'addMOIInstitutes']);
     Route::post('/getMIOList', [MoiAcceptedController::class, 'getMIOList']);
     Route::post('/updateMOIInstitutes', [MoiAcceptedController::class, 'updateMOIInstitutes']);
    //  Route::post('/deleteUniversityRule', [UniversityRuleController::class, 'deleteUniversityRule']);

    // reports 
    Route::post('/reports/visa-analysis', [ReportsController::class, 'visaAnalysis']);
    Route::get('/reports/deposit-analysis', [ReportsController::class, 'depositAnalysis']);



    // general routes
    Route::get('/getRolesPluck', [GeneralController::class, 'getRolesPluck']);
    Route::get('/getAllBrands', [GeneralController::class, 'getAllBrands']);
    Route::post('/getDefaultFiltersData', [GeneralController::class, 'getDefaultFiltersData']);
    Route::get('/getAllProjectDirectors', [GeneralController::class, 'getAllProjectDirectors']);
    Route::post('/getRegionBrands', [GeneralController::class, 'getRegionBrands']);
    Route::post('/getMultiRegionBrands', [GeneralController::class, 'getMultiRegionBrands']);
    Route::post('/getFilterData', [GeneralController::class, 'getFilterData']);
    Route::post('/getFilterBranchUsers', [GeneralController::class, 'getFilterBranchUsers']);
    Route::post('/getSavedFilters', [GeneralController::class, 'getSavedFilters']);
    Route::get('/getSources', [GeneralController::class, 'getSources']);
    Route::get('/getBranches', [GeneralController::class, 'getBranches']);
    Route::get('/getStages', [GeneralController::class, 'getStages']);
    Route::get('/getTags', [GeneralController::class, 'getTags']);
    Route::get('/getJobCategories', [GeneralController::class, 'getJobCategories']);
    Route::post('/FilterSave', [GeneralController::class, 'FilterSave']);
    Route::post('/Country', [GeneralController::class, 'Country']);
    Route::post('/Country/by/code', [GeneralController::class, 'CountryByCode']);
    Route::post('/UniversityByCountryCode', [GeneralController::class, 'UniversityByCountryCode']);
    Route::post('/getLogActivity', [GeneralController::class, 'getLogActivity']);
    Route::post('/DeleteSavedFilter', [GeneralController::class, 'DeleteSavedFilter']);
    Route::post('/GetBranchByType', [GeneralController::class, 'GetBranchByType']);
    Route::post('/leadsrequireddata', [GeneralController::class, 'leadsrequireddata']);
    Route::post('/getCitiesOnCode', [GeneralController::class, 'getCitiesOnCode']);

    Route::post('/DealTagPluck', [GeneralController::class, 'DealTagPluck']);
    Route::post('/DealStagPluck', [GeneralController::class, 'DealStagPluck']);
    Route::post('/ApplicationStagPluck', [GeneralController::class, 'ApplicationStagPluck']);
});
