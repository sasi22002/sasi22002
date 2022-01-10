<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Http\Controllers\restrictcontroller;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use App\Models\packageinfo;
use App\Models\UserPackage;
use App\Models\ScheduleTaskStatus as task;
use App\Models\Customer as Customers;
use Validator;
use Mail;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\AuthAdmin;
use App\Models\ApiAuth;
use telesign\sdk\messaging\MessagingClient;
use App\Models\EmpMapping;
use DB;

class UserController extends Controller {

    public function getalluser(Request $request) {
        $data = User::with('role')
                        ->where('is_active', 1)
                        ->whereIn('role_id', [1, 2])->
                        get()->toArray();
        foreach ($data as $key => $value) {

            $data[$key]['role'] = $data[$key]['role']['display_name'];
        }
        return Base::touser($data, true);
    }

    public function update_package(Request $request) {

        $beg_date = '2018-06-01';
        $end_date = '2018-06-24';
        // echo $dt;
        $userPackage = UserPackage::where([['beg_date', '<=', $beg_date]])->get();

        foreach ($userPackage as $use => $user) {

            $users_val = User::where([['user_id', '=', $user->user_id], ['role_id', '=', '2']])->first()->toArray();
            if ($users_val['user_id']) {
                $email = $users_val['email'];
                $userPackage = UserPackage::find($user->id);
                $userPackage->end_date = $end_date;
                $userPackage->save();

                // $user = \App\Models\User::find(46158);
                try {
                    $user = \App\Models\User::find($user->user_id);

                    if ($user->user_id) {
                        $notification = $user->notify(new \App\Notifications\Packageupdate($user));
                        event(new \App\Events\NotificationEvent($user));
                    }
                } catch (\Exception $e) {
                    echo $user->user_id . '-';
                    Mail::raw($userPackage, function ($message) {
                        $message->to('abinayah@way2smile.com');
                    });
                }
            }
            Mail::raw($userPackage, function ($message) {
                $message->to('abinayah@way2smile.com');
            });
            // Mail::raw($userPackage, function($message) use ($userPackage) {
            //         $message->to('abinayah@way2smile.com',"Abi")
            //         ->from('bd@manageteamz.com', 'fromName')
            //         ->replyTo('bd@manageteamz.com', 'fromName')
            //         ->subject("Package Upgrade!");
            //     });


            return Base::touser($userPackage, true);
        }
    }

    public function index(Request $request) {
        // try {
        $user_id = Base::auth_token($_SERVER['HTTP_AUTHORIZATION'])[1]->auth_user_id;
        $role = DB::table('user')->where('user_id', $user_id)->pluck('role_id')->first();
        if ($role == 4) {
            if ($request->input('active')) {
                $data = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                // ->where('user.is_active', 1)
                                ->where('emp_mapping.is_active', 1)
                                //->whereIn('user.role_id', [1,2])
                                ->where('emp_mapping.manager_id', '=', $this->emp_id)
                                //->whereNotIn('user.is_delete',[true,"true"])
                                ->where('user.user_token', '!=', null)
                                ->get()->toArray();
            } else {
                $data = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                // ->where('user.is_active', 1)
                                //->whereIn('user.role_id', [1,2])
                                ->where('emp_mapping.manager_id', '=', $this->emp_id)
                                //->whereNotIn('user.is_delete',[true,"true"])
                                ->where('user.user_token', '!=', null)
                                ->get()->toArray();
            }

            return Base::touser($data, true);
        }
        if ($this->admin || $this->backend) {

            if ($request->input('active')) {
                $data = User::with('role')
                                ->where('is_active', 1)
                                ->where('user_token', '!=', null)
                                ->whereIn('role_id', [1, 2])->
                                get()->toArray();
            } else {
                $data = User::with('role')->get()->toArray();
            }

            foreach ($data as $key => $value) {

                $data[$key]['role'] = $data[$key]['role']['display_name'];
            }
            return Base::touser($data, true);
        } elseif ($this->manager) {

            if ($request->input('active')) {

                /* $data = User::with('role')
                  ->where('is_active', 1)
                  ->whereIn('role_id', [1,2])
                  ->whereNotIn('is_delete',[true,"true"])
                  ->where('belongs_manager', $this->emp_id)
                  ->where('user_token','!=',null)
                  ->orWhere('user_id', $this->emp_id)

                  ->get()->toArray(); */
                $data = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                //->where('user.is_active', 1)
                                //->whereIn('user.role_id', [1,2])
                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                //->whereNotIn('user.is_delete',[true,"true"])
                                ->where('emp_mapping.is_delete', '!=', true)
                                ->where('emp_mapping.is_active', '=', 1)
                                ->where('user.user_token', '!=', null)
                                ->get()->toArray();
            } else {

                /* $data = User::with('role')
                  ->where('belongs_manager', $this->emp_id)
                  ->where('user_token','!=',null)
                  ->orWhere('user_id', $this->emp_id)
                  ->get()->toArray(); */
                $data = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                ->where('user.user_token', '!=', null)
                                ->get()->toArray();
                foreach ($data as $key => $val) {
                    $manager = User::find($val->manager_id);
                    if ($manager) {
                        $data[$key]->mmanager_first_name = $manager->first_name;
                        $data[$key]->mmanager_last_name = $manager->last_name;
                    } else {
                        $data[$key]->mmanager_first_name = '';
                        $data[$key]->mmanager_last_name = '';
                    }
                }
            }

            /* foreach ($data as $key => $value) {

              $data[$key]['role'] = $data[$key]['role']['display_name'];

              } */

            return Base::touser($data, true);
        } else {
            return Base::throwerror();
        }
        // } catch (\Exception $e) {
        //     return Base::throwerror();
        // }
    }

    public function get_managers(Request $request) {
        $user_id = Base::auth_token($_SERVER['HTTP_AUTHORIZATION'])[1]->auth_user_id;
        if (isset($request->active)) {
            $data = DB::table('user')
                            ->select(
                                    "user.phone",
                                    "emp_mapping.is_delete",
                                    "user.email",
                                    "user.first_name",
                                    "emp_mapping.is_active",
                                    "user.last_name",
                                    "emp_mapping.manager_id",
                                    "user.role_id",
                                    "user.user_id"
                            )
                            ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                            ->where('user.is_active', 1)
                            //->whereIn('user.role_id', [1,2])
                            ->where('emp_mapping.admin_id', '=', $user_id)
                            ->where('emp_mapping.emp_id', '=', null)
                            //->whereNotIn('user.is_delete',[true,"true"])
                            ->where('user.user_token', '!=', null)
                            ->get()->toArray();
        } else {
            $data = DB::table('user')
                            ->select(
                                    "user.phone",
                                    "emp_mapping.is_delete",
                                    "user.email",
                                    "user.first_name",
                                    "emp_mapping.is_active",
                                    "user.last_name",
                                    "emp_mapping.manager_id",
                                    "user.role_id",
                                    "user.user_id"
                            )
                            ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                            //->where('user.is_active', 1)
                            //->whereIn('user.role_id', [1,2])
                            ->where('emp_mapping.admin_id', '=', $user_id)
                            ->where('emp_mapping.emp_id', '=', null)
                            //->whereNotIn('user.is_delete',[true,"true"])
                            ->where('user.user_token', '!=', null)
                            ->get()->toArray();
        }

        return Base::touser($data, true);
    }

    public function resetpassword(Request $request) {
        $rules = [
            'new_password' => 'required',
            'confirm_password' => 'required|same:new_password',
            'user_id' => 'exists:user,user_id',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        // try {
        if ($this->admin || $this->backend) {
            $reset = User::find($data['user_id']);
        } elseif ($this->manager) {
            $reset = User::where('user_id', $data['user_id'])->first();
        } else {
            return Base::throwerror();
        }
        /* oauth api update call */

        $usr_tkn = $reset->user_token;

        if ($usr_tkn == null) {
            return Base::touser("Usertoken not updated,contact admin", false);
        }

        $oauth_token = AuthAdmin::find('1')->get()->toArray();
        $authtoken = $oauth_token[0]['auth_key'];

        $authtoken = $_SERVER['HTTP_AUTHORIZATION'];

        $token = Base::checkTokenStatus($authtoken);
        $x_client_data = Base::get_x_clinet_data_from_token($token);
        $password = $data['new_password'];

        $userInfo = array(
            "usr_name" => "super",
            "password" => $password,
            "usr_token" => $usr_tkn
        );

        $auth_url = "https://eazyfoodapp.com/api/users/UpdateAppUserCredentials";
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $authorization = "Authorization: Bearer " . $token;
        $_client_data = "x_client_data: " . $x_client_data;

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
            CURLOPT_POSTFIELDS => json_encode($userInfo),
            CURLOPT_HTTPHEADER => array(
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
                $_client_data,
                $authorization
            ),
        ));
        $curlresponse = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err) {
            return "cURL Error #:" . $err;
        } else {

            $json = json_decode($curlresponse, true);

            if ($json['status']['status_code'] == 200) {
                $json_data = $json['data'];

                if ($json_data['is_success'] == true) {
                    $reset->user_pwd = encrypt($data['new_password']);
                    $reset->save();
                    return Base::touser('Password Changed', true);
                } else {
                    return Base::throwerror();
                }
            } else {
                return Base::touser($json['status']['message'], false);
            }
        }

        // $reset->user_pwd = encrypt($data['new_password']);
        // $reset->save();
        // return Base::touser('Password Changed', true);
        // } catch (\Exception $e) {
        //     return Base::throwerror();
        // }
    }

    public function resetpasswordbyemp(Request $request) {
        $rules = [
            'new_password' => 'required',
            'confirm_password' => 'required|same:new_password',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        try {
            $reset = User::find($this->emp_id);
            $reset->user_pwd = encrypt($data['new_password']);
            $reset->save();
            return Base::touser('Password Changed', true);
        } catch (\Exception $e) {
            return Base::throwerror();
        }
    }

    public static function roles() {
        return Base::touser(UserRole::all(), true);
    }

    public static function managers() {
        try {
            $manager = UserRole::where('name', Base::manager())->get()->toArray();

            $role = $manager[0]['role_id'];

            return Base::touser(User::where('role_id', $role)->get(), true);
        } catch (\Exception $e) {
            return Base::throwerror();
        }
    }

    public function manager_details(Request $request, $id) {
        $user_id = Base::auth_token($_SERVER['HTTP_AUTHORIZATION'])[1]->auth_user_id;
        $data = DB::table('user')
                        ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                        ->where('emp_mapping.admin_id', '=', $user_id)
                        ->where('emp_mapping.manager_id', '=', $id)
                        ->get()->toArray()[0];
        return Base::touser($data, true);
    }

    public function store(Request $request) {
        $data = $request->input("data");

        if (isset($data["email"]) && $data["email"] != "") {
            $rules = [
                "role_id" => "required",
                "first_name" => "required",
                "phone" => "required"
            ];
        } else {
            $rules = [
                "role_id" => "required",
                "first_name" => "required",
                "phone" => "required"
            ];
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        /* check phone number in organization based */
        if (isset($data["email"]) && $data["email"] != "") {
            $get_emp_id = User::where("phone", "=", $data["phone"])->orWhere('email', '=', $data["email"])->get()->first();
        } else {
            $get_emp_id = User::where("phone", "=", $data["phone"])->get()->first();
        }
        $_count = count($get_emp_id);

        if ($_count > 0) {

            $r_emp_id = $get_emp_id->user_id;
            $email = isset($data["email"]) ? $data["email"] : $data["phone"];
            $emp = DB::table('user')
                    ->select('*')
                    ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                    ->where('user.phone', '=', $data["phone"])
                    //->orWhere('user.email', '=', $email)
                    ->where('emp_mapping.emp_id', '=', $r_emp_id)
                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                    ->get();
            $mgr = DB::table('user')
                    ->select('*')
                    ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                    ->where('user.phone', '=', $data["phone"])
                    //->orWhere('user.email', '=', $email)
                    ->where('emp_mapping.manager_id', '=', $r_emp_id)
                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                    ->get();
            $check_count = count($emp);
            if ($check_count > 0) {
                return Base::touser("Given delivery agent is already associated with your account.", false);
            }
            if (count($mgr) > 0) {
                return Base::touser("Given delivery agent is already associated with your account.", false);
            }
        }

        /* check email exists or not */
        /* if(isset($data["email"]) && $data["email"] != ""){
          $count = User::where('phone','=',$data["phone"])->get()->count();
          if($count > 0){
          if(isset($data["email"])){
          $_cunt = User::where('email','=',$data["email"])->get()->count();
          if($_cunt > 0){
          return Base::touser('The given email already exists.', false);
          }
          }
          }else{
          if(isset($data["email"]) && $data["email"] != ""){
          $_cunt = User::where('email','=',$data["email"])->get()->count();
          if($_cunt > 0){
          return Base::touser('The given email already exists.', false);
          }
          }
          }
          } */


        // $totalcount = User::where('is_delete','false')->where('belongs_manager',$this->emp_id)->count();
        // if(isset($data["email"]) && $data["email"] != ""){
        // }
        if ($this->role == 'sub_manager') {
            $emp = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            //->where('user.is_delete','=', 'false')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.emp_id', '!=', '')
                            //->where('emp_mapping.emp_id','!=',$this->emp_id)
                            ->where('emp_mapping.manager_id', '=', $this->emp_id)
                            ->get()->count();
        } else {
            if (isset($data['manager_id']) && $data['manager_id'] != '') {
                $emp = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                //->where('user.is_delete','=', 'false')
                                ->where('emp_mapping.is_delete', '=', false)
                                ->where('emp_mapping.manager_id', '=', $data['manager_id'])
                                ->where('emp_mapping.emp_id', '!=', '')
                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                ->get()->count();
            } else {
                $emp = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                //->where('user.is_delete','=', 'false')
                                ->where('emp_mapping.is_delete', '=', false)
                                ->where('emp_mapping.manager_id', '=', null)
                                ->where('emp_mapping.emp_id', '!=', '')
                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                ->get()->count();
            }
        }

        $totalcount = $emp;

        $emps = UserPackage::where('user_id', $this->emp_id)->first();

        if ($this->role == 'manager') {
            $mgr = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.emp_id', '=', '')
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->get()->count();

            if ($data['role_id'] == 4 && $emps['no_of_mgr'] <= $mgr) {
                return Base::touser('Manager Limit Crossed not able to create', false);
            }
        }

        $count = $emps['no_of_emp'] - $totalcount;

        if (isset($data["email"]) && $data["email"] != "") {
            $is_manager = DB::table('user')->where('role_id', '=', 4)->where('is_active', '=', true)->where(function ($query) use ($data) {
                        $query->where('phone', '=', $data["phone"])->orWhere('email', '=', $data["email"]);
                    })->get()->count();
        } else {
            $is_manager = DB::table('user')->where('role_id', '=', 4)->where('phone', '=', $data["phone"])->where('is_active', '=', true)->get()->count();
        }
        if ($is_manager > 0) {
            return Base::touser("The given Email already exist.", false);
        }
        if ($data['role_id'] == 4) {
            if (isset($data["email"]) && $data["email"] != "") {
                $is_delivery = DB::table('user')->where('role_id', '=', 1)->where('is_active', '=', true)->where(function ($query) use ($data) {
                            $query->where('phone', '=', $data["phone"])->orWhere('email', '=', $data["email"]);
                        })->get()->count();
            } else {
                $is_delivery = DB::table('user')->where('role_id', '=', 1)->where('phone', '=', $data["phone"])->where('is_active', '=', true)->get()->count();
            }

            if ($is_delivery > 0) {
                return Base::touser("Delivery agent can't be associate as manager.", false);
            }
        }

        if ($count > 0) {
            $pass = isset($data['user_pwd']) ? $data['user_pwd'] : '123';
            $password = encrypt($pass);
            $usertoken = User::where('user_id', '=', $this->emp_id)->get()->toArray();
            $usr_tkn = $usertoken[0]['user_token'];

            /* To signup provider */

            // $userInfo[] = array(
            //     "first_name" => $data['first_name'],
            //     "last_name" => isset($data['last_name']) ? $data['last_name'] : '',
            //     "email_address" => isset($data['email']) ? $data['email'] : '',
            //     "phone" => isset($data['phone']) ? trim($data['phone']) : '',
            //     "user_name" => isset($data['email']) ? $data['email'] : $data['phone'],
            //     "password" => $pass,
            //     "base64_img" => null,
            //     "app_usr_id" => 0,
            //     "ref_token" => $usr_tkn
            // );

            // $oauth_token = AuthAdmin::find('1')->get()->toArray();
            // $authtoken = $oauth_token[0]['auth_key'];

            // $token = Base::checkTokenStatus($authtoken);
            // $x_client_data = Base::get_x_clinet_data_from_token($token);

            // $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
            // $agent = $_SERVER['HTTP_USER_AGENT'];
            // $authorization = "Authorization: Bearer " . $token;
            // $_client_data = "x_client_data: " . $x_client_data;

            // $curl = curl_init();
            // curl_setopt_array($curl, array(
            //     CURLOPT_URL => $auth_url,
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_ENCODING => "",
            //     CURLOPT_MAXREDIRS => 10,
            //     CURLOPT_TIMEOUT => 30000,
            //     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //     CURLOPT_CUSTOMREQUEST => "POST",
            //     CURLOPT_USERAGENT => $agent,
            //     CURLOPT_POSTFIELDS => json_encode($userInfo),
            //     CURLOPT_HTTPHEADER => array(
            //         "accept: */*",
            //         "accept-language: en-US,en;q=0.8",
            //         "content-type: application/json",
            //         $_client_data,
            //         $authorization
            //     ),
            // ));
            // $curlresponse = curl_exec($curl);
            // $err = curl_error($curl);

            // curl_close($curl);
            // if ($err) {
            //     return Base::touser("cURL error", false);
            // } else {
            //     $json = json_decode($curlresponse, true);

            //     if ($json['status']['status_code'] == 200) {
            //         $json_data = $json['data'][0];
            //         if ($json_data['is_success'] == true) {
                        $user = new User();
                        $user->role_id = $data['role_id'];
                        $user->user_token = "";
                        $user->first_name = $data['first_name'];
                        $user->last_name = isset($data['last_name']) ? $data['last_name'] : '';
                        $user->user_pwd = $password;
                        $user->phone = isset($data['phone']) ? trim($data['phone']) : '';
                        $user->email = isset($data['email']) ? $data['email'] : '';
                        //$user->is_delete   = true;
                        //$user->is_delete      = $data['active'];
                        // $user->comments    = $data['comments'];
                        $user->comments1 = $request->get("comments1");
                        $user->comments2 = $request->get("comments2");
                        $user->comments3 = $request->get("comments3");
                        $user->comments4 = $request->get("comments4");
                        $user->comments5 = $request->get("comments5");
                        $user->whatsapp = $request->get("whatsapp");
                        $user->resaddress = $request->get("resaddress");
                        $user->city = isset($data['city']) ? $data['city'] : null;
                        $user->street = isset($data['street']) ? $data['street'] : null;
                        $user->employee_lat = isset($data["employee_lat"]) ? $data["employee_lat"] : null;
                        $user->employee_lng = isset($data["employee_lng"]) ? $data["employee_lng"] : null;
                        $user->state = isset($data['state']) ? $data['state'] : null;
                        $user->zipcode = isset($data['zipcode']) ? $data['zipcode'] : 0;
                        $user->country = isset($data['country']) ? $data['country'] : null;
                        $user->is_onboarding_success = 0;

                        $user->vehicle_type = isset($data['vehicle_type']) ? $data['vehicle_type'] : null;
                        $user->vehicle_model = isset($data['vehicle_model']) ? $data['vehicle_model'] : null;
                        $user->license_plate = isset($data['license_plate']) ? $data['license_plate'] : null;
                        $user->vehicle_number = isset($data['license_plate']) ? $data['license_plate'] : '';


                        // $user->profile_image = isset($data['profile_image']) ? json_encode($data['profile_image'], true) : '[]';
                        $user->profile_image = isset($data['profile_image']) ? $data['profile_image'] : '';


                        $user->phone_imei = isset($data['phone_imei']) ? $data['phone_imei'] : '';
                        //$user->is_active  = isset($data['is_active']) ? $data['is_active'] : 0;

                        if ($data['role_id'] == 1) {
                            if ($this->admin || $this->backend) {
                                if (empty($data['belongs_manager'])) {
                                    return Base::touser('Sale Person must have belongs to manager');
                                }
                                $user->belongs_manager = isset($data['belongs_manager']) ? $data['belongs_manager'] : null;
                            } elseif ($this->manager) {
                                $phone_number = str_replace("+", "", $data['phone']);
                                $customer_id = env("TELESIGN_CUSTOMER_ID");
                                $api_key = env("TELESIGN_API_KEY");
                                $message = "Welcome to Cybrix - A delivery solution. Your account is successfully registered. Please use the below link to install our mobile app " . env('APP_DOWNLOAD_LINK') . " Cybrix Your userId is : " . $data["phone"];
                                $message_type = "ARN";
                                $messaging = new MessagingClient($customer_id, $api_key);
                                $response = $messaging->message($phone_number, $message, $message_type);

                                $user->belongs_manager = $this->emp_id;
                            } else {
                                
                            }
                        }
                        $user->save();
                        /* organization mapping */
                        $EmpMapping = new EmpMapping();
                        $EmpMapping->admin_id = $this->emp_id;
                        if ($data['role_id'] == 4) {
                            $EmpMapping->manager_id = $user->user_id;
                        } else {
                            if (isset($data['manager_id'])) {
                                $EmpMapping->manager_id = $data['manager_id'];
                                $EmpMapping->emp_id = $user->user_id;
                            } else {
                                $EmpMapping->emp_id = $user->user_id;
                                if ($data['role_id'] == 2) {
                                    $user->belongs_manager = $user->user_id;
                                    $user->save();
                                } else if ($this->role == 'sub_manager') {
                                    $_data = DB::table('user')
                                                    ->select('*')
                                                    ->join('emp_mapping', 'emp_mapping.admin_id', '=', 'user.user_id')
                                                    ->where('emp_mapping.manager_id', '=', $this->emp_id)
                                                    ->where('emp_id', 'is', null)
                                                    ->where('emp_mapping.is_delete', '!=', true)
                                                    ->get()->toArray();
                                    if (count($_data) > 0) {
                                        $EmpMapping->admin_id = $_data[0]->admin_id;
                                    }
                                    $EmpMapping->manager_id = $this->emp_id;
                                }
                            }
                        }

                        $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
                        $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 1;
                        $EmpMapping->save();

                        $restrictcontroller = new restrictcontroller();
                        $restrictcontroller->updatecount();
                        #\App\Http\Controllers\NotificationsController::WelcomeEmp($user);
                        if ($data['role_id'] == 4) {
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
                            $userPackage->no_of_task = 0;
                            $userPackage->save();
                            return Base::touser('New Manager Created', true);
                        } else {
                            return Base::touser('Delivery Agent Created', true);
                        }
            //         } else {

            //             /* check count */
            //             if (isset($data["email"]) && $data["email"] != "") {
            //                 $count = User::where('email', '=', $data["email"])->where("phone", "=", $data['phone'])->get()->count();
            //             } else {
            //                 $count = User::where("phone", "=", $data['phone'])->get()->count();
            //             }

            //             if ($count > 0) {
            //                 if (isset($data["email"]) && $data["email"] != "") {
            //                     $get_id = User::where("phone", "=", $data['phone'])->where('email', '=', $data["email"])->get()->first();
            //                     $_user_update = User::where("phone", "=", $data['phone'])->where('email', '=', $data["email"])->first();
            //                     $_user_update->whatsapp = isset($data["whatsapp"]) ? $data["whatsapp"] : $_user_update->whatsapp;
            //                     $_user_update->save();
            //                 } else {
            //                     $get_id = User::where("phone", "=", $data['phone'])->get()->first();
            //                     $_user_update = User::where("phone", "=", $data['phone'])->get()->first();
            //                     $_user_update->whatsapp = isset($data["whatsapp"]) ? $data["whatsapp"] : $_user_update->whatsapp;
            //                     $_user_update->save();
            //                 }

            //                 $phone_number = str_replace("+", "", $data['phone']);
            //                 $customer_id = env("TELESIGN_CUSTOMER_ID");
            //                 $api_key = env("TELESIGN_API_KEY");
            //                 $message = "Welcome to ManageTeamz - A delivery solution. Your account is successfully registered. Please use the below link to install our mobile app " . env('APP_DOWNLOAD_LINK') . " manageteamz Your userId is : " . $data["phone"];
            //                 $message_type = "ARN";
            //                 $messaging = new MessagingClient($customer_id, $api_key);
            //                 $response = $messaging->message($phone_number, $message, $message_type);
            //                 /* organization mapping */
            //                 $EmpMapping = new EmpMapping();
            //                 $EmpMapping->admin_id = $this->emp_id;
            //                 if ($data['role_id'] == 4) {
            //                     $EmpMapping->manager_id = $get_id->user_id;
            //                 } else {
            //                     if (isset($data['manager_id'])) {
            //                         $EmpMapping->manager_id = $data['manager_id'];
            //                         $EmpMapping->emp_id = $get_id->user_id;
            //                     } else {
            //                         $EmpMapping->emp_id = $get_id->user_id;

            //                         if ($this->role == 'sub_manager') {
            //                             if ($data['role_id'] != 2) {
            //                                 //                                         $EmpMapping->emp_id = $get_id->user_id;
            //                                 $_data = DB::table('user')
            //                                                 ->select('*')
            //                                                 ->join('emp_mapping', 'emp_mapping.admin_id', '=', 'user.user_id')
            //                                                 ->where('emp_mapping.manager_id', '=', $this->emp_id)
            //                                                 ->where('emp_id', 'is', null)
            //                                                 ->where('emp_mapping.is_delete', '!=', true)
            //                                                 ->get()->toArray()[0];
            //                                 $EmpMapping->admin_id = $_data->admin_id;
            //                                 $EmpMapping->manager_id = $this->emp_id;
            //                             }
            //                         }
            //                     }
            //                 }

            //                 $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
            //                 $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 1;
            //                 $EmpMapping->save();
            //                 return Base::touser('Delivery Agent Associated', true);
            //             } else {
            //                 /* new entry */
            //                 $user = new User();
            //                 $user->role_id = $data['role_id'];
            //                 $user->user_token = $json_data['user_token'];
            //                 $user->first_name = $data['first_name'];
            //                 $user->last_name = isset($data['last_name']) ? $data['last_name'] : '';
            //                 $user->user_pwd = $password;
            //                 $user->phone = isset($data['phone']) ? trim($data['phone']) : '';
            //                 $user->email = isset($data['email']) ? $data['email'] : '';
            //                 //$user->is_delete   = true;
            //                 //$user->is_delete      = $data['active'];
            //                 // $user->comments    = $data['comments'];
            //                 $user->comments1 = $request->get("comments1");
            //                 $user->comments2 = $request->get("comments2");
            //                 $user->comments3 = $request->get("comments3");
            //                 $user->comments4 = $request->get("comments4");
            //                 $user->comments5 = $request->get("comments5");
            //                 $user->whatsapp = $request->get("whatsapp");
            //                 $user->resaddress = $request->get("resaddress");
            //                 $user->city = isset($data['city']) ? $data['city'] : null;
            //                 $user->street = isset($data['street']) ? $data['street'] : null;
            //                 $user->state = isset($data['state']) ? $data['state'] : null;
            //                 $user->zipcode = isset($data['zipcode']) ? $data['zipcode'] : 0;
            //                 $user->country = isset($data['country']) ? $data['country'] : null;
            //                 $user->is_onboarding_success = 0;

            //                 $user->vehicle_type = isset($data['vehicle_type']) ? $data['vehicle_type'] : null;
            //                 $user->vehicle_model = isset($data['vehicle_model']) ? $data['vehicle_model'] : null;
            //                 $user->license_plate = isset($data['license_plate']) ? $data['license_plate'] : null;
            //                 $user->vehicle_number = isset($data['license_plate']) ? $data['license_plate'] : '';


            //                 $user->profile_image = isset($data['profile_image']) ? json_encode($data['profile_image'], true) : '[]';

            //                 $user->phone_imei = isset($data['phone_imei']) ? $data['phone_imei'] : '';
            //                 //$user->is_active  = isset($data['is_active']) ? $data['is_active'] : 0;

            //                 if ($data['role_id'] == 1) {
            //                     if ($this->admin || $this->backend) {
            //                         if (empty($data['belongs_manager'])) {
            //                             return Base::touser('Sale Person must have belongs to manager');
            //                         }
            //                         $user->belongs_manager = isset($data['belongs_manager']) ? $data['belongs_manager'] : null;
            //                     } elseif ($this->manager) {
            //                         $phone_number = str_replace("+", "", $data['phone']);
            //                         $customer_id = env("TELESIGN_CUSTOMER_ID");
            //                         $api_key = env("TELESIGN_API_KEY");
            //                         $message = "Welcome to ManageTeamz - A delivery solution. Your account is successfully registered. Please use the below link to install our mobile app " . env('APP_DOWNLOAD_LINK') . " manageteamz Your userId is : " . $data["phone"];
            //                         $message_type = "ARN";
            //                         $messaging = new MessagingClient($customer_id, $api_key);
            //                         $response = $messaging->message($phone_number, $message, $message_type);

            //                         $user->belongs_manager = $this->emp_id;
            //                     } else {
                                    
            //                     }
            //                 }
            //                 $user->save();
            //                 if ($data['role_id'] == 2) {
            //                     $user->belongs_manager = $user->user_id;
            //                     $user->save();
            //                 }

            //                 /* organization mapping */
            //                 $EmpMapping = new EmpMapping();
            //                 $EmpMapping->admin_id = $this->emp_id;
            //                 if ($data['role_id'] == 4) {
            //                     $EmpMapping->manager_id = $user->user_id;
            //                     $EmpMapping->emp_id = $user->user_id;
            //                 } else {
            //                     $EmpMapping->emp_id = $user->user_id;
            //                     if (isset($data['manager_id'])) {
            //                         $EmpMapping->manager_id = $data['manager_id'];
            //                     } else {
            //                         if ($data['role_id'] == 2) {
            //                             $EmpMapping->emp_id = $user->user_id;
            //                         }
            //                     }
            //                 }

            //                 $EmpMapping->is_delete = 0;
            //                 $EmpMapping->is_active = 1;
            //                 $EmpMapping->save();

            //                 $restrictcontroller = new restrictcontroller();
            //                 $restrictcontroller->updatecount();
            //                 #\App\Http\Controllers\NotificationsController::WelcomeEmp($user);
            //                 if ($data['role_id'] == 4) {
            //                     $userPackage = new UserPackage();
            //                     $userPackage->user_id = $user->user_id;
            //                     $userPackage->package_id = "1";
            //                     $date = date('Y-m-d H:i:s');
            //                     $d = strtotime("+7 days");
            //                     $enddate = date("Y-m-d H:i:s", strtotime($date . ' + 7 days'));
            //                     $userPackage->beg_date = $date;
            //                     $userPackage->end_date = $enddate;
            //                     $userPackage->no_of_emp = 2;
            //                     $userPackage->no_of_mgr = 2;
            //                     $userPackage->no_of_cust = 0;
            //                     $userPackage->no_of_task = 0;
            //                     $userPackage->save();
            //                     return Base::touser('New Manager Created', true);
            //                 } else {
            //                     return Base::touser('Delivery Agent Created', true);
            //                 }
            //             }
            //         }
            // //     } else {
            // //         return Base::touser($json['status']['message'], false);
            // //     }
            // //     return Base::touser($curlresponse, true);
            }
            else{
                if (isset($data['manager_id']) && $data['manager_id'] == '') {
                    return Base::touser('Delivery Agent Limit Crossed not able to create', false);
                } else {
                    return Base::touser('Manager Limit Crossed not able to create', false);
                }

            }
        
           
    }

    public function show(Request $request, $id) {
        try {
            if ($this->admin || $this->backend) {


                if (!empty($request->input('belongs'))) {

                    $data = User::with('role')->with('cust')->find($id)->toArray();
                } else {
                    $data = User::with('role')->find($id)->toArray();
                }





                $data['role'] = $data['role']['display_name'];

                return Base::touser($data, true);
            } elseif ($this->manager) {

                if (!empty($request->input('belongs'))) {


                    // $data = User::with('role')->with('cust')->where('belongs_manager', $this->emp_id)
                    //     ->where('user_id', $id)->get()->toArray()[0];

                    $data = DB::table('user')
                                    ->select('*')
                                    ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                    ->where('emp_mapping.emp_id', '=', $id)
                                    ->get()->toArray()[0];
                } else {
                    /* $data = User::with('role')->where('belongs_manager', $this->emp_id)

                      ->where('user_id', $id)->get()->toArray()[0]; */
                    $data = DB::table('user')
                                    ->select(
                                            "user.activated_on",
                                            "user.activity",
                                            "emp_mapping.admin_id",
                                            "user.api_key",
                                            "user.auto_allocation_expires_in",
                                            "user.auto_allocation_find_in_km",
                                            "user.auto_allocation_max_radius_in_km",
                                            "user.belongs_manager",
                                            "user.bigcomm_hash",
                                            "user.bigcomm_store_id",
                                            "user.bigcomm_token",
                                            "user.business_name",
                                            "user.business_type",
                                            "user.city",
                                            "user.comments",
                                            "user.comments1",
                                            "user.comments2",
                                            "user.comments3",
                                            "user.comments4",
                                            "user.comments5",
                                            "user.comments6",
                                            "user.company_lat",
                                            "user.company_lng",
                                            "user.country",
                                            "user.country_code",
                                            "user.created_at",
                                            "user.current_package_id",
                                            "user.default_customer_address",
                                            "user.default_delivery_address",
                                            "user.deleted_at",
                                            "user.delivery_logic",
                                            "user.demo_links",
                                            "user.device_login_status",
                                            "user.email",
                                            "user.emp_id",
                                            "user.employee_lat",
                                            "user.employee_lng",
                                            "user.first_name",
                                            "user.id",
                                            "emp_mapping.is_active",
                                            "user.is_auto_allocation_enable",
                                            "user.is_auto_allocation_logic",
                                            "emp_mapping.is_delete",
                                            "user.is_multipick",
                                            "user.is_onboarding_success",
                                            "user.is_otp_verified",
                                            "user.is_redline",
                                            "user.is_task_track",
                                            "user.last_login",
                                            "user.last_name",
                                            "user.license_plate",
                                            "user.license_plate_back",
                                            "user.license_plate_front",
                                            "user.mailnote",
                                            "emp_mapping.manager_id",
                                            "user.multiple_deliveryaddress",
                                            "user.multiple_pickupaddress",
                                            "user.notification_time_in_minutes",
                                            "user.otp",
                                            "user.paper_task_count",
                                            "user.pdpa_compilance",
                                            "user.phone",
                                            "user.phone_imei",
                                            "user.pickup_address",
                                            "user.profile_image",
                                            "user.radius_address_zone",
                                            "user.radius_lat",
                                            "user.radius_long",
                                            "user.radius_zone",
                                            "user.resaddress",
                                            "user.role_id",
                                            "user.smsnote",
                                            "user.state",
                                            "user.street",
                                            "user.timezone",
                                            "user.timezonename",
                                            "user.updated_at",
                                            "user.user_id",
                                            "user.user_pwd",
                                            "user.user_token",
                                            "user.vehicle_image",
                                            "user.vehicle_model",
                                            "user.vehicle_number",
                                            "user.vehicle_type",
                                            "user.whatsapp",
                                            "user.zipcode",
                                            "user.zoom_control"
                                    )
                                    ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                                    ->where('emp_mapping.emp_id', '=', $id)
                                    ->get()->toArray()[0];
                }

                //$data['role'] = $data['role']['display_name'];

                $query = task::query();
                $query->where('emp_id', $id);

                $query->whereIn('status', array('Started Ride', 'In Supplier Place', 'Products Picked up'));
                $alivedata = $query->get();
                if (count($alivedata) == 0) {
                    //$data['alive'] = false;
                    $data->alive = false;
                } else {
                    //$data['alive'] = true;   
                    $data->alive = true;
                }
                return Base::touser($data, true);
            } else {
                if ($this->role == "sub_manager") {
                    $data = DB::table('user')
                                    ->select('*')
                                    ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                    ->where('emp_mapping.manager_id', '=', $this->emp_id)
                                    ->where('emp_mapping.emp_id', '=', $id)
                                    ->get()->toArray()[0];
                    return Base::touser($data, true);
                }
                return Base::throwerror();
            }
        } catch (\Exception $e) {
            return Base::throwerror();
        }
    }

    public function profile_image(Request $request, $id) {
        $data = $request->input('data');
        $user = new User();
        $user = $user->where('user_id', '=', $id)->first();
        $user->profile_image = isset($data['profile_image']) ? $data['profile_image'] : '';
//        $user->profile_image = isset($data['profile_image']) ? $data['profile_image'] : '';
        $user->save();
        return Base::touser('Profile Pic Updated', true);
    }

    public function status_update(Request $request, $id) {
        $data = $request->input('data');

        $rules = [
            'is_active' => 'required',
            'is_delete' => 'required'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
        if ($this->role == "sub_manager") {
            $role = "Delivery Agent";
            $EmpMapping = EmpMapping::where("manager_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->first();
            if ($EmpMapping->is_delete == 1 && $data['is_active'] == 1) {
                $totalcount = DB::table('user')
                                ->select('*')
                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                                //->where('user.is_delete','=', 'false')
                                ->where('emp_mapping.is_delete', '=', false)
                                //->where('emp_mapping.emp_id','!=',$this->emp_id)
                                ->where('emp_mapping.manager_id', '=', $this->emp_id)
                                ->get()->count();
                $emps = UserPackage::where('user_id', $EmpMapping->admin_id)->first();
//                $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
                //         if ((int)$emps['no_of_emp'] == $totalcount) {
                //             return Base::touser('Limit Crossed not able to changed to active', false);
                //         }
            }

            $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
            $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 0;
            $EmpMapping->save();
            return Base::touser($role . ' Updated', true);
        }


        if (isset($request->manager_id)) {
            $data_mgr = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->where('emp_mapping.manager_id', '=', $request->manager_id)
                            ->where('emp_mapping.is_delete', '!=', true)
                            ->where('emp_mapping.is_active', '=', 1)
                            ->where('user.user_token', '!=', null)
                            ->get()->count();
            $emps = UserPackage::where('user_id', $request->manager_id)->first();
        } else {
            $data_mgr = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->where('emp_mapping.manager_id', '=', null)
                            ->where('emp_mapping.is_delete', '!=', true)
                            ->where('emp_mapping.is_active', '=', 1)
                            ->where('user.user_token', '!=', null)
                            ->get()->count();
            $emps = UserPackage::where('user_id', $this->emp_id)->first();
        }

//        $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
//        $total_emp = (int)$emps["no_of_emp"] - (int)$emps["no_of_mgr"];
        $remaining_emp = (int) $emps["no_of_emp"] - (int) $data_mgr;
        if ($remaining_emp <= 0 && $data['is_active'] == 1) {
            return Base::touser('Limit Crossed not able to changed to active', false);
        }

        /* oauth api update call */
//        $empMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->toArray();
//        if(count($empMapping) == 0){
//            $role = "Manager";
//            $mgrMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("manager_id", "=", $id)->get()->toArray();
//            if(count($mgrMapping) > 0){
//                if ($mgrMapping[0]['is_delete'] == 1 && $data['is_active'] == 1) {
//                    $totalcount = DB::table('user')
//                                    ->select('*')
//                                    ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
//                                    //->where('user.is_delete','=', 'false')
//                                    ->where('emp_mapping.is_delete', '=', false)
//                                    ->where('emp_mapping.emp_id', '=', null)
//                                    //->where('emp_mapping.emp_id','!=',$this->emp_id)
//                                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
//                                    ->get()->count();
//                    $emps = UserPackage::where('user_id', $this->emp_id)->first();
//                    $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
//        
//                    if ($emps['no_of_emp'] == $totalcount) {
//                        return Base::touser('Manager Limit Crossed not able to changed to active', false);
//                    }
//                }
//            }
//        }
//        else{
//            $role = "Delivery Agent";
//            if ($empMapping[0]['is_delete'] == 1 && $data['is_active'] == 1) {
//
//                $emps = UserPackage::where('user_id', $this->emp_id)->first();
//                $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
//
//                $manager_ids = DB::table('emp_mapping')->where('admin_id', $this->emp_id)->where('is_delete', 0)->pluck('manager_id');
//                if(isset($request->manager_id)){
//                    $totalmgrcount = DB::table('user')
//                                ->select('*')
//                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
//                                //->where('user.is_delete','=', 'false')
//                                ->where('emp_mapping.is_delete', '=', false)
//                                //->where('emp_mapping.emp_id','!=',$this->emp_id)
//                                ->where('emp_mapping.manager_id', $request->manager_id)
//                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
//                                ->get()->count();
//                    if ($emps['no_of_emp'] <= $totalmgrcount) {
//                        return Base::touser('Delivery agent limit crossed to this manager please assign or active to another manager', false);
//                    }
//                }
//                else{
//                    $totalcount = DB::table('user')
//                                ->select('*')
//                                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
//                                //->where('user.is_delete','=', 'false')
//                                ->where('emp_mapping.is_delete', '=', false)
//                                //->where('emp_mapping.emp_id','!=',$this->emp_id)
//                                ->where('emp_mapping.admin_id', '=', $this->emp_id)
//                                ->get()->count();
//                
//                    $totalmax = $emps['no_of_mgr'] * $emps['no_of_emp'] + $emps['no_of_emp'];
//        
//                    if ($totalmax == $totalcount) {
//                        return Base::touser('Delivery agent limit crossed to this manager please assign or active to another manager', false);
//                    }
//                }
//            }
//        }

        $user = User::where('user_id', '=', $id)->first();
        if ($this->admin || $this->backend) {

            if (empty($data['belongs_manager'])) {

                return Base::touser('Sale Person must have belongs to manager');
            }

            $user->belongs_manager = isset($data['belongs_manager']) ? $data['belongs_manager'] : null;
        } elseif ($this->manager) {
            /* organization mapping */

            $EmpMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->first();
            if (!$EmpMapping) {
                $EmpMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("manager_id", "=", $id)->get()->first();
            }
            $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
            $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 0;
            $EmpMapping->save();
//            $user_update = User::where('user_id', $id)->update(['is_active' => isset($data['is_active']) ? $data['is_active'] : 0, 'is_delete' => isset($data['is_delete']) ? $data['is_delete'] : false]);

            $user->belongs_manager = $this->emp_id;
        } else {
            $EmpMapping = EmpMapping::where("manager_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->first();

            $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
            $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 0;
            $EmpMapping->save();
//            $user_update = User::where('user_id', $id)->update(['is_active' => isset($data['is_active']) ? $data['is_active'] : false, 'is_delete' => isset($data['is_delete']) ? $data['is_delete'] : false]);
        }
        $user->save();
        return Base::touser('Status Updated', true);
    }

    public function check_limit_crossed_or_not(Request $request) {

        if (isset($request->role_id)) {
            $total_emp = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.manager_id', '=', $this->emp_id)
                            ->get()->count();
            $empmapping = EmpMapping::where('manager_id', $this->emp_id)->first()->admin_id;
            $emps = UserPackage::where('user_id', $empmapping)->first();
            $emps_info = PackageInfo::where('id', $emps['package_id'])->first();

            $totalcount = $emps['no_of_mgr'];
        } else {
            $total_emp = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->get()->count();
            $emps = UserPackage::where('user_id', $this->emp_id)->first();
            $emps_info = PackageInfo::where('id', $emps['package_id'])->first();

            $totalcount = $emps['no_of_mgr'] * $emps['no_of_emp'] + $emps['no_of_emp'];
        }
        $count = $totalcount - $total_emp;
        $count = $emps['no_of_emp'] - $total_emp;
        // dd($total_emp, $totalcount);
        if ($count <= 0 && $emps['no_of_emp'] != -1) {
            return Base::touser('Delivery Agent Limit Crossed not able to changed to active', false);
        }

        return Base::touser([], true);
    }

    public function check_mgr_limit_crossed_or_not(Request $request) {

        if (isset($request->role_id)) {
            $totalcount = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.emp_id', '=', null)
                            ->where('emp_mapping.manager_id', '=', $this->emp_id)
                            ->get()->count();
        } else {
            $totalcount = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                            ->where('emp_mapping.is_delete', '=', false)
                            ->where('emp_mapping.emp_id', '=', null)
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->get()->count();
        }
        $emps = UserPackage::where('user_id', $this->emp_id)->first();
        $emps_info = PackageInfo::where('id', $emps['package_id'])->first();
        // dd($emps_info['no_of_mgr']);
        $count = (int) $emps['no_of_mgr'] - $totalcount;
        if ($count <= 0) {
            return Base::touser('Manager Limit Crossed not able to changed to active', false);
        }

        return Base::touser([], true);
    }

    public function update(Request $request, $id) {
        $data = $request->input('data');

        $rules = [
            'role_id' => 'required',
            'first_name' => 'required',
            'street' => 'required',
            // 'state'         => 'required',
            // 'zipcode'       => 'required',
            //             'country'       => 'required',
            'phone' => 'required|unique:user,phone,' . $id . ',user_id',
            // 'phone' => 'required',
            // 'profile_image' => 'required',
            'email' => 'email|unique:user,email,' . $id . ',user_id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        /* oauth api update call */


        $usertoken = User::where('user_id', '=', $id)->get()->toArray();

        $empMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->toArray();

        if ($empMapping[0]['is_delete'] == 1 && $data['is_active'] == 1) {
            $totalcount = DB::table('user')
                            ->select('*')
                            ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                            //->where('user.is_delete','=', 'false')
                            ->where('emp_mapping.is_delete', '=', false)
                            //->where('emp_mapping.emp_id','!=',$this->emp_id)
                            ->where('emp_mapping.admin_id', '=', $this->emp_id)
                            ->get()->count();
            $emps = UserPackage::where('user_id', $this->emp_id)->first();

            if ($emps['no_of_emp'] == $totalcount) {
                return Base::touser('Delivery Agent Limit Crossed not able to changed to active', false);
            }
        }

        $usr_tkn = $usertoken[0]['user_token'];

        if (empty($usr_tkn)) {
            return Base::touser('UserToken empty', false);
        }

        /* To signup provider */
        $first_name = $data['first_name'];
        $last_name = isset($data['last_name']) ? $data['last_name'] : '';
        $email = isset($data['email']) ? $data['email'] : '';
        $phone = isset($data['phone']) ? $data['phone'] : '';
        $user_name = isset($data['email']) ? $data['email'] : $data['phone'];

        $userInfo = array(
            "first_name" => $first_name,
            "last_name" => $last_name,
            "email_address" => $email,
            "phone" => $phone,
            "usr_token" => $usr_tkn
        );

        $oauth_token = AuthAdmin::find('1')->get()->toArray();
        $authtoken = $oauth_token[0]['auth_key'];

        $authtoken = $_SERVER['HTTP_AUTHORIZATION'];

        $token = Base::checkTokenStatus($authtoken);
        $x_client_data = Base::get_x_clinet_data_from_token($token);

        $auth_url = "https://eazyfoodapp.com/api/users/UpdateAppUserDetails";
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $authorization = "Authorization: Bearer " . $token;
        $_client_data = "x_client_data: " . $x_client_data;
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
            CURLOPT_POSTFIELDS => json_encode($userInfo),
            CURLOPT_HTTPHEADER => array(
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
                $_client_data,
                $authorization
            ),
        ));
        $curlresponse = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err) {
//            return "cURL Error #:" . $err;
            return Base::touser("cURL Error #:" . $err, false);
        } else {

            $json = json_decode($curlresponse, true);

            if ($json['status']['status_code'] == 200) {
                $json_data = $json['data'];

                if ($json_data['is_success'] == true) {
                    $user = new User();
                    $user = $user->where('user_id', '=', $id)->first();
                    $user->role_id = $data['role_id'];
                    $user->first_name = $first_name;
                    $user->last_name = $last_name;

                    # $user->user_pwd = isset($data['user_pwd']) ? $data['user_pwd'] : $user->user_pwd;
                    $user->phone = $phone;
                    $user->email = $email;
                    $user->comments1 = $request->get("comments1");
                    $user->comments2 = $request->get("comments2");
                    $user->comments3 = $request->get("comments3");
                    $user->comments4 = $request->get("comments4");
                    $user->comments5 = $request->get("comments5");
                    $user->whatsapp = $request->get("whatsapp");
                    $user->resaddress = $request->get("resaddress");
                    //$user->is_delete      = $data['active'];
                    $user->city = isset($data['city']) ? $data['city'] : null;
                    $user->street = isset($data['street']) ? $data['street'] : null;
                    $user->state = isset($data['state']) ? $data['state'] : null;
                    $user->zipcode = isset($data['zipcode']) ? $data['zipcode'] : 0;
                    $user->country = isset($data['country']) ? $data['country'] : null;
                    $user->license_plate = isset($data['license_plate']) ? $data['license_plate'] : null;
                    $user->vehicle_number = isset($data['license_plate']) ? $data['license_plate'] : '';
                    $user->vehicle_model = isset($data['vehicle_model']) ? $data['vehicle_model'] : null;
                    $user->vehicle_type = isset($data['vehicle_type']) ? $data['vehicle_type'] : null;
                    $user->profile_image = isset($data['profile_image']) ? json_encode($data['profile_image'], true) : '[]';
                    $user->phone_imei = isset($data['phone_imei']) ? $data['phone_imei'] : '';



                    //$user->is_active     = isset($data['is_active']) ? $data['is_active'] : 0;



                    if ($data['role_id'] == 1) {

                        if ($this->admin || $this->backend) {

                            if (empty($data['belongs_manager'])) {

                                return Base::touser('Sale Person must have belongs to manager');
                            }

                            $user->belongs_manager = isset($data['belongs_manager']) ? $data['belongs_manager'] : null;
                        } elseif ($this->manager) {

                            /* organization mapping */
                            $EmpMapping = EmpMapping::where("admin_id", '=', $this->emp_id)->where("emp_id", "=", $id)->get()->first();
                            $EmpMapping->is_delete = isset($data['is_delete']) ? $data['is_delete'] : 0;
                            $EmpMapping->is_active = isset($data['is_active']) ? $data['is_active'] : 1;
                            $EmpMapping->save();

                            $user->belongs_manager = $this->emp_id;
                        } else {
                            
                        }
                    }

                    $user->save();


                    if ($user->is_active == 0 && $data['role_id'] == 1) {


                        $customers = new Customers;

                        $customers->where('emp_id', $user->user_id)->update(array('emp_id' => $user->belongs_manager));
                    }

                    $restrictcontroller = new restrictcontroller();
                    $restrictcontroller->updatecount();
                    return Base::touser('Delivery Agent Updated', true);
                } else {
                    return Base::touser($json_data['response_msg'], false);
                }
            } else {
                return Base::touser($json['status']['message'], false);
            }
        }
    }

    public function destroy($id) {

        try {

            $api = new User();
            $api = $api->find($id);
            $api->delete();
            return Base::touser('Delivery Agent Deleted', true);
        } catch (\Exception $e) {

            return Base::touser("Can't able to delete Delivery Agent its connected to Other Data !");
            //return Base::throwerror();
        }
    }

    public function recover(Request $request) {
        $api = new User();
        $id = $request->input('id');
        $user = $api->onlyTrashed()->where('user_id', '=', $id)->first();
        $user->restore();
        return Base::touser('Delivery Agent Recovered', true);
    }

    public function update_otp(Request $request) {
        $res_data = $request->input('data');

        $data = User::find($this->emp_id);
        if ($data->phone == $res_data['phone'] && $data->otp == $res_data['otp']) {
            $user_id = $this->emp_id;
            $data = User::find($this->emp_id);
            $data->is_otp_verified = 'true';
            $data->save();
            return Base::touser($data, true);
        } else {
            return Base::touser("OTP verification failed", false);
        }
    }

    public static function ExistuserMigrations() {
        $users = User::all()->toArray();
        //$users = User::where('user_id','>',46283)
        //->get();
        $user_array = array();

        foreach ($users as $key => $userIn) {
            $password = $userIn['user_pwd'];
            $hash_password = decrypt($password);
            $userInfo[] = array(
                "first_name" => $userIn['first_name'],
                "last_name" => isset($userIn['last_name']) ? $userIn['last_name'] : '',
                "email_address" => isset($userIn['email']) ? $userIn['email'] : '',
                "phone" => isset($userIn['phone']) ? $userIn['phone'] : '',
                "user_name" => $userIn['email'],
                "password" => $hash_password,
                "base64_img" => null,
                "app_usr_id" => $userIn['user_id'],
                "ref_token" => ""
            );

            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5NDcxODg5LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.gigoTd2VWZQlu6B9iYpilVwMqbIml22OGuCe2Cx5eFE";

            $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $authorization = "Authorization: Bearer " . $token;
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
                CURLOPT_POSTFIELDS => json_encode($userInfo),
                CURLOPT_HTTPHEADER => array(
                    "accept: */*",
                    "accept-language: en-US,en;q=0.8",
                    "content-type: application/json",
                    "x_client_data: test",
                    $authorization
                ),
            ));
            $curlresponse = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            if ($err) {
                return "cURL Error #:" . $err;
            } else {

                $json = json_decode($curlresponse, true);

                $json_data = $json['data'];

                foreach ($json_data as $k => $value) {
                    if ($value['is_success'] == true) {
                        $user = User::find($value['app_usr_id']);
                        $user->user_token = $value['user_token'];
                        $user->save();
                    } else {
                        echo "not";
                    }
                }
            }

            $key++;
        }

        return Base::touser("Created", true);
    }

    public function getEmployeeByPhone(Request $request, $phone) {
        $get_emp_id = User::where("phone", "=", $phone)->get()->first();
        if (isset($request->manager_id) == 'true') {
            $mgr = User::where('phone', '=', $phone)->get();
            if (count($mgr) > 0) {
                return Base::touser("The given phone is already exist", false);
            }
        }
        $_count = count($get_emp_id);
        if ($_count > 0) {
            $r_emp_id = $get_emp_id->user_id;
            // $emp = DB::table('user')
            //         ->select('*')
            //         ->where('phone', '=', $phone)
            //         ->get();
            $emp = DB::table('user')
                    ->select('*')
                    ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                    ->where('user.phone', '=', $phone)
                    ->where('emp_mapping.emp_id', '=', $r_emp_id)
                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                    ->get();
            $mgr = DB::table('user')
                    ->select('*')
                    ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                    ->where('user.phone', '=', $phone)
                    ->where('emp_mapping.manager_id', '=', $r_emp_id)
                    ->where('emp_mapping.admin_id', '=', $this->emp_id)
                    ->get();
            // $check_count = count($emp);
            // if ($check_count > 0) {
            //     return Base::touser("Given delivery agent is already associated with your account.", false);
            // }
            // if (count($mgr) > 0) {
            //     return Base::touser("Given manager is already associated with your account.", false);
            // }
        }
        $user = User::where('phone', '=', $phone)->first();
        return Base::touser($user, true);
    }

}
