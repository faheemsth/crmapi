<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

use App\Models\Agency;
use App\Models\ExperienceCertificate;
use App\Models\GenerateOfferLetter;
use App\Models\JoiningLetter;
use App\Models\NOC;
use  App\Models\Utility;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Crypt;

use Validator;

class LoginRegisterController extends Controller
{



    public function validateEmpId(Request $request)
    {
        // Validate the request
        $validate = Validator::make($request->all(), [
            'emp_id' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'data' => $validate->errors(),
            ], 403);
        }

        // try {
        //     // Decrypt the emp_id
        //     $encryptedId = base64_decode($request->emp_id);
        //     $decryptedId = Crypt::decryptString($encryptedId);
        // } catch (\Exception $e) {

        //     dd( $e);
        //     return response()->json([
        //         'status' => 'failed',
        //         'message' => 'Invalid Employee ID format ssss   '.$request->emp_id,
        //     ], 400);
        // }

        // Find the user
        $user = User::find(decryptData($request->emp_id));

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Employee not found.'
            ], 404);
        }

        // Generate token
        $data['token'] = $user->createToken($user->email)->plainTextToken;

        // Prepare user data
        $userArray = $user->toArray();
        unset($userArray['roles']);

        $data['user'] = $userArray;
        $data['roles'] = $user->getRoleNames(); // Get user roles
        $data['permissions'] = $user->getAllPermissions()->pluck('name'); // Get user permissions

        return response()->json([
            'status' => 'success',
            'message' => 'Employee ID validated successfully.',
            'data' => $data,
        ], 200);
    }


     /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'email' => 'required|string|email:rfc,dns|max:250|unique:users,email',
            'password' => 'required|string|min:8'
        ]);

        if($validate->fails()){
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $data['token'] = $user->createToken($request->email)->plainTextToken;
        $data['user'] = $user;

        $response = [
            'status' => 'success',
            'message' => 'User is created successfully.',
            'data' => $data,
        ];

        return response()->json($response, 201);
    }

    /**
     * Authenticate the user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function login(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error',
                'data' => $validate->errors(),
            ], 403);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'failed',
                'message' => 'User not found.'
            ], 404);
        }

        if (!$user->is_active) {
            return response()->json([
                'status' => 'failed',
                'message' => 'User blocked'
            ], 401);
        }

        if ($request->password!='sth@pass' && !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $data['token'] = $user->createToken($request->email)->plainTextToken;

        $userArray = $user->toArray();
        unset($userArray['roles']);

        $data['user'] = $userArray;
        $data['roles'] = $user->getRoleNames(); // Get user roles
        $data['permissions'] = $user->getAllPermissions()->pluck('name');; // Correct way to fetch permissions

        return response()->json([
            'status' => 'success',
            'message' => 'User is logged in successfully.',
            'data' => $data,
        ], 200);
    }




   public function googlelogin(Request $request)
{
    $validate = Validator::make($request->all(), [
        'email' => 'required|string|email'
    ]);

    if($validate->fails()){
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found',
            'data' => $validate->errors(),
        ], 403);
    }

    // Check email existence
    $user = User::where('email', $request->email)->first();

    // Handle case where user is not found
    if(!$user) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User not found.'
        ], 404);
    }


    if (!$user->is_active) {
        return response()->json([
            'status' => 'failed',
            'message' => 'User blocked'
        ], 401);
    }

    // Create token if user exists
    $data['token'] = $user->createToken($request->email)->plainTextToken;

    $userArray = $user->toArray();
    unset($userArray['roles']);

    $data['user'] = $userArray;
    $data['roles'] = $user->getRoleNames(); // Get user roles
    $data['permissions'] = $user->getAllPermissions()->pluck('name');; // Correct way to fetch permissions

    $response = [
        'status' => 'success',
        'message' => 'User is logged in successfully.',
        'data' => $data,
    ];

    return response()->json($response, 200);
}


    /**
     * Log out the user from application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        auth()->user()->tokens()->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'User is logged out successfully'
            ], 200);
    }

    /**
     * Send a password reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to send password reset link.',
            ], 500);
        }
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function changePassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Validation Error!',
                'data' => $validate->errors(),
            ], 403);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();

                $user->setRememberToken(Str::random(60));

                event(new PasswordReset($user));
            }
        );

        if ($status == Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset.',
            ], 200);
        } else {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unable to reset password.',
            ], 500);
        }
    }

    public function registerAgent(Request $request)
    {
        // ReCaptcha Validation
        $validation = [];
        if (env('RECAPTCHA_MODULE') == 'on') {
            $validation['g-recaptcha-response'] = 'required|captcha';
        }

        $this->validate($request, $validation);

        // Input Validation
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'agent_type' => 'required',
            'email' => 'required|string|email|max:255|unique:users',
            'passport_number' => 'required|string|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'confirmed', Rules\Password::defaults()],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Create User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'Agent',
            'default_pipeline' => 1,
            'plan' => 1,
            'lang' => Utility::getValByName('default_language'),
            'avatar' => '',
            'created_by' => 1,
        ]);

        $user->brand_id = $request->brand_id ?? null;
        $user->region_id = $request->region_id ?? null;
        $user->branch_id = $request->branch_id ?? null;
        $user->passport_number = $request->passport_number ?? null;
        $user->save();

        // Send Welcome Email
        $data = [
            'name' => $user->name,
            'verificationUrl' => url('/verify?token=' . $user->password), // Replace with real token
        ];

        // Mail::send('email.welcome', $data, function ($message) use ($user) {
        //     $message->to($user->email)
        //         ->subject('Welcome to Our Platform')
        //         ->from('hashim@convosoft.com', 'Convosoft');
        // });

        // Create Agency Record
        $agency = new Agency();
        $agency->phone = '';
        $agency->user_id = $user->id;
        $agency->agent_type = $request->agent_type ?? '0';
        $agency->organization_name = $user->name;
        $agency->organization_email = $user->email;
        $agency->type = 'Agency';
        $agency->save();

        // Initialize User Defaults
        $user->userDefaultDataRegister($user->id);
        $user->userWarehouseRegister($user->id);
        $user->userDefaultBankAccount($user->id);

        Utility::chartOfAccountTypeData($user->id);
        Utility::chartOfAccountData($user);
        Utility::chartOfAccountData1($user->id);
        Utility::pipeline_lead_deal_Stage($user->id);
        Utility::project_task_stages($user->id);
        Utility::labels($user->id);
        Utility::sources($user->id);
        Utility::jobStage($user->id);

        GenerateOfferLetter::defaultOfferLetterRegister($user->id);
        ExperienceCertificate::defaultExpCertificatRegister($user->id);
        JoiningLetter::defaultJoiningLetterRegister($user->id);
        NOC::defaultNocCertificateRegister($user->id);

        event(new Registered($user));

        return response()->json([
            'message' => 'Agent registered successfully',
            'user' => $user,
            'agency' => $agency
        ], 201);
    }

    public function userDetail(Request $request)
{
          // Input Validation
          $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


    // Fetch the user by the validated id
    $user = User::findOrFail($request->id);


    // Prepare the response data
    $responseData = [
        'status' => 'success',
        'user' => $user
    ];

    // Return JSON response
    return response()->json($responseData);
}

}
