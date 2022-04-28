<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Authentication;
use App\OldStaff,App\User,App\QuestionPreviousExperience;
use DB,Auth,Validator;
use Illuminate\Validation\Rule;
use JWTAuth;
use \GeniusTS\HijriDate\Hijri as Hijri;

class AuthController extends Controller
{
    use Authentication;
  /**
  * API Register
  *
  * @param object $q Request data
  * @return \Illuminate\Http\JsonResponse
  */
    public function postRegister(Request $q)
    {
      if(config('app.app_settings.is_register_closed')) {
        return response()->json(['message' => 'register_closed','message_string' => 'عذراً التسجيل متوقف الآن, عاود الزيارة في وقت آخر']);
      }

      $validator = validator()->make($q->all(), [
        'firstname' => ['required','regex:/\p{Arabic}/u'],
        'secondname' => ['required','regex:/\p{Arabic}/u'],
        'thirdname' => ['required','regex:/\p{Arabic}/u'],
        'lastname' => ['required','regex:/\p{Arabic}/u'],
        'email' => 'required|email',
        'phone' => ['required','regex:/^(05)(5|0|3|6|4|9|1|8|7)([0-9]{6})/i'],
        'password' => 'required',
        'birthday_hijri' => 'required',
        'sa_id' => ['required','max:10','min:10','regex:/^(1|2|3|4)([0-9]{9})/i'],
        'blood_type' => ['required',Rule::in(['AB+','AB-','A+','A-','B+','B-','O+','O-'])],
        'nationality' => 'required',
        'gender' => ['required',Rule::in(['ذكر','انثى'])],
        'qualification' => 'required',
        'question_dismissal_from_work' => 'required',
        'bank_account_name' => ['required','regex:/[a-zA-Z ]/i'],
        'bank_name' => 'required',
        'bank_iban' => ['required','min:22','max:22'],
        'bank_sa_id_of_iban_owner' => ['required','max:10','min:10','regex:/^(1|2|3|4)([0-9]{9})/i'],
        'image_of_personal' => 'required',
        'image_of_sa_id' => 'required',
        'image_of_iban' => 'required',
        'city' => 'required'
      ]);

      if($validator->fails()) {
        return response()->json(['message' => 'invalid_fields','message_string' => 'يرجى منك التأكد من الحقول جيداً', 'errors' => $validator->messages()]);
      }
      // Check hijri date
      $hijri = explode('/',$q->birthday_hijri);
      if(count($hijri) != 3){
        return response()->json(['message' => 'invalid_hijri_date','message_string' => 'التاريخ الهجري غير صحيح']);
      }

      // Check user sa_id if exist
      $checkSAID = User::where('sa_id',$q->sa_id)->orWhere('email',$q->email)->select('id','sa_id','email')->first();
      if ($checkSAID) {
        if($checkSAID->sa_id == $q->sa_id){
          return response()->json(['message' => 'sa_id_already_exists','message_string' => 'رقم الهوية مسجل مسبقاً, يرجى منك تسجيل الدخول']);
        }else {
          return response()->json(['message' => 'email_already_exists','message_string' => 'البريد الألكتروني مسجل مسبقاً, يرجى منك تسجيل الدخول']);
        }
      }

      // Get and set old staff status of user
      $checkOldStaff = OldStaff::where('sa_id',$q->sa_id)->first();

      $User = new User;
      if ($checkOldStaff) {
        $User->staff_is_old = 1;
        $User->job_id = $checkOldStaff->job_id; // Initially we must to update job_id with the old staff job_id
        $User->staff_is_old_job_id = $checkOldStaff->job_id;
      }
      $User->firstname = $q->firstname;
      $User->secondname = $q->secondname;
      $User->thirdname = $q->thirdname;
      $User->lastname = $q->lastname;
      $User->name = $User->firstname.' '.$User->secondname.' '.$User->thirdname.' '.$User->lastname;
      $User->email = $q->email;
      $User->phone = $q->phone;
      $User->password = bcrypt($q->password);
      $User->blood_type = $q->blood_type;
      if (!$User->id) {
        $User->sa_id = $q->sa_id;
      }
      $User->birthday_hijri = $q->birthday_hijri;
      $birthday_hijri = explode('-',substr(Hijri::convertToGregorian($hijri[0],$hijri[1],$hijri[2]),0,10));
      $User->birthday = $birthday_hijri[0].'-'.$birthday_hijri[1].'-'.(((int) $birthday_hijri[2])+1);
      $User->nationality = $q->nationality;
      $User->staff_is_saudi = ($q->nationality == 'Saudi') ? 1 : 0;
      $User->qualification = $q->qualification;
      $User->specialization = $q->specialization;
      $User->bank_account_name = $q->bank_account_name;
      $User->bank_name = $q->bank_name;
      $User->bank_iban = 'SA'.$q->bank_iban;
      $User->bank_sa_id_of_iban_owner = $q->bank_sa_id_of_iban_owner;
      $User->image_of_personal = $q->image_of_personal;
      $User->image_of_sa_id = $q->image_of_sa_id;
      $User->image_of_iban = $q->image_of_iban;
      $User->image_of_cv = $q->image_of_cv;
      $User->gender = $q->gender;
      $User->city = $q->city;
      $User->question_dismissal_from_work = ($q->question_dismissal_from_work == 'Yes') ? 'Yes' : 'No';
      $User->question_language_arabic = ($q->question_language_arabic == true) ? 1 : 0;
      $User->question_language_english = ($q->question_language_english == true) ? 1 : 0;
      $User->question_language_urdu = ($q->question_language_urdu == true) ? 1 : 0;
      $User->question_language_other = ($q->question_language_other == true) ? 1 : 0;

      $User->save();
      /* Save previous experiences */
      if(count($q->question_previous_experiences)){
        $insertPrv = [];
        Authentication($User->id, $q->password);
        foreach($q->question_previous_experiences as $k => $v){
          if (isset($q->question_previous_experiences_jobs[$k])) {
            $insertPrv[] = [
              'user_id' => $User->id,
              'year' => $k,
              'job' => $q->question_previous_experiences_jobs[$k],
            ];
          }
        }
        if (count($insertPrv)) {
          $QuestionPreviousExperience = QuestionPreviousExperience::insert($insertPrv);
        }
      }
      

      if($q->is_web){
        Auth::loginUsingId($User->id, true);
      }

      return response()->json(['message' => 'user_created']);

    }

    /**
    * API Login, on success return JWT Auth token
    *
    * @param Request $q
    * @return \Illuminate\Http\JsonResponse
    */
    public function postLogin(Request $q)
    {
      $credentials = $q->only('sa_id', 'password');

      $rules = [
      'sa_id' => ['required','max:10','regex:/^(1|2|3|4)([0-9]{9})/i'],
      'password' => 'required',
      ];
      $validator = Validator::make($credentials, $rules);
      if($validator->fails()) {
        return response()->json(['message' => 'invalid_fields', 'errors' => $validator->messages()]);
      }
      if($q->is_web){
        $User = User::where('sa_id',$q->sa_id)->select('id','sa_id','password')->first();
        if($User && \Hash::check($q->password,$User->password)){
          Auth::login($User);
          Authentication($User->id, $q->password);
          return response()->json(['message' => 'logged_in']);
        }else {
          return response()->json(['message' => 'login_failed']);
        }
      }else {
        try {
          // attempt to verify the credentials and create a token for the user
          if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'we_cant_find_account']);
          }
        } catch (JWTException $e) {
          return response()->json(['message' => 'login_failed']);
        }

        return response()->json(['message' => 'logged_in','token' => $token,'expires_in' => (auth('api')->factory()->getTTL() * 60),'user' => \Auth::User()], 200);
      }
    }


    /**
    * Forget password
    *
    * @param Request $q
    * @return \Illuminate\Http\JsonResponse
    */
    public function postForgetPassword(Request $q)
    {
      $credentials = $q->only('email');
      $rules = [
      'email' => 'required|email',
      ];
      $validator = Validator::make($credentials, $rules);
      if($validator->fails()) {
        return response()->json(['message' => 'invalid_fields', 'errors' => $validator->messages()]);
      }
      if($q->is_web){
        $User = User::where('email',$q->email)->select('email')->first();
        if(!$User){
          return response()->json(['message' => 'no_email_found']);
        }else {
          // Send validation code to email

        }
      }
    }

    /**
    * Get the authenticated User.
    *
    * @return \Illuminate\Http\JsonResponse
    */
    public function postMe(Request $q)
    {

      if (auth('api')->user()) {
        $User = auth('api')->user();
        return response()->json($User);
      }else {
        return response()->json(['message' => 'user_not_found']);
      }
    }
}
