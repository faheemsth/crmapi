<?php

namespace App\Http\Controllers;

use Auth;
use App\Models\User;
use App\Models\Course;
use App\Models\University;
use App\Models\CourseLevel;
use Illuminate\Http\Request;
use App\Models\CourseDuration;
use App\Models\instalment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getCourses(Request $request)
    {
        // Permission check
        if (!Auth::user()->can('manage courses') && Auth::user()->type !== 'super admin') {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission Denied.')
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'university_id' => 'required|exists:universities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Base query
        $query = Course::with(['university', 'created_by'])
    ->where('university_id', $request->university_id);



        $courses = $query->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $courses,
        ], 200);
    }




    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addCourses(Request $request)
    {
        if (!in_array(Auth::user()->type, ['Product Coordinator', 'super admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:150',
            'university_id' => 'required|exists:universities,id',
            'campus' => 'required',
            'intake_month' => 'required',
            'intakeYear' => 'required|integer|min:2000',
            'duration' => 'required',
            'gross_fees' => 'required|numeric|min:0',
            'net_fees' => 'required|numeric|min:0',
            'scholarship' => 'nullable|numeric|min:0',
            'first_instalment' => 'nullable|numeric|min:0',
            'second_instalment' => 'nullable|numeric|min:0',
            'third_instalment' => 'nullable|numeric|min:0',
            'final_instalment' => 'nullable|numeric|min:0',
            'installments' => 'nullable|array',
            'installments.*' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $course = new Course();
        $course->name = $request->name;
        $course->university_id = $request->university_id;
        $course->campus = is_array($request->campus) ? implode(',', $request->campus) : $request->campus;
        $course->intake_month = is_array($request->intake_month) ? implode(',', $request->intake_month) : $request->intake_month;
        $course->intakeYear = $request->intakeYear;
        $course->duration = $request->duration;
        $course->gross_fees = $request->gross_fees;
        $course->net_fees = $request->net_fees;
        $course->scholarship = $request->scholarship;
        $course->first_instalment = $request->first_instalment;
        $course->second_instalment = $request->second_instalment;
        $course->third_instalment = $request->third_instalment;
        $course->final_instalment = $request->final_instalment;
        $course->created_by = Auth::user()->id;
        $course->save();

        if (!empty($request->installments)) {
            foreach ($request->installments as $installmentAmount) {
                $installment = new instalment();
                $installment->course_id = $course->id;
                $installment->fee = $installmentAmount;
                $installment->save();
            }
        }

        // Optional: Log activity
        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => 'Course Created',
                'message' => "A new course '{$course->name}' has been created successfully.",
            ]),
            'module_id' => $course->id,
            'module_type' => 'course',
            'notification_type' => 'Course Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Course successfully created!',
            'data' => [
                'course_id' => $course,
                'university_id' => $course->university,
            ]
        ]);
    }


    public function show(Course $course)
    {
        //
        return redirect()->route('course.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
        if (\Auth::user()->type == 'Product Coordinator' || \Auth::user()->type == 'super admin' ) {
            $course = Course::find($id);
            $Instalment = Instalment::where('course_id', $id)->orderBy('id', 'asc')->get();
            $months = months();
            $universities     = University::get()->pluck('name', 'id');
            $universities->prepend('Select University', '');

            $courselevel     = CourseLevel::get()->pluck('name', 'id');
            $courselevel->prepend('Select Course Level', '');

            $courseduration     = CourseDuration::get()->pluck('duration', 'id');
            $courseduration->prepend('Select Course Duration', '');

            $country_curr =new Collection(self::getCountryCurrency());
            $country_curr = $country_curr->pluck('name', 'code');
            $country_curr->prepend('Select Currency');
            $campuss = University::where('id', $course->university_id)
            ->whereNotNull('campuses')
            ->pluck('campuses')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString)); // Trim spaces from each campus
            })
            ->toArray();
            $intake_months = University::where('id', $course->university_id)
            ->whereNotNull('intake_months')
            ->pluck('intake_months')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString)); // Trim spaces from each month abbreviation
            })
            ->map(function ($abbr) use ($months) {
                // Convert abbreviations to full month names
                $monthMap = [
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
                    'DEC' => 'December',
                ];
                return $monthMap[strtoupper($abbr)] ?? $abbr; // Return full name if found, else original
            })
            ->toArray();
            return view('course.edit', compact('Instalment','intake_months','campuss','months','course', 'universities', 'courselevel', 'courseduration', 'country_curr'));
        } else {
            return response()->json(['error' => __('Permission Denied.')], 401);
        }
    }

    private function getCountryCurrency(){

            $currency_list = array(
                array("name" => "Afghan Afghani", "code" => "AFA"),
                array("name" => "Albanian Lek", "code" => "ALL"),
                array("name" => "Algerian Dinar", "code" => "DZD"),
                array("name" => "Angolan Kwanza", "code" => "AOA"),
                array("name" => "Argentine Peso", "code" => "ARS"),
                array("name" => "Armenian Dram", "code" => "AMD"),
                array("name" => "Aruban Florin", "code" => "AWG"),
                array("name" => "Australian Dollar", "code" => "AUD"),
                array("name" => "Azerbaijani Manat", "code" => "AZN"),
                array("name" => "Bahamian Dollar", "code" => "BSD"),
                array("name" => "Bahraini Dinar", "code" => "BHD"),
                array("name" => "Bangladeshi Taka", "code" => "BDT"),
                array("name" => "Barbadian Dollar", "code" => "BBD"),
                array("name" => "Belarusian Ruble", "code" => "BYR"),
                array("name" => "Belgian Franc", "code" => "BEF"),
                array("name" => "Belize Dollar", "code" => "BZD"),
                array("name" => "Bermudan Dollar", "code" => "BMD"),
                array("name" => "Bhutanese Ngultrum", "code" => "BTN"),
                array("name" => "Bitcoin", "code" => "BTC"),
                array("name" => "Bolivian Boliviano", "code" => "BOB"),
                array("name" => "Bosnia-Herzegovina Convertible Mark", "code" => "BAM"),
                array("name" => "Botswanan Pula", "code" => "BWP"),
                array("name" => "Brazilian Real", "code" => "BRL"),
                array("name" => "British Pound Sterling", "code" => "GBP"),
                array("name" => "Brunei Dollar", "code" => "BND"),
                array("name" => "Bulgarian Lev", "code" => "BGN"),
                array("name" => "Burundian Franc", "code" => "BIF"),
                array("name" => "Cambodian Riel", "code" => "KHR"),
                array("name" => "Canadian Dollar", "code" => "CAD"),
                array("name" => "Cape Verdean Escudo", "code" => "CVE"),
                array("name" => "Cayman Islands Dollar", "code" => "KYD"),
                array("name" => "CFA Franc BCEAO", "code" => "XOF"),
                array("name" => "CFA Franc BEAC", "code" => "XAF"),
                array("name" => "CFP Franc", "code" => "XPF"),
                array("name" => "Chilean Peso", "code" => "CLP"),
                array("name" => "Chilean Unit of Account", "code" => "CLF"),
                array("name" => "Chinese Yuan", "code" => "CNY"),
                array("name" => "Colombian Peso", "code" => "COP"),
                array("name" => "Comorian Franc", "code" => "KMF"),
                array("name" => "Congolese Franc", "code" => "CDF"),
                array("name" => "Costa Rican Colón", "code" => "CRC"),
                array("name" => "Croatian Kuna", "code" => "HRK"),
                array("name" => "Cuban Convertible Peso", "code" => "CUC"),
                array("name" => "Czech Republic Koruna", "code" => "CZK"),
                array("name" => "Danish Krone", "code" => "DKK"),
                array("name" => "Djiboutian Franc", "code" => "DJF"),
                array("name" => "Dominican Peso", "code" => "DOP"),
                array("name" => "East Caribbean Dollar", "code" => "XCD"),
                array("name" => "Egyptian Pound", "code" => "EGP"),
                array("name" => "Eritrean Nakfa", "code" => "ERN"),
                array("name" => "Estonian Kroon", "code" => "EEK"),
                array("name" => "Ethiopian Birr", "code" => "ETB"),
                array("name" => "Euro", "code" => "EUR"),
                array("name" => "Falkland Islands Pound", "code" => "FKP"),
                array("name" => "Fijian Dollar", "code" => "FJD"),
                array("name" => "Gambian Dalasi", "code" => "GMD"),
                array("name" => "Georgian Lari", "code" => "GEL"),
                array("name" => "German Mark", "code" => "DEM"),
                array("name" => "Ghanaian Cedi", "code" => "GHS"),
                array("name" => "Gibraltar Pound", "code" => "GIP"),
                array("name" => "Greek Drachma", "code" => "GRD"),
                array("name" => "Guatemalan Quetzal", "code" => "GTQ"),
                array("name" => "Guinean Franc", "code" => "GNF"),
                array("name" => "Guyanaese Dollar", "code" => "GYD"),
                array("name" => "Haitian Gourde", "code" => "HTG"),
                array("name" => "Honduran Lempira", "code" => "HNL"),
                array("name" => "Hong Kong Dollar", "code" => "HKD"),
                array("name" => "Hungarian Forint", "code" => "HUF"),
                array("name" => "Icelandic Króna", "code" => "ISK"),
                array("name" => "Indian Rupee", "code" => "INR"),
                array("name" => "Indonesian Rupiah", "code" => "IDR"),
                array("name" => "Iranian Rial", "code" => "IRR"),
                array("name" => "Iraqi Dinar", "code" => "IQD"),
                array("name" => "Israeli New Sheqel", "code" => "ILS"),
                array("name" => "Italian Lira", "code" => "ITL"),
                array("name" => "Jamaican Dollar", "code" => "JMD"),
                array("name" => "Japanese Yen", "code" => "JPY"),
                array("name" => "Jordanian Dinar", "code" => "JOD"),
                array("name" => "Kazakhstani Tenge", "code" => "KZT"),
                array("name" => "Kenyan Shilling", "code" => "KES"),
                array("name" => "Kuwaiti Dinar", "code" => "KWD"),
                array("name" => "Kyrgystani Som", "code" => "KGS"),
                array("name" => "Laotian Kip", "code" => "LAK"),
                array("name" => "Latvian Lats", "code" => "LVL"),
                array("name" => "Lebanese Pound", "code" => "LBP"),
                array("name" => "Lesotho Loti", "code" => "LSL"),
                array("name" => "Liberian Dollar", "code" => "LRD"),
                array("name" => "Libyan Dinar", "code" => "LYD"),
                array("name" => "Litecoin", "code" => "LTC"),
                array("name" => "Lithuanian Litas", "code" => "LTL"),
                array("name" => "Macanese Pataca", "code" => "MOP"),
                array("name" => "Macedonian Denar", "code" => "MKD"),
                array("name" => "Malagasy Ariary", "code" => "MGA"),
                array("name" => "Malawian Kwacha", "code" => "MWK"),
                array("name" => "Malaysian Ringgit", "code" => "MYR"),
                array("name" => "Maldivian Rufiyaa", "code" => "MVR"),
                array("name" => "Mauritanian Ouguiya", "code" => "MRO"),
                array("name" => "Mauritian Rupee", "code" => "MUR"),
                array("name" => "Mexican Peso", "code" => "MXN"),
                array("name" => "Moldovan Leu", "code" => "MDL"),
                array("name" => "Mongolian Tugrik", "code" => "MNT"),
                array("name" => "Moroccan Dirham", "code" => "MAD"),
                array("name" => "Mozambican Metical", "code" => "MZM"),
                array("name" => "Myanmar Kyat", "code" => "MMK"),
                array("name" => "Namibian Dollar", "code" => "NAD"),
                array("name" => "Nepalese Rupee", "code" => "NPR"),
                array("name" => "Netherlands Antillean Guilder", "code" => "ANG"),
                array("name" => "New Taiwan Dollar", "code" => "TWD"),
                array("name" => "New Zealand Dollar", "code" => "NZD"),
                array("name" => "Nicaraguan Córdoba", "code" => "NIO"),
                array("name" => "Nigerian Naira", "code" => "NGN"),
                array("name" => "North Korean Won", "code" => "KPW"),
                array("name" => "Norwegian Krone", "code" => "NOK"),
                array("name" => "Omani Rial", "code" => "OMR"),
                array("name" => "Pakistani Rupee", "code" => "PKR"),
                array("name" => "Panamanian Balboa", "code" => "PAB"),
                array("name" => "Papua New Guinean Kina", "code" => "PGK"),
                array("name" => "Paraguayan Guarani", "code" => "PYG"),
                array("name" => "Peruvian Nuevo Sol", "code" => "PEN"),
                array("name" => "Philippine Peso", "code" => "PHP"),
                array("name" => "Polish Zloty", "code" => "PLN"),
                array("name" => "Qatari Rial", "code" => "QAR"),
                array("name" => "Romanian Leu", "code" => "RON"),
                array("name" => "Russian Ruble", "code" => "RUB"),
                array("name" => "Rwandan Franc", "code" => "RWF"),
                array("name" => "Salvadoran Colón", "code" => "SVC"),
                array("name" => "Samoan Tala", "code" => "WST"),
                array("name" => "São Tomé and Príncipe Dobra", "code" => "STD"),
                array("name" => "Saudi Riyal", "code" => "SAR"),
                array("name" => "Serbian Dinar", "code" => "RSD"),
                array("name" => "Seychellois Rupee", "code" => "SCR"),
                array("name" => "Sierra Leonean Leone", "code" => "SLL"),
                array("name" => "Singapore Dollar", "code" => "SGD"),
                array("name" => "Slovak Koruna", "code" => "SKK"),
                array("name" => "Solomon Islands Dollar", "code" => "SBD"),
                array("name" => "Somali Shilling", "code" => "SOS"),
                array("name" => "South African Rand", "code" => "ZAR"),
                array("name" => "South Korean Won", "code" => "KRW"),
                array("name" => "South Sudanese Pound", "code" => "SSP"),
                array("name" => "Special Drawing Rights", "code" => "XDR"),
                array("name" => "Sri Lankan Rupee", "code" => "LKR"),
                array("name" => "St. Helena Pound", "code" => "SHP"),
                array("name" => "Sudanese Pound", "code" => "SDG"),
                array("name" => "Surinamese Dollar", "code" => "SRD"),
                array("name" => "Swazi Lilangeni", "code" => "SZL"),
                array("name" => "Swedish Krona", "code" => "SEK"),
                array("name" => "Swiss Franc", "code" => "CHF"),
                array("name" => "Syrian Pound", "code" => "SYP"),
                array("name" => "Tajikistani Somoni", "code" => "TJS"),
                array("name" => "Tanzanian Shilling", "code" => "TZS"),
                array("name" => "Thai Baht", "code" => "THB"),
                array("name" => "Tongan Pa'anga", "code" => "TOP"),
                array("name" => "Trinidad & Tobago Dollar", "code" => "TTD"),
                array("name" => "Tunisian Dinar", "code" => "TND"),
                array("name" => "Turkish Lira", "code" => "TRY"),
                array("name" => "Turkmenistani Manat", "code" => "TMT"),
                array("name" => "Ugandan Shilling", "code" => "UGX"),
                array("name" => "Ukrainian Hryvnia", "code" => "UAH"),
                array("name" => "United Arab Emirates Dirham", "code" => "AED"),
                array("name" => "Uruguayan Peso", "code" => "UYU"),
                array("name" => "US Dollar", "code" => "USD"),
                array("name" => "Uzbekistan Som", "code" => "UZS"),
                array("name" => "Vanuatu Vatu", "code" => "VUV"),
                array("name" => "Venezuelan BolÃvar", "code" => "VEF"),
                array("name" => "Vietnamese Dong", "code" => "VND"),
                array("name" => "Yemeni Rial", "code" => "YER"),
                array("name" => "Zambian Kwacha", "code" => "ZMK"),
                array("name" => "Zimbabwean dollar", "code" => "ZWL")
            );
            return  $currency_list;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Course $course)
    {
        //
        if (\Auth::user()->type == 'Product Coordinator' || \Auth::user()->type == 'super admin' ) {

            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:150',
                    'university_id' => 'required',
                    'campus' => 'required',
                    'intake_month' => 'required',
                    'duration' => 'required',
                    'intakeYear' => 'required',
                    'gross_fees' => 'required',
                    'net_fees' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return json_encode([
                    'status' => 'error',
                    'message' => $messages->first()
                ]);
            }

            $course->name        = $request->name;
            $course->university_id        = $request->university_id;
            $campusData = is_array($campus = $request->input('campus')) ? implode(',', $campus) : $campus;
            $course->campus = $campusData;
            $course->intake_month        = is_array($intake_month = $request->input('intake_month')) ? implode(',', $intake_month) : $intake_month;
            $course->duration        = $request->duration;
            $course->gross_fees        = $request->gross_fees;
            $course->net_fees        = $request->net_fees;
            $course->intakeYear        = $request->intakeYear;
            $course->scholarship        = $request->scholarship;
            $course->save();

            $installments = is_array($request->installments) ? $request->installments : [];
            $installment_ids = is_array($request->installment_ids) ? $request->installment_ids : [];

            if (!empty($installments) && isset($course->id)) {
                // Fetch all existing installment IDs for the course
                $existingInstallments = Instalment::where('course_id', $course->id)->pluck('id')->toArray();

                // Track processed installment IDs
                $processedInstallmentIds = [];

                foreach ($installments as $index => $fetch) {
                    if (!empty($fetch)) {
                        $installmentId = $installment_ids[$index] ?? null;

                        // Check if installment ID exists and belongs to this course
                        if ($installmentId && in_array($installmentId, $existingInstallments)) {
                            // Update existing installment
                            $installment = Instalment::find($installmentId);
                            $installment->fee = $fetch;
                            $installment->save();

                            // Mark this installment ID as processed
                            $processedInstallmentIds[] = $installmentId;
                        } else {
                            // Create new installment if it doesn't exist
                            $newInstallment = Instalment::create([
                                'course_id' => $course->id,
                                'fee' => $fetch,
                            ]);

                            // Track the new installment ID
                            $processedInstallmentIds[] = $newInstallment->id;
                        }
                    }
                }

                // Remove installments that are not in the processed list
                $installmentsToDelete = array_diff($existingInstallments, $processedInstallmentIds);
                if (!empty($installmentsToDelete)) {
                    Instalment::whereIn('id', $installmentsToDelete)->delete();
                }
            }

            return json_encode([
                'status' => 'success',
                'message' => 'Course successfully updated!',
                'id' => $course->university_id
            ]);

        } else {
            return json_encode([
                'status' => 'error',
                'message' => 'Permission Denied.'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
        if (\Auth::user()->type == 'Product Coordinator' || \Auth::user()->type == 'super admin' ) {
            Course::find($id)->delete();

            return back()->with('success', __('Course successfully deleted!'));
        }
        else
        {
            return back()->with('error', __('Permission Denied.'));
        }
    }

    public function get_course_campus()
    {
        $id = $_GET['id'];

        // Fetch campus details
        $campus = Course::where('id', $id)
            ->whereNotNull('campus')
            ->pluck('campus')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->toArray();
            if(empty($campus)){
                return json_encode([
                    'status' => 'success',
                    'campus_html' => '',
                    'intake_html' => '',
                    'intake_year_html' => '',
                ]);
            }
        // Fetch intake month details
        $intake_month = Course::where('id', $id)
            ->whereNotNull('intake_month')
            ->pluck('intake_month')
            ->flatMap(function ($campusString) {
                return array_map('trim', explode(',', $campusString));
            })
            ->toArray();

        $course_html = '';
        $intake_html = '';
        $intake_year_html = '';

        // Campus dropdown (read-only)
        // ............
        $course_html = '<select name="campus" class="form form-control select2 validationSideColor" id="campus" disabled>';
        $course_html .= '<option value="">Select Campus</option>';
        if (!empty($campus)) {
            foreach ($campus as $course) {
                $course_html .= '<option value="' . $course . '" selected disabled> ' . $course . ' </option>';
            }
        }
        $course_html .= '</select>';

        // Intake month dropdown (read-only)
        $intake_html = '<select name="intake_month" class="form form-control select2 validationSideColor" id="intake_month" disabled>';
        $intake_html .= '<option value="">Select Month</option>';
        if (!empty($intake_month)) {
            foreach ($intake_month as $intake) {
                $intake_html .= '<option value="' . $intake . '" selected disabled> ' . $intake . ' </option>';
            }
        }
        $intake_html .= '</select>';

        // Fetch course details for intake year
        $campusfetch = Course::find($id);

        $intake_year_html = '<select name="intakeYear" class="form form-control select2 validationSideColor" id="intakeYear" disabled>';
        $intake_year_html .= '<option value="">Select Year</option>';

        // Check if $campusfetch is not null before accessing intakeYear
        if ($campusfetch && !empty(intakeYear())) {
            foreach (intakeYear() as $intakeYear) {
                $selected = (trim((string) $intakeYear) === trim((string) $campusfetch->intakeYear)) ? 'selected' : '';
                $intake_year_html .= '<option value="' . $intakeYear . '" ' . $selected . ' disabled> ' . $intakeYear . ' </option>';
            }
        }

        $intake_year_html .= '</select>';
        if(!empty($campusfetch))
        {
        $intake_year_html .= '
        <input type="hidden" name="intakeYear" value="'.$campusfetch->intakeYear.'">
        <input type="hidden" name="intake_month" value="'.$campusfetch->intake_month.'">
        <input type="hidden" name="campus" value="'.$campusfetch->campus.'">
        ';
        }


        return json_encode([
            'status' => 'success',
            'campus_html' => $course_html,
            'intake_html' => $intake_html,
            'intake_year_html' => $intake_year_html,
        ]);
    }

}
