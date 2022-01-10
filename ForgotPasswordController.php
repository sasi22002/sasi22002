<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use App\Models\User;
use Vinkla\Hashids\Facades\Hashids;
use App\Models\AuthAdmin;
use App\Models\ApiAuth;

class ForgotPasswordController extends Controller
{

    public function sendmail(Request $request)
    {

        $rules = [
            'email' => 'required|email',
            'email' => 'exists:user,email',
        ];
        $data = $request->input('data');

        $validator = \Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $user = \App\Models\User::where('email', $data['email'])->first();
        $usr_tkn = $user['user_token'];

        if (!$user->is_active) {
            return Base::touser("Your Account is not active");
        }

        /*get details*/
        //   $oauth_token = AuthAdmin::find('1')->get()->toArray();
        //   $authtoken = $oauth_token[0]['auth_key'];

        //   $token = Base::checkTokenStatus($authtoken);
          
        //   $x_client_data = Base::get_x_clinet_data_from_token($token);
          $new =  str_random(10); 
          
          $userInfo = array(
                  "usr_name" => "super",
                  "password" => $new,
                  "usr_token" => $usr_tkn
              );
        //   $auth_url = "https://eazyfoodapp.com/api/users/UpdateAppUserCredentials";
        //       $agent = $_SERVER['HTTP_USER_AGENT'];
        //       $authorization = "Authorization: Bearer ".$token;
        //       $_client_data = "x_client_data: ".$x_client_data;
              
        //       $curl = curl_init();
        //       curl_setopt_array($curl, array(
        //       CURLOPT_URL => $auth_url,
        //       CURLOPT_RETURNTRANSFER => true,
        //       CURLOPT_ENCODING => "",
        //       CURLOPT_MAXREDIRS => 10,
        //       CURLOPT_TIMEOUT => 30000,
        //       CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //       CURLOPT_CUSTOMREQUEST => "POST",
        //       CURLOPT_USERAGENT => $agent,
        //       CURLOPT_POSTFIELDS => json_encode($userInfo) ,
        //       CURLOPT_HTTPHEADER => array(
        //               "accept: */*",
        //               "accept-language: en-US,en;q=0.8",
        //               "content-type: application/json",
        //               $_client_data,
        //               $authorization
        //           ) ,
        //       ));
        //       $curlresponse = curl_exec($curl);
        //       $err = curl_error($curl);

            //   curl_close($curl);
            //   if ($err){
            //       return "cURL Error #:" . $err;
            //   }else{

            //       $json = json_decode($curlresponse, true);
                
            //       if($json['status']['status_code'] == 200){
            //           $json_data = $json['data'];

            //           if($json_data['is_success'] == true){
                         $user->user_pwd = encrypt($new);
                         $user->save();
                           \App\Http\Controllers\NotificationsController::resetPassword($user);

        return Base::touser("New password has been sent to your e-mail address ", true);
                      
       
    }

    public function sendotp(Request $request)
    {

    $rules = [
          'phone' => 'required|phone',
          'phone' => 'exists:user,phone',
      ];

     $data = $request->input('data');

      $validator = \Validator::make($data, $rules);

      if ($validator->fails()) {
          return Base::touser($validator->errors()->all()[0]);
      }

      $user = \App\Models\User::where('phone', $data['phone'])->first();

      if (!$user->is_active) {
          return Base::touser("Your Account is not active");
      }

    // return $data;
   
    $otp = rand (1000 ,9999);
    $user = User::where('phone',$data['phone'])->get()->toArray();
    $usr_tkn = $user[0]['user_token'];
    
    if($user[0]['phone'])
    {
        $otp_val = encrypt($otp);
        

        $curl = curl_init();
        $otp = decrypt($otp_val);
        $message = "Cybrix: Your Password is ".$otp;
        $number = $data['phone'];
        $sender = "MTEAMZ";
        $auth_key = "250611AJUEMGrDiX5c08cb4c";
        curl_setopt_array($curl, array(
          CURLOPT_URL => "http://control.msg91.com/api/sendotp.php?template=&otp_length=&authkey=".$auth_key."&message=".$message."&sender=".$sender."&mobile=".$number."&otp=".$otp."&otp_expiry=&email=",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => "",
          CURLOPT_SSL_VERIFYHOST => 0,
          CURLOPT_SSL_VERIFYPEER => 0,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          // return "cURL Error #:" . $err;
          return Base::touser($err, false);
        } else {

          /*get details*/
          $oauth_token = AuthAdmin::find('1')->get()->toArray();
          $authtoken = $oauth_token[0]['auth_key'];

          $token = Base::checkTokenStatus($authtoken);
          $x_client_data = Base::get_x_clinet_data_from_token($token);

          $userInfo = array(
                  "usr_name" => "super",
                  "password" => $otp,
                  "usr_token" => $usr_tkn
              );
          $auth_url = "https://eazyfoodapp.com/api/users/UpdateAppUserCredentials";
              $agent = $_SERVER['HTTP_USER_AGENT'];
              $authorization = "Authorization: Bearer ".$token;
              $_client_data = "x_client_data: ".$x_client_data;
              
              $curl = curl_init();
              curl_setopt_array($curl, array(
              CURLOPT_URL => $auth_url,
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30000,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_USERAGENT => $agent,
              CURLOPT_POSTFIELDS => json_encode($userInfo) ,
              CURLOPT_HTTPHEADER => array(
                      "accept: */*",
                      "accept-language: en-US,en;q=0.8",
                      "content-type: application/json",
                      $_client_data,
                      $authorization
                  ) ,
              ));
              $curlresponse = curl_exec($curl);
              $err = curl_error($curl);

              curl_close($curl);
              if ($err){
                  return "cURL Error #:" . $err;
              }else{

                  $json = json_decode($curlresponse, true);
                
                  if($json['status']['status_code'] == 200){
                      $json_data = $json['data'];

                      if($json_data['is_success'] == true){
                          $user_data =User::find($user[0]['user_id']);
                          $user_data->user_pwd=$otp_val;
                          $user_data->save();
                          return Base::touser('Password send to registered mobile number', true);
                      }else{
                          return Base::throwerror();
                      }
                  }else{
                      return Base::touser($json['status']['message'],false);
                  }
              }
        }

    }
    
    }
    public function resetpassword(Request $request)
    {


      // $salt = Base::salt;

      //   $data = $user->user_id;

      //   $date = new \DateTime();
      //   $date->modify("+1 hour");

      //   $token = array(
      //       "resettoken" => Hashids::encode($data) . '_' . \Hash::make(str_random(500)),
      //       "iss"        => Base::get_domin(),
      //       "expire"     => $date,
      //   );

      //   $jwt = JWT::encode($token, $salt);



    	// $rules = [
     //        'token' => 'required|min:10',
     //    ];

     //    $data = $request->input('data');

     //    $validator = \Validator::make($data, $rules);

     //    if ($validator->fails()) {
     //        return Base::touser($validator->errors()->all()[0]);
     //    }

      
     //    $decoded = JWT::decode($jwt, $salt, array('HS256'));

     //    $decoded = (array) $decoded;

     //    $expire = new \DateTime($decoded['expire']->date);

     //    $current = new \DateTime();

     //    if ($expire < $current) {
     //        return Base::touser("Token Expired", true);
     //    }

     //    $decoded['resettoken'] = strstr($decoded['resettoken'], '_', true);

     //    $token_user_id = Hashids::decode($decoded['resettoken'])[0];

     //     $user = \App\Models\User::find($token_user_id);


     //    print_r($user);
    }

}
