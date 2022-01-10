<?php
namespace App\Http\Controllers;

use App\Models\CarbageHistory;
use App\Models\EmpSchedule as allocation;
use App\Models\ScheduleTaskStatus as TaskStatus;
use App\Models\TravelHistory as api;
use App\Models\EmpCustSchedule as task;

use App\Models\SnapData as snapdata;
use App\Jobs\roadapi;
use App\Models\TravelHistory as history;
use Illuminate\Http\Request;
use Toin0u\Geotools\Facade\Geotools;
use Validator;
use Session;
use Illuminate\Pagination\LengthAwarePaginator ;
use Illuminate\Support\Facades\Paginator;
// use Illuminate\Pagination\Paginator;
use App\Models\distance;
use DateTime;
use App\Models\User;
use DB;

class LocationApi extends Controller
{
    public function store(Request $request){

        $rules = [
            'lat'               => 'required',
            'lng'               => 'required',
            'timestamp'         => 'required',
            'compass_direction' => 'required',
        ];
        $data      = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
        
        if ($data['lat'] == '0.000' || $data['lng'] == '0.000') {
            return Base::touser('Invalid Lat/Long values');
        }

        /*store lat lng*/
        $user = User::find($this->emp_id);
        $user->employee_lat = $data['lat'];
        $user->employee_lng = $data['lng'];
        $user->save();

        //To Delete old Travel data
        $date = new DateTime;
        $date->modify('-30 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');
//        $del_old_api = api::where([['created_at','<=',$formatted_date]])->count();
        
//        if($del_old_api>0){
//            $del_old_api= api::where([['created_at','<=',$formatted_date]])->delete();
//        }


        $task_status = TaskStatus::where('emp_id',$this->emp_id)
        ->whereIn('status', ['Allocated', 'In-Progress', 'Started Ride', 'In Supplier Place', 'Products Picked up'])
        //->whereDate('created_at', '=', date('Y-m-d'))->orderBy('id','DESC')
        ->count();
        #$task_status=$task_status->toArray();

        if($task_status>0){
            $api                    = new CarbageHistory();
            $api->user_id           = $this->emp_id;
            $api->accuracy          = isset($data['accuracy']) ? $data['accuracy'] : null;
            $api->speed             = isset($data['speed']) ? $data['speed'] : null;
            $api->bearing           = isset($data['bearing']) ? $data['bearing'] : null;
            $api->compass_direction = $data['compass_direction'];
            $api->lng               = $data['lng'];
            $api->lat               = $data['lat'];
            $api->battery_status    = isset($data['battery_status']) ? $data['battery_status'] : null;
            $api->IS_MOVING         = isset($data['S_MOVING']) ? $data['S_MOVING'] : null;
            $api->MOVING_DATA         = isset($data['MOVING_DATA']) ? $data['MOVING_DATA'] : null;
            $api->timestamp = Base::tomysqldatetime($data['timestamp']);
            $api->activity  = isset($data['activity']) ? $data['activity'] : null;
            $api->order_id  = isset($data["order_id"]) ? $data["order_id"] : null;
            $api->save();

            if (isset($data['S_MOVING'])) {
                if($data['S_MOVING'] == "STILL") {
                    return Base::touser([], true);
                }
            }

            $ch = false;

            if (($data['activity'] == 'Start') || ($data['activity'] == 'Stop')){
                $last = api::where('user_id', $this->emp_id)
                    ->get()->last();
                if($last) {
                    if ($last->activity == 'Monitor') {
                        if($data['activity'] != 'Stop') {
                            $ch             = true;
                            $last->activity = "Stop";
                            $last->save();
                        }
                    }
                    if ($last->activity == 'Start') {
                        $api                    = new api;
                        $api->user_id           = $this->emp_id;
                        $api->accuracy          = $last->accuracy;
                        $api->speed             = $last->speed;
                        $api->bearing           = $last->bearing;
                        $api->lng               = $last->lng;
                        $api->lat               = $last->lat;
                        $api->compass_direction = $last->compass_direction;
                        $api->timestamp         = $last->timestamp;
                        $api->activity          = 'Monitor';
                        $api->order_id          = $last->order_id;
                        $api->save();
                    }
                }
            }

            $insert = false;
            $last   = api::where('user_id', $this->emp_id)
                ->get()->last();
            if ($last) {
                if ($last->activity == 'Stop' && ($data['activity'] == 'Monitor' || $data['activity'] == 'Stop') && ($ch == false)) {
                    //  $insert = true;

                }
                if ($last->activity == 'Monitor') {
                    if ($data['activity'] == 'Start') {
                        $prelast = api::where('user_id', $this->emp_id)->get()->last();
                        $prelast->activity == 'Stop';
                        $prelast->update();
                    }
                }
            } else {
                if ($data['activity'] == 'Monitor' || $data['activity'] == 'Stop') {

                    // $insert = true;

                }
            }


            if ($last) {
                if ($last->activity == 'Monitor') {
                    $datetime1 = date_create($last->timestamp);
                    $datetime2 = date_create(Base::tomysqldatetime($data['timestamp']));
                    $interval  = date_diff($datetime1, $datetime2);
                    $time      = $interval->format("%i");
                    if ($time > 15) {
                        $last->activity = "Stop";
                        $last->save();
                        $data['activity'] = "Start";

                    }
                    // print_r($interval);
                }
                if ($last->activity == 'Start') {
                    if ($data['activity'] == "Stop") {
                        $history = new history();
                        $history = $last->replicate();
                        $history->save();
                        $last->delete();
                        $api                    = new history();
                        $api->user_id           = $this->emp_id;
                        $api->accuracy          = isset($data['accuracy']) ? $data['accuracy'] : null;
                        $api->speed             = isset($data['speed']) ? $data['speed'] : null;
                        $api->bearing           = isset($data['bearing']) ? $data['bearing'] : null;
                        $api->compass_direction = $data['compass_direction'];
                        $api->lng               = $data['lng'];
                        $api->lat               = $data['lat'];
                        $api->battery_status    = isset($data['battery_status']) ? $data['battery_status'] : null;

                        $api->timestamp = Base::tomysqldatetime($data['timestamp']);
                        $api->activity  = isset($data['activity']) ? $data['activity'] : null;
                        $api->order_id  = isset($data["order_id"]) ? $data["order_id"] : null;
                        $api->save();
                        $insert = true;
                    } elseif ($data['activity'] == "Start") {
                        $history = new history();
                        $history = $last->replicate();
                        $history->save();
                        $last->delete();
                        $data['activity'] = "Start";
                    }
                }
            }

            if ($insert == false) {
                $api                    = new api;
                $api->user_id           = $this->emp_id;
                $api->accuracy          = isset($data['accuracy']) ? $data['accuracy'] : null;
                $api->speed             = isset($data['speed']) ? $data['speed'] : null;
                $api->bearing           = isset($data['bearing']) ? $data['bearing'] : null;
                $api->compass_direction = $data['compass_direction'];
                $api->lng               = $data['lng'];
                $api->lat               = $data['lat'];
                $api->battery_status    = isset($data['battery_status']) ? $data['battery_status'] : null;

                $api->timestamp = Base::tomysqldatetime($data['timestamp']);
                $api->activity  = isset($data['activity']) ? $data['activity'] : null;
                $api->order_id  = isset($data["order_id"]) ? $data["order_id"] : null;
                $api->save();

                event(new \App\Events\LocationUpdate($api, $this->emp_id));

            }

            $array = api::orderBy('timestamp', 'asc')->with('user')->where('user_id', '=', $this->emp_id)->where('is_snapped','=',0)->limit(50)->count();
            // print_r(count($array));
            if(($array)>10){
                roadapi::dispatch($this->emp_id);
                // self::snap_data($this->emp_id);
            }


        }else{
            $date = new DateTime;
            $date->modify('-5 minutes');
            $formatted_date = $date->format('Y-m-d H:i:s');
            $del_old_api  = api::where([['IS_MOVING','=',1],['user_id','=',$this->emp_id],['created_at','<=',$formatted_date]])->count();
            
            if($del_old_api>0)
            {
                $del_old_api  = api::where([['IS_MOVING','=',1],['user_id','=',$this->emp_id],['created_at','<=',$formatted_date]])->delete();
            }

            $api                    = new api;
            $api->user_id           = $this->emp_id;
            $api->accuracy          = isset($data['accuracy']) ? $data['accuracy'] : null;
            $api->speed             = isset($data['speed']) ? $data['speed'] : null;
            $api->bearing           = isset($data['bearing']) ? $data['bearing'] : null;
            $api->compass_direction = $data['compass_direction'];
            $api->lng               = $data['lng'];
            $api->lat               = $data['lat'];
            $api->battery_status    = isset($data['battery_status']) ? $data['battery_status'] : null;
            $api->IS_MOVING = 1;
            $api->is_snapped=1;
            $api->timestamp = Base::tomysqldatetime($data['timestamp']);
            $api->activity  = isset($data['activity']) ? $data['activity'] : null;
            $api->order_id  = isset($data["order_id"]) ? $data["order_id"] : null;
            $api->save();

            event(new \App\Events\LocationUpdate($api, $this->emp_id));
        }

        return Base::touser([], true);
    }

    public function GPSDataClean()
    {

        // to clear the start and stop with out monitoring
        $checkold = api::where('user_id', $this->emp_id)
            ->orderBy('id', 'desc')
            ->take(2)->get();

        $st = false;
        if (count($checkold) == 2) {

            if ($checkold[0]['activity'] == 'Stop' && $checkold[1]['activity'] == 'Start') {
                $checkold = api::where('user_id', $this->emp_id)
                    ->orderBy('id', 'desc')
                    ->where('activity', '<>', 'Monitor')
                    ->take(2)->delete();
                $st = true;
            }
        }

        if ($st) {
            self::GPSDataClean();
        }

        // to clear the start and stop with out monitoring
        $checkold = snapdata::where('user_id', $this->emp_id)
            ->orderBy('id', 'desc')
            ->take(2)->get();

        $st = false;
        if (count($checkold) == 2) {

            if ($checkold[0]['activity'] == 'Stop' && $checkold[1]['activity'] == 'Start') {
                $checkold = snapdata::where('user_id', $this->emp_id)
                    ->orderBy('id', 'desc')
                    ->where('activity', '<>', 'Monitor')
                    ->take(2)->delete();
                $st = true;
            }
        }

        if ($st) {
            self::GPSDataClean();
        }

        return;

    }

    public function TravelClear()
    {
        \App\Models\TravelHistory::where('user_id', $this->emp_id)->delete();
        \App\Models\SnapData::where('user_id', $this->emp_id)->delete();

        return 'User Travel Cleared :)';
    }

    public function get($id)
    {
        
        roadapi::dispatch($id);

        $array = snapdata::orderBy('timestamp', 'desc')->with('user')->where('user_id', '=', $id)->first();

        $array['timestamp'] = Base::time_elapsed_string($array['timestamp']);

        return Base::touser($array, true);
    }

    public function locationbulk(Request $request)
    {

        $data = $request->input('data');
        $date = new DateTime;
        $date->modify('-1 day');
        $formatted_date = $date->format('Y-m-d H:i:s');

        foreach ($data as $key => $value) {
            // print_r($data[$key]['timestamp']);
            $timestamp=Base::tomysqldatetime($data[$key]['timestamp']);

            if($timestamp<  $formatted_date)
            {
                // print_r("old_data");
                return Base::touser([], true);
            }
            else
            {
            $api                 = new api;
            $api->user_id        = $this->emp_id;
            $api->accuracy       = isset($data[$key]['accuracy']) ? $data[$key]['accuracy'] : null;
            $api->speed          = isset($data[$key]['speed']) ? $data[$key]['speed'] : null;
            $api->bearing        = isset($data[$key]['bearing']) ? $data[$key]['bearing'] : null;
            $api->battery_status = isset($data[$key]['battery_status']) ? $data[$key]['battery_status'] : null;
            $api->lng            = $data[$key]['lng'];
            $api->lat            = $data[$key]['lat'];
            $api->timestamp      = Base::tomysqldatetime($data[$key]['timestamp']);
            $api->activity       = isset($data[$key]['activity']) ? $data[$key]['activity'] : null;
            $api->is_offline    =   1;
            $api->order_id      = isset($data[$key]["order_id"]) ? $data[$key]["order_id"] : null;
            $api->save();
            }
            

        }
        self::GPSDataClean();
        $array = api::orderBy('timestamp', 'asc')->with('user')->where('user_id', '=', $this->emp_id)->where('is_snapped','=',0)->limit(50)->count();
        // print_r(count($array));
        if(($array)>40)
        {
            // roadapi::dispatch($this->emp_id);
            // self::snap_data($this->emp_id);
        }

        
        return Base::touser([], true);
    }

    public function emp_getoffline(Request $request){
        return Base::touser([], true);
        if($this->manager){
            $res = array();
            $belongsemp = Base::getOfflineEmpBelongsUser($this->emp_id);
            foreach ($belongsemp as $key => $value) {
                $l = Base::getSnap($value->user_id);
                $new = [
                    "def_lat" => !empty($l['lat'])?$l['lat'] : 0,
                    "def_lng" => !empty($l['lng'])?$l['lng'] : 0
                ];
                $r = array_merge($value,$new);
                array_push($res, $r);
            }
            return Base::touser($res, true);
        }
    }

    public function emp_getNotLoggedUser(Request $request){
        if($this->manager){
            $res = array();
            $belongsemp = Base::getNotYetLoggeduser($this->emp_id);
            
            foreach ($belongsemp as $key => $value) {
                $address = str_replace(" ", "+", $value['street']);

               $l = Base::get_lat_long($this->emp_id);
                $new = [
                    "def_lat" => !empty($l['lat'])?$l['lat'] : 'London',
                    "def_lng" => !empty($l['lng'])?$l['lng'] : 'London'
                ];
                $r = array_merge($value,$new);
                array_push($res, $r);
            }
            return Base::touser($res, true);
        }
    }

    public function location_online_emp_status(Request $request){
        $array = array();
        if($this->manager){
            $belongsemp = Base::getEmpBelongsUsers($this->emp_id);
            $get_lng = Base::get_lat_long($this->emp_id);
            
            foreach ($belongsemp as $key => $value) {
               $check = snapdata::where('user_id',$value->user_id)
                        ->orderBy('timestamp','desc')
                        ->count();
                if($check > 0){
                    $user = snapdata::where('user_id',$value->user_id)
                        ->orderBy('timestamp','desc')
                        ->first();
                    //check garbage for current driver location
                    if(count(CarbageHistory::where('user_id',$value->user_id)->orderBy('timestamp','DESC'))>0)
                    {
                        $garbage = CarbageHistory::where('user_id',$value->user_id)->orderBy('timestamp','DESC')->first()->toArray();
                        if($garbage['timestamp']>$user['timestamp'])
                        {
                        $user['lat'] = $garbage['lat'];
                        $user['lng'] = $garbage['lng'];
                        }                        
                    }
                    // end code
                    if($this->emp_id == $value->user_id){
                        $is_logged = true;
                    }else{
                        $is_logged = false;
                    }

                    $get =[
                        "user_id" => $value->user_id,
                        "lat" => isset($value->employee_lat)?$value->employee_lat:$user['lat'],
                        "lng" => isset($value->employee_lng)?$value->employee_lng:$user['lng'],
                        "is_logged" => $is_logged,
                        "is_active" => $value->is_active,
                        "first_name" => $value->first_name,
                        "last_name" => $value->last_name,
                        "profile_image" => $value->profile_image,
                        "timestamp" => $user['timestamp'],
                        "device_status" => $value->device_login_status
                    ];
                    array_push($array, $get);
                }else{

                    if($this->emp_id == $value->user_id){
                        $is_logged = true;
                    }else{
                        $is_logged = false;
                    }
                    $get =[
                        "user_id" => $value->user_id,
                        "lat" => isset($value->employee_lat)?$value->employee_lat:$get_lng['lat'],
                        "lng" => isset($value->employee_lng)?$value->employee_lng:$get_lng['lng'],
                        "is_logged" => $is_logged,
                        "is_active" => $value->is_active,
                        "first_name" => $value->first_name,
                        "last_name" => $value->last_name,
                        "profile_image" => $value->profile_image,
                        "timestamp" => $value->created_at,
                        "device_status" => $value->device_login_status
                    ];
                    array_push($array, $get);
                }
            }
            return Base::touser($array, true);
        }
    }

    public function emp_getonline(Request $request)
    {

        if ($this->manager || $this->role == "sub_manager") {
            $array = [];
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);
            
            // print_r(count($belongsemp));
            
            /*$array = snapdata::with('user')
                ->whereIn('user_id', $belongsemp)
                ->orderBy('timestamp', 'desc')
                ->limit(count($belongsemp))
                ->get()
               ->unique('user_id');*/
               foreach($belongsemp as $key => $value){
                    $check = snapdata::where('user_id',$value)
                        ->orderBy('timestamp','desc')
                        ->count();
                    if($check > 0){
                        $users = snapdata::where('user_id',$value)
                            ->orderBy('timestamp','desc')
                            ->first();
                    }
                    $user = User::where('user_id',$value)->get()->first();
                    $timestamp = isset($user["updated_at"]) ? $user["updated_at"] : $users->created_at;
                    $ago = Base::timeAgo($timestamp);
                    $user["activated_on"] = $ago;
                    $array[] = ["lat" => isset($user["employee_lat"])?$user["employee_lat"]:$user["company_lat"],"lng"=>isset($user["employee_lng"])?$user["employee_lng"]:$user["company_lng"],"user"=>$user,"user_id"=>$value,"timestamp"=> $timestamp];

               }

        } else {
            // print_r("no");
            $array = snapdata::with('user')->orderBy('timestamp', 'desc')->limit(10)->get()->unique('user_id');
        }

        return Base::touser($array, true);
    }

    public function customer_report(Request $request){
        $data = $request->input('data');
        if((null !== $data['start_date']) && (null !== $data['end_date'])){
            $start      = Base::tomysqldate($data['start_date']);
            $end        = Base::tomysqldate($data['end_date']);
            $start_time = Base::tomysqldate($data['start_date']) . ' 00:00:0';
            $end_time   = Base::tomysqldate($data['end_date']) . ' 23:59:00';
            if($data['emp_id'] == "all"){
                if ($this->manager) {

                    $belongsemp = Base::getEmpBelongsCustomers($this->emp_id);
                    $users = [];
                    foreach ($belongsemp as $key => $value) {
                        $count = task::where('schedule_date_time','<=',$end)
                            ->where('schedule_date_time','>=',$start)
                            ->where('cust_id','=',$value)
                            ->with('filter_cust')->count();

                        if($count > 0){
                            $users[] = task::where('schedule_date_time','<=',$end)
                                ->where('schedule_date_time','>=',$start)
                                ->where('cust_id','=',$value)
                                ->with('filter_cust')->get();  
                            // array_push($users, $task);
                        }  
                    }

                    return Base::touser($users, true);
                }
            }else{

                $task[] = task::where('schedule_date_time','<=',$end)
                            ->where('schedule_date_time','>=',$start)
                            ->where('cust_id','=',$data['emp_id'])
                            ->with('filter_cust')->get();

                return Base::touser($task, true);
            }

        }else {
            Base::touser([], false);
        }
    }

    public function emp_filter(Request $request)
    {
        $data = $request->input('data');
        // dd('ddd');
        if ((null !== $data['start_date']) && (null !== $data['end_date'])) {

            $Allocated   = [];
            $InProgress  = [];
            $Incomplete  = [];
            $Delivered   = [];
            $Canceled    = [];
            $Unallocated = [];
            $dataBag     = [];

            $start      = Base::tomysqldate($data['start_date']);
            $end        = Base::tomysqldate($data['end_date']);
            $start_time = Base::tomysqldate($data['start_date']) . ' 00:00:0';
            $end_time   = Base::tomysqldate($data['end_date']) . ' 23:59:00';

            if ($data['emp_id'] == 'all') {

                if ($this->manager) {

                    $belongsemp = Base::getEmpBelongsUser($this->emp_id);
                    $mgr =DB::table('emp_mapping')
                        //->where('user.is_delete','=', 'false')
                        ->where('is_delete', '=', false)
                        ->where('emp_id', '=', null)
                        //->where('emp_mapping.emp_id','!=',$this->emp_id)
                        ->where('admin_id', '=', $this->emp_id)
                        ->get();
                    $added_by_arr[] = $this->emp_id;
                    foreach($mgr as $val){
                        array_push($added_by_arr, $val->manager_id);
                        foreach(Base::getEmpBelongsCustomers($val->manager_id) as $val1){
                            array_push($belongsemp, $val1);
                        }
                    }

                    $tasks = allocation::whereIn('emp', $belongsemp)
                        ->whereIn('add_by', $added_by_arr)
                        ->wherehas('task', function ($q) use ($start, $end) {
                            $q->where(\DB::raw("date(schedule_date_time)"), '<=', $end)->
                                where(\DB::raw("date(schedule_date_time)"), '>=', $start);
                        })
                        ->with('task')->get();
                    // $tasks = allocation::whereIn('add_by', $added_by_arr)
                    //     ->wherehas('task', function ($q) use ($start, $end) {
                    //         $q->where(\DB::raw("date(schedule_date_time)"), '<=', $end)->
                    //             where(\DB::raw("date(schedule_date_time)"), '>=', $start);
                    //     })
                    //     ->with('task')->get();
                } else {

                    $tasks = allocation::wherehas('task', function ($q) use ($start, $end) {
                        $q->where(\DB::raw("date(schedule_date_time)"), '<=', $end)->
                            where(\DB::raw("date(schedule_date_time)"), '>=', $start);
                    })
                        ->with('task')->get();
                }

                $user = [];
            } else {

                $mgr =DB::table('emp_mapping')
                        //->where('user.is_delete','=', 'false')
                        ->where('is_delete', '=', false)
                        ->where('emp_id', '=', null)
                        //->where('emp_mapping.emp_id','!=',$this->emp_id)
                        ->where('admin_id', '=', $this->emp_id)
                        ->get();
                    $added_by_arr[] = $this->emp_id;
                    foreach($mgr as $val){
                        array_push($added_by_arr, $val->manager_id);
                    }

                $tasks = allocation::where('emp', $data['emp_id'])->whereIn('add_by', $added_by_arr)
                    ->wherehas('task', function ($q) use ($start, $end) {
                        $q->where(\DB::raw("date(schedule_date_time)"), '<=', $end)->
                            where(\DB::raw("date(schedule_date_time)"), '>=', $start);
                    })
                    ->with('task')->get();

            }

            // $geo = snapdata::orderBy('timestamp', 'asc')
            //     ->where('user_id', '=', $data['emp_id'])
            //     ->where('timestamp', '<=', $end_time)
            //     ->where('timestamp', '>=', $start_time)
            //     ->get(["id", "lat", "lng", "activity", "timestamp", "battery_status"])->toArray();

            $geo = DB::table('snapped_data')
                ->select(["snapped_data.id", "snapped_data.lat", "snapped_data.lng", "snapped_data.activity", "snapped_data.timestamp", "snapped_data.battery_status"])
                ->join('orders','orders.id','=','snapped_data.order_id')
                ->where('snapped_data.user_id', '=', $data['emp_id'])
                ->where('orders.added_by', '=', $this->emp_id)
                ->where('snapped_data.timestamp', '<=', $end_time)
                ->where('snapped_data.timestamp', '>=', $start_time)
                ->get()->toArray();

            $discount = 0;
            // return $tasks;
            foreach ($tasks as $key => $data) {
                $orders = User::where('user_id','=',$data['emp'])->get()->pluck('profile_image');
                // dd($orders);
                $data['images']=$orders[0];

                if ($data['task']['status'] == "Allocated") {
                    $Allocated[$discount]        = $data;
                    $Allocated[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Started Ride") {
                    $InProgress[$discount]        = $data;
                    $InProgress[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "In Supplier Place") {
                    $Incomplete[$discount]        = $data;
                    $Incomplete[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Delivered") {
                    $Delivered[$discount]        = $data;
                    $Delivered[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Products Picked up") {
                    $Canceled[$discount]        = $data;
                    $Canceled[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Declined") {
                    $Canceled[$discount]        = $data;
                    $Canceled[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Unallocated") {
                    $Unallocated[$discount]        = $data;
                    $Unallocated[$discount]['imp'] = '123';
                } elseif ($data['task']['status'] == "Delivered back") {
                    $Unallocated[$discount]        = $data;
                    $Unallocated[$discount]['imp'] = '123';
                } else {

                }
                $discount++;

            }

            $dataBag = array_merge($Allocated,
                $InProgress,
                $Incomplete,
                $Delivered,
                $Canceled,
                $Unallocated);
            $data                          = [];
            $data['visits']['total']       = count($dataBag);
            $data['visits']['allocated']   = count($Allocated);
            $data['visits']['in-progress'] = count($InProgress);
            $data['visits']['incomplete']  = count($Incomplete);
            $data['visits']['canceled']    = count($Canceled);
            $data['visits']['unallocated'] = count($Unallocated);
            $data['visits']['delivered']   = count($Delivered);
            $data['visit_list']            = $dataBag;

            $data['geo'] = isset($geo) ? $geo : [];

            //print $distInMeter;
            return Base::touser($data, true);

        } else {
            Base::touser([], false);
        }
    }

    public function current_emp_filter(Request $request)
    {   if ($request->input('emp')) {
                $emp = $request->input('emp');
            } else {
                $emp = $this->emp_id;
            }
        roadapi::dispatch($emp);
        if ($request->input('date')) {

            $data  = $request->input('date');
            $start = Base::tomysqldate($data) . ' 00:00:00';
            $end   = Base::tomysqldate($data) . ' 23:59:00';
        } elseif ($request->input('start') && $request->input('end')) {

            $start = $request->input('start');
            $end   = $request->input('end');
            $start = Base::tomysqldate($start) . ' 00:00:00';
            $end   = Base::tomysqldate($end) . ' 23:59:00';
        } else {
            return Base::touser('Invalid Parameters');
        }

        if ((null !== $start) && (null !== $end)) {

            $start = Base::tomysqldatetime($start);
            $end   = Base::tomysqldatetime($end);

            if ($request->input('emp')) {
                $emp = $request->input('emp');
            } else {
                $emp = $this->emp_id;
            }

            $distInMeter = 0;

            $array = snapdata::orderBy('timestamp', 'asc')->
                where('user_id', $emp)->
                where('timestamp', '<=', $end)->
                where('timestamp', '>=', $start)
                ->limit(80000)
                ->get()->toArray();
            // print_r($array);
            $distance   = [];
            $distance[] = 0;
            for ($x = 0; $x < count($array) - 1; $x++) {

                if (($array[$x]['activity'] == 'Start')) {
                    $distance[0] = $distance[count($distance) - 1];
                    $distance[] = 0;
                } else {
                    $data1                 = $array[$x];
                    $data2                 = $array[$x + 1];
                    $array[$x]['path']     = [$data1['lat'], $data1['lng']];
                    $array[$x + 1]['path'] = [$data2['lat'], $data2['lng']];
                    $coordA                = Geotools::coordinate($array[$x]['path']);
                    $coordB                = Geotools::coordinate($array[$x + 1]['path']);
                    $dist                  = Geotools::distance()->setFrom($coordA)->setTo($coordB);
//                       $distance  = round($dist->in('km')->haversine(), 2) + $distance;

                    $distance[count($distance) - 1] = round($dist->in('km')->haversine(), 2) + $distance[count($distance) - 1];

                }

            }

            $distance = array_sum($distance);

            $start      = false;
            $end        = false;
            $time_taken = 'No Data';
            if (count($array) > 1) {
                if ($array[0]['timestamp']) {
                    $start = $array[0]['timestamp'];
                }

                if ($array[count($array) - 1]['timestamp']) {
                    $end = $array[count($array) - 1]['timestamp'];
                }

                if (($start) && ($end)) {

                    $time_taken = Base::time_elapsed_string($end, true, $start);

                    if (empty($time_taken)) {
                        $time_taken = '1 min';
                    }
                }
            }

            $data               = [];
            $data['geoData']    = $array;
            $data['distance']   = round($distance, 2) . ' Kms';
            $data['time_taken'] = $time_taken;

            return Base::touser($data, true);
        }
    }
    public function current_emp_filterweb(Request $request)
    {
        if ($request->input('emp')) {
                $emp = $request->input('emp');
            } else {
                $emp = $this->emp_id;
            }
        roadapi::dispatch($emp);

        if ($request->input('date')) {

            $data  = $request->input('date');
            $start = Base::tomysqldate($data);
            $end   = Base::tomysqldate($data);
        } elseif ($request->input('start') && $request->input('end')) {

            $start = $request->input('start');
            $end   = $request->input('end');
            $start = Base::tomysqldate($start) . ' 00:00:00';
            $end   = Base::tomysqldate($end) . ' 23:59:00';
        } else {
            return Base::touser('Invalid Parameters');
        }

        if ((null !== $start) && (null !== $end)) {

            $start = Base::tomysqldatetime($start);
            $end   = Base::tomysqldatetime($end);

            if ($request->input('emp')) {
                $emp = $request->input('emp');
            } else {
                $emp = $this->emp_id;
            }

            $distInMeter = 0;

           
            $query = distance::query();
            if($emp!= 0){
                    // $emp=$request->input('emp');
                   $query->where('emp_id',$emp)
                         ->where('start_time', '<=',$end)
                         ->where('start_time','>=',$start);
                         $task_data = $query->get();
                   
            }else {
                   $query->where('start_time', '<=',$end)
                         ->where('start_time','>=',$start);
                        $task_data = $query->get();
                }
                  

                

             $array = snapdata::orderBy('timestamp', 'asc')->
                where('user_id', $emp)->
                where('timestamp', '<=', $end)->
                where('timestamp', '>=', $start)
                ->limit(80000)
                ->get()->toArray();


            // $limit = 10;
            // $array = snapdata::orderBy('timestamp', 'asc')->
            //     where('user_id', $emp)->
            //     where('timestamp', '<=', $end)->
            //     where('timestamp', '>=', $start)
            //     ->take($limit)
            //     ->get();   
            // $array = Paginator::make($array->all(), $array->count(), $limit);
            // // $array = $array['data'];
            // print_r($array);
            // die();
            $distance   = [];
            $distance[] = 0;

            for ($x = 0; $x < count($array) - 1; $x++) {

                if (($array[$x]['activity'] == 'Start')) {
                    $distance[0] = $distance[count($distance) - 1];
                    $distance[] = 0;
                } else {
                    $data1                 = $array[$x];
                    $data2                 = $array[$x + 1];
                    $array[$x]['path']     = [$data1['lat'], $data1['lng']];
                    $array[$x + 1]['path'] = [$data2['lat'], $data2['lng']];
                    $coordA                = Geotools::coordinate($array[$x]['path']);
                    $coordB                = Geotools::coordinate($array[$x + 1]['path']);
                    $dist                  = Geotools::distance()->setFrom($coordA)->setTo($coordB);
//                       $distance  = round($dist->in('km')->haversine(), 2) + $distance;

                $distance[count($distance) - 1] = round($dist->in('km')->haversine(), 2) + $distance[count($distance) - 1];
//              print_r($distance);
                }

            }
//print_r($distance);
            $distance = array_sum($distance);

            $start      = false;
            $end        = false;
            $time_taken = 'No Data';
            if (count($array) > 1) {
                if ($array[0]['timestamp']) {
                    $start = $array[0]['timestamp'];
                }

                if ($array[count($array) - 1]['timestamp']) {
                    $end = $array[count($array) - 1]['timestamp'];
                }

                if (($start) && ($end)) {

                    $time_taken = Base::time_elapsed_string($end, true, $start);

                    if (empty($time_taken)) {
                        $time_taken = '1 min';
                    }
                }
            }

           

            $data               = [];
            $data['geoData']    = $array;
            $data['distance']   = round($distance, 2) . ' Kms';
            $data['time_taken'] = $time_taken;
            $data['task_data'] = $task_data;

            return Base::touser($data, true);
        }
    }

    public function emp(Request $request, $id)
    {
        $array = [];
        $array = snapdata::orderBy('timestamp', 'desc')->with('user')->where('user_id', '=', $id)->get()->limit(80000)->toArray();

        foreach ($array as $i => $value) {
            $array[$i]['timestamp'] = Base::time_elapsed_string($array[$i]['timestamp']);

            $array[$i]['user']['profile_image'] = (Array) json_decode(stripslashes($array[$i]['user']['profile_image']));
        }

        return Base::touser($array, true);
    }


    public function snap_data($id)
    {
        
        // print_r($id);
        if($id){
            $id=$id;
        }
        else{ $id=$this->emp_id;}
       
        $array = api::orderBy('timestamp', 'asc')->with('user')->where('user_id', '=', $id)->where('is_snapped','=',0)->limit(800)->get();
        // print_r(count($array));
        
$result = json_decode($array,true);

$result =array_chunk($result,100);
// $result = json_encode($result);


foreach ($result as $mainkey => $data) {

   // print_r($mainkey);
    $loc =array();
    $lat_lng=array();
    $import_lat=[];
    $data_pass = [];
    
    foreach ($data as $location) {

        $api = api::where('id',$location['id'])->first();
        $api->is_snapped = 1;
        $api->save();

        $import_lat=[];
        array_push($import_lat,$location['lat'],$location['lng']);

        $lat_lng = implode(",",$import_lat);

        $locs = array_push($loc, $lat_lng);
    }
        // print_r($original_data);
         array_push($data_pass,join('|',$loc));
            # code...
        $datas = join('|',$data_pass);

        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => "https://roads.googleapis.com/v1/snapToRoads?path=".$datas."&interpolate=true&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk",
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array(
                'field1' => 'some date',
                'field2' => 'some other data',
            )
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        curl_close($ch);
        // print_r($result);
        $result = json_decode($result,true);
        foreach ($result as $key => $value) {
            
            foreach ($value as $key2 => $variable) {
                 // print_r($variable);
            // print_r($variable['originalIndex']);
                if(is_array($variable) && array_key_exists('originalIndex',$variable))
                {
                    
                    Session::put('originalIndex',$variable['originalIndex']);
                    // print_r($data[$variable['originalIndex']]['timestamp']);
                    $timestamp = $data[$variable['originalIndex']]['timestamp'];
                    $accuracy = $data[$variable['originalIndex']]['accuracy'];
                    $speed=$data[$variable['originalIndex']]['speed'];
                    $bearing=$data[$variable['originalIndex']]['bearing'];
                    $battery_status=$data[$variable['originalIndex']]['battery_status'];
                    $activity =$data[$variable['originalIndex']]['activity'];
                }
                else
                {
                    $val = Session::get('originalIndex');
                    if(($val==0)&& ($data[$val]['activity']=='Start')) 
                        {
                            $data[$val]['activity'] = 'Start';
                        } 
                    else { 
                        $val2 =$val-1;
                        if(($data[$val]['activity']=='Start')&&($data[$val2]['activity']=='Start'))
                        {
                            $data[$val]['activity'] = 'Monitor';
                        }
                    }
                    
                    // print_r($key);
                    $timestamp = $data[$val]['timestamp'];
                    $accuracy = $data[$val]['accuracy'];
                    $speed=$data[$val]['speed'];
                    $bearing=$data[$val]['bearing'];
                    $battery_status=$data[$val]['battery_status'];
                    $activity =$data[$val]['activity'];
                    // print_r($data[$key]['activity']);
                    // print_r("fsdfj");
                }
            $api                 = new snapdata;
            $api->user_id        = $id;
            $api->accuracy       = $accuracy;
            $api->speed          = $speed;
            $api->bearing        = $bearing;
            $api->battery_status = $battery_status;
            $api->lng            = $variable['location']['longitude'];
            $api->lat            = $variable['location']['latitude'];
            $api->timestamp      = Base::tomysqldatetime($timestamp);
            $api->activity       = $activity;
            $api->save();

            // print_r($api);

            // print_r($data[$variable['originalIndex']]);
           // exit();
            }
           
        }
        // print_r($data);

}
//  print_r($data_pass);

    }


}
