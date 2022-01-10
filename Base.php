<?php
namespace App\Http\Controllers;

use App\Models\ApiAuth;
use App\Models\CompanyDbInfo as db_info;
use App\Models\SuperAdmin as master;
use App\Models\UserRole;
use App\Models\User;
use App\Models\CustomerAddress;
use App\Models\UserPackage;
use App\Models\timezone as timezonemang;
use App\Models\Customer;
use App\Models\packageinfo;
use App\Models\Master as CompanyInfo;
use App\Models\EmpCustSchedule as emp_cust;
use Config;
use DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Vinkla\Hashids\Facades\Hashids;
use \DateTime;
use \DateTimeZone;
use App\Models\SessionClient as clientinfo;
use Carbon\Carbon;
use App\Models\TravelHistory;
use App\Http\Controllers\Textlocal;
use PushNotification;
use Mail;
use App\Models\AuthAdmin;
use App\Models\SnapData as snapdata;
use App\Models\ApiOrders;
use App\Models\ScheduleTaskStatus;
use App\Models\Questions;
use App\Models\ItemMap;
use telesign\sdk\messaging\MessagingClient;
use function telesign\sdk\util\randomWithNDigits;
use telesign\sdk\voice\VoiceClient;
use App\Models\EmpMapping;
use App\Models\MapSettings;
use App\Models\Items;
use App\Models\Master as ModelsMaster;

class Base extends Controller
{
    const salt = "92e46214d71e4362ab48b1cc72cc1d36";

    const tasktypes = ['Normal Visit', 'Request', 'Take Order', 'Complaint'];

    const modelmap = array(
        'Normal Visit' => 'OrderBooking',
        'Request' => 'OrderBooking',
        'Take Order' => 'OrderBooking',
        'Complaint' => 'OrderBooking',
        'Complaint' => 'OrderBooking',
    );

    const active = ['De Active', 'Active'];
    const visit_types = ['Waiting for Approvel', 'Approved', 'Un Approved'];

    const urls = array(
        "login" => "/dashboard/#/signin"
    );

    public function broadcastAuth(Request $request)
    {
        echo 'auth';
        // print_r($request->all());
        // abort(403);
        
    }

    public static function convert_to_url($name)
    {
        return preg_replace('/\s+/', '', $name);
        //return str_slug($name, '-');
        
    }

    public function update_license_info(Request $request){
        $data = $request->input("data");
        $user = User::find($this->emp_id);
        $user->license_plate_front = isset($data['license_plate_front']) ? json_encode($data['license_plate_front'], true) : '[]';
        $user->license_plate_back = isset($data['license_plate_back']) ? json_encode($data['license_plate_back'], true) : '[]';
        $user->save();
        return self::touser('successfully updated',true);

    }

    public function update_vehicle_info(Request $request){
        $data = $request->input("data");
        $user = User::find($this->emp_id);
        $user->vehicle_image = isset($data['vehicle_image']) ? json_encode($data['vehicle_image'], true) : '[]';
        $user->vehicle_type = isset($data['vehicle_type']) ? $data['vehicle_type'] : '';
        $user->vehicle_number = isset($data['vehicle_number']) ? $data['vehicle_number'] : '';
        $user->license_plate = isset($data['vehicle_number']) ? $data['vehicle_number'] : '';
        $user->vehicle_model = isset($data['vehicle_model']) ? $data['vehicle_model'] : '';
        $user->save();
        return self::touser('successfully updated',true);

    }

    public function radius_update(Request $request){
        $data   = $request->input("data");
        $user   = User::find($this->emp_id);
        $user->radius_address_zone = isset($data['radius_address_zone']) ? $data['radius_address_zone'] : '';
        $user->radius_zone    = isset($data['radius_zone']) ? $data['radius_zone'] : '';
        $user->radius_lat    = isset($data['radius_lat']) ? $data['radius_lat'] : '';
        $user->radius_long    = isset($data['radius_long']) ? $data['radius_long'] : '';
        $user->save();
        return self::touser('successfully updated',true);
    }

    public function addquestions(Request $request){
        $data = $request->input("data");
        $questions = new Questions();
        $questions->questions = $data["questions"];
        $questions->status = 1;
        $questions->save();
        return self::touser("Question Added", true);
    }

    public function questions_list(){
        $questions = Questions::where("status","=",1)->get()->toArray();
        return self::touser($questions, true);
    }

    public function remaining_emp(Request $request){
        $data = [
            "total_emp"     => 0,
            "remaining_emp" => 0
        ];
        $total_emp = 0;
        $remaining_emp = 0;
//        $total = 0;
        
        if($this->role == "sub_manager"){
            $admin_emp_id = EmpMapping::where('manager_id',$this->emp_id)->first()->admin_id;
            $emp_id = $admin_emp_id;
        } else {
            $emp_id = $this->emp_id;
        }

        $data = DB::table('user')
                ->select('*')
                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                ->where('emp_mapping.admin_id', '=', $emp_id)
                ->where('emp_mapping.is_delete', '!=', true)
                ->where('emp_mapping.is_active', '=', 1)
                ->where('user.user_token', '!=', null)
                ->get()->count();
        
        $mgr = DB::table('user')
                ->select('*')
                ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                ->where('emp_mapping.admin_id', '=', $emp_id)
                ->where('emp_mapping.is_delete', '!=', true)
                ->where('emp_mapping.emp_id', '=', null)
                ->where('emp_mapping.is_active', '=', 1)
                ->where('user.user_token', '!=', null)
                ->get()->count();

        $emps = UserPackage::where('user_id', $emp_id)->first();
//        $package_info = packageinfo::where('id', $emps['package_id'])->first();

        if($this->role == "sub_manager") {
            $total_emp = (int)$emps["no_of_emp"];
            $remaining_emp = (int)$total_emp - (int)$data;
            $data = [
                "total_emp"     => (int)$total_emp,
                "remaining_emp" => $remaining_emp
            ];
//            $empmapping = EmpMapping::where('manager_id',$this->emp_id)->first()->admin_id;
//            $emps = UserPackage::where('user_id', $empmapping)->first();
//            $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
//            $data_mgr = DB::table('user')
//                ->select('*')
//                ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
//                ->where('emp_mapping.manager_id', '=', $this->emp_id)
//                ->where('emp_mapping.is_delete', '!=', true)
//                ->where('emp_mapping.is_active', '=', 1)
//                ->where('user.user_token', '!=', null)
//                ->get()->count();
//            $remaining_mgr_emp = (int)$emps["no_of_emp"] - $data_mgr;
//            $data = [
//                "total_emp" => (int)$emps["no_of_emp"],
//                "remaining_emp" => $remaining_mgr_emp
//            ];
//            return Base::touser($data,true);
        } else {
            $total_emp = (int)$emps["no_of_emp"];
            $remaining_emp = (int)$total_emp - (int)$data;
            $remaining_mgr = (int)$emps["no_of_mgr"] - $mgr;
            $data = [
                "total_emp"     => (int)$total_emp,
                "remaining_emp" => $remaining_emp,
                "total_mgr" => (int)$emps["no_of_mgr"],
                "remaining_mgr" => $remaining_mgr
            ];
        }
        return Base::touser($data, true);
    }
    
    public static function check_all_task_status($order_id) {
        $count = emp_cust::where("mt_order_id", $order_id)->whereNotIn("status", ["Allocated", "In-Progress", "Started Ride", "In Supplier Place", "Products Picked up"])->count();
        $r_count = emp_cust::where("mt_order_id", $order_id)->count();
        if ($r_count == $count) {
            return true;
        } else {
            return false;
        }
    }

    public static function clone_full_order($order_id){
        if($order_id){
            $_task_id = Base::getTaskId($order_id);
            $order_details = ApiOrders::where("id","=",$order_id)->get()->first();

            $Orders = new ApiOrders();
            $Orders->emp_id = $order_details->emp_id;
            $Orders->added_by = $order_details->added_by;
            $Orders->order_start_time = $order_details->order_start_time;
            $Orders->order_end_time = $order_details->order_end_time;
            $Orders->is_multipickup = $order_details->is_multipickup;
            $Orders->is_multidelivery = $order_details->is_multidelivery;
            $Orders->delivery_logic = $order_details->delivery_logic;
            $Orders->status = "Unallocated";
            $Orders->save();

            $itemmap = ItemMap::where("order_id","=",$order_id)->get()->toArray();
            if(count($itemmap)>0){
                foreach ($itemmap as $key => $value) {

                    $createitemmap = new ItemMap();
                    $createitemmap->item_id = $value['item_id'];
                    $createitemmap->order_id = $Orders->id;
                    $createitemmap->stage = $value['stage'];
                    $createitemmap->quantity = $value['quantity'];
                    $createitemmap->save();
                }
                
            }
            foreach($_task_id as $key => $value){
                $task_details = emp_cust::where("id","=",$value)->get()->first();

                /*clone the task*/
                if($task_details->status != "Delivered") {

                    /*create task*/
                    $task    = new emp_cust(); 
                    $task->schedule_date_time = $task_details->schedule_date_time;

                    $task->added_by = $task_details->added_by;

                    $order_id=$task_details->order_id;

                    $task->cust_id        = $task_details->cust_id;
                    $task->notes          = $task_details->notes;
                    
                    $task->pick_address   = $task_details->pick_address;
                    $task->order_id       = $order_id;
                    $task->comments       = $task_details->comments;
                    $task->mob            = $task_details->mob;
                    $task->receiver_name = $task_details->receiver_name;
                    $task->cust_email     = $task_details->cust_email;
                    $task->sender_name    = $task_details->sender_name;

                    $task->picktime       = $task_details->picktime;
                    $task->pickup_long    = $task_details->pickup_long;
                    $task->pickup_ladd    = $task_details->pickup_ladd;
                    $task->pickup_phone    = $task_details->pickup_phone;
                    $task->sent_address   =  $task_details->sent_address;
                    $task->sender_name   =  $task_details->sender_name;
                    $task->sender_number   =  $task_details->sender_number;

                    $task->status         = "Unallocated";
                    $task->customer_pickupaddr_id = $task_details->customer_pickupaddr_id;
                    $task->customer_deliveryaddr_id = $task_details->customer_deliveryaddr_id;
                    $task->cust_phone   = $task_details->cust_phone;
                    $task->loc_lat      = $task_details->loc_lat;
                    $task->loc_lng      = $task_details->loc_lng;
                    $task->cust_address = $task_details->cust_address;
                    $task->delivery_notes = $task_details->delivery_notes;
                    $task->method         = $task_details->method;
                    $is_new_address = false;
                    $task->is_new_address = $is_new_address;
                    
                    $task->geo_fence_meter = $task_details->geo_fence_meter;
                    $task->is_geo_fence    = $task_details->is_geo_fence;

                    $task->task_status = 0;
                    $task->approve_status = 0;


                    $task->mt_order_id = $Orders->id;
                    $task->priority = 1;



                    $task->product_weight      = 0;
                    $task->product_size      =  null;
                    $task->time_to_delivery      = null;
                    $task->time_requirement      = null;

                    $task->product_length      = 0;
                    $task->product_height      = 0;
                    $task->product_breadth      =  0;

                    $task->save();

                    $task_status             = new ScheduleTaskStatus();
                    $task_status->emp_id     = $task_details->allocated_emp_id;
                    $task_status->task_id    = $task->id;
                    $task_status->address    = '';
                    $task_status->lat        = '';
                    $task_status->long       = '';
                    $task_status->status     = "Unallocated";
                    $task_status->timestamps = date("Y-m-d H:i:s");
                    $task_status->created_time = date("Y-m-d H:i:s");
                    $task_status->save();
                }
            }
        }
    }

    public static function clone_order($task_id){
        if($task_id){
            $task_details = emp_cust::where("id","=",$task_id)->get()->first();
            if($task_details){
                $order_id = $task_details->mt_order_id;
                $stage = $task_details->priority;
                $order_details = ApiOrders::where("id","=",$order_id)->get()->first();
                if($order_details){
                    /*create order*/
                    $Orders = new ApiOrders();
                    $Orders->emp_id = $order_details->emp_id;
                    $Orders->added_by = $order_details->added_by;
                    $Orders->order_start_time = $order_details->order_start_time;
                    $Orders->order_end_time = $order_details->order_end_time;
                    $Orders->is_multipickup = $order_details->is_multipickup;
                    $Orders->is_multidelivery = $order_details->is_multidelivery;
                    $Orders->delivery_logic = $order_details->delivery_logic;
                    $Orders->status = "Unallocated";
                    $Orders->save();

                    $itemmap = ItemMap::where("order_id","=",$order_id)->where('stage','=',$stage)->get()->toArray();
                    if(count($itemmap)>0)
                    {
                        foreach ($itemmap as $key => $value) {

                            $createitemmap = new ItemMap();
                            $createitemmap->item_id = $value['item_id'];
                            $createitemmap->order_id = $Orders->id;
                            $createitemmap->stage = 1;
                            $createitemmap->quantity = $value['quantity'];
                            $createitemmap->save();
                        }
                        
                    }

                    /*create task*/
                    $task    = new emp_cust(); 
                    $task->schedule_date_time = $task_details->schedule_date_time;

                    $task->added_by = $task_details->added_by;

                    $order_id=$task_details->order_id;

                    $task->cust_id        = $task_details->cust_id;
                    $task->notes          = $task_details->notes;
                    
                    $task->pick_address   = $task_details->pick_address;
                    $task->order_id       = $order_id;
                    $task->comments       = $task_details->comments;
                    $task->mob            = $task_details->mob;
                    $task->receiver_name = $task_details->receiver_name;
                    $task->cust_email     = $task_details->cust_email;
                    $task->sender_name    = $task_details->sender_name;

                    $task->picktime       = $task_details->picktime;
                    $task->pickup_long    = $task_details->pickup_long;
                    $task->pickup_ladd    = $task_details->pickup_ladd;
                    $task->sent_address   =  $task_details->sent_address;
                    $task->sender_name   =  $task_details->sender_name;
                    $task->sender_number   =  $task_details->sender_number;

                    $task->status         = "Unallocated";
                    $task->customer_pickupaddr_id = $task_details->customer_pickupaddr_id;
                    $task->customer_deliveryaddr_id = $task_details->customer_deliveryaddr_id;
                    $task->cust_phone   = $task_details->cust_phone;
                    $task->loc_lat      = $task_details->loc_lat;
                    $task->loc_lng      = $task_details->loc_lng;
                    $task->cust_address = $task_details->cust_address;
                    $task->delivery_notes = $task_details->delivery_notes;
                    $task->method         = $task_details->method;
                    $is_new_address = false;
                    $task->is_new_address = $is_new_address;
                    
                    $task->geo_fence_meter = $task_details->geo_fence_meter;
                    $task->is_geo_fence    = $task_details->is_geo_fence;

                    $task->task_status = 0;
                    $task->approve_status = 0;


                    $task->mt_order_id = $Orders->id;
                    $task->priority = 1;



                    $task->product_weight      = 0;
                    $task->product_size      =  null;
                    $task->time_to_delivery      = null;
                    $task->time_requirement      = null;

                    $task->product_length      = 0;
                    $task->product_height      = 0;
                    $task->product_breadth      =  0;

                    $task->save();

                    //check Customer exists 
                     $getcust = count(Customer::where('contact_no','=',$task_details->cust_phone)->get()->toArray());
                    if($getcust==0)
                    {
                        $cust_create            = new Customer();   
                        $cust_create->name      = $task_details->receiver_name;
                        $cust_create->emp_id     = $task_details->allocated_emp_id;
                        $cust_create->email     = $task_details->cust_email;
                        $cust_create->address   = $task_details->cust_address;
                        $cust_create->loc_lat   = $task_details->loc_lat;
                        $cust_create->loc_lng   = $task_details->loc_lng;
                        $cust_create->contact_no = $task_details->cust_phone;
                        $cust_create->save();                    
                    }
                    // end customer exists

                    $task_status             = new ScheduleTaskStatus();
                    $task_status->emp_id     = $task_details->allocated_emp_id;
                    $task_status->task_id    = $task->id;
                    $task_status->address    = '';
                    $task_status->lat        = '';
                    $task_status->long       = '';
                    $task_status->status     = "Unallocated";
                    $task_status->timestamps = date("Y-m-d H:i:s");
                    $task_status->created_time = date("Y-m-d H:i:s");
                    $task_status->save();
                } 

            }
        }
    }

    public static function send_otp(Request $request)
    {
        $data = $request->input('data');
        $user = self::getRole();
        $userid = $user[1];
        $rules = ['phone' => 'required|unique:user,phone,' . $userid . ',user_id', ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails())
        {
            return Base::touser($validator->errors()
                ->all() [0]);
        }
        // return $data;
        $otp = rand(1000, 9999);
        $user = User::where([['user_id', '=', $userid], ['phone', '=', $data['phone']]])->get();
        if ($user)
        {
            $user_data = User::find($userid);
            $user_data->phone = $data['phone'];
            $user_data->otp = $otp;
            $user_data->save();

            $curl = curl_init();

            $message = "Cybrix : Your OTP is " . $otp;
            $number = $data['phone'];
            $sender = "MTEAMZ";
            $auth_key = "250611AJUEMGrDiX5c08cb4c";
            curl_setopt_array($curl, array(
                CURLOPT_URL => "http://control.msg91.com/api/sendotp.php?template=&otp_length=&authkey=" . $auth_key . "&message=" . $message . "&sender=" . $sender . "&mobile=" . $number . "&otp=" . $otp . "&otp_expiry=&email=",
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

            if ($err)
            {
                return "cURL Error #:" . $err;
            }
            else
            {
                return $response;
            }
        }

        // return $user_data;
        
    }

    public static function latlong($location)
    {
        // $url = htmlspecialchars_decode('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($location) . '&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk');
        //return $url;
        $url = htmlspecialchars_decode('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($location) . '&key=AIzaSyA75u0SjeByU7Rm4P7TJ4ifaDsmd9fwQ-w');

        $json = file_get_contents($url);
        $res = json_decode($json);
        if (!empty($res->results))
        {
            $lat = $res->results[0]
                ->geometry
                ->location->lat;
            $lng = $res->results[0]
                ->geometry
                ->location->lng;
            return $lat . '|' . $lng;
        }
        else
        {
            return '|';
        }

    }

    public function checkstr($string, $len)
    {
        if (strlen($string) > 50)
        {
            $string = substr($string, 0, 50) . '...';
        }
        return $string;
    }

    public function getPickupAddress()
    {
        $data = CustomerAddress::where('user_id', '=', $this->emp_id)
            ->where('is_pickup_address', '=', 1)
            ->where('is_delivery_address', '=', 0)
            ->get()
            ->toArray();
        $users = [];
        foreach ($data as $key => $value)
        {

            $users[] = ['businessname' => $value['businessname'], 'customer_lat' => $value['customer_lat'], 'customer_lng' => $value['customer_lng'], 'id' => $value['id'], 'is_delivery_address' => $value['is_delivery_address'], 'is_pickup_address' => $value['is_pickup_address'], 'street' => self::checkstr($value['street'], 30) , 'user_id' => $value['user_id']];
        }
        return self::touser($users, true);
    }

    public function getDeliveryAddress()
    {
        $data = CustomerAddress::where('user_id', '=', $this->emp_id)
            ->where('is_pickup_address', '=', 0)
            ->where('is_delivery_address', '=', 1)
            ->get()
            ->toArray();
        $users = [];
        foreach ($data as $key => $value)
        {

            $users[] = ['businessname' => $value['businessname'], 'customer_lat' => $value['customer_lat'], 'customer_lng' => $value['customer_lng'], 'id' => $value['id'], 'is_delivery_address' => $value['is_delivery_address'], 'is_pickup_address' => $value['is_pickup_address'], 'street' => self::checkstr($value['street'], 30) , 'user_id' => $value['user_id']];
        }
        return self::touser($users, true);
    }

    public function removeAddress(Request $request)
    {
        $data = $request->input('data');
        $api = CustomerAddress::find($data);
        $api->delete();
        return self::touser("Address removed", true);
    }

    public function defaultcustaddress(Request $request)
    {
        $data = $request->input('data');

        $api = User::find($this->emp_id);
        $api->default_customer_address = $data;
        $api->save();
        return self::touser("Default address selected", true);
    }

    public function defaultdeliveryaddress(Request $request)
    {
        $data = $request->input('data');

        $api = User::find($this->emp_id);
        $api->default_delivery_address = $data;
        $api->save();
        return self::touser("Default address selected", true);
    }

    public function getPickupAddressbyid(Request $request)
    {
        $data = $request->input('data');
        $api = CustomerAddress::where('id', '=', $data)->count();
        if ($api > 0)
        {
            $api_ = CustomerAddress::where('id', '=', $data)->get()
                ->first();
            return self::touser($api_, true);
        }
        else
        {
            return self::touser("No data found", false);
        }
    }

    public function task_check(Request $request, $task_id)
    {
        $status = true;
        $data = emp_cust::where('id', '=', $task_id)->get()
            ->first();
        if (!empty($data))
        {
            $order_id = $data['mt_order_id'];
            $datas = emp_cust::where('mt_order_id', $order_id)->get()
                ->toArray();
            foreach ($datas as $key => $value)
            {
                if ($value["status"] != "Delivered")
                {
                    $status = false;
                }
            }
        }

        return Base::touser($status);
    }

    public static function delivery_status_check($task_id)
    {

        $status = true;
        $data = emp_cust::where('id', '=', $task_id)->get()
            ->first();
        if (!empty($data))
        {
            $order_id = $data['mt_order_id'];
            $datas = emp_cust::where('mt_order_id', $order_id)->get()
                ->toArray();
            foreach ($datas as $key => $value)
            {
                if ($value["status"] != "Delivered")
                {
                    $status = false;
                }
            }
        }

        return $status;

    }

    public static function getTaskId($order_id)
    {
        $task_id = [];
        $data = emp_cust::where('mt_order_id', $order_id)->get(['id'])
            ->toArray();

        foreach ($data as $key => $value)
        {
            $task_id[] = $data[$key]['id'];
        }
        return $task_id;
    }

    public static function getOrderId($task_id)
    {
        $data = emp_cust::where('id', '=', $task_id)->get()
            ->first();
        if (!empty($data))
        {
            return $data['mt_order_id'];
        }
    }

    public function getDeliveryAddressbyid(Request $request)
    {
        $data = $request->input('data');
        $api = CustomerAddress::where('id', '=', $data)->count();
        if ($api > 0)
        {
            $api_ = CustomerAddress::where('id', '=', $data)->get()
                ->first();
            return self::touser($api_, true);
        }
        else
        {
            return self::touser("No data found", false);
        }
    }

    public function customeraddress(Request $request)
    {
        $data = $request->input('data');

        // $api = CustomerAddress::where('user_id','=',$this->emp_id)
        //         ->delete();
        foreach ($data as $key => $value)
        {

            $id = isset($value['id']) ? $value['id'] : '';

            if (!empty($id))
            {
                $customeraddress = CustomerAddress::find($id);
                $customeraddress->street = $value['street'];
                $customeraddress->businessname = $value['businessname'];
                $customeraddress->user_id = $this->emp_id;
                $customeraddress->customer_lat = $value['customer_lat'];
                $customeraddress->customer_lng = $value['customer_lng'];
                $customeraddress->is_pickup_address = 1;
                $customeraddress->is_delivery_address = 0;
                $customeraddress->save();
            }
            else
            {
                $customeraddress = new CustomerAddress();
                $customeraddress->street = $value['street'];
                $customeraddress->businessname = $value['businessname'];
                $customeraddress->user_id = $this->emp_id;
                $customeraddress->customer_lat = $value['customer_lat'];
                $customeraddress->customer_lng = $value['customer_lng'];
                $customeraddress->is_pickup_address = 1;
                $customeraddress->is_delivery_address = 0;
                $customeraddress->save();
            }

        }

        return self::touser("Address added", true);
    }

    public function customerDeliveryaddress(Request $request)
    {
        $data = $request->input('data');

        // $api = CustomerAddress::where('user_id','=',$this->emp_id)
        //         ->delete();
        foreach ($data as $key => $value)
        {

            $id = isset($value['id']) ? $value['id'] : '';

            if (!empty($id))
            {
                $customeraddress = CustomerAddress::find($id);
                $customeraddress->street = $value['street'];
                $customeraddress->businessname = $value['businessname'];
                $customeraddress->user_id = $this->emp_id;
                $customeraddress->customer_lat = $value['customer_lat'];
                $customeraddress->customer_lng = $value['customer_lng'];
                $customeraddress->is_pickup_address = 0;
                $customeraddress->is_delivery_address = 1;
                $customeraddress->save();
            }
            else
            {
                $customeraddress = new CustomerAddress();
                $customeraddress->street = $value['street'];
                $customeraddress->businessname = $value['businessname'];
                $customeraddress->user_id = $this->emp_id;
                $customeraddress->customer_lat = $value['customer_lat'];
                $customeraddress->customer_lng = $value['customer_lng'];
                $customeraddress->is_pickup_address = 0;
                $customeraddress->is_delivery_address = 1;
                $customeraddress->save();
            }

        }

        return self::touser("Address added", true);
    }

    public function testkali()
    {

        $data = PushNotification::setService('apn')->setMessage(['aps' => ['alert' => ['title' => 'jskajsakskaks', 'body' => 'saldkskdfjkd',
        // "click_action" => "FCM_PLUGIN_ACTIVITY",  //Must be present for Android
        // "icon" => "sfaicon"  //White icon Android resource
        ], 'sound' => 'default', ],

        // ,'extraPayLoad' => [
        //     'custom' => 'My custom data',
        // ]
        ])
            ->setDevicesToken(['c7cXwgWJfG8:APA91bGfb9NWzyHFX5s5b8hFTAimkKWGHp5gLpXsZLz3tCugIjXMFrYEg3dYn5g8X7wbVaEcV1ZaKNmbzECFXWqZZNVkRx6KAiOhcsnbN5k75BzBCLc3HYkrtWLbnUJjoOp2Vx7Z93q6'])
            ->send()
            ->getFeedback();

        print_r($data);
        return 'hi';

    }

    public function gettime_zone(Request $request)
    {
        $data = $request->input('data');
        $key = $data['key'];
        if (!empty($key))
        {
            $count = timezonemang::where('name', 'like', '%' . $key . '%')->orWhere('country_name', 'like', '%' . $key . '%')->count();
            if ($count > 0)
            {
                $data = timezonemang::where('name', 'like', '%' . $key . '%')->orWhere('country_name', 'like', '%' . $key . '%')->get();
                return self::touser($data, true);
            }
            else
            {
                return self::touser("Please enter value", false);
            }
        }
        else
        {
            return self::touser("Please enter value", false);
        }
    }

    public static function getEmpBelongsCustomers($emp_id)
    {

        $users = [];

        $data = Customer::where('emp_id', $emp_id)->get(['id'])
            ->toArray();

        foreach ($data as $key => $value)
        {

            $users[] = $data[$key]['id'];
        }

        return $users;
    }

    public static function getCustname($cust_id){
        $data=User::where('user_id','=',$cust_id)->get();
        return ucwords($data[0]['first_name']);
    }

    public static function getEmpBelongsUser($emp_id)
    {

        $users = [];

        /*$data = User::where('belongs_manager', $emp_id)->where('is_active', 1)
            ->whereIn('role_id', [1, 2])
            ->whereNotIn('is_delete', [true, "true"])
            ->where('user_token', '!=', null)
            ->orwhere('user_id', $emp_id)->get(['user_id'])
            ->toArray();
            */
        // $data = DB::table('user')
        //             ->select('user.user_id')
        //             ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
        //             ->where('emp_mapping.is_active','=',1)
        //             ->where('emp_mapping.emp_id','=',$emp_id)
        //             //->whereNotIn('user.is_delete',[true,"true"])
        //             ->where('emp_mapping.is_delete','!=',true)
        //             ->where('user.user_token','!=',null)
        //             ->get()->toArray();
        if(User::where('user_id', $emp_id)->pluck('role_id')->first() == 4){
            $data = DB::table('user')
                ->select('user.user_id')
                ->join('emp_mapping','emp_mapping.emp_id','=','user.user_id')
                ->where('emp_mapping.is_active','=',1)
                ->whereIn('user.role_id', [1, 2])
                ->where('emp_mapping.manager_id','=',$emp_id)
                //->whereNotIn('user.is_delete',[true,"true"])
                ->where('emp_mapping.is_delete','!=',true)
                ->where('user.user_token','!=',null)
                ->get()->toArray();
        }
        else{
            $data = DB::table('user')
                    ->select('user.user_id')
                    ->join('emp_mapping','emp_mapping.emp_id','=','user.user_id')
                    ->where('emp_mapping.is_active','=',1)
                    ->whereIn('user.role_id', [1, 2])
                    ->where('emp_mapping.admin_id','=',$emp_id)
                    //->whereNotIn('user.is_delete',[true,"true"])
                    ->where('emp_mapping.is_delete','!=',true)
                    ->where('user.user_token','!=',null)
                    ->get()->toArray();
        }

        foreach ($data as $key => $value)
        {

            //$users[] = $data[$key]['user_id'];
            $users[] = $data[$key]->user_id;
        }

        return $users;
    }

    public static function getEmpBelongsUsers($emp_id)
    {

        $users = [];

        /*$data = User::where('belongs_manager', $emp_id)->whereIn('role_id', [1, 2])
            ->whereNotIn('is_delete', [true, "true"])
            ->where('user_token', '!=', null)
            ->orwhere('user_id', $emp_id)->get()
            ->toArray();*/

        $data = DB::table('user')
                ->select('*')
                ->join('emp_mapping','emp_mapping.emp_id','=','user.user_id')
                ->whereIn('user.role_id', [1, 2])
                ->where('emp_mapping.admin_id','=',$emp_id)
                //->whereNotIn('user.is_delete',[true,"true"])
                ->where('emp_mapping.is_delete','!=',true)
                ->where('user.user_token','!=',null)
                ->get()->toArray();
        
        foreach ($data as $key => $value)
        {

            $users[] = $data[$key];
        }

        return $users;
    }

    public static function getOfflineEmpBelongsUser($emp_id)
    {
        $users = [];
        /*$data = User::with('role')->where('is_active', 1)
            ->whereIn('role_id', [1, 2])
            ->whereNotIn('is_delete', [true, "true"])
            ->where('belongs_manager', $emp_id)->where('user_token', '!=', null)
            ->orWhere('user_id', $emp_id)->get()
            ->toArray();*/

        $data = DB::table('user')
                ->select('*')
                ->join('emp_mapping','emp_mapping.emp_id','=','user.user_id')
                ->whereIn('user.role_id', [1, 2])
                ->where('emp_mapping.admin_id','=',$emp_id)
                //->whereNotIn('user.is_delete',[true,"true"])
                ->where('emp_mapping.is_active','=',true)
                ->where('emp_mapping.is_delete','!=',true)
                ->where('user.user_token','!=',null)
                ->get()->toArray();

        foreach ($data as $key => $value)
        {
            $id = $value->user_id;
            $count = snapdata::where('user_id', '=', $id)->count();
            if ($count > 0)
            {
                $users[] = (object)$value;
            }
        }
        return $users;
    }

    public static function getSnap($user_id)
    {
        $data = snapdata::where('user_id', $user_id)->orderBy('id', 'desc')
            ->first();

        return $data;
    }

    public static function get_lat_long($userid)
    {
        $street = User::where('user_id', $userid)->get(['street'])
            ->toArray();
        $address = $street[0]['street'];

        $array = array();

        if ($address == null)
        {
            $array = array(
                'lat' => 'london',
                'lng' => 'london'
            );
            return $array;
        }

        $geo = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($address) . '&sensor=false&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk');

        // We convert the JSON to an array
        $geo = json_decode($geo, true);
        // If everything is cool
        if ($geo['status'] = 'OK')
        {
            $latitude = $geo['results'][0]['geometry']['location']['lat'];
            $longitude = $geo['results'][0]['geometry']['location']['lng'];
            $array = array(
                'lat' => $latitude,
                'lng' => $longitude
            );
        }
        else
        {
            $array = array(
                'lat' => 'london',
                'lng' => 'london'
            );
        }

        return $array;
    }

    public static function getNotYetLoggeduser($emp_id)
    {
        $users = [];
        $data = User::with('role')->where('is_active', 1)
            ->whereIn('role_id', [1, 2])
            ->whereNotIn('is_delete', [true, "true"])
            ->where('belongs_manager', $emp_id)->where('user_token', '!=', null)
            ->orWhere('user_id', $emp_id)->get()
            ->toArray();

        foreach ($data as $key => $value)
        {
            $id = $value;
            $count = snapdata::where('user_id', '=', $id)->count();
            if ($count > 0)
            {
            }
            else
            {
                $users[] = $value;
            }
        }
        return $users;
    }

    public static function setHeaderContentType($file)
    {
        //Number to Content Type
        $ntct = array(
            "1" => "image/gif",
            "2" => "image/jpeg", #Thanks to "Swiss Mister" for noting that 'jpg' mime-type is jpeg.
            "3" => "image/png",
            "6" => "image/bmp",
            "17" => "image/ico"
        );

        header('Content-type: ' . $ntct[exif_imagetype($file) ]);
    }

    public static function TestSendSms()
    {
        // dd(self::SendSms([919524609638],'Info for testing'));
        
    }

    public static function send_Sms($number, $msg)
    {
        $sms_username = "demopack";
        $sms_passwod = "pack@1234";

        $message = urlencode($msg);
        $type = "1";
        $route = "TA";

        $sms_sender_id = "SMSPCK";

        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL => "http://websms.bulksmspack.com/index.php/smsapi/httpapi/?uname=demopack&password=demo@1234&sender=SMSPCK&receiver=" . $number . "&route=TA&msgtype=1&sms=" . $message . "",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => array(
                'field1' => 'some date',
                'field2' => 'some other data',
            )
        );

        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        curl_close($ch);

    }

    public static function SendSms($phone, $body)
    {

        if (!is_array($phone))
        {
            $phone = array(
                $phone
            );
        }

        if (count($phone) < 1)
        {
            return false;
        }

        if (empty($body))
        {
            return false;
        }

        try
        {

            // Authorisation details.
            $username = "pratap.murugan@way2smile.com";
            $hash = "8fdcff84d495f05367649d3334b5adfb5d57ef101c2c48068fb59cc074ec5118";
            // Config variables. Consult http://api.textlocal.in/docs for more info.
            $test = "0";
            // Data for text message. This is the text message data.
            $sender = "TXTLCL"; // This is who the message appears to be from.
            $numbers = implode(',', $phone); // A single number or a comma-seperated list of numbers
            $message = $body;
            // 612 chars or less
            // A single number or a comma-seperated list of numbers
            $message = urlencode($message);
            $data = "username=" . $username . "&hash=" . $hash . "&message=" . $message . "&sender=" . $sender . "&numbers=" . $numbers . "&test=" . $test;
            $ch = curl_init('http://api.textlocal.in/send/?');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = json_decode(curl_exec($ch));
            curl_close($ch);

            //print_r($result);
            if ($result->status === 'success')
            {
                return true;
            }
            else
            {
                return false;
            }

        }
        catch(\Exception $e)
        {
            return false;
        }

    }

    public static function GoogleShortner($longUrl)
    {

        try
        {
            $ch = curl_init('https://www.googleapis.com/urlshortener/v1/url?key=AIzaSyCg3dCG78pwNxafQnLUkt9cCYN22ETf5is');
            # Setup request to send json via POST.
            $payload = json_encode(array(
                "longUrl" => $longUrl
            ));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json'
            ));
            # Return response instead of printing.
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            # Send request.
            $result = json_decode(curl_exec($ch));
            curl_close($ch);
            if ($result->id)
            {
                return $result->id;
            }
            else
            {
                return $longUrl;
            }

        }
        catch(\Exception $e)
        {
            return $longUrl;
        }

    }

    public static function change_db($domain)
    {
        if ($domain == self::app_domain())
        {

            return;
        }
        self::set_database_config($domain);
    }

    public static function getLogo()
    {

        return 'http://qa.way2smile.com/mtzwp/wp-content/uploads/2018/02/logo-web.png';

        // return 'https://delivery.manageteamz.com/api/uploads/delivery_db/d1KLxpKgbDEHivPP1tNFFyoNqigh10IsCtmk9TQV.png';
        // $is_local =  env('LOAD_LOGO', 1);
        // if($is_local!=1)
        // {
        //     return;
        // }
        

        DB::setDefaultConnection('mysql');

        $name = Config::get('app.logo');

        $data = db_info::with('company')->where('sub_domain_url', self::get_domin())
            ->get()
            ->toArray();

        if (count($data) == 1)
        {

            if ($data[0]['company']['logo'])
            {

                $info = json_decode($data[0]['company']['logo']);

                if (is_array($info))
                {
                    $name = $info[0];
                }
            }
        }

        function get_http_response_code($url)
        {
            $headers = get_headers($url);
            return substr($headers[0], 9, 3);
        }

        if (get_http_response_code($name) != "200")
        {

            $name = 'https://delivery.manageteamz.com/api/uploads/delivery_db/d1KLxpKgbDEHivPP1tNFFyoNqigh10IsCtmk9TQV.png';

        }

        $file = file_get_contents($name);
        self::setHeaderContentType($name);
        echo $file;

    }

    public static function getTaskByModel()
    {

        //self::createDb('kali');
        print_r(self::modelmap[self::tasktypes[1]]);
    }

    public static function throwerror()
    {
        return self::touser('Data or System Error');
    }

    public static function create_jserror(Request $request)
    {
        if ($request->input('value') ['info'] !== null)
        {
            DB::table('js_error')
                ->insert(['error' => $request->input('value') ['info'], 'created_at' => \Carbon\Carbon::now() , 'user_agent' => self::user_agent() ]);
        }
    }

    public static function get_jserror(Request $request)
    {
        $data = DB::table('js_error')->distinct('error')
            ->get();

        echo 'Total Erros : ' . count($data) . '';

        foreach ($data as $key => $value)
        {
            echo '<br/><br/>Date : ' . $value->created_at;

            echo '<br/><br/>Browser Agent : ' . $value->user_agent;

            echo '<br/><pre>';

            echo 'Error Stack : ' . $value->error;
            echo '</pre>';
        }
    }

    public static function db_connection()
    {
        if (DB::connection()
            ->getDatabaseName())
        {
            return DB::connection()
                ->getDatabaseName();
        }
        else
        {
            return 'root_db';
        }
    }

    public static function UpdateTask($type, $date, $cust_id, $emp_id, $complete = true)
    {
        if ($complete)
        {
            $info = array(
                'status' => 'complete'
            );
        }
        else
        {
            $info = array(
                'status' => 'waiting'
            );
        }

        emp_cust::where('date', '=', $date)->where('emp_id', '=', $emp_id)->where('type', '=', $type)->where('cust_id', '=', $cust_id)->update($info);

        return;
    }

    public static function super_admin()
    {
        return 'super_admin';
    }

    public static function guest()
    {
        return 'guest';
    }

    public function updatepickupstatus(Request $request)
    {
        $userid = $this->emp_id;
        $data = $request->input('data');
        $user = new User();
        $user = User::where('user_id', '=', $this->emp_id)
            ->first();
        $user->multiple_pickupaddress = $data;
        $user->save();
        return Base::touser('Pickup address updates successfully', true);
    }

    public function updatedeliverystatus(Request $request)
    {
        $userid = $this->emp_id;
        $data = $request->input('data');
        $user = new User();
        $user = User::where('user_id', '=', $this->emp_id)
            ->first();
        $user->multiple_deliveryaddress = $data;
        $user->save();
        return Base::touser('Delivery address updates successfully', true);
    }

    public static function backendadmin()
    {
        return 'admin';
    }

    public static function manager($check = 0)
    {
        if (self::mobile_header() == 1 && $check == 0)
        {
            return "emp";
        }

        return 'manager';
    }

    public static function tomysqldate($date)
    {

        $timestamp = strtotime($date);
        $date = date('Y-m-d', $timestamp);
        return $date;
    }

    public static function client_time($id)
    {
        $user = User::where('user_id', $id)->get();
        // dd($user[0],$id);
        $zone = timezonemang::where('desc', $user[0]->timezone)
            ->get();
        // dd($zone);

        $zonetime = $zone[0]->desc;
        //$date = new DateTime("now", new DateTimeZone($zonetime));
        $from = new DateTimeZone('GMT');
        $to   = new DateTimeZone($zonetime);
        $currDate = new DateTime('now', $from);
        $currDate->setTimezone($to);
        $date = $currDate->format('Y-m-d H:i:s');
        #return $date->format('Y-m-d H:i:s');
        return $date;
    }

    public function Getzonetime(Request $request)
    {
        $dt = self::client_time($request->data);
        return self::touser($dt, true);
    }

    public static function ConvertTimezone($value, $get = false)
    {
        if ($get)
        {
            if ($value == null)
            {
                return $value;
            }
            else
            {
                $value = Carbon::createFromTimestamp(strtotime($value))->timezone(self::client_timezone());
                return $value->toDateTimeString();
            }
        }
        if ($value instanceof Carbon)
        {

            return $value;

        }

        return Carbon::createFromTimestamp(strtotime($value))->timezone(self::client_timezone());
    }

    public static function timezone_check($timezone)
    {
        return in_array($timezone, timezone_identifiers_list());
    }

    public static function toHumanRead($minutes)
    {
        $d = floor($minutes / 1440);
        $h = floor(($minutes - $d * 1440) / 60);
        $m = $minutes - ($d * 1440) - ($h * 60);
        return "{$d} days {$h} hours  {$m} mins";
    }

    public static function tomysqldatetime($date)
    {

        if (self::mobile_header() == 1)
        {
            $dt = new DateTime($date, new DateTimeZone(self::client_timezone()));
            $tz = new DateTimeZone(\Config::get('app.timezone'));
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d H:i:s');

        }
        else
        {
            $dt = new DateTime($date, new DateTimeZone(self::client_timezone()));
            $tz = new DateTimeZone(\Config::get('app.timezone'));
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d H:i:s');

        }

    }

    public static function current_client_datetime()
    {

        if (array_key_exists('HTTP_X_CLIENT_DATE', $_SERVER))
        {
            if (null == $_SERVER['HTTP_X_CLIENT_DATE'] && empty($_SERVER['HTTP_X_CLIENT_DATE']))
            {
                return date('Y-m-d H:i:s');
            }
        }
        else
        {
            return date('Y-m-d H:i:s');
        }

        if ($_SERVER['HTTP_X_CLIENT_DATE'] == null)
        {
            return date('Y-m-d H:i:s');
        }
        try
        {
            $date = $_SERVER['HTTP_X_CLIENT_DATE'];
            $dt = new DateTime($date);
            return $dt->format('Y-m-d H:i:s');
        }
        catch(\Exception $e)
        {
            return date('Y-m-d H:i:s');
        }

    }

    public static function client_timezone()
    {
        return "UTC";
    }

    public static function utcToUserTimeZone($datetime)
    {
        $dt = new \DateTime($_SERVER['HTTP_X_CLIENT_DATE'], new \DateTimeZone(self::client_timezone()));
        $tz = new \DateTimeZone(\Config::get('app.timezone'));
        $dt->setTimezone($tz);
        return $dt->format('Y-m-d H:i');
    }

    public static function create_sub_db($company_name, $comp_id)
    {
        $web = self::getNewSubDomain($company_name) . '.' . str_replace('www.', '', self::app_domain());
        $sub_db = new db_info();
        $sub_db->sub_domain_url = $web;
        $sub_db->sub_db_host = Config::get('database.connections.mysql.host');
        $sub_db->sub_db_port = Config::get('database.connections.mysql.port');
        $sub_db->sub_db_user = Config::get('database.connections.mysql.username');
        $sub_db->sub_db_name = self::createDb($company_name);
        $sub_db->sub_db_pwd = encrypt(Config::get('database.connections.mysql.password'));
        $sub_db->company_id = $comp_id;
        $sub_db->save();
        $company = new CompanyInfo();
        $company_data = $company->with('db_info')
            ->find($comp_id)->toArray();
        self::createSubDb($sub_db->sub_db_name, $company_data);
        return;
    }

    public static function createDb($dbname)
    {
        $dbname = htmlentities($dbname, ENT_QUOTES, 'UTF-8', false);
        $dbname = strtolower((preg_replace("/[^a-zA-Z0-9]+/", "", $dbname) . '_' . str_random(5)));

        return $dbname;
    }

    public static function createSubDb($dbname, $company_data)
    {

        $dbsql = str_replace('core_db', $dbname, file_get_contents(env('SUB_DB_FILE')));

        DB::unprepared($dbsql);

        self::set_database_config(self::app_domain());

        //self::set_database_config($company_data['db_info']['sub_domain_url']);
        

        // self::set_database_config(self::app_domain());
        $user = new User();
        $user->role_id = 3;
        $user->first_name = 'Admin';
        $user->last_name = $company_data['company_name'];
        $user->user_pwd = encrypt('root');
        $user->is_active = 1;
        $user->phone = $company_data['company_phone'];
        $user->email = $company_data['company_email'];
        $user->save();

        return $dbname;
    }

    public static function up_db(Request $request)
    {
        $dbsql = file_get_contents(env('DB_FILE'));

        DB::unprepared($dbsql);

        self::set_database_config();

        return 'ok';
    }

    public static function getNewSubDomain($name)
    {
        $name = self::convert_to_url($name);

        $db = db_info::where('sub_domain_url', '=', $name)->first();
        if ($db === null)
        {
            return $name;
        }
        else
        {
            return $name . '1';
        }
    }

    public static function code(Request $request)
    {
        $users = DB::table('master')->paginate(1);

        return self::touser($users, true);
    }

    public static function set_database_config($url = 1)
    {
        return;
        // DB::setDefaultConnection('mysql');
        // if ($url == self::app_domain()) {
        //     return;
        // }
        // $data = db_info::where('sub_domain_url', $url)->first();
        // if (count($data) == 1) {
        //     Config::set('database.connections.subdb', array(
        //         'driver' => 'mysql',
        //         'host' => $data->sub_db_host,
        //         'port' => $data->sub_db_port,
        //         'database' => $data->sub_db_name,
        //         'username' => $data->sub_db_user,
        //         'password' => decrypt($data->sub_db_pwd),
        //         'charset' => 'utf8',
        //         'collation' => 'utf8_unicode_ci',
        //         'prefix' => '',
        //         ));
        

        //     DB::setDefaultConnection('subdb');
        // } else {
        //     DB::setDefaultConnection('mysql');
        // }
        
    }

    public static function super_admin_db()
    {
        // if (self::role() == self::super_admin()) {
        self::sub_root_domain();
        // }
        
    }

    public static function db_connection_reset()
    {
        self::set_database_config();
    }

    public static function timeAgo($time_ago){
        $time_ago = strtotime($time_ago);
        $cur_time   = time();
        $time_elapsed   = $cur_time - $time_ago;
        $seconds    = $time_elapsed ;
        $minutes    = round($time_elapsed / 60 );
        $hours      = round($time_elapsed / 3600);
        $days       = round($time_elapsed / 86400 );
        $weeks      = round($time_elapsed / 604800);
        $months     = round($time_elapsed / 2600640 );
        $years      = round($time_elapsed / 31207680 );
        // Seconds
        if($seconds <= 60){
            return "just now";
        }
        //Minutes
        else if($minutes <=60){
            if($minutes==1){
                return "one minute ago";
            }
            else{
                return "$minutes minutes ago";
            }
        }
        //Hours
        else if($hours <=24){
            if($hours==1){
                return "an hour ago";
            }else{
                return "$hours hrs ago";
            }
        }
        //Days
        else if($days <= 7){
            if($days==1){
                return "yesterday";
            }else{
                return "$days days ago";
            }
        }
        //Weeks
        else if($weeks <= 4.3){
            if($weeks==1){
                return "a week ago";
            }else{
                return "$weeks weeks ago";
            }
        }
        //Months
        else if($months <=12){
            if($months==1){
                return "a month ago";
            }else{
                return "$months months ago";
            }
        }
        //Years
        else{
            if($years==1){
                return "one year ago";
            }else{
                return "$years years ago";
            }
        }
    }

    public static function time_elapsed_string($datetime, $full = false, $start = false)
    {
        if ($start == false)
        {
            $now = new DateTime;
        }
        else
        {
            $now = new DateTime($start);
        }

        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'min',
            // 's' => 'sec',
            
        );
        foreach ($string as $k => & $v)
        {
            if ($diff->$k)
            {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            }
            else
            {
                unset($string[$k]);
            }
        }

        if (!$full)
        {
            $string = array_slice($string, 0, 1);
        }

        if ($start == false)
        {
            return $string ? implode(', ', $string) . ' ago' : 'just now';
        }
        else
        {
            return $string ? implode(', ', $string) . '' : '';
        }
    }

    public static function is_app_domain()
    {
        $val = false;

        if (self::get_domin() == self::app_domain())
        {
            $val = true;
        }

        if (self::get_sub_domain() === 'www')
        {
            $val = true;
        }

        return $val;
    }

    public static function get_sub_domain()
    {

        $domains = explode('.', parse_url(\URL::current()) ['host']);

        if ($domains[0] == 'manageteamz')
        {
            return 'www';
        }
        elseif ($domains[0] == 'www')
        {
            return 'www';
        }
        else
        {

            return $domains[0];
        }

        // $url = parse_url(Config::get('app.url'))['host'];
        // return $url;
        
    }

    public static function app_domain()
    {
        $url = parse_url(Config::get('app.url')) ['host'];

        return $url;
    }

    public static function app_unauthorized()
    {
//        abort(401, 'Un Authorized Access');
//        die();

        $code = 401;
        $data = json_encode(array(
            'data' => 'Unauthorized Access',
            'status' => 'error',
            'code' => $code
        ));
        return response($data, $code)->withHeaders(['Content-Type' => 'application/json']);
    }

    public static function app_endvalidity()
    {
        $code = 403;
        $data = json_encode(array(
            'data' => 'Your trial period has expired. You are not able to add drivers / tasks. Please contact us 
                to upgrade your account.',
            'status' => 'error',
            'code' => $code
        ));
        return response($data, $code)->withHeaders(['Content-Type' => 'application/json']);
    }

    public static function get_domin()
    {
        $url = parse_url(\URL::current()) ['host'];
        return $url;
    }

    public static function mobile_header()
    {
        // dd($_SERVER);
        if (array_key_exists('HTTP_MOBILE', $_SERVER))
        {
            if (null == $_SERVER['HTTP_MOBILE'] && empty($_SERVER['HTTP_MOBILE']))
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }

        return 1;
    }

    public static function timezone()
    {
        if (array_key_exists('HTTP_TIMEZONE', $_SERVER))
        {
            if (null == $_SERVER['HTTP_TIMEZONE'] && empty($_SERVER['HTTP_TIMEZONE']))
            {
                return 0;
            }
        }
        else
        {
            return 0;
        }
        return 1;
    }

    public static function client_data()
    {
        if (array_key_exists('HTTP_X_CLIENT_DATA', $_SERVER))
        {
            // dd('sss');
            if (null == $_SERVER['HTTP_X_CLIENT_DATA'] && empty($_SERVER['HTTP_X_CLIENT_DATA']))
            {
                self::app_unauthorized();
            }
        }
        else
        {
            self::app_unauthorized();
        }
        // dd($_SERVER);

        return $_SERVER['HTTP_X_CLIENT_DATA'];
    }

    public static function check_client_data()
    {
        $auth_user = clientinfo::where('client_data', '=', self::client_data())->first();

        if (count($auth_user) == 1)
        {
            return true;
        }
        return false;
    }

    public static function emp_id($api = false)
    {
        if (self::is_token())
        {
            if (self::get_token() == false)
            {
                self::app_unauthorized();
            }

            self::set_database_config();

            $auth = self::auth_token(self::get_token());

            if ($auth[0] == 'true')
            {
                $model = $auth[1]->toArray();

                if ($model['auth_model'] == self::master_model())
                {
                    self::sub_root_domain();
                    if ($api)
                    {
                        return self::touser(self::super_admin() , true);
                    }
                    else
                    {
                        return self::super_admin();
                    }
                }
                else
                {
                    $model = $model['auth_model']::findorfail(self::decode_token(self::get_token()));

                    if ($api)
                    {
                        return self::touser($model->user_id, true);
                    }
                    else
                    {
                        return $model->user_id;
                    }
                }
            }
            else
            {
                self::app_unauthorized();
            }
        }
        else
        {
            self::app_unauthorized();
        }
    }

    public static function auth_token($token)
    {
        if ($token == false)
        {
            self::app_unauthorized();
        }
        // dd($token);

        $auth_user = ApiAuth::where('auth_key', '=', $token)->orWhere('api_key', '=', $token)->first();
        // dd($auth_user->auth_key);
        // dd(count($auth_user) == 1 && ($auth_user->auth_key == $token));

        if (count($auth_user) == 1 && ($auth_user->api_key == $token || self::check_client_data()))
        {
            if ($auth_user->is_active)
            {
                return ['true', $auth_user];
            }
            else
            {
                return ['false'];
            }
        }
        else
        {
            // dd('er');

            return ['false'];
        }
    }

    public static function decode_token($data)
    {
        // dd($data);
        $salt = self::salt;
        try
        {
            $decoded = JWT::decode($data, $salt, array(
                'HS256'
            ));
            $decoded = (array)$decoded;
            // dd($decoded);
            if(isset($decoded['email'])){

                $user = User::where('email', '=', $decoded['email'])->get()
                ->toArray();
            }
            else{
                $user = User::where('phone', '=', $decoded['phone'])->get()
                ->toArray();
            }

           
        }
        catch(\Exception $ex)
        {
            $user = User::where('api_key', '=', $data)->get()
                ->toArray();
            if(!$user)
            {
                $auth = ApiAuth::where('auth_key',$data)->orWhere('api_key',$data)->get()->first();
                if(count($auth)==1)
                {
                    $user = User::where('user_id',$auth->auth_user_id)->get()->toArray();
                }
            }
        }

        //print_r($user);exit;
        // $decoded['apitoken'] = strstr($decoded['apitoken'], '_', true);
        // $token_user_id = Hashids::decode($decoded['apitoken'])[0];
        $token_user_id = isset($user[0]['user_id']) ? $user[0]['user_id'] : '0';

        return $token_user_id;
    }

    public function token_validate(Request $request)
    {
        $data = $request->data;
        $salt = self::salt;
        $decoded = JWT::decode($data, $salt, array(
            'HS256'
        ));

        $decoded = (array)$decoded;
        return date('d-m-Y h:i A', strtotime($decoded['exp']));
    }

    public static function role()
    {
        if (self::is_token())
        {
            if (self::get_token() == false)
            {
                return self::guest();
            }
            // dd(self::get_token());
            $auth = self::auth_token(self::get_token());
            // dd($auth);
            if ($auth[0] == 'true')
            {
                $model = $auth[1]->toArray();
                // dd($model);

                if ($model['auth_model'] == self::master_model())
                {                    
                    // dd('l');
                    self::sub_root_domain();
                    return self::super_admin();
                }
                else
                {
                    if(self::decode_token(self::get_token()) == 0){
                        return self::guest();
                    }
                    $model = $model['auth_model']::findorfail(self::decode_token(self::get_token()));

                    if (!$model->is_active)
                    {
                        return self::guest();
                    }

                    return $model
                        ->role->name;
                }
            }
            else
            {
                return self::guest();
            }
        }
        else
        {
            return self::guest();
        }
    }

    public static function getRole()
    {
        if (self::get_token() == false)
        {
            return self::guest();
        }

        $auth_user = ApiAuth::where('auth_key', '=', self::get_token())->orWhere('api_key', '=', self::get_token())
            ->first();

        if (count($auth_user) == 1)
        {

            $model = $auth_user->toArray();
            if ($model['auth_model'] == self::masters_model())
            {
                return [self::super_admin() , null];
            }
            else
            {
                if(self::decode_token(self::get_token()) == 0){
                    // dd($model['auth_model']);

                    return self::guest();
                }

                $model = $model['auth_model']::findorfail(self::decode_token(self::get_token()));

                return [$model
                    ->role->name, $model->user_id];
            }
        }
        else
        {
            return self::guest();
        }
    }

    public static function sub_root_domain()
    {
        if (array_key_exists('HTTP_X_SUB_ROOT_DOMAIN', $_SERVER))
        {
            if (null == $_SERVER['HTTP_X_SUB_ROOT_DOMAIN'] && empty($_SERVER['HTTP_X_SUB_ROOT_DOMAIN']))
            {
            }
            else
            {
                if (self::app_domain() == $_SERVER['HTTP_X_SUB_ROOT_DOMAIN'])
                {
                }
                else
                {
                    self::set_database_config($_SERVER['HTTP_X_SUB_ROOT_DOMAIN']);
                }
            }
        }
        return true;
    }

    public static function to_month($input)
    {
        $monthNum = $input;
        $dateObj = DateTime::createFromFormat('!m', $monthNum);

        return $dateObj->format('F'); // March
        
    }

    public static function get_token()
    {
        $val = false;

        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER))
        {
            if (null == $_SERVER['HTTP_AUTHORIZATION'] && empty($_SERVER['HTTP_AUTHORIZATION']))
            {
                return $val;
            }
            else
            {
                return $_SERVER['HTTP_AUTHORIZATION'];
            }
        }
        elseif (isset($_REQUEST['token']) && !empty($_REQUEST['token']))
        {
            // dd($_REQUEST['token']);

            return $_REQUEST['token'];
        }
        else
        {
            return $val;
        }
    }

    public static function is_token()
    {
        if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER))
        {
            if (null == $_SERVER['HTTP_AUTHORIZATION'] && empty($_SERVER['HTTP_AUTHORIZATION']))
            {
                return false;
            }
            else
            {
                return true;
            }
        }
        elseif (isset($_REQUEST['token']) && !empty($_REQUEST['token']))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function isAdmin($role)
    {
        if (self::super_admin() == $role)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function isManager($role)
    {
        if (self::manager() == $role)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function isBackendAdmin($role)
    {
        if (self::backendadmin() == $role)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public static function master_model()
    {
        return User::class;
    }
    public static function masters_model()
    {
        return master::class;
    }

    public static function decode_token_model($data)
    {
        $salt = self::salt;
        $decoded = JWT::decode($data, $salt, array(
            'HS256'
        ));

        $decoded = (array)$decoded;

        $token_user_id = Hashids::decode($decoded['apitoken']) [0];
        return $token_user_id;
    }

    public function appcall($val)
    {
        $this->appcall = $val;
    }

    public function logout(Request $request)
    {
        $data = $request->input('data');

        clientinfo::where('client_data', $data['client_data'])->delete();

        return self::touser($request->input('data') , true);
    }

    public static function oauth_login($email, $password){
        
        $length = 5;
        $email = $email;
        $password = $password;

        $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);

        $userInfo = array(
            "Username" => $email,
            "password" => $password,
            "app_token" => env('APP_TOKEN'),
            "auth_data" => $randomletter,
            "is_app_user" => 1
        );

        /*weblogin*/
        $auth_url = "https://eazyfoodapp.com/api/Auth";
        $agent = $_SERVER['HTTP_USER_AGENT'];
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
                "content-type: application/json"
            ) ,
        ));
        $curlresponse = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err)
        {
            return "cURL Error #:" . $err;
        }
        else
        {       

            $json = json_decode($curlresponse, true);

            if ($json['status']['status_code'] == 200)
            {
                $salt = self::salt;
                $decoded = JWT::decode($json['token'], $salt, array(
                    'HS256'
                ));
                $decoded = (array)$decoded;
                $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                    ->first();
                $exits = (int)count($valid);
                if ($exits == 1)
                {
                    if ($valid->user_token == $decoded['usr_token'])
                    {
                        if ($valid->is_active == 1)
                        {
                            
                            $key = self::token($json['token'], User::class , '','');
                            $data = [
                                "message" => ['token' => $key, 'role' => $valid
                                    ->role->name, 'gps_active' => (int)$valid->zipcode, 'demo_links' => $valid->demo_links],
                                "status" => true
                            ];
                            return $data;
                        }
                        else
                        {
                            $data = [
                                "message" => 'Account not activated',
                                "status" => false
                            ];
                            return $data;
                        }
                    }
                    else
                    {
                        $data = [
                                "message" => 'User does not match in our record',
                                "status" => false
                            ];
                        return $data;
                    }
                }

                //Sass Method Code *******************
                
                elseif($exits==0)
                {
                    $update_token = User::where('email', '=', $email)->update(['user_token'=>$decoded['usr_token']]);
                    $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                    ->first();
                    $exits = (int)count($valid);
                    if ($exits == 1)
                    {
                        if ($valid->user_token == $decoded['usr_token'])
                        {
                            if ($valid->is_active == 1)
                            {
                                
                                $key = self::token($json['token'], User::class , '','');
                                $data = [
                                        "message" => ['token' => $key, 'role' => $valid
                                        ->role->name, 'gps_active' => (int)$valid->zipcode, 'demo_links' => $valid->demo_links],
                                        "status" => true
                                    ];
                                return $data;
                            }
                            else
                            {
                                $data = [
                                    "message" => 'Account not activated',
                                    "status" => false
                                ];
                                return $data;
                            }
                        }
                        else
                        {
                            $data = [
                                    "message" => 'User does not match in our record',
                                    "status" => false
                                ];
                            return $data;
                        }
                }
                else
                {
                    $data = [
                            "message" => 'No Account Found',
                            "status" => false
                        ];
                    return $data;
                }
                }

                // Sass Method End *************

                //return $decoded;
                
            }
            else
            {   
                $data = [
                        "message" => $json['status']['message'],
                        "status" => false
                    ];
                return $data;
            }
        }
    }

    public function generate_otp_web(Request $request){
        $data = $request->input("data");
         /* generate otp for phone number */
        $rules = [
            'phone' => 'required|unique:user',
            'email' => 'required|email|unique:user',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()){
            return Base::touser($validator->errors()
                ->all() [0]);
        }

        $email_count = User::where('email', '=', $data["email"])->get()->count();
        if($email_count > 0){
            return self::touser("This email is already exists with our system",false);
        }
       
        $count = User::where('phone', '=', $data["phone"])->get()->count();
        if($count > 0){
            return self::touser("This phone number is already exists with our system",false);
        }

        $customer_id = env("TELESIGN_CUSTOMER_ID");
        $api_key = env("TELESIGN_API_KEY");
        $phone_number = str_replace("+", "", $data['phone']);
        if((isset($data["gateway"]) ? $data["gateway"] : "sms") == "sms"){
            $verify_code =randomWithNDigits(5);
            $message = "Your code is $verify_code";
            $message_type = "OTP";
            $messaging = new MessagingClient($customer_id, $api_key);
            $response = $messaging->message($phone_number, $message, $message_type);
            
            $msg = ["message" => "otp send successfully","otp" => $verify_code];
            if($response->status_code == 200){
                return self::touser($msg,true);
            }elseif($response->status_code == 401){
                return self::touser("Requested number is unverified",false);
            }else{
                return self::touser("otp not send",false);
            }
        }elseif((isset($data["gateway"]) ? $data["gateway"] : "voice") == "voice"){
            $verify_code = randomWithNDigits(5);
            $message = sprintf('Hello, your code is %1$s. Once again, your code is %1$s. Goodbye.',
                join(", ", str_split($verify_code)));
            $message_type = "OTP";
            $voice = new VoiceClient($customer_id, $api_key);
            $response = $voice->call($phone_number, $message, $message_type);

            if($response->status_code == 200){
                return self::touser("call state onboard",true);
            }elseif($response->status_code == 201){
                return self::touser("Call in progress",true);
            }elseif($response->status_code == 401){
                return self::touser("Requested number is unverified",true);
            }else{
                return self::touser("can not make",false);
            }
        }else{
            $customer_id = env("TELESIGN_CUSTOMER_ID");
            $api_key = env("TELESIGN_API_KEY");
              
            /* generate otp for sms*/
            $phone_number = str_replace("+", "", $data['phone']);
            $verify_code =randomWithNDigits(5);
            $message = "Your code is $verify_code";
            $message_type = "OTP";
            $messaging = new MessagingClient($customer_id, $api_key);
            $response = $messaging->message($phone_number, $message, $message_type);
            
            $msg = ["message" => "otp send successfully","otp" => $verify_code];
            if($response->status_code == 200){
                return self::touser($msg,true);
            }elseif($response->status_code == 401){
                return self::touser("Requested number is unverified",false);
            }else{
                return self::touser("otp not send",false);
            }
        }
    }


    public function generate_otp_mobile(Request $request){
        $data = $request->input("data");
         /* generate otp for phone number */
        $rules = [
            'phone' => 'required|unique:user',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()){
            //$msg = ["message" => $validator->errors()->all()[0], "otp" => ""];
            return Base::touser($validator->errors()->all()[0]);
        }

        if(isset($data["email"])){
            $email_count = User::where('email', '=', $data["email"])->get()->count();
            if($email_count > 0){
                $msg = ["message" => "This email is already exists with our system","otp" => ""];
                return self::touser($msg,false);
            }
        }
       
        $count = User::where('phone', '=', $data["phone"])->get()->count();
        if($count > 0){
            $msg = ["message" => "This phone number is already exists with our system","otp" => ""];
            return self::touser($msg,false);
        }

        $customer_id = env("TELESIGN_CUSTOMER_ID");
        $api_key = env("TELESIGN_API_KEY");
        $phone_number = str_replace("+", "", $data['phone']);
        if($data["gateway"] == "sms"){
            $verify_code = randomWithNDigits(5);
            $message = "Your code is $verify_code";
            $message_type = "OTP";
            $messaging = new MessagingClient($customer_id, $api_key);
            $response = $messaging->message($phone_number, $message, $message_type);
            
            $msg = ["message" => "otp send successfully","otp" => $verify_code];
            if($response->status_code == 200){
                return self::touser($msg,true);
            }elseif($response->status_code == 401){
                $msg = ["message" => "Requested number is unverified","otp" => $verify_code];
                return self::touser($msg,false);
            }else{
                $msg = ["message" => "otp not send","otp" => $verify_code];
                return self::touser($msg,false);
            }
        }elseif($data["gateway"] == "voice"){
            $verify_code = randomWithNDigits(5);
            $message = sprintf('Hello, your code is %1$s. Once again, your code is %1$s. Goodbye.',
                join(", ", str_split($verify_code)));
            $message_type = "OTP";
            $voice = new VoiceClient($customer_id, $api_key);
            $response = $voice->call($phone_number, $message, $message_type);
            
            if($response->status_code == 200){
                $msg = ["message" => "call state onboard","otp" => $verify_code];
                return self::touser($msg,true);
            }elseif($response->status_code == 201){
                $msg = ["message" => "Call in progress","otp" => $verify_code];
                return self::touser($msg,true);
            }elseif($response->status_code == 401){
                $msg = ["message" => "Requested number is unverified","otp" => $verify_code];
                return self::touser($msg,true);
            }else{
                $msg = ["message" => "can not make","otp" => $verify_code];
                return self::touser($msg,false);
            }
        }else{
            $msg = ["message" => "Please provide a gateway","otp" => ""];
            return self::touser($msg,false);
        }
    }

    public function generate_otp(Request $request){
        
        $data = $request->input("data");
        $req_param = $request->input('api');
        
        if(isset($data['phone'])){
            /* generate otp for phone number */
            $rules = ['phone' => 'required' ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }

            /*check if user is driver or not from web request
            if($req_param == "web"){

                $count = User::where('phone', '=', $data["phone"])->get()->count();
                if($count > 0){
                    $query = User::where('phone', '=', $data["phone"])->get()->first();
                    if($query->role_id == 1){
                        return self::touser("You have a driver account with this mobile number.  Driver account does not have a web acccess. Would you like to signup as admin ?",false);
                    }
                }
            }*/

            if (self::mobile_header() != 1){
                /*is deleted or not check*/
                $count = User::where([['phone', '=', $data["phone"]], ['is_delete', '=', 'true']])->count();
                if($count>0){
                    return self::touser("Your account is deleted please contact admin",true);
                }

                /*is Active or not check*/
                $count = User::where([['phone', '=', $data["phone"]], ['is_active', '=', false]])->count();
                if($count>0){
                    return self::touser("Your account is de-activated please contact admin",true);
                }
            }

            $count = User::where('phone', '=', $data["phone"])->get()->count();

            if($count > 0){
                /*check is_delete for all admin*/
                $query = User::where('phone', '=', $data["phone"])->get()->first();
                $_valid_id = $query->user_id;
                if($query->role_id == 4) {
                    $_total_count = EmpMapping::where("manager_id", "=", $_valid_id)->count();
                    $is_delete_count = EmpMapping::where("manager_id", '=', $_valid_id)->where("is_delete",'=',1)->count();
                } else {
                    $_total_count = EmpMapping::where("emp_id", "=", $_valid_id)->count();
                    $is_delete_count = EmpMapping::where("emp_id", '=', $_valid_id)->where("is_delete",'=',1)->count();
                }
                if($_total_count == $is_delete_count) {
                    return self::touser("Your account is deleted please contact admin",false);
                }

                if($query->role_id == 4) {
                    /*check is_active for all admin*/
                    $is_active_count = EmpMapping::where("manager_id",'=', $_valid_id)->where("is_active",'=',0)->count();
                } else {
                    /*check is_active for all admin*/
                    $is_active_count = EmpMapping::where("emp_id",'=', $_valid_id)->where("is_active",'=',0)->count();
                }
                if($_total_count == $is_active_count){
                    return self::touser("Your account is de-activated please contact admin",false);
                }
            }
            

            $customer_id = env("TELESIGN_CUSTOMER_ID");
            $api_key = env("TELESIGN_API_KEY");
              
            $user = User::where('phone', '=', $data['phone'])->get()->first();

            if($user){ 
                /* generate otp for sms*/
                $phone_number = str_replace("+", "", $data['phone']);
                if($data["gateway"] == "sms"){
                    $verify_code =randomWithNDigits(5);
                    $message = "Your code is $verify_code";
                    $message_type = "OTP";
                    $messaging = new MessagingClient($customer_id, $api_key);
                    $response = $messaging->message($phone_number, $message, $message_type);
                    
                    $user_id = $user->user_id;
                    $user_data = User::find($user_id);
                    $user_data->otp = $verify_code;
                    $user_data->save();

                    if($response->status_code == 200){
                        return self::touser("otp send successfully",true);
                    }elseif($response->status_code == 401){
                        return self::touser("Requested number is unverified",false);
                    }else{
                        return self::touser("otp not send",false);
                    }
                }elseif($data["gateway"] == "voice"){
                    $verify_code = randomWithNDigits(5);
                    $message = sprintf('Hello, your code is %1$s. Once again, your code is %1$s. Goodbye.',
                        join(", ", str_split($verify_code)));
                    $message_type = "OTP";
                    $voice = new VoiceClient($customer_id, $api_key);
                    $response = $voice->call($phone_number, $message, $message_type);

                    $user_id = $user->user_id;
                    $user_data = User::find($user_id);
                    $user_data->otp = $verify_code;
                    $user_data->save();
                    if($response->status_code == 200){
                        return self::touser("call state onboard",true);
                    }elseif($response->status_code == 201){
                        return self::touser("Call in progress",true);
                    }elseif($response->status_code == 401){
                        return self::touser("Requested number is unverified",true);
                    }else{
                        return self::touser("can not make",false);
                    }
                }else{
                    return self::touser("Please provide a gateway",false);
                }

            }else{
                return self::touser("User does not exists",false);
            }
        }elseif(isset($data['email'])){
            
            /* generate otp for email */
            $rules = ['email' => 'required' ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }
            $otp = rand(1000, 9999);
            $user = User::where('email', '=', $data['email'])->get()->first();
            if($user){  
                $user_id = $user->user_id;
                $user_data = User::find($user_id);
                $user_data->otp = $otp;
                $user_data->save();

                \App\Http\Controllers\NotificationsController::send_otp_to_email($user,$otp);
                return Base::touser("New OTP has been sent to your e-mail address ", true);

            }else{
                return self::touser("User does not exists",false);
            }
        }else{
            return self::touser("Inputs not valid",false);
        }
    }

    public function promote_to_admin(Request $request){
        $req_param = $request->input('api');
        $data = $request->input("data");
        $user = "";
        if(isset($data["phone"])){
            $rules = ['phone' => 'required'];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }
            $user = User::where('phone', '=', $data["phone"])->get()->first();
        }elseif(isset($data["email"])){
            $rules = ['email' => 'required'];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }
            $user = User::where('email', '=', $data["email"])->get()->first();
        }

        if($user){
            $username = isset($data["email"])?$data["email"]:$data["phone"];
            $length = 5;
            $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);
            $userInfo = array(
                "Username" => $username,
                "password" => decrypt($user->user_pwd),
                "app_token" => env('APP_TOKEN'),
                "auth_data" => $randomletter,
                "is_app_user" => 1
            );

        $auth_url = "https://eazyfoodapp.com/api/Auth";
        $agent = $_SERVER['HTTP_USER_AGENT'];
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
                "content-type: application/json"
            ) ,
        ));
        $curlresponse = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        if ($err)
        {
            return self::touser("cURL error",false);
        }
        else
        {       

            $json = json_decode($curlresponse, true);
            if ($json['status']['status_code'] == 200){
                
                if($req_param == "web"){
                    $salt = self::salt;
                    $decoded = JWT::decode($json['token'], $salt, array(
                        'HS256'
                    ));
                    $decoded = (array)$decoded;
                    $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
                        ->first();
                    $valid->role_id = 2;
                    $valid->save();
                    $exits = (int)count($valid);
                    if ($exits == 1)
                    {
                        if ($valid->user_token == $decoded['usr_token'])
                        {
                            if ($valid->is_active == 1)
                            {
                                $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                if (null !== $request->input('api'))
                                {
                                    $getuser = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
                                        ->first();

                                    $userPackage = new UserPackage();
                                    $userPackage->user_id = $valid->user_id;
                                    $userPackage->package_id = "1";
                                    $date = date('Y-m-d H:i:s');
                                    $d = strtotime("+7 days");
                                    $enddate = date("Y-m-d H:i:s", strtotime($date . ' + 7 days'));
                                    $userPackage->beg_date = $date;
                                    $userPackage->end_date = $enddate;
                                    $userPackage->no_of_emp = 2;
                                    $userPackage->no_of_cust = 0;
                                    $userPackage->no_of_task = 0;
                                    $userPackage->save();
                                    $user = User::where('user_id', $valid->user_id)->first();
                                    $user->current_package_id = $userPackage->id;
                                    $user->update();
                                    $mapsettings = new MapSettings();
                                    $mapsettings->user_id = $user->user_id;
                                    $mapsettings->save();

                                    $logged_userId = $getuser['user_id'];
                                    $updateuser = User::find($logged_userId);
                                    /*organization mapping*/
                                    $EmpMapping = new EmpMapping();
                                    $EmpMapping->admin_id = $logged_userId;
                                    $EmpMapping->emp_id = $logged_userId;
                                    $EmpMapping->save();
                                    return self::touser(['token' => $key, 'role' => $valid
                                        ->role->name, 'gps_active' => (int)$valid->zipcode, 'demo_links' => $valid->demo_links,"refreshToken"=> $json["refreshToken"], "profile" => $updateuser], true);
                                }
                            }
                            else
                            {
                                return self::touser('Account not activated',false);
                            }
                        }
                        else
                        {
                            return self::touser('User does not match in our record',false);
                        }
                    }

                    //Sass Method Code *******************
                    
                    elseif($exits==0)
                    {
                        $update_token = User::where('email', '=', $data['email'])->update(['user_token'=>$decoded['usr_token']]);
                        $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                        ->first();
                        $exits = (int)count($valid);
                        if ($exits == 1)
                        {
                            if ($valid->user_token == $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                    $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                    $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                    if (null !== $request->input('api'))
                                    {
                                        return self::touser(['token' => $key, 'role' => $valid
                                            ->role->name, 'gps_active' => (int)$valid->zipcode, 'demo_links' => $valid->demo_links,"refreshToken"=> $json["refreshToken"]], true);
                                    }
                                }
                                else
                                {
                                    return self::touser('Account not activated',false);
                                }
                            }
                            else
                            {
                                return self::touser('User does not match in our record',false);
                            }
                    }
                    else
                    {
                        return self::touser('No Account Found', false);
                    }
                    }

                    // Sass Method End *************


                    elseif (self::is_app_domain())
                    {
                        if (count(master::where('email', $data['email'])->first()) == 1)
                        {
                            $valid = master::where('email', $data['email'])->first();

                            if ($valid->user_token === $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $key = self::token($valid->id, master::class); //logged
                                    return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                                }
                                else
                                {
                                    return self::touser('Account not activated', false);
                                }
                            }
                            else
                            {
                                return self::touser('Password not match', false);
                            }
                        }
                        else
                        {
                            return self::touser('No Account Found', false);
                        }
                    }
                    else
                    {
                        return self::touser('No Account Found', false);
                    }
                    //return $decoded;
                }
            }
            else
            {
                $msg = $json['status']['message'];
                return self::touser($msg, false);
            }
        }
        }else{
            return self::touser("Required params is missing", false);
        }
    }

    public function verify_mobile(Request $request){
        $data = $request->input("data");
        // dd($data);
        if(isset($data["phone"])){
            $rules = ['phone' => 'required'];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }

            if(isset($data["email"]) && $data["email"] != ""){
                $email_count = User::where('email', '=', $data["email"])->get()->count();
                if($email_count > 0){
                    return self::touser("This email is already exists with our system",false);
                }
            }

            // $user = User::where([['phone', '=', $data["phone"]], ['otp', '=', $data['otp']]])->get()->first();
            // if(count($user) <= 0){
            //     return Base::touser("Please enter a valid OTP",false);
            // }

            $password = encrypt(strtolower(str_random(5)));
            $pass = decrypt($password);
            $apiKey = hash_hmac('md5', $pass, 'MT_W2S');

            $userInfo[] = array(
                "first_name" => $data['first_name'],
                "last_name" => isset($data['last_name']) ? $data['last_name'] : '',
                "email_address" => isset($data['email']) ? $data['email'] : '',
                "phone" => isset($data['phone']) ? trim($data['phone']) : '',
                "user_name" => isset($data['email']) ? $data['email'] : $data['phone'],
                "password" => $pass,
                "base64_img" => null,
                "app_usr_id" => 1,
                "ref_token" => "null"
            );

            $oauth_token = AuthAdmin::find('1')->get()->toArray();
            $authtoken = $oauth_token[0]['auth_key'];

            $token = Base::checkTokenStatus($authtoken);

            // $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
            // $agent = $_SERVER['HTTP_USER_AGENT'];
            // $authorization = "Authorization: Bearer " . $token;
            // $clinet_data = "x_client_data:" . rand(0, 9999);
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
            //         "x_client_data: test",
            //         $authorization
            //     ),
            // ));
            // $curlresponse = curl_exec($curl);

            // $err = curl_error($curl);
            // curl_close($curl);
            // if ($err) {

            //     return self::touser("cURL error",false);
            // } else {
            //     $json = json_decode($curlresponse, true);
            //     if ($json['status']["status_code"] == 200) {
            //         $json_data = $json['data'][0];
            //         if ($json_data['is_success'] == true) {

                        $data['role_id'] = 1;
                        $user = new User();
                        $user->role_id = 1;
                        $user->user_token = '';
                        $user->first_name = $data['first_name'];
                        $user->last_name = $data['last_name'];

                        $user->user_pwd = $password;
                        $user->phone = isset($data['phone']) ? trim($data['phone']) : '';
                        $user->email = isset($data['email'])? $data['email'] : null;
                        $user->city = isset($data['city']) ? $data['city'] : null;
                        $user->street = isset($data['street']) ? $data['street'] : null;
                        $user->state = isset($data['state']) ? $data['state'] : null;
                        $user->zipcode = isset($data['zipcode']) ? $data['zipcode'] : 1;
                        $user->country = isset($data['country']) ? $data['country'] : null;
                        $user->whatsapp = isset($data["whatsapp"])?$data["whatsapp"]:'';
                        $user->profile_image = isset($data['profile_image']) ? json_encode($data['profile_image'], true) : '[]';
                        $user->phone_imei = isset($data['phone_imei']) ? $data['phone_imei'] : '';
                        $user->is_active = isset($data['is_active']) ? $data['is_active'] : 1;
                        $user->api_key = $apiKey;
                        $user->mailnote = true;
                        $user->is_onboarding_success = 0;
                        $user->save();
                        $user->belongs_manager = $user->user_id;
                        $user->save();

                        
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

                        
                        //Email Notification
                        //\App\Http\Controllers\NotificationsController::WelcomeEmp($user);

                        /*direct login*/
                        // try{
                             $valid = User::where('phone', '=', $user->phone)->orWhere('email','=',$user->email)->whereIn('role_id', [1, 2, 3, 4])
                            ->first();
                            // $length = 5;
                            // $username = isset($data["email"])?$data["email"]:$data["phone"];
                            // $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);
                            // $userInfo = array(
                            //     "Username" => $username,
                            //     "password" => decrypt($user->user_pwd),
                            //     "app_token" => env('APP_TOKEN'),
                            //     "auth_data" => $randomletter,
                            //     "is_app_user" => 1
                            // );

                            // $auth_url = "https://eazyfoodapp.com/api/Auth";
                            // $agent = $_SERVER['HTTP_USER_AGENT'];
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
                            //     CURLOPT_POSTFIELDS => json_encode($userInfo) ,
                            //     CURLOPT_HTTPHEADER => array(
                            //         "accept: */*",
                            //         "accept-language: en-US,en;q=0.8",
                            //         "content-type: application/json"
                            //     ) ,
                            // ));
                            // $curlresponse = curl_exec($curl);
                            // $err = curl_error($curl);

                            // curl_close($curl);

                            // if ($err){
                            //     return Base::touser([], true);
                            // }
                            // else{
                            //     $json_auth = json_decode($curlresponse, true);
                                // if ($json_auth['status']['status_code'] == 200){
                                    $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                    $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                    // $key = Base::token($json_auth['token'], User::class , $data['device_token'], $data['device_type']);
                                    $updateuser = User::find($valid->user_id);
                                    $manager_data = DB::table('user')
                                                        ->select('*')
                                                        ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
                                                        ->where('emp_mapping.emp_id','=',$this->emp_id)
                                                        ->take(1)
                                                        ->get()->toArray();
                                    if($manager_data[0]->map_api_key == null || $manager_data[0]->map_api_key == "") {
                                        $updateuser->map_api_key = env('MAPS_API_KEY');
                                    } else {
                                        $updateuser->map_api_key = $manager_data[0]->map_api_key;
                                    }
                                    return Base::touser(['token' => $token,'api_key' => $apiKey, 'role' => $valid->role->name, 'is_loggedIn' => false, 
                                        "message" => "logged in success", 'gps_active' => $valid->zipcode, 'activity' => $valid->activity, 
                                        'demo_links' => $valid->demo_links, "refreshToken"=>'', 
                                        "profile" => $updateuser], true);
                //                 }else{
                //                     return Base::touser([], true);
                //                 }
                //             }
                //         // }catch(\Exception $e){
                //         //     return Base::touser([], true);
                //         // }
                        
                //     } else {
                //         return Base::touser($json_data['response_msg'], false);
                //     }
                // } else {
                //     return Base::touser($json['status']['message'], false);
                // }
                /*status 200 condition close*/

            // }

        }
    }

    public function login(Request $request) {
        $req_param = $request->input('api');
        $data = $request->input("data");
        // $token = array(
        //     "token" => ['phone'=>$data['email']],
        //     );
    
        // $jwt = JWT::encode($token, self::salt);
        // return $jwt;
        if(isset($data["phone"])){
            $rules = ['phone' => 'required','otp'=>'required'];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }

            if (self::mobile_header() != 1){
                /*is deleted or not check*/
                $count = User::where([['phone', '=', $data["phone"]], ['is_delete', '=', 'true']])->count();
                if($count>0){
                    return self::touser("Your account is deleted please contact admin",true);
                }

                /*is Active or not check*/
                $count = User::where([['phone', '=', $data["phone"]], ['is_active', '=', false]])->count();
                if($count>0){
                    return self::touser("Your account is de-activated please contact admin",true);
                }
            }

            $user = User::where([['phone', '=', $data["phone"]], ['otp', '=', $data['otp']]])->get()->first();
            if(count($user) <= 0){
                return Base::touser("Please enter a valid OTP",false);
            }

            if($req_param == "web"){

                $count = User::where('phone', '=', $data["phone"])->get()->count();
                if($count > 0){
                    $query = User::where('phone', '=', $data["phone"])->get()->first();
                    if($query->role_id == 1){
                        return self::touser("You have a driver account with this mobile number.  Driver account does not have a web acccess. Would you like to signup as admin ?",false);
                    }
                }
            }
        } elseif(isset($data["email"])) {
            $rules = ['email' => 'required','password'=>'required' ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()){
                return Base::touser($validator->errors()
                    ->all() [0]);
            }

            if($req_param == "web"){

                $count = User::where('email', '=', $data["email"])->get()->count();
                if($count > 0){
                    $query = User::where('email', '=', $data["email"])->get()->first();
                    if($query->role_id == 1){
                        return self::touser("You have a driver account with this email id.  Driver account does not have a web acccess. Would you like to signup as admin ?",false);
                    }
                }
            }

            /*is account found or not check*/
            $count = User::where('email', '=', $data["email"])->count();
            if($count == 0){
                return self::touser("No account found", false);
            }

            if (self::mobile_header() != 1){
                /*is deleted or not check*/
                $count = User::where([['email', '=', $data["email"]], ['is_delete', '=', 'true']])->count();
                if($count>0){
                    return self::touser("Your account is deleted please contact admin",true);
                }

                /*is Active or not check*/
                $count = User::where([['email', '=', $data["email"]], ['is_active', '=', false]])->count();
                if($count>0){
                    return self::touser("Your account is de-activated please contact admin",true);
                }   
            }

            $user = User::where('email', '=', $data["email"])->get()->first();
            $password = decrypt($user->user_pwd);
            if($data["password"] != $password){
                return Base::touser("Please enter a valid Password",false);
            }
        }else{
            return Base::touser("params is missing",false);
        }

        $username = isset($data["email"])?$data["email"]:$data["phone"];
        $length = 5;
        $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);
        if( isset($data["email"])){
            $userInfo = array(
                // "Username" => $username,
                // "password" => decrypt($user->user_pwd),
                // "app_token" => env('APP_TOKEN'),
                // "auth_data" => $randomletter,
                // "is_app_user" => 1,
                // "first_name" => $data['first_name'],
                // "last_name" => $data['last_name'],
                "email" => $data['email'],
                // "phone" => isset($data['phone']) ? trim($data['phone']) : '',
                // "user_name" => $data['email'],
                "password" => $data['password'],
                "base64_img" => null,
                "app_usr_id" => 1,
                "ref_token" => "null",
                "app_token" => env('APP_TOKEN'),
                "is_app_user" => 1,
                "zoom_control" => 10
            );
        }
        else{
            $userInfo = array(
                // "Username" => $username,
                // "password" => decrypt($user->user_pwd),
                // "app_token" => env('APP_TOKEN'),
                // "auth_data" => $randomletter,
                // "is_app_user" => 1,
                // "first_name" => $data['first_name'],
                // "last_name" => $data['last_name'],
                // "email" => $data['email'],
                "phone" => isset($data['phone']) ? trim($data['phone']) : '',
                // "user_name" => $data['email'],
                // "password" => $data['password'],
                "base64_img" => null,
                "app_usr_id" => 1,
                "ref_token" => "null",
                "app_token" => env('APP_TOKEN'),
                "is_app_user" => 1,
                "zoom_control" => 10
            );

        }
        $token = JWT::encode($userInfo, self::salt);
        // dd($token);
        // $json = json_decode($curlresponse, true);
            
        if($req_param == "web"){
            $salt = self::salt;
            $decoded = JWT::decode($token, $salt, array(
                'HS256'
            ));
            $decoded = (array)$decoded;
            // dd($decoded['phone']);
            if(!isset($decoded['email']))
            {
                $decoded['email'] = "";
            }
            if(!isset($decoded['phone']))
            {
                $decoded['phone'] = "";
            }
            // $valid = User::where('email', '=', $data["email"])->Orwhere('phone', '=', $decoded['phone'])->whereIn('role_id', [2, 3, 4])
            //     ->first();
            $valid = User::where('email', '=', $decoded['email'])->Orwhere('phone', '=', $decoded['phone'])->whereIn('role_id', [2, 3, 4])
            ->first();
            // return ($valid);
            $exits = (int)count($valid);
            if ($exits == 1)
            {
                if ($valid->email == $decoded['email']|| $valid->phone == $decoded['phone'])
                {
                    if ($valid->is_active == 1)
                    {
                        $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                        $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                        $key = self::token($token, User::class , $data['device_token'], $data['device_type']);
                        // return $key['user_token'];

                        if (null !== $request->input('api'))
                        {
                            $getuser = User::where('email', '=', $decoded['email'])->Orwhere('phone', '=', $decoded['phone'])->whereIn('role_id', [2, 3, 4])
                                ->first();
                            $logged_userId = $getuser['user_id'];
                            $updateuser = User::find($logged_userId);
                            $manager_data = DB::table('user')
                                                ->select('*')
                                                ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
                                                ->where('emp_mapping.emp_id','=',$logged_userId)
                                                ->take(1)
                                                ->get()->toArray();
                            if($manager_data[0]->map_api_key == null || $manager_data[0]->map_api_key == "") {
                                $updateuser->map_api_key = env('MAPS_API_KEY');
                            } else {
                                $updateuser->map_api_key = $manager_data[0]->map_api_key;
                            }
                            return self::touser(['token' =>$key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                'demo_links' => $valid->demo_links, "profile" => $updateuser], true);
                                // "refreshToken"=> $json["refreshToken"],
                        }
                    }
                    else
                    {
                        return self::touser('Account not activated',false);
                    }
                }
                else
                {
                    return self::touser('User does not match in our record',false);
                }
            }

            //Sass Method Code *******************
            
            elseif($exits==0)
            {
                // $update_token = User::where('email', '=', $data['email'])->update(['user_token'=>$decoded['usr_token']]);
                $valid = User::where('user_token', '=', $data['email'])->whereIn('role_id', [2, 3, 4])
                ->first();
                $exits = (int)count($valid);
                if ($exits == 1)
                {
                    if ($valid->email == $data['email'])
                    {
                        if ($valid->is_active == 1)
                        {
                            $manager_data = DB::table('user')
                                                ->select('*')
                                                ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
                                                ->where('emp_mapping.emp_id', '=', $valid->user_id)
                                                ->take(1)
                                                ->get()->toArray();
                            
                            $maps_api_key = $manager_data[0]->map_api_key;
                            if($maps_api_key == null || $maps_api_key == "") {
                                $maps_api_key = env('MAPS_API_KEY');
                            }
                            
                            $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                            $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                            $key = self::token($token, User::class , $data['device_token'], $data['device_type']);

                            if (null !== $request->input('api'))
                            {
                                return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                    'demo_links' => $valid->demo_links, "refreshToken"=> $json["refreshToken"], 
                                    'maps_api_key' => $maps_api_key], true);
                            }
                        }
                        else
                        {
                            return self::touser('Account not activated',false);
                        }
                    }
                    else
                    {
                        return self::touser('User does not match in our record',false);
                    }
            }
            else
            {
                return self::touser('No Account Found', false);
            }
            }

            // Sass Method End *************


            elseif (self::is_app_domain())
            {
                if (count(master::where('email', $data['email'])->first()) == 1)
                {
                    $valid = master::where('email', $data['email'])->first();

                    if ($valid->email === $data['email'])
                    {
                        if ($valid->is_active == 1)
                        {
                            $key = self::token($valid->id, master::class); //logged
                            return self::touser(['token' => $token, 'role' => self::super_admin() ], true);
                        }
                        else
                        {
                            return self::touser('Account not activated', false);
                        }
                    }
                    else
                    {
                        return self::touser('Password not match', false);
                    }
                }
                else
                {
                    return self::touser('No Account Found', false);
                }
            }
            else
            {
                return self::touser('No Account Found', false);
            }
            //return $decoded;
        }else{
            /*mobile process*/
            // $mobile_token = $json['token'];
            // dd(self::mobile_header());
            if (self::mobile_header() == 1)
            {

                $salt = self::salt;
                $decoded = JWT::decode($token, $salt, array(
                    'HS256'
                ));
                $decoded = (array)$decoded;
                if(!isset($decoded['email']))
                {
                    $decoded['email'] = "";
                }
                if(!isset($decoded['phone']))
                {
                    $decoded['phone'] = "";
                }
                // dd($decoded,'l');
                $valid = User::where('email', '=', $decoded['email'])->Orwhere('phone', '=', $decoded['phone'])->whereIn('role_id', [2, 1])
                    ->first();
                /*get ref token*/

                $getuser = User::where('email', '=',  $decoded['email'])->Orwhere('phone', '=', $decoded['phone'])->whereIn('role_id', [1, 2])
                    ->first();
                $role = $getuser['role_id'];
                // dd($role);
                /*check single and multi device login*/

                //$device_login_status = $getuser['device_login_status'];
                $logged_userId = $getuser['user_id'];

                // if($device_login_status == 1){
                

                //     return self::touser(['token' => null, 'role' => $valid->role->name,'gps_active' => (int) $valid->zipcode,'activity' => $valid->activity,"ref_token" => null,"is_loggedIn" => true,"message" => "The requested params already logged in another device, can u contact admin"], false);
                // }
                if ($role == 1){
                    $getbelongs = $getuser['belongs_manager'];
                    $getRef = User::where('user_id', '=', $getbelongs)->get()
                        ->toArray();

                    $ref_token = $getRef[0]['user_token'];
                }else{
                    $ref_token = $getuser['user_token'];
                }

                $exits = (int)count($valid);


                if ($exits == 1)
                {

                    if ($valid->email ==  $decoded['email']|| $valid->phone == $decoded['phone'])
                    {
                        // dd($exits);

                        //if ($valid->is_active == 1){
                            $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';

                            if (self::mobile_header() == 1)
                            {
                                $data['device_type'] = $_SERVER['HTTP_MOBILE'];
                                /*$date = $_SERVER['HTTP_X_CLIENT_DATE'];
                                $dt = new DateTime($date);
                                $dt = $dt->format('Y-m-d');

                                $user = UserPackage::where([['user_id', '=', $valid->user_id], ['end_date', '<=', $dt]])->count();

                                if ($user > 0)
                                {
                                    return self::app_endvalidity();
                                }*/
                            }
                            $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                            $key = self::token($token, User::class , $data['device_token'], $data['device_type']);
                            $updateuser = User::find($logged_userId);
                            $updateuser->device_login_status = 1;
                            $updateuser->save();
                            $manager_data = DB::table('user')
                                                ->select('*')
                                                ->join('emp_mapping','emp_mapping.admin_id','=','user.user_id')
                                                ->where('emp_mapping.emp_id','=',$this->emp_id)
                                                ->take(1)
                                                ->get()->toArray();
                            if($manager_data[0]->map_api_key == null || $manager_data[0]->map_api_key == "") {
                                $updateuser->map_api_key = env('MAPS_API_KEY');
                            } else {
                                $updateuser->map_api_key = $manager_data[0]->map_api_key;
                            }
                            return self::touser(['token' => $key, 'role' => $valid
                                ->role->name, 'gps_active' => (int)$valid->zipcode, 'activity' => $valid->activity, "ref_token" => $ref_token, 
                                "is_loggedIn" => false, "message" => "logged in success","profile" => $updateuser], true);
                                // "refreshToken"=> $json["refreshToken"]
                        /*}else{
                            //return self::touser('Account not activated');
                            return self::touser(["message" => 'Account is InActivate'], false);
                            //return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, "ref_token" => null, "is_loggedIn" => false, "message" => "Account is InActivate","refreshToken"=> null], false);
                        }*/
                    }
                    else
                    {
                        return self::touser('Password does not match');
                        //return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, "ref_token" => null, "is_loggedIn" => false, "message" => "Password does not match","refreshToken"=> null], false);
                    }
                }
                elseif (self::is_app_domain())
                {
                    if (count(master::where('email', $data['email'])->first()) == 1)
                    {
                        $valid = master::where('email', $data['email'])->first();

                        if ((decrypt($valid->pwd) === $data['password']))
                        {
                            if ($valid->is_active == 1)
                            {
                                $key = self::token($valid->id, master::class); //logged
                                return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                            }
                            else
                            {
                                return self::touser('Account not activated',false);
                            }
                        }
                        else
                        {
                            return self::touser('Password not match',false);
                        }
                    }
                    else
                    {
                        return self::touser('No Account Found',false);
                    }
                }
                else
                {
                    return self::touser('No Account Found',false);
                }
            }
            /*end mobile process*/
        }
    

    }

    public function refresh_token(Request $request){
        $data = $request->input("data");
        $req_param = $request->input('api');
        $refresh_token = $data["refresh_token"];

        $auth_url = "https://eazyfoodapp.com/api/auth/{$refresh_token}/refresh"; 
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $token = self::get_token();

        $authorization = "Authorization: Bearer ".$token;
        $_client_data = "x_client_data: test";

        $curl = curl_init();
          curl_setopt_array($curl, array(
          CURLOPT_URL => $auth_url,
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

          if ($err)
        {
            return self::touser("cURL error",false);
        }
        else
        {       

            $json = json_decode($curlresponse, true);
            if ($json['status']['status_code'] == 200){
                
                if($req_param == "web"){
                    $salt = self::salt;
                    $decoded = JWT::decode($json['token'], $salt, array(
                        'HS256'
                    ));
                    $decoded = (array)$decoded;
                    $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                        ->first();
                    $exits = (int)count($valid);
                    if ($exits == 1)
                    {
                        if ($valid->user_token == $decoded['usr_token'])
                        {
                            if ($valid->is_active == 1)
                            {
                                $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                if (null !== $request->input('api'))
                                {
                                    return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                        'demo_links' => $valid->demo_links,"refreshToken"=> $json["refreshToken"]], true);
                                }
                            }
                            else
                            {
                                return self::touser('Account not activated');
                            }
                        }
                        else
                        {
                            return self::touser('User does not match in our record');
                        }
                    }

                    //Sass Method Code *******************
                    
                    elseif($exits==0)
                    {
                        $update_token = User::where('email', '=', $data['email'])->update(['user_token'=>$decoded['usr_token']]);
                        $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                        ->first();
                        $exits = (int)count($valid);
                        if ($exits == 1)
                        {
                            if ($valid->user_token == $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                    $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                    $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                    if (null !== $request->input('api'))
                                    {
                                        return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                            'demo_links' => $valid->demo_links, "refreshToken"=> $json["refreshToken"]], true);
                                    }
                                }
                                else
                                {
                                    return self::touser('Account not activated');
                                }
                            }
                            else
                            {
                                return self::touser('User does not match in our record');
                            }
                    }
                    else
                    {
                        return self::touser(["message" => 'No Account Found'], false);
                    }
                    }

                    // Sass Method End *************


                    elseif (self::is_app_domain())
                    {
                        if (count(master::where('email', $data['email'])->first()) == 1)
                        {
                            $valid = master::where('email', $data['email'])->first();

                            if ($valid->user_token === $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $key = self::token($valid->id, master::class); //logged
                                    return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                                }
                                else
                                {
                                    return self::touser(["message" => 'Account not activated'], false);
                                }
                            }
                            else
                            {
                                return self::touser(["message" => 'Password not match'], false);
                            }
                        }
                        else
                        {
                            return self::touser(["message" => 'No Account Found'], false);
                        }
                    }
                    else
                    {
                        return self::touser(["message" => 'No Account Found'], false);
                    }
                    //return $decoded;
                }else{
                    /*mobile process*/
                    $mobile_token = $json['token'];

                    if (self::mobile_header() == 1)
                    {
                        $salt = self::salt;
                        $decoded = JWT::decode($mobile_token, $salt, array(
                            'HS256'
                        ));
                        $decoded = (array)$decoded;
                        $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 1])
                            ->first();
                        /*get ref token*/

                        $getuser = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
                            ->first();
                        $role = $getuser['role_id'];

                        /*check single and multi device login*/

                        //$device_login_status = $getuser['device_login_status'];
                        $logged_userId = $getuser['user_id'];

                        // if($device_login_status == 1){
                        

                        //     return self::touser(['token' => null, 'role' => $valid->role->name,'gps_active' => (int) $valid->zipcode,'activity' => $valid->activity,"ref_token" => null,"is_loggedIn" => true,"message" => "The requested params already logged in another device, can u contact admin"], false);
                        // }
                        if ($role == 1)
                        {
                            $getbelongs = $getuser['belongs_manager'];
                            $getRef = User::where('user_id', '=', $getbelongs)->get()
                                ->toArray();

                            $ref_token = $getRef[0]['user_token'];
                        }
                        else
                        {
                            $ref_token = $getuser['user_token'];
                        }

                        $exits = (int)count($valid);

                        if ($exits == 1)
                        {

                            if ($valid->user_token == $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';

                                    if (self::mobile_header() == 1)
                                    {
                                        $data['device_type'] = $_SERVER['HTTP_MOBILE'];
                                        /*$date = $_SERVER['HTTP_X_CLIENT_DATE'];
                                        $dt = new DateTime($date);
                                        $dt = $dt->format('Y-m-d');

                                        $user = UserPackage::where([['user_id', '=', $valid->user_id], ['end_date', '<=', $dt]])->count();

                                        if ($user > 0)
                                        {
                                            return self::app_endvalidity();
                                        }*/
                                    }
                                    $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                    $key = self::token($mobile_token, User::class , $data['device_token'], $data['device_type']);
                                    $updateuser = User::find($logged_userId);
                                    //$updateuser->device_login_status = 1;
                                    $updateuser->save();
                                    return self::touser(['token' => $key, 'role' => $valid
                                        ->role->name, 'gps_active' => (int)$valid->zipcode, 'activity' => $valid->activity, "ref_token" => $ref_token, 
                                        "is_loggedIn" => false, "message" => "logged in success","profile" => $updateuser,
                                        "refreshToken"=> $json["refreshToken"]], true);
                                }
                                else
                                {
                                    //return self::touser('Account not activated');
                                    return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, 
                                        "ref_token" => null, "is_loggedIn" => false, "message" => "Account is InActivate",
                                        "refreshToken"=> null], false);
                                }
                            }
                            else
                            {
                                //return self::touser('Password does not match');
                                return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, "ref_token" => null, 
                                    "is_loggedIn" => false, "message" => "Password does not match","refreshToken"=> null], false);
                            }
                        }
                        elseif (self::is_app_domain())
                        {
                            if (count(master::where('email', $data['email'])->first()) == 1)
                            {
                                $valid = master::where('email', $data['email'])->first();

                                if ((decrypt($valid->pwd) === $data['password']))
                                {
                                    if ($valid->is_active == 1)
                                    {
                                        $key = self::token($valid->id, master::class); //logged
                                        return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                                    }
                                    else
                                    {
                                        return self::touser('Account not activated');
                                    }
                                }
                                else
                                {
                                    return self::touser('Password not match');
                                }
                            }
                            else
                            {
                                return self::touser('No Account Found');
                            }
                        }
                        else
                        {
                            return self::touser('No Account Found');
                        }
                    }
                    /*end mobile process*/
                }
            }
            else
            {
                $msg = $json['status']['message'];
                return self::touser($msg, false);
            }
        }   


    }

    public function web_auth(Request $request)
    {
        $req_param = $request->input('api');
        $data = $request->input('data');

        if ($req_param == "web")
        {
            /*web login process*/
            if (isset($data['email']))
            {
                $rules = ['email' => 'required|email', 'password' => 'required|min:3'];
            }

            $validator = Validator::make($data, $rules);
            if ($validator->fails())
            {
                return self::touser($validator->errors()
                    ->all() [0]);
            }

            $count = User::where('email', '=', $data["email"])->count();
            if($count == 0){
                return self::touser("No account found", false);
            }

            $length = 5;

            $randomletter = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz") , 0, $length);

            $userInfo = array(
                "Username" => $data['email'],
                "password" => $data['password'],
                "app_token" => env('APP_TOKEN'),
                "auth_data" => $randomletter,
                "is_app_user" => 1
            );

            /*weblogin*/
            $auth_url = "https://eazyfoodapp.com/api/Auth";
            $agent = $_SERVER['HTTP_USER_AGENT'];
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
                    "content-type: application/json"
                ) ,
            ));
            $curlresponse = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            if ($err)
            {
                return "cURL Error #:" . $err;
            }
            else
            {       

                $json = json_decode($curlresponse, true);
                if ($json['status']['status_code'] == 200)
                {
                    /*web process*/
                    if($req_param == "web"){
                        $salt = self::salt;
                        $decoded = JWT::decode($json['token'], $salt, array(
                            'HS256'
                        ));
                        $decoded = (array)$decoded;
                        $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                            ->first();
                        $exits = (int)count($valid);
                        if ($exits == 1)
                        {
                            if ($valid->user_token == $decoded['usr_token'])
                            {
                                if ($valid->is_active == 1)
                                {
                                    $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                    $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                    $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                    if (null !== $request->input('api'))
                                    {
                                        return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                            'demo_links' => $valid->demo_links], true);
                                    }
                                }
                                else
                                {
                                    return self::touser('Account not activated');
                                }
                            }
                            else
                            {
                                return self::touser('User does not match in our record');
                            }
                        }

                        //Sass Method Code *******************
                        
                        elseif($exits==0)
                        {
                            $update_token = User::where('email', '=', $data['email'])->update(['user_token'=>$decoded['usr_token']]);
                            $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 3, 4])
                            ->first();
                            $exits = (int)count($valid);
                            if ($exits == 1)
                            {
                                if ($valid->user_token == $decoded['usr_token'])
                                {
                                    if ($valid->is_active == 1)
                                    {
                                        $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';
                                        $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                        $key = self::token($json['token'], User::class , $data['device_token'], $data['device_type']);

                                        if (null !== $request->input('api'))
                                        {
                                            return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                                'demo_links' => $valid->demo_links], true);
                                        }
                                    }
                                    else
                                    {
                                        return self::touser('Account not activated');
                                    }
                                }
                                else
                                {
                                    return self::touser('User does not match in our record');
                                }
                        }
                        else
                        {
                            return self::touser(["message" => 'No Account Found'], false);
                        }
                        }

                        // Sass Method End *************


                        elseif (self::is_app_domain())
                        {
                            if (count(master::where('email', $data['email'])->first()) == 1)
                            {
                                $valid = master::where('email', $data['email'])->first();

                                if ($valid->user_token === $decoded['usr_token'])
                                {
                                    if ($valid->is_active == 1)
                                    {
                                        $key = self::token($valid->id, master::class); //logged
                                        return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                                    }
                                    else
                                    {
                                        return self::touser(["message" => 'Account not activated'], false);
                                    }
                                }
                                else
                                {
                                    return self::touser(["message" => 'Password not match'], false);
                                }
                            }
                            else
                            {
                                return self::touser(["message" => 'No Account Found'], false);
                            }
                        }
                        else
                        {
                            return self::touser(["message" => 'No Account Found'], false);
                        }
                        //return $decoded;
                    }else{
                        /*mobile process*/
                        $mobile_token = $json['token'];

                        if (self::mobile_header() == 1)
                        {
                            $salt = self::salt;
                            $decoded = JWT::decode($mobile_token, $salt, array(
                                'HS256'
                            ));
                            $decoded = (array)$decoded;
                            $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 1])
                                ->first();
                            /*get ref token*/

                            $getuser = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
                                ->first();
                            $role = $getuser['role_id'];

                            /*check single and multi device login*/

                            //$device_login_status = $getuser['device_login_status'];
                            $logged_userId = $getuser['user_id'];

                            // if($device_login_status == 1){
                            

                            //     return self::touser(['token' => null, 'role' => $valid->role->name,'gps_active' => (int) $valid->zipcode,'activity' => $valid->activity,"ref_token" => null,"is_loggedIn" => true,"message" => "The requested params already logged in another device, can u contact admin"], false);
                            // }
                            if ($role == 1)
                            {
                                $getbelongs = $getuser['belongs_manager'];
                                $getRef = User::where('user_id', '=', $getbelongs)->get()
                                    ->toArray();

                                $ref_token = $getRef[0]['user_token'];
                            }
                            else
                            {
                                $ref_token = $getuser['user_token'];
                            }

                            $exits = (int)count($valid);

                            if ($exits == 1)
                            {

                                if ($valid->user_token == $decoded['usr_token'])
                                {
                                    if ($valid->is_active == 1)
                                    {
                                        $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';

                                        if (self::mobile_header() == 1)
                                        {
                                            $data['device_type'] = $_SERVER['HTTP_MOBILE'];
                                            /*$date = $_SERVER['HTTP_X_CLIENT_DATE'];
                                            $dt = new DateTime($date);
                                            $dt = $dt->format('Y-m-d');

                                            $user = UserPackage::where([['user_id', '=', $valid->user_id], ['end_date', '<=', $dt]])->count();

                                            if ($user > 0)
                                            {
                                                return self::app_endvalidity();
                                            }*/
                                        }
                                        $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                                        $key = self::token($mobile_token, User::class , $data['device_token'], $data['device_type']);
                                        $updateuser = User::find($logged_userId);
                                        //$updateuser->device_login_status = 1;
                                        $updateuser->save();
                                        return self::touser(['token' => $key, 'role' => $valid->role->name, 'gps_active' => (int)$valid->zipcode, 
                                            'activity' => $valid->activity, "ref_token" => $ref_token, "is_loggedIn" => false, 
                                            "message" => "logged in success","profile" => $updateuser], true);
                                    }
                                    else
                                    {
                                        //return self::touser('Account not activated');
                                        return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, 
                                            "ref_token" => null, "is_loggedIn" => false, "message" => "Account is InActivate"], false);
                                    }
                                }
                                else
                                {
                                    //return self::touser('Password does not match');
                                    return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, 
                                        "ref_token" => null, "is_loggedIn" => false, "message" => "Password does not match"], false);
                                }
                            }
                            elseif (self::is_app_domain())
                            {
                                if (count(master::where('email', $data['email'])->first()) == 1)
                                {
                                    $valid = master::where('email', $data['email'])->first();

                                    if ((decrypt($valid->pwd) === $data['password']))
                                    {
                                        if ($valid->is_active == 1)
                                        {
                                            $key = self::token($valid->id, master::class); //logged
                                            return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                                        }
                                        else
                                        {
                                            return self::touser('Account not activated');
                                        }
                                    }
                                    else
                                    {
                                        return self::touser('Password not match');
                                    }
                                }
                                else
                                {
                                    return self::touser('No Account Found');
                                }
                            }
                            else
                            {
                                return self::touser('No Account Found');
                            }
                        }
                        /*end mobile process*/
                    }
                    
                }
                else
                {
                    $msg = $json['status']['message'];
                    return self::touser($msg, false);
                }
            }
        }
        else
        {
            /*mobile login process*/
            $mobile_token = $data['token'];

            if (self::mobile_header() == 1)
            {
                $salt = self::salt;
                $decoded = JWT::decode($mobile_token, $salt, array(
                    'HS256'
                ));
                $decoded = (array)$decoded;
                $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 1])
                    ->first();
                /*get ref token*/

                $getuser = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
                    ->first();
                $role = $getuser['role_id'];

                /*check single and multi device login*/

                //$device_login_status = $getuser['device_login_status'];
                $logged_userId = $getuser['user_id'];

                // if($device_login_status == 1){
                

                //     return self::touser(['token' => null, 'role' => $valid->role->name,'gps_active' => (int) $valid->zipcode,'activity' => $valid->activity,"ref_token" => null,"is_loggedIn" => true,"message" => "The requested params already logged in another device, can u contact admin"], false);
                // }
                if ($role == 1)
                {
                    $getbelongs = $getuser['belongs_manager'];
                    $getRef = User::where('user_id', '=', $getbelongs)->get()
                        ->toArray();

                    $ref_token = $getRef[0]['user_token'];
                }
                else
                {
                    $ref_token = $getuser['user_token'];
                }

                $exits = (int)count($valid);

                if ($exits == 1)
                {

                    if ($valid->user_token == $decoded['usr_token'])
                    {
                        if ($valid->is_active == 1)
                        {
                            $data['device_token'] = isset($data['device_token']) ? $data['device_token'] : '';

                            if (self::mobile_header() == 1)
                            {
                                $data['device_type'] = $_SERVER['HTTP_MOBILE'];
                                /*$date = $_SERVER['HTTP_X_CLIENT_DATE'];
                                $dt = new DateTime($date);
                                $dt = $dt->format('Y-m-d');

                                $user = UserPackage::where([['user_id', '=', $valid->user_id], ['end_date', '<=', $dt]])->count();

                                if ($user > 0)
                                {
                                    return self::app_endvalidity();
                                }*/
                            }
                            $data['device_type'] = isset($data['device_type']) ? $data['device_type'] : '';
                            $key = self::token($mobile_token, User::class , $data['device_token'], $data['device_type']);
                            $updateuser = User::find($logged_userId);
                            //$updateuser->device_login_status = 1;
                            $updateuser->save();
                            return self::touser(['token' => $key, 'role' => $valid
                                ->role->name, 'gps_active' => (int)$valid->zipcode, 'activity' => $valid->activity, "ref_token" => $ref_token, 
                                "is_loggedIn" => false, "message" => "logged in success"], true);
                        }
                        else
                        {
                            //return self::touser('Account not activated');
                            return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, "ref_token" => null, 
                                "is_loggedIn" => false, "message" => "Account is InActivate"], false);
                        }
                    }
                    else
                    {
                        //return self::touser('Password does not match');
                        return self::touser(['token' => null, 'role' => null, 'gps_active' => null, 'activity' => null, "ref_token" => null, 
                            "is_loggedIn" => false, "message" => "Password does not match"], false);
                    }
                }
                elseif (self::is_app_domain())
                {
                    if (count(master::where('email', $data['email'])->first()) == 1)
                    {
                        $valid = master::where('email', $data['email'])->first();

                        if ((decrypt($valid->pwd) === $data['password']))
                        {
                            if ($valid->is_active == 1)
                            {
                                $key = self::token($valid->id, master::class); //logged
                                return self::touser(['token' => $key, 'role' => self::super_admin() ], true);
                            }
                            else
                            {
                                return self::touser('Account not activated');
                            }
                        }
                        else
                        {
                            return self::touser('Password not match');
                        }
                    }
                    else
                    {
                        return self::touser('No Account Found');
                    }
                }
                else
                {
                    return self::touser('No Account Found');
                }
            }
        }
        /*end else*/
    }

    public function device_logout(Request $request)
    {
        $req_param = $request->input('api');
        $data = $request->input('data');
        $mobile_token = $data['token'];
        $salt = self::salt;
        $decoded = JWT::decode($mobile_token, $salt, array(
            'HS256'
        ));
        $decoded = (array)$decoded;
        $valid = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [2, 1])
            ->first();
        /*get ref token*/

        $getuser = User::where('user_token', '=', $decoded['usr_token'])->whereIn('role_id', [1, 2])
            ->first();
        $role = $getuser['role_id'];

        /*check single and multi device login*/

        //$device_login_status = $getuser['device_login_status'];
        $logged_userId = $getuser['user_id'];
        $updateuser = User::find($logged_userId);
        if ($getuser['role_id'] == 2)
        {
            $updateuser->device_login_status = 0;
        }
        else
        {
            $updateuser->device_login_status = 1;
        }
        $updateuser->save();
        return self::touser('Logout success', true);
    }

    public static function check_null($data)
    {
        $data = isset($data) ? $data : null;
        return $data;
    }

    public static function file_check($data)
    {
        if (is_array($data))
        {
            return json_encode($data);
        }
        else
        {
            return '[]';
        }
    }

    public static function touser($data, $status = false, $code = 200)
    {
        $st = 'error';

        if ($status)
        {
            $st = 'ok';
        }

        /* $else = json_encode(array('data' => $data, 'status' => $st));
         $data = $else;
        */
        $data = json_encode(array(
            'data' => $data,
            'status' => $st
        ));
        return response($data, $code)->withHeaders(['Content-Type' => 'application/json']);
    }

    public static function touserloc($data, $place, $status = false, $code = 200)
    {
        $st = 'error';

        if ($status)
        {
            $st = 'ok';
        }
        $data = json_encode(array(
            'data' => $data,
            'place' => $place,
            'status' => $st
        ));
        return response($data, $code)->withHeaders(['Content-Type' => 'application/json']);
    }

    public static function token($data, $model, $device_token = null, $device_type = null)
    {
        // return $data[0]['token'];
        $salt = self::salt;
        $user_ip = self::ip();
        $user_agent = self::user_agent();

        // $token = array(
        //     "apitoken" => Hashids::encode($data).'_'.\Hash::make(str_random(500)),
        //     "iss" => self::get_domin(),
        //     "type" =>  str_replace('App\\Models\\','',$model),
        //     );
        // $jwt = JWT::encode($token, $salt);
        $decoded = JWT::decode($data, $salt, array(
            'HS256'
        ));
        $decoded = (array)$decoded;
        // dd($decoded,'sss');
        // $exist = count(ApiAuth::where('auth_key', '=', $data)->get()
        //     ->toArray());
        $session = clientinfo::firstOrNew(array(
            'client_data' => self::client_data()
        ));
        $session->client_ip = $user_ip;
        $session->client_data = self::client_data();
        $session->client_info = $user_agent;
        $session->save();

        /*new implementaion*/
        // dd($data);

        // $decoded = JWT::decode($data, $salt, array(
        //     'HS256'
        // ));
        // $decoded = (array)$decoded;
        if(isset($decoded['email']))
        {
            $user = User::where('email', '=',$decoded['email'])->get()
            ->toArray();
        }
        else{
            $user = User::where('phone', '=', $decoded['phone'])->get()
            ->toArray();
        }


       
        // dd('lpoppo');
        // dd($user);
        // dd($user[0]['user_id']);
        // $auth_user_id=$user[0]['user_id'];
        // $array =
        // [
        // "auth_key" => $jwt,
        // "auth_user_agent" => $user_agent,
        // "auth_ip" => $user_ip,
        // "auth_model" => $model,
        // "auth_user_id" => $auth_user_id,
        // ];
        

        // $api = ApiAuth::updateOrCreate($array);
        // return $api;
        // remove other previous device token
        // dd($device_type);
        if (empty($device_type) || $device_type === 'web')
        {
            $device_type = 'web';
            
            // $check_user = count(ApiAuth::where('auth_user_id', '=', $user[0]['user_id'])->whereNotIn('device_type', ['android', 'iOS'])
            //     ->get()
            //     ->toArray());
            // if ($check_user > 0)
            // {
            //     // dd('ent');

            //     $api = ApiAuth::where('auth_user_id', '=', $user[0]['user_id'])->whereNotIn('device_type', ['android', 'iOS'])
            //         ->first();
            //     $api->auth_key = $data;
            //     $api->auth_user_agent = $user_agent;
            //     $api->auth_ip = $user_ip;
            //     $api->auth_model = $model;
            //     $api->user_token = $data;
            //     $api->auth_user_id = $user[0]['user_id'];
            //     $api->api_key = $user[0]['api_key'];
            //     $api->device_token = $device_token;
            //     $api->device_type = $device_type;
            //     $api->save();
            //     // dd($api);
            //     return $api;
            // }
            
        }
        else
        {
            // dd($user[0]['user_id']);
            $check_user = count(ApiAuth::where('auth_user_id', '=', $user[0]['user_id'])->whereIn('device_type', ['android', 'iOS'])
                ->get()
                ->toArray());
            // dd($check_user);
            if ($check_user > 0)
            {
                ApiAuth::where([['auth_user_id', '=', $user[0]['user_id']]])->whereIn('device_type', ['android', 'iOS'])
                    ->delete();
            }
            
            // Device token already placed in another account
            if(!empty($device_token)) {
                $check_device = count(ApiAuth::where('device_token', '=', $device_token)->where('auth_user_id', '!=', $user[0]['user_id'])
                                ->get()
                                ->toArray());
                
                if ($check_device > 0) {
                    ApiAuth::where('device_token', '=', $device_token)->where('auth_user_id', '!=', $user[0]['user_id'])
                            ->delete();
                }
            }
        }

        //        $check_user = count(ApiAuth::where([['auth_user_id', '=', $user[0]['user_id']]])->get()->toArray());
        //        if ($check_user > 0) {
        //            ApiAuth::where([['auth_user_id', '=', $user[0]['user_id']]])->delete();
        //        }
        $api = new ApiAuth;
        $api->auth_key = $data;
        $api->auth_user_agent = $user_agent;
        $api->auth_ip = $user_ip;
        $api->auth_model = $model;
        $api->user_token =$data;
        $api->auth_user_id = $user[0]['user_id'];
        $api->api_key = $user[0]['api_key'];
        $api->device_token = $device_token;
        $api->device_type = $device_type;
        $api->save();

        // remove other previous device token
        

        // old code *********************
        // if ($exist > 0) {
        //     $api = ApiAuth::where('auth_key', '=', $data)->first();
        //     $api->auth_key = $data;
        //     $api->auth_user_agent = $user_agent;
        //     $api->auth_ip = $user_ip;
        //     $api->auth_model = $model;
        //     $api->user_token = $decoded['usr_token'];
        //     $api->auth_user_id = $user[0]['user_id'];
        //     $api->api_key = $user[0]['api_key'];
        //     $api->device_token = $device_token;
        //     $api->device_type = $device_type;
        //     $api->save();
        // } else {
        //     $api = new ApiAuth;
        //     $api->auth_key = $data;
        //     $api->auth_user_agent = $user_agent;
        //     $api->auth_ip = $user_ip;
        //     $api->auth_model = $model;
        //     $api->user_token = $decoded['usr_token'];
        //     $api->auth_user_id = $user[0]['user_id'];
        //     $api->api_key = $user[0]['api_key'];
        //     $api->device_token = $device_token;
        //     $api->device_type = $device_type;
        //     $api->save();
        // }
        // old code  *********************************
        

        return $api;
    }

    public function removeLocal(Request $request)
    {
        $token = $request->data;
        $check = self::isTokenValid($token);
        return $check;
    }

    public static function isTokenValid($token)
    {
        try
        {
            $salt = self::salt;
            $decoded = JWT::decode($token, $salt, ['HS256']);
            //TODO: do something if exception is not fired
            return self::touser('Token valid', true);
        }
        catch(\Firebase\JWT\ExpiredException $e)
        {
            if (ApiAuth::where('api_key', '=', $token)->exists())
            {
                return self::touser('Token valid', true);
            }
            /*get new token*/
            return self::touser('Token Expired', false);
        }
        catch(\Exception $e)
        {
            if (ApiAuth::where('api_key', '=', $token)->exists())
            {
                return self::touser('Token valid', true);
            }
            //var_dump($e);
            return self::touser($token, false);
        }
    }

    public static function checkTokenStatus($token)
    {
        try
        {
            $salt = self::salt;
            $decoded = JWT::decode($token, $salt, ['HS256']);
            //TODO: do something if exception is not fired
            return $token;
        }
        catch(\Firebase\JWT\ExpiredException $e)
        {
            /*get new token*/
            $auth_username = env('AUTH_USERNAME');
            $auth_password = env('AUTH_PASSWORD');
            $app_token = env('APP_TOKEN');
            $x_client_data = "test";
            $is_app_user = 0;

            $login_Info = array(
                "Username" => $auth_username,
                "Password" => $auth_password,
                "app_token" => $app_token,
                "auth_data" => $x_client_data,
                "is_app_user" => $is_app_user
            );

            $auth_url = "https://eazyfoodapp.com/api/Auth";
            $agent = $_SERVER['HTTP_USER_AGENT'];
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
                CURLOPT_POSTFIELDS => json_encode($login_Info) ,
                CURLOPT_HTTPHEADER => array(
                    "accept: */*",
                    "accept-language: en-US,en;q=0.8",
                    "content-type: application/json"
                ) ,
            ));
            $curlresponse = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            if ($err)
            {
                return "cURL Error #:" . $err;
            }
            else
            {

                $json = json_decode($curlresponse, true);

                if ($json['status']["status_code"] == 200)
                {
                    $new_token = $json["token"];
                    $admin_auth = AuthAdmin::find('1');
                    $admin_auth->auth_key = $new_token;
                    $admin_auth->save();

                    return $new_token;
                }
            }
        }
        catch(\Exception $e)
        {
            //var_dump($e);
            return self::touser($token, false);
        }
    }

    public static function refreshToken($token)
    {
        $salt = self::salt;
        JWT::$leeway = 720000;
        $t = time();
        $decoded = (array)JWT::decode($token, $salt, ['HS256']);
        // TODO: test if token is blacklisted
        //$decoded['iat'] = time();
        $decoded['exp'] = time() + $t;

        $new_token = JWT::encode($decoded, $salt);
        return self::touser($new_token, true);
    }

    public function getActiveDevice(Request $request)
    {
        $logged_userId = $this->emp_id;
        $getuserList = $getuser = User::where('belongs_manager', '=', $logged_userId)->whereIn('role_id', [1, 2])
            ->where('device_login_status', 1)
            ->get();

        $device_status = array();

        if (count($getuserList) > 0)
        {
            foreach ($getuserList as $value)
            {
                $user_token = $value['user_token'];
                $api_auth = ApiAuth::where('user_token', '=', $user_token)->orderBy('auth_id', 'DESC')
                    ->first();
                $login_time = $api_auth['created_at'];
                $lastLoggedIn = self::time_elapsed_string($login_time);
                $value->lastLoggedIn = $lastLoggedIn;
                array_push($device_status, $value);
            }

            return self::touser($device_status, true);
        }
        else
        {
            return self::touser('No device found', false);
        }

    }

    public static function get_x_clinet_data_from_token($token)
    {
        try
        {
            $salt = self::salt;
            $decoded = JWT::decode($token, $salt, ['HS256']);
            return $decoded->x_client_data;
            //return self::touser($decoded,true);
            
        }
        catch(\Firebase\JWT\ExpiredException $e)
        {
            /*get new token*/
            return self::touser('TOken Expired', false);
        }
        catch(\Exception $e)
        {
            //var_dump($e);
            return self::touser($token, false);
        }
    }

    public function removeAccess(Request $request, $id)
    {
        if (!empty($id))
        {
            $user = User::find($id);
            $user->device_login_status = 0;
            $user->save();
            return self::touser('Access removed successfully', true);
        }
    }

    public static function ip()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP']))
        {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        else
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }

    public static function user_agent()
    {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    public function deftimezone()
    {

        //return self::client_time();
        
    }

    public static function user_timezone()
    {

        //         $date = "04/30/1973";
        // $array = explode("/",$date);
        // print_r($array);
        $user = User::all()->toArray();
        foreach ($user as $user_data)
        {
            if ($user_data['timezone'] != '')
            {
                $timezone = timezonemang::where('desc', $user_data['timezone'])->get()
                    ->toArray();
                // print_r($timezone);
                if (count($timezone) >= 1)
                {
                    // print_r($user_data);
                    // print_r($timezone[0]['name']);
                    $user = User::find($user_data['user_id']);
                    $user->timezonename = $timezone[0]['name'];
                    $user->timezone = $timezone[0]['desc'];
                    $user->save();
                }
                else
                {
                    $timezone2 = timezonemang::where('name', 'like', '%' . $user_data['timezone'] . '%')->get()
                        ->toArray();
                    if (count($timezone2) > 0)
                    {
                        $user = User::find($user_data['user_id']);
                        $user->timezonename = $timezone2[0]['name'];
                        $user->timezone = $timezone2[0]['desc'];
                        $user->save();
                    }
                    else
                    {
                        $user = User::find($user_data['user_id']);
                        $user->timezonename = '';
                        $user->timezone = '';
                        $user->save();
                    }
                    // print_r($timezone2[0]['name']);
                    
                }

            }
            // $user_data = User::find()
            
        }
        // echo "Month: $month; Day: $day; Year: $year<br />\n";
        // $timezone = timezonemang::all()->toArray();
        // print_r($timezone);
        // foreach($timezone as $time)
        // {
        //     // $zone = str_replace(array( '[', ']' ), '', $time['timezone']);
        //     $timezone_update = timezonemang::find($time['id']);
        //     $timezone_update->name = $time['desc'];
        //     $timezone_update->desc = $time['name'];
        //     $timezone_update->timezone = '('.$time['timezone'].') '.$time['name'];
        //     $timezone_update->save();
        // }
        // str_replace(array( '[', ']' ), '', $timezone);
        
    }

    public static function check_user_validity()
    {
        $user = self::getRole();
        // print_r($user);
        $userid = $user[1];
        // print_r($userid);
        $date = $_SERVER['HTTP_X_CLIENT_DATE'];
        $dt = new DateTime($date);
        $dt = $dt->format('Y-m-d');
        // echo $dt;
        $user = UserPackage::where([['user_id', '=', $userid], ['end_date', '<=', $dt]])->count();
        // echo $user;
        return $user;
    }

    public static function send_mail()
    {
        $user = self::getRole();
        $userid = $user[1];
        $data = User::where('user_id', $userid)->get();
        // print_r($data[0]->email);
        $data = $data[0];
        // $data->content = "User Requested for Package upgrade,user details as follows. User: ".$data->first_name."\n"."Email :".$data->email."\n"."Phone No:".$data->phone;
        $data->content = <<<HTML
    <html>
    <body><h3>Hi,</h3>
    <p>User Requested for Package upgrade,user details as follows</p>
    <p>User:$data->first_name</p>
    <p>Email:$data->email</p>
    <p>Phone:$data->phone</p>
    </body>
    </html>
HTML;
        // print($data);
        Mail::send([], [], function ($message) use ($data)
        {
            $message->from('bd@manageteamz.com', 'Admin');
            $message->to('bd@manageteamz.com');
            $message->subject("Request for Package Upgrade");
            $message->setBody($data->content, 'text/html');
        });

        return response()
            ->json(['data' => 'Mail Sent', 'status' => 'ok']);
    }

    public static function ReferalUsers()
    {
        $users = User::all()->toArray();
        // return $users;
        //$users = User::where('user_id','<',10)
        //->get();
        $user_array = array();
        foreach ($users as $key => $user_data)
        {
            if (($user_data['user_id'] == $user_data['belongs_manager']) && ($user_data['role_id'] == 2))
            {
                $password = $user_data['user_pwd'];
                $hash_password = decrypt($password);
                $userInfo[] = array(
                    "first_name" => $user_data['first_name'],
                    "last_name" => isset($user_data['last_name']) ? $user_data['last_name'] : '',
                    "email_address" => isset($user_data['email']) ? $user_data['email'] : '',
                    "phone" => isset($user_data['phone']) ? $user_data['phone'] : 'null',
                    "user_name" => $user_data['email'],
                    "password" => $hash_password,
                    "base64_img" => null,
                    "app_usr_id" => $user_data['user_id'],
                    "ref_token" => null
                );

                $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5NDcxODg5LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.gigoTd2VWZQlu6B9iYpilVwMqbIml22OGuCe2Cx5eFE";

                // $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
                // $agent = $_SERVER['HTTP_USER_AGENT'];
                // $authorization = "Authorization: Bearer ".$token;
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
                //         "x_client_data: test",
                //         $authorization
                //     ) ,
                // ));
                // $curlresponse = curl_exec($curl);
                // $err = curl_error($curl);
                // curl_close($curl);
                //     if($err){
                //         return "cURL Error #:" . $err;
                //     }else{
                //         $json = json_decode($curlresponse, true);
                //         $json_data = $json['data'];
                //         foreach($json_data as $k => $value){
                //         if($value['is_success'] == true){
                //             $user = User::find($value['app_usr_id']);
                //             $user->user_token = $value['user_token'];
                //             $user->save();
                //         }else{
                //             echo "not";
                //         }
                //     }
                //     }
                
            }
            else
            {
                /*not equal*/
                // if($user_data['role_id'] == 1){
                //     $user_token = User::where('user_id','=',$user_data['user_id'])->get()->toArray();
                //     /*Check count*/
                //     if(count($user_token)>0){
                //         $belongs_id = $user_token[0]['belongs_manager'];
                //         $t = User::where('user_id','=',$belongs_id)->get()->toArray();
                

                //         if(count($t) > 0){ /*no user id found case*/
                //             $referel_token = $t[0]['user_token'];
                //             $geteferel = User::where('belongs_manager','=',$belongs_id)->get()->toArray();
                //             foreach ($geteferel as $user) {
                //                if($user['role_id'] == 1){
                //                     $password = $user['user_pwd'];
                //                     $hash_password = decrypt($password);
                //                     $userInfo[] = array(
                //                         "first_name" => $user['first_name'],
                //                         "last_name" => isset($user['last_name'])?$user['last_name']:'',
                //                         "email_address" => isset($user['email'])?$user['email']:'',
                //                         "phone" => isset($user['phone'])?$user['phone']:'',
                //                         "user_name" => $user['email'],
                //                         "password" => $hash_password,
                //                         "base64_img" => null,
                //                         "app_usr_id" => $user['user_id'],
                //                         "ref_token" => $referel_token
                //                     );
                //                     $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5NDcxODg5LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.gigoTd2VWZQlu6B9iYpilVwMqbIml22OGuCe2Cx5eFE";
                //                     $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
                //                     $agent = $_SERVER['HTTP_USER_AGENT'];
                //                     $authorization = "Authorization: Bearer ".$token;
                //                     $curl = curl_init();
                //                     curl_setopt_array($curl, array(
                //                     CURLOPT_URL => $auth_url,
                //                     CURLOPT_RETURNTRANSFER => true,
                //                     CURLOPT_ENCODING => "",
                //                     CURLOPT_MAXREDIRS => 10,
                //                     CURLOPT_TIMEOUT => 30000,
                //                     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                //                     CURLOPT_CUSTOMREQUEST => "POST",
                //                     CURLOPT_USERAGENT => $agent,
                //                     CURLOPT_POSTFIELDS => json_encode($userInfo) ,
                //                     CURLOPT_HTTPHEADER => array(
                //                             "accept: */*",
                //                             "accept-language: en-US,en;q=0.8",
                //                             "content-type: application/json",
                //                             "x_client_data: test",
                //                             $authorization
                //                         ) ,
                //                     ));
                //                     $curlresponse = curl_exec($curl);
                //                     $err = curl_error($curl);
                //                     curl_close($curl);
                //                         if($err){
                //                             return "cURL Error #:" . $err;
                //                         }else{
                //                             $json = json_decode($curlresponse, true);
                //                             $json_data = $json['data'];
                //                             echo "ok";
                //                             //print_r($json_data);
                //                             //print_r($json_data);
                //                             //print_r($json_data[$key]['app_usr_id']);
                //                         }
                //                }
                //             }
                //         }
                //     }
                // }
                
            }
        }
        $key++;
    }

    public static function adminRefereal()
    {
        $users = User::where('user_token', '=', null)->where('role_id', '=', 2)
        //->whereRaw('user_id = belongs_manager')
        
            ->get();
        //return $users;
        // $users = User::where('user_token','=','test')
        //         ->get();
        $user_array = array();
        $count = 0;
        $newarray = array();
        foreach ($users as $key => $user_data)
        {
            // if(($user_data['user_id'] == $user_data['belongs_manager']) && ($user_data['role_id'] == 2)){
            try
            {
                $password = $user_data['user_pwd'];
                $hash_password = decrypt($password);
                $userInfo = array(
                    "first_name" => $user_data['first_name'],
                    "last_name" => isset($user_data['last_name']) ? $user_data['last_name'] : '',
                    "email_address" => isset($user_data['email']) ? $user_data['email'] : '',
                    "phone" => isset($user_data['phone']) ? $user_data['phone'] : '',
                    "user_name" => isset($user_data['email']) ? $user_data['email'] : $user_data['phone'],
                    "password" => $hash_password,
                    "base64_img" => null,
                    "app_usr_id" => $user_data['user_id'],
                    "ref_token" => null
                );

                array_push($newarray, $userInfo);

                $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5OTk0Njg4LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.w51GmfdRgZdFN8xPZ-KDOY9t0s642X_mQItWsWXq2Z8";

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
                    CURLOPT_POSTFIELDS => json_encode($newarray) ,
                    CURLOPT_HTTPHEADER => array(
                        "accept: */*",
                        "accept-language: en-US,en;q=0.8",
                        "content-type: application/json",
                        "x_client_data: test",
                        $authorization
                    ) ,
                ));
                $curlresponse = curl_exec($curl);
                $err = curl_error($curl);

                curl_close($curl);

                if ($err)
                {
                    return "cURL Error #:" . $err;
                }
                else
                {
                    $newarray = array();
                    // print_r($count++);
                    $json = json_decode($curlresponse, true);

                    $json_data = $json['data'];

                    $_resp = var_export($json_data[0]['is_success']);

                    if ($json_data[0]['is_success'] == true)
                    {

                        $user = User::find($json_data[0]['app_usr_id']);
                        $user->user_token = $json_data[0]['user_token'];
                        $user->save();
                    }
                    else
                    {
                        echo "not";
                    }

                }

            }
            catch(exception $e)
            {
                print_r($e);
            }
        } /*end foreach*/
        $key++;
    }

    public static function returnUsrToken($belongs_id)
    {
        $token = User::where('user_id', '=', $belongs_id)->get();
        return $token[0]['user_token'];
    }

    public static function Driverupdate()
    {

        $users = User::where('role_id', 1)->whereRaw('user_id != belongs_manager')
            ->get();

        $newarray = array();
        $count = 0;
        foreach ($users as $key => $value)
        {
            $belongs_manager = $value['belongs_manager'];
            $get_ref_token = User::where('user_id', $belongs_manager)->get();
            if (count($get_ref_token) > 0)
            {
                $ref_token = $get_ref_token[0]['user_token'];
                try
                {
                    $password = $value['user_pwd'];
                    $hash_password = decrypt($password);
                    $userInfo = array(
                        "first_name" => $value['first_name'],
                        "last_name" => isset($value['last_name']) ? $value['last_name'] : '',
                        "email_address" => isset($value['email']) ? $value['email'] : '',
                        "phone" => isset($value['phone']) ? $value['phone'] : '',
                        "user_name" => isset($value['email']) ? $value['email'] : $value['phone'],
                        "password" => $hash_password,
                        "base64_img" => null,
                        "app_usr_id" => $value['user_id'],
                        "ref_token" => $ref_token
                    );
                    array_push($newarray, $userInfo);
                    $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5OTk0Njg4LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.w51GmfdRgZdFN8xPZ-KDOY9t0s642X_mQItWsWXq2Z8";

                    $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
                    $agent = $_SERVER['HTTP_USER_AGENT'];
                    $authorization = "Authorization: Bearer " . $token;
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $auth_url,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => "",
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 0,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => "POST",
                        CURLOPT_USERAGENT => $agent,
                        CURLOPT_POSTFIELDS => json_encode($newarray) ,
                        CURLOPT_HTTPHEADER => array(
                            "accept: */*",
                            "accept-language: en-US,en;q=0.8",
                            "content-type: application/json",
                            "x_client_data: test",
                            $authorization
                        ) ,
                    ));
                    $curlresponse = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err)
                    {
                        return "cURL Error #:" . $err;
                    }
                    else
                    {
                        $newarray = array();
                        // print_r($count++);
                        $json = json_decode($curlresponse, true);

                        $json_data = $json['data'];
                        // echo "<pre>";
                        //     print_r($json);
                        //     echo "</pre>";
                        $_resp = var_export($json_data[0]['is_success']);

                        if ($json_data[0]['is_success'] == true)
                        {
                            $user = User::find($json_data[0]['app_usr_id']);
                            $user->user_token = $json_data[0]['user_token'];
                            $user->save();

                        }
                        else
                        {
                            echo "not";
                        }

                    }

                }
                catch(exception $e)
                {
                    print_r($e);
                }
            }
            else
            {
                /*get_ref_token_count_check*/
            }

        }
    }

    public static function DriverRef()
    {
        $users = User::where('role_id', '=', 1)->whereRaw('user_id != belongs_manager')
            ->where('user_token', null)
            ->get();

        $newarray = array();
        foreach ($users as $key => $user_data)
        {
            /*get Drivers list*/
            try
            {
                $DriverList = "";
                $DriverList = User::where('role_id', '=', 1)->where('belongs_manager', '=', $user_data['belongs_manager'])->get();

                if (!empty($DriverList))
                {
                    foreach ($DriverList as $key => $value)
                    {
                        try
                        {
                            $belongs_id = "";
                            $Usertoken = "";
                            $belongs_id = $value['belongs_manager'];

                            $Usertoken = User::where('user_id', '=', $belongs_id)->get();
                            $get_usr_token = $Usertoken[0]['user_token'];

                            $password = $value['user_pwd'];
                            $hash_password = decrypt($password);
                            $userInfo = array(
                                "first_name" => $value['first_name'],
                                "last_name" => isset($value['last_name']) ? $value['last_name'] : '',
                                "email_address" => isset($value['email']) ? $value['email'] : '',
                                "phone" => isset($value['phone']) ? $value['phone'] : '',
                                "user_name" => isset($value['email']) ? $value['email'] : $value['phone'],
                                "password" => $hash_password,
                                "base64_img" => null,
                                "app_usr_id" => $value['user_id'],
                                "ref_token" => $get_usr_token
                            );

                            array_push($newarray, $userInfo);

                            echo "<pre>";
                            print_r($newarray);
                            echo "</pre>";

                            $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5NzAwNjgwLCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.C82DJXIiNfIlDTyqN42BxoK4sWikzP6LuK8gqxQRkYI";

                            $auth_url = "https://eazyfoodapp.com/api/users/CreateAppUsers";
                            $agent = $_SERVER['HTTP_USER_AGENT'];
                            $authorization = "Authorization: Bearer " . $token;
                            $curl = curl_init();
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => $auth_url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 0,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_USERAGENT => $agent,
                                CURLOPT_POSTFIELDS => json_encode($newarray) ,
                                CURLOPT_HTTPHEADER => array(
                                    "accept: */*",
                                    "accept-language: en-US,en;q=0.8",
                                    "content-type: application/json",
                                    "x_client_data: test",
                                    $authorization
                                ) ,
                            ));
                            $curlresponse = curl_exec($curl);
                            $err = curl_error($curl);

                            curl_close($curl);

                            if ($err)
                            {
                                return "cURL Error #:" . $err;
                            }
                            else
                            {
                                $newarray = array();
                                $json = json_decode($curlresponse, true);

                                $json_data = $json['data'];
                                echo "<pre>";
                                print_r($json);
                                echo "</pre>";
                                $_resp = var_export($json_data[0]['is_success']);

                                if ($_resp == false)
                                {
                                    $user = User::find($json_data[0]['app_usr_id']);
                                    $user->user_token = $json_data[0]['response_msg'];
                                    $user->save();

                                }
                                else
                                {
                                    echo "not";
                                    // echo "<pre>";
                                    // print_r($DriverList[$i]['user_id']);
                                    // echo "</pre>";
                                    
                                }

                                // foreach($json_data as $k => $value){
                                //      if($value['is_success'] == true){
                                //         $user = User::find($value['app_usr_id']);
                                //         $user->user_token = $value['user_token'];
                                //         $user->save();
                                //     }else{
                                //         echo "not";
                                //     }
                                // }
                                
                            }

                        }
                        catch(exception $e)
                        {
                            print_r($e);
                        }
                    }
                }
            }
            catch(exception $e)
            {
                print_r($e);
            }

        } /*foreach close*/

    }

    public static function driverReferel()
    {
        $users = User::where('role_id', '=', 1)->get();
        // return $users;
        //$users = User::where('user_id','<',10)
        //->get();
        $user_array = array();
        foreach ($users as $key => $user_data)
        {
            /*not equal*/
            $user_token = User::where('user_id', '=', $user_data['user_id'])->get()
                ->toArray();

            $belongs_id = $user_token[0]['belongs_manager'];
            $t = User::where('user_id', '=', $belongs_id)->get()
                ->toArray();

            if (count($t) > 0)
            { /*no user id found case*/
                $referel_token = $t[0]['user_token'];

                $geteferel = User::where('belongs_manager', '=', $belongs_id)->get()
                    ->toArray();

                foreach ($geteferel as $user)
                {
                    $password = $user['user_pwd'];
                    $hash_password = decrypt($password);
                    $userInfo[] = array(
                        "first_name" => $user['first_name'],
                        "last_name" => isset($user['last_name']) ? $user['last_name'] : '',
                        "email_address" => isset($user['email']) ? $user['email'] : '',
                        "phone" => isset($user['phone']) ? $user['phone'] : '',
                        "user_name" => $user['email'],
                        "password" => $hash_password,
                        "base64_img" => null,
                        "app_usr_id" => $user['user_id'],
                        "ref_token" => $referel_token
                    );

                    $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c3JfaWQiOiIzIiwidXNyX3Rva2VuIjoiYmIyYmQ5NmU5MWMzNDY3OTg4Y2Q3ZTJkYWU5ZmVkZDciLCJhcHBfdG9rZW4iOiI1ZjgzYzg5NzYzYzU0NDRiOGNiZDRlMGU0Njg4ZjA4ZCIsImlzX2FwcF91c2VyIjoiMCIsInhfY2xpZW50X2RhdGEiOiJ0ZXN0IiwiZXhwIjoxNTU5NjM4NDA1LCJpc3MiOiJodHRwczovL2xvY2FsaG9zdDo1MDAxIiwiYXVkIjoiOTJlNDYyMTRkNzFlNDM2MmFiNDhiMWNjNzJjYzFkMzYifQ.UqCiN2NDBI_HIi88W47iKtSuyCU_fBD_-EchPqXqtgU";

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
                        CURLOPT_POSTFIELDS => json_encode($userInfo) ,
                        CURLOPT_HTTPHEADER => array(
                            "accept: */*",
                            "accept-language: en-US,en;q=0.8",
                            "content-type: application/json",
                            "x_client_data: test",
                            $authorization
                        ) ,
                    ));
                    $curlresponse = curl_exec($curl);
                    $err = curl_error($curl);

                    curl_close($curl);

                    if ($err)
                    {
                        return "cURL Error #:" . $err;
                    }
                    else
                    {
                        $json = json_decode($curlresponse, true);
                        $json_data = $json['data'];
                        echo "ok";
                        //print_r($json_data);
                        //print_r($json_data);
                        //print_r($json_data[$key]['app_usr_id']);
                        
                    }
                }
            }
            else
            {
                echo "no";
            }

        }
        $key++;
    }

    public function decryptPass(Request $request, $pass)
    {
        $password = decrypt($pass);
        return self::touser($password, true);
    }

}

/*if($request->ajax()){

   return response()->json(['data' => ['ajax'],'status' => 'ok']);
}

return response()->json(['data' => ['http'],'status' => 'ok']);

need when go live with domain

if(parse_url($request->url())['host'] !== parse_url(\Config::get('app.url'))['host'] )
{
Base::set_database_config($request->url());
}

URL::full();
URL::current();
URL::previous();


URL::to('foo/bar', $parameters, $secure);
URL::action('FooController@method', $parameters, $absolute);
URL::route('foo', $parameters, $absolute);
URL::secure('foo/bar', $parameters);
URL::asset('css/foo.css', $secure);

URL::secureAsset('css/foo.css');
URL::isValidUrl('http://example.com');
URL::getRequest();
URL::setRequest($request);
URL::getGenerator();
URL::setGenerator($generator);


 public function __construct()
    {

        $this->role_info = Base::getRole();
        $this->admin = false;
        $this->role = Base::guest();
        $this->emp_id = null;

        if(is_array($this->role_info))
        {
        $this->emp_id = $this->role_info[1];
        $this->role = $this->role_info[0];

        if($this->role  == Base::super_admin())
        {
        $this->admin = true;
        }

       }
      else
       {
        self::app_unauthorized();
       }
    }

    if(self::get_token() !== false)
{
$auth_user = ApiAuth::where('auth_key','=', self::get_token())->toSql();
}

      if(\DB::connection()->getDatabaseName())
   {
     echo "connected successfully to database ".\DB::connection()->getDatabaseName();
   }*/

