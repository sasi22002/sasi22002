<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\CompanyDbInfo;
use App\Models\Master;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Models\UserPackage;
use App\Models\MapSettings;
use App\Models\ApiAuth;
use App\Models\AuthAdmin;
use App\Models\EmpMapping;
use telesign\sdk\messaging\MessagingClient;
use App\Models\Items;
use Firebase\JWT\JWT;


class CreateAccountsController extends Controller
{

    public static function store(Request $request)
    {
        // |unique:user',
        $rules = [
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => 'required',
            // 'street'        => 'required',
            // 'city'          => 'required',
            // 'state'         => 'required',
            // 'zipcode'       => 'required',
            // 'country'       => 'required',
            // 'profile_image' => 'required',
            'email' => 'required|email',
        ];

        $data = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $password = encrypt(strtolower(str_random(5)));
        // $password=encrypt("12345");
        $pass = decrypt($password);
        $apiKey = hash_hmac('md5', $pass, 'MT_W2S');

        /*To signup provider*/
        $token = array(
            "email" => $data['email'],
            "iss" => Base::get_domin(),
            "type" =>  str_replace('App\\Models\\','',"User"),
            );
        $jwt = JWT::encode($token, "92e46214d71e4362ab48b1cc72cc1d36");

        $tokens = Base::checkTokenStatus($jwt);
        
        if ($jwt != null) {

            $data['role_id'] = 2;
            $user = new User();
            $user->role_id = $data['role_id'];
            $user->user_token = $jwt;
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $user->zoom_control = 10;
            $user->user_pwd = $password;
            $user->phone = isset($data['phone']) ? trim($data['phone']) : '';
            $user->email = $data['email'];
            $user->city = isset($data['city']) ? $data['city'] : null;
            $user->street = isset($data['street']) ? $data['street'] : null;
            $user->state = isset($data['state']) ? $data['state'] : null;
            $user->zipcode = isset($data['zipcode']) ? $data['zipcode'] : 1;
            $user->country = isset($data['country']) ? $data['country'] : null;
            $user->profile_image = isset($data['profile_image']) ? json_encode($data['profile_image'], true) : '[]';
            $user->phone_imei = isset($data['phone_imei']) ? $data['phone_imei'] : '';
            $user->is_active = isset($data['is_active']) ? $data['is_active'] : 1;
            $user->api_key = $apiKey;
            $user->mailnote = true;
            $user->is_onboarding_success = 1;
            $user->save();
            $user->belongs_manager = $user->user_id;
            $user->save();

            
            
            $userPackage = new UserPackage();
            $userPackage->user_id = $user->user_id;
            $userPackage->package_id = "1";
            $date = date('Y-m-d H:i:s');
            $d = strtotime("+7 days");
            $enddate = date("Y-m-d H:i:s", strtotime($date . ' + 7 days'));
            $userPackage->beg_date = $date;
            $userPackage->end_date = $enddate;
            $userPackage->no_of_emp = 2;
            $userPackage->no_of_mgr = 2;
            $userPackage->no_of_cust = 0;
            $userPackage->no_of_task = -1;
            $userPackage->save();
            $user = User::where('user_id', $user->user_id)->first();
            $user->current_package_id = $userPackage->id;
            $user->update();
            $mapsettings = new MapSettings();
            $mapsettings->user_id = $user->user_id;
            $mapsettings->save();

            /*organization mapping*/
            $EmpMapping = new EmpMapping();
            $EmpMapping->admin_id = $user->user_id;
            $EmpMapping->emp_id = $user->user_id;
            $EmpMapping->save();

            /*items entry*/
            $Items        = new Items();
            $Items->name  = "Yummy Lunch";
            $Items->emp_id   =  $user->user_id;
            $Items->save();

            /*welcome message*/
            $phone_number = str_replace("+", "", $data['phone']);
            $customer_id = env("TELESIGN_CUSTOMER_ID");
            $api_key = env("TELESIGN_API_KEY");
            $message = "Welcome Onboard to Cybrix. To download the Cybrix Delivery App, Please click the link below ".env('APP_DOWNLOAD_LINK')." Your userID is : ".$data["phone"];
            $message_type = "ARN";
            $messaging = new MessagingClient($customer_id, $api_key);
            $response = $messaging->message($phone_number, $message, $message_type);

            //SMS
            $msg = "Hi " . $data['first_name'] . " Your Password is " . decrypt($password);
            // $msg='1234';

            if (isset($data['phone'])) {
                // Base::send_Sms($data['phone'], $msg);
            }
            //Email Notification
            if(env('SES_EMAIL_SEND') == 1){
                \App\Http\Controllers\NotificationsController::WelcomeEmp($user);
            }

            /*direct login*/
            
                    $valid = User::where('user_token', '=', $user->user_token)->whereIn('role_id', [2, 3])
                ->first();
                $length = 5;
                $username = isset($data["email"])?$data["email"]:$data["phone"];
                $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);
                $userInfo = array(
                    "Username" => $username,
                    "password" => decrypt($user->user_pwd),
                    "app_token" => env('APP_TOKEN'),
                    "auth_data" => $randomletter,
                    "is_app_user" => 1
                );

                $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                $key = Base::token($jwt, User::class , $data['device_token'], $data['device_type']);
                $updateuser = User::find($valid->user_id);
                return Base::touser(['token' => $key,'api_key' => $apiKey, 'role' => $valid
                        ->role->name, 'gps_active' => $valid->zipcode, 'demo_links' => $valid->demo_links,"refreshToken"=>'', "profile" => $updateuser], true);

        }  
        else
        {
            return Base::touser($json_data['response_msg'], false);
        }
    }

    public static function createCompany(Request $request)
    {
        $data = $request->input('data');

        $rules = [
            'company_name' => 'required|unique:master',
            // 'company_street'  => 'required',
            // 'company_city'    => 'required',
            // 'company_state'   => 'required',
            // 'company_zipcode' => 'required',
            // 'company_url'     => 'required',
            'company_phone' => 'required',
            // 'company_country' => 'required',
            'company_email' => 'required|email|unique:master,company_email',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $company = new Master;

        $company->company_name = $data['company_name'];
        $company->company_zipcode = isset($data['company_zipcode']) ? $data['company_zipcode'] : '';
        $company->company_state = isset($data['company_state']) ? $data['company_state'] : '';
        $company->company_city = isset($data['company_city']) ? $data['company_city'] : '';
        $company->company_street = isset($data['company_street']) ? $data['company_street'] : '';
        $company->company_phone = $data['company_phone'];
        $company->company_url = isset($data['company_url']) ? $data['company_url'] : '';
        $company->company_desc = isset($data['company_desc']) ? $data['company_desc'] : '';
        $company->company_country = isset($data['company_country']) ? $data['company_country'] : '';
        $company->company_pwd = encrypt(Hash::make(str_random(12)));
        $company->company_email = $data['company_email'];
        $company->logo = isset($data['logo']) ? json_encode($data['logo']) : '';
        $company->save();

        Base::create_sub_db($company->company_name, $company->company_id);

        return Base::touser('Company Created', true);
    }

}