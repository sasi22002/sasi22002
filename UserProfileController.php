<?php
namespace App\Http\Controllers;

use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\timezone as timezonemang;
use App\Http\Controllers\Base;
use Validator;
use App\Models\AuthAdmin;
use App\Models\EmpMapping;
use DB;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    public function index()
    {
        return Base::touser(User::where('user_id', '=', $this->emp_id)->first(), true);
    }

    public function singleprofile(){
        $data = User::where('user_id', '=', $this->emp_id)->first();

        $manager_data = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
                            ->where('emp_mapping.emp_id','=',$this->emp_id)
                            ->orderBy('user.is_redline', 'desc')
                            ->take(1)
                            ->get()->toArray();
        // dd($manager_data);
        if($manager_data[0]->map_api_key == null || $manager_data[0]->map_api_key == "") {
            $data->map_api_key = env('MAPS_API_KEY');
        } else {
            $data->map_api_key = $manager_data[0]->map_api_key;
        }
        // dd($manager_data[0]->is_redline);
        // $image_urls=[];
        // foreach($manager_data as $data){
        //     $image_urls=array("image_url"=>url($data->profile_image));
        // }
        $data->is_task_track = $manager_data[0]->is_task_track;
        $data->timezone = $manager_data[0]->timezone;
        $data->timezonename = $manager_data[0]->timezonename;
        $data->is_redline = $manager_data[0]->is_redline;
        $data->profile_image=url($data->profile_image);
        return Base::touser($data, true);
    }
    public static function user_profileimage(Request $request)
    {
        return $request;
    	$file = $request->file('file');
        $user_id=$request->user_id;
        // $fileName = $user_id.'_'.time().'_'.Base::db_connection().'_'.$file->getClientOriginalName();
        // $filePath =$file->store('user_profile', ['disk' => 's3']);
        // $filepath =$file->getClientOriginalName();

        $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');

        $image_path=Storage::disk('s3')->url($t);    
        $user=User::find($user_id);
        if(isset($user)){
            $user->profile_image=$image_path;
            $user->save();    
            return Base::touser("profile uploaded successfully", true);
        }
        else{
            return Base::touser("user cant finded", false);
        }
      

    }

    public function updatezoom(Request $request){
    	$data = $request->input('data');
		$user = new User();
		$user = User::where('user_id','=',$this->emp_id)->first();
		$user->zoom_control = $data;
		$user->save();
		return Base::touser('Zoom updates successfully',true);	
    }

    public function update(Request $request)
    {
        $data = $request->input('data');


        $rules = [
                'first_name' => 'required',
                'last_name' => 'required',
//                'street' => 'required',
                // 'city' => 'required',
                // 'state' => 'required',
                // 'zipcode' => 'required',
                'phone' => 'required|unique:user,phone,'.$this->emp_id.',user_id',
                // 'country' => 'required',
                // 'profile_image' => 'required',
                #'email' => 'required|email|unique:user,email,'.$this->emp_id.',user_id'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        /*oauth api update call*/

        $usertoken = User::where('user_id','=',$this->emp_id)->get()->toArray();


        $usr_tkn = $usertoken[0]['user_token'];


         /*To signup provider*/
         $first_name = $data['first_name'];
         $last_name = isset($data['last_name'])?$data['last_name'] : '';
         $email = isset($data['email']) ? $data['email'] : '';
         $delivery_logic = isset($data['delivery_logic']) ? $data['delivery_logic'] : '';
         $pickup_address = "";
         if(Base::mobile_header() == 1){
            $pickup_address = isset($data['pickup_address']) ? $data['pickup_address'] : $usertoken[0]["pickup_address"];
         }else{
            $pickup_address = isset($data['pickup_address']) ? $data['pickup_address'] : "";
         }
         
         $phone = isset($data['phone']) ? $data['phone']:'';
         $user_name = isset($data['email']) ? $data['email'] : '';

        $userInfo = array(
                "firstname" => $first_name,
                "lastname" => $last_name,
                "email_address" => $email,
                "delivery_logic" => $delivery_logic,
                "pickup_address" => $pickup_address,
                "phone" => $phone,
                "usr_token" => $usr_tkn
            );


            if(isset($data['street']) and empty(explode('|',Base::latlong($data['street']))[0]))
             {
                return Base::touserloc('Location is not valid kindly use drag and drop','pickup');
             }

            $oauth_token = AuthAdmin::find('1')->get()->toArray();
            $authtoken = $oauth_token[0]['auth_key'];

            $authtoken = $_SERVER['HTTP_AUTHORIZATION'];
            //$x_client_data = $_SERVER['HTTP_X_CLIENT_DATA'];

            $token = Base::checkTokenStatus($authtoken);

            // $x_client_data = Base::get_x_clinet_data_from_token($token);

            // $auth_url = "https://eazyfoodapp.com/api/users/UpdateAppUserDetails";
            // $agent = $_SERVER['HTTP_USER_AGENT'];
            // $authorization = "Authorization: Bearer ".$token;
            // $_client_data = "x_client_data: ".$x_client_data;

            // $curl = curl_init();
            // curl_setopt_array($curl, array(
            // CURLOPT_URL => $auth_url,
            // CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_ENCODING => "",
            // CURLOPT_MAXREDIRS => 10,
            // CURLOPT_TIMEOUT => 30000,
            // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            // CURLOPT_CUSTOMREQUEST => "POST",
            // CURLOPT_USERAGENT => $agent,
            // CURLOPT_POSTFIELDS => json_encode($userInfo) ,
            // CURLOPT_HTTPHEADER => array(
            //         "accept: */*",
            //         "accept-language: en-US,en;q=0.8",
            //         "content-type: application/json",
            //         $_client_data,
            //         $authorization
            //     ) ,
            // ));
            // $curlresponse = curl_exec($curl);
            // $err = curl_error($curl);

            // curl_close($curl);
            // if ($err){
            //     return "cURL Error #:" . $err;
            // }else{

            //     $json = json_decode($curlresponse, true);
                
            //     if($json['status']['status_code'] == 200){
            //         $json_data = $json['data'];
            //         if($json_data['is_success'] == true){
                        
                        $user = new User();
                        $user = $user->where('user_id', '=', $this->emp_id)->first();
                        $user->first_name = $first_name;
                        $user->last_name = $last_name;
                        $user->phone = $phone;
                        
                        if (isset($data['street'])) {
                            $user->street = $data['street'];
                        }
                        if (isset($data['employee_lat'])) {
                            $user->company_lat = $data['employee_lat'];
                        }
                        if (isset($data['employee_lng'])) {
                            $user->company_lng = $data['employee_lng'];
                        }
                        $user->email = $email;
                        $user->pdpa_compilance = isset($data["pdpa_compilance"]) ? $data["pdpa_compilance"] : 0;

                        if(isset($data['delivery_logic'])) {
                            $user->delivery_logic = $delivery_logic;
                        }

                        $user->pickup_address = $pickup_address;

                        $user->notification_time_in_minutes = 60;
                        
                        if($request->input('timezone') !== null) {
                            $timedata = timezonemang::where('desc',$request->input('timezone'))->get();
                            $user->timezone = $request->input('timezone');
                            if(count($timedata)!= 0){
                                $user->timezonename = $timedata[0]['name'] ;
                            }
                        }
                        
//                        $user->mailnote=$request->input('mailnote');
                        $user->smsnote=$request->input('smsnote');
                        $user->is_multipick=$request->input('is_multipick');

                        if(isset($data['is_task_track']))
                        {
                            $user->is_task_track = $data['is_task_track'];
                        }

                        if(isset($data['is_redline']))
                        {
                            $user->is_redline = $data['is_redline'];
                        }

                        if(isset($data['whatsapp']))
                        {
                            $user->whatsapp = $data['whatsapp'];
                        }
                        // $user->city = $data['city'];
                        // $user->street = $data['street'];
                        // $user->state = $data['state'];
                        // $user->zipcode = $data['zipcode'];
                        // $user->email = $data['email'];
                        // $user->state = $data['state'];
                        // $user->zipcode = $data['zipcode'];
                        // $user->country = $data['country'];

                        if(isset($data['profile_image']))
                        {
                            Log::info($data['profile_image']);
                            $user->profile_image = $data['profile_image'];
                        }

                        
                        if (isset($data['business_name'])) {
                            $user->business_name = $data['business_name'];
                        }
                        
                        if (array_key_exists('map_api_key' , $data)) {
                            $user->map_api_key = $data['map_api_key'];
                        }
                        
                        if (isset($data['business_type'])) {
                            $user->business_type = $data['business_type'];
                        }
                        
                        if (isset($data['country_code'])) {
                            $user->country_code = $data['country_code'];
                        }

                        $user->save();
                        return Base::touser('Profile has been successfully updated', true);
            //         }else{
            //             return Base::touser('Failed to update', false);
            //         }
            //     }else{
            //         return Base::touser($json['status']['message'], false);
            //     }
                
            // }
    }



        public function updateActivity(Request $request)
        {
            $data = $request->input('data');

            $rules = [
                     'activity' => 'required'
            ];

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return Base::touser($validator->errors()->all()[0]);
            }
            $user = new User();
            $user = $user->where('user_id', '=', $this->emp_id)->first();
            $user->activity = $data['activity'];
            $user->save();

            // Active
            // In Active
            // Offline

// if($user->activity  == 'Offline')
// {

           $api =  \App\Models\TravelHistory::where('user_id','=', $user->user_id)
           ->orderBy('timestamp', 'desc')
           ->first();

if ($api === null) {

}
else
{
           event(new \App\Events\LocationUpdate($api, $this->emp_id));
}


// }

            return Base::touser('Profile Updated', true);
        }

        public function auto_allocation_update(Request $request){
            $data = $request->input('data');
            $user = User::find($this->emp_id);
            $user->is_auto_allocation_enable = isset($data['is_auto_allocation_enable'])?$data['is_auto_allocation_enable'] : $user->is_auto_allocation_enable;
            $user->is_auto_allocation_logic = isset($data['is_auto_allocation_logic'])?$data['is_auto_allocation_logic']: $user->is_auto_allocation_logic;
            $user->auto_allocation_expires_in = isset($data['auto_allocation_expires_in'])?$data['auto_allocation_expires_in']:$user->auto_allocation_expires_in;
            $user->auto_allocation_max_radius_in_km = isset($data['auto_allocation_max_radius_in_km'])?$data['auto_allocation_max_radius_in_km']: $user->auto_allocation_max_radius_in_km;
            $user->save();
            return Base::touser('Auto Allocation details are updated', true);
        }


    public function reset_password(Request $request)
    {
        $data = $request->input('data');


        $rules = [
'old_password' => 'required',
'new_password' => 'required',
'confirm_password' => 'required|same:new_password',
        ];


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }


        /*oauth api update call*/


        $usertoken = User::where('user_id','=',$this->emp_id)->get()->toArray();

        $usr_tkn = $usertoken[0]['user_token'];
        /*check password*/
        $oauth_token = AuthAdmin::find('1')->get()->toArray();
        $authtoken = $oauth_token[0]['auth_key'];

        $authtoken = $_SERVER['HTTP_AUTHORIZATION'];

        $token = Base::checkTokenStatus($authtoken);
        $x_client_data = Base::get_x_clinet_data_from_token($token);


        $get_pass = "https://eazyfoodapp.com/api/users/GetAppUserCredentials?usr_token=".$usr_tkn;
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $authorization = "Authorization: Bearer ".$token;
        $_client_data = "x_client_data: ".$x_client_data;
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $get_pass,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30000,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_USERAGENT => $agent,
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
                    $auth_psw = $json_data['password'];
                    if($data['old_password'] == $auth_psw){
                        /*To signup provider*/
                         $password = $data['new_password'];

                        $userInfo = array(
                                "usr_name" => "super",
                                "password" => $password,
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
                                        $reset = User::find($this->emp_id);
                                        $reset->user_pwd = encrypt($data['new_password']);
                                        $reset->save();
                                        return Base::touser('Password Changed', true);
                                    }else{
                                        return Base::throwerror();
                                    }
                                }else{
                                    return Base::touser($json['status']['message'],false);
                                }
                            }
                    }else{
                        return Base::touser('Old Password does not match');
                    }
                }else{
                    return Base::throwerror();
                }
            }else{
                return Base::touser($json['status']['message'],false);
            }
        }


         


        // try {
        //     $reset = User::find($this->emp_id);

        //     if ((decrypt($reset->user_pwd) === $data['old_password'])) {
        //         $reset->user_pwd = encrypt($data['new_password']);
        //         $reset->save();
        //         return Base::touser('Password Changed', true);
        //     }
        //     return Base::touser('Old Password Incorrect');
        // } catch (\Exception $e) {
        //     return Base::throwerror();
        // }
    }
}
