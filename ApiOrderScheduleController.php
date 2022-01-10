<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\Customer;
use App\Models\User;
use DB;
use Log;
use App\Models\EmpCustSchedule as task;
use App\Models\EmpSchedule as allocation;
use App\Models\ScheduleTaskStatus;
use App\Models\SnapData as snapdata;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Toin0u\Geotools\Facade\Geotools;
use Validator;
use Session;
use Carbon\Carbon;
use Mail;
use App\Models\ApiOrders;
use App\Models\ItemMap;
use App\Models\EmpMapping;
use App\Models\AutoAllocation;
use App\Models\Items;
use App\Models\UserPackage;
use App\Jobs\AutoAllocationLogic;
use App\Http\Services\IntegrationServiceFactory;
use App\Models\OrderImage;
use App\Models\PickupImage;
use GuzzleHttp;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use phpDocumentor\Reflection\Types\Null_;

class ApiOrderScheduleController extends Controller {

    public function index(Request $request) {

        $value = [];
        $new_orders = [];
        if ($this->admin || $this->backend) {
            $array = task::with('cust', 'emp_info')->orderBy('picktime', 'desc')->all()->toArray();
        } elseif ($this->manager) {

            $belongsemp = Base::getEmpBelongsUser($this->emp_id);


            $start = Base::tomysqldatetime($request->input('date') . ' 00:00:00');
            $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');

            if ($request->input('date')) {
                $emps = [$this->emp_id];


                $start = Base::tomysqldatetime($request->input('date') . ' 00:00:00');
                $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');

                $manager_ids = DB::table('emp_mapping')
                    ->where('is_active','=',1)
                    ->where('admin_id','=',$this->emp_id)
                    //->whereNotIn('user.is_delete',[true,"true"])
                    ->where('is_delete','!=',true)
                    ->pluck('manager_id')->toArray();
                $user_ids = array_merge($manager_ids, [$this->emp_id]);
                $orders = ApiOrders::where('order_start_time', '<=', $end)
                                ->where('order_start_time', '>=', $start)
                                ->whereIn('added_by', $user_ids)
                                ->get()->toArray();


                foreach ($orders as $orders_data) {

                    $array = task::where('mt_order_id', '=', $orders_data['id'])
                                    //->where('picktime', '<=', $end)
                                    //->where('picktime', '>=', $start)
                                    ->whereIn('task_status', [0, 1, 2])
                                    ->whereIn('approve_status', [0, 1, 2])
                                    ->whereIn('added_by', $user_ids)
                                    ->with('cust', 'emp_info')->orderBy('picktime', 'desc')->get()->toArray();

                    $stage = count($array);
                    for ($i = 0; $i < count($array); $i++) {
                        $getitems = ItemMap::where('order_id', $array[$i]['mt_order_id'])->where('stage', $i + 1)->with('Items')->get()->toArray();

                        if ($orders_data['delivery_logic'] == 2) {
                            $array[$stage - 1]['items'] = $getitems;
                        } else {
                            $array[$i]['items'] = $getitems;
                        }

                        $_emp_id = $array[$i]['allocated_emp_id'];
                        if ($orders_data['delivery_logic'] == 1) {
                            $array[$i]["source"] = "Delivery Address";
                        } else if ($orders_data['delivery_logic'] == 2) {
                            $array[$i]["source"] = "Pickup Address";
                        } else if ($orders_data['delivery_logic'] == 3) {
                            $array[$i]["source"] = "Delivery Address";
                        }
                        if (!empty($_emp_id)) {

                            $orders_data['allocated_emp_id'] = $_emp_id;
                            $user = User::where('user_id', '=', $_emp_id)->first();
                            $orders_data["driver_name"] = $user["first_name"];
                            $orders_data["driver_phone_no"] = $user["phone"];
                            $orders_data["allocated_emp"] = $user["first_name"];
                            $orders_data["profile_image"] = $user["profile_image"];
                            $orders_data["driver_employee_lat"] = $user["employee_lat"];
                            $orders_data["driver_employee_lng"] = $user["employee_lng"];
                        }
                        $stage--;
                    }

                    if (count($array) > 0) {
                        usort($array, array($this, 'comparator'));
                        $merge = array('order' => $array, 'order_count' => count($array));
                        $order_push = array_merge($merge, $orders_data);
                        array_push($new_orders, $order_push);
                    }
                }
            } elseif ($request->input('start') && $request->input('end')) {

                $start = Base::tomysqldate($request->input('start')) . ' 00:00:00';
                $end = Base::tomysqldate($request->input('end')) . ' 23:59:00';
                $array = task::where('picktime', '<=', $end)
                                ->where('picktime', '>=', $start)
                                ->where('added_by', $this->emp_id)
                                ->with('cust')->orderBy('picktime', 'desc')->get()->toArray();
            } else {
                $array = task::where('added_by', $this->emp_id)->with('cust')->get()->toArray();
            }
        } else {

            if ($request->input('date')) {
                $value = Base::tomysqldate($request->input('date'));
                if($this->role == "sub_manager"){
                    $array = task::where('added_by', $this->emp_id)->with('cust')->get()->toArray();
                    $start = Base::tomysqldatetime($request->input('date') . ' 00:00:00');
                    $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');


                    $orders = ApiOrders::where('order_start_time', '<=', $end)
                                    ->where('order_start_time', '>=', $start)
                                    ->where('added_by', $this->emp_id)
                                    ->get()->toArray();


                    foreach ($orders as $orders_data) {

                        $array = task::where('mt_order_id', '=', $orders_data['id'])
                                        ->where('picktime', '<=', $end)
                                        ->where('picktime', '>=', $start)
                                        ->whereIn('task_status', [0, 1, 2])
                                        ->whereIn('approve_status', [0, 1, 2])
                                        ->where('added_by', $this->emp_id)
                                        ->with('cust', 'emp_info')->orderBy('picktime', 'desc')->get()->toArray();

                        $stage = count($array);
                        for ($i = 0; $i < count($array); $i++) {
                            $getitems = ItemMap::where('order_id', $array[$i]['mt_order_id'])->where('stage', $i + 1)->with('Items')->get()->toArray();

                            if ($orders_data['delivery_logic'] == 2) {
                                $array[$stage - 1]['items'] = $getitems;
                            } else {
                                $array[$i]['items'] = $getitems;
                            }

                            $_emp_id = $array[$i]['allocated_emp_id'];
                            if ($orders_data['delivery_logic'] == 1) {
                                $array[$i]["source"] = "Delivery Address";
                            } else if ($orders_data['delivery_logic'] == 2) {
                                $array[$i]["source"] = "Pickup Address";
                            } else if ($orders_data['delivery_logic'] == 3) {
                                $array[$i]["source"] = "Delivery Address";
                            }
                            if (!empty($_emp_id)) {

                                $orders_data['allocated_emp_id'] = $_emp_id;
                                $user = User::where('user_id', '=', $_emp_id)->first();
                                $orders_data["driver_name"] = $user["first_name"];
                                $orders_data["driver_phone_no"] = $user["phone"];
                                $orders_data["allocated_emp"] = $user["first_name"];
                                $orders_data["profile_image"] = $user["profile_image"];
                                $orders_data["driver_employee_lat"] = $user["employee_lat"];
                                $orders_data["driver_employee_lng"] = $user["employee_lng"];
                            }
                            $stage--;
                        }

                        if (count($array) > 0) {
                            usort($array, array($this, 'comparator'));
                            $merge = array('order' => $array, 'order_count' => count($array));
                            $order_push = array_merge($merge, $orders_data);
                            array_push($new_orders, $order_push);
                        }
                    }

                    return Base::touser($new_orders, true);

                }
            } else {
                $value = date('Y-m-d');
            }

            $result = allocation::where('emp', $this->emp_id)
                            ->wherehas('task', function ($q) use ($value) {
                                $start = Base::tomysqldatetime($value . ' 00:00:00');
                                $end = Base::tomysqldatetime($value . ' 23:59:00');
                                $q->where('picktime', '<=', $end)
                                ->where('picktime', '>=', $start);
                            })
                            ->with('task')->get()->toArray();
            $Allocated = [];
            $InProgress = [];
            $Incomplete = [];
            $Delivered = [];

            foreach ($result as $key => $data) {

                if ($data['task']['status'] == "Allocated") {
                    $Allocated[] = $data;
                } elseif ($data['task']['status'] == "In-Progress") {
                    $InProgress[] = $data;
                } elseif ($data['task']['status'] == "Started Ride") {
                    $InProgress[] = $data;
                } elseif ($data['task']['status'] == "In Supplier Place") {
                    $InProgress[] = $data;
                } elseif ($data['task']['status'] == "Products Picked up") {
                    $InProgress[] = $data;
                } elseif ($data['task']['status'] == "In-Progress") {
                    $InProgress[] = $data;
                } elseif ($data['task']['status'] == "Incomplete") {

                    $Summary = self::gpsData($data['task']['id'], false);

                    if ($Summary == 'error') {
                        $data['task']['task_info'] = new \stdClass;
                    } else {
                        $data['task']['task_info'] = $Summary;
                    }


                    $Incomplete[] = $data;
                } elseif ($data['task']['status'] == "Delivered") {


                    $Summary = self::gpsData($data['task']['id'], false);

                    if ($Summary == 'error') {

                        $data['task']['task_info'] = new \stdClass;
                    } else {
                        $data['task']['task_info'] = $Summary;
                    }

                    $Delivered[] = $data;
                } else {
                    
                }
            }


            if ($request->input('filterStatus') == 'deliveries') {
                $dataBag = array_merge(
                        $Incomplete,
                        $Delivered);
            } else {

                $dataBag = array_merge(
                        $InProgress,
                        $Allocated,
                        $Incomplete,
                        $Delivered);
            }



            if (\Request::get('page')) {
                $perPage = 10;
                $pageStart = \Request::get('page', 1);
                $offSet = ($pageStart * $perPage) - $perPage;
                $itemsForCurrentPage = array_slice($dataBag, $offSet, $perPage);

                $paginator = new LengthAwarePaginator($itemsForCurrentPage, count($dataBag), $perPage);

                $paginator->withPath(url()->current() . '?date=' . $value);

                return $paginator;
            }

            return Base::touser($dataBag, true);
        }


        return Base::touser($new_orders, true);
    }

    function comparator($object1, $object2) {
        return $object1['id'] > $object2['id'];
    }

    function schedule_date($object1, $object2) {
        return $object1['schedule_date_time'] > $object2['schedule_date_time'];
    }

    public function deleteTask(Request $request){
        $data = $request->input('data');
        $task_id = $data["task_id"];
        $status  = $data["status"];
        \DB::beginTransaction();
        try{
            if($status == "Unallocated"){
                $task_status = ScheduleTaskStatus::where('task_id','=',$task_id)->delete();
                $task = task::where('id','=',$task_id)->delete();
            }elseif($status == "Allocated"){
                $task_status = ScheduleTaskStatus::where('task_id','=',$task_id)->delete();
                $allocation = allocation::where('task_id','=',$task_id)->delete();
                $task = task::where('id','=',$task_id)->delete();
            }
            
        }catch(Exception $e){
            \DB::rollBack();
            return Base::touser("Failed to delete",false);
        }
        \DB::commit();
        return Base::touser("successfully deleted",true);
    }

    public function driver_info(Request $request){
        $data = $request->input('data');
        $driver_id = $data["driver_id"];
        $filter_type = $data["filter_type"];

        $task_resp = array(
            'multiple_delivery' => array(),
            'multiple_pickup' => array()
        );
        if($filter_type == "All"){
            $Orders = ApiOrders::where([['emp_id','=',$driver_id], ['status','!=','Unallocated']])->with(['mt_tasks' => function ($q) {
                $q->orderBy('schedule_date_time', 'desc');
              }])->get()->toArray();
        }else{
            $Orders = ApiOrders::where([['emp_id','=',$driver_id], ['status','!=','Unallocated']])->where('delivery_logic','=',$filter_type)->with(['mt_tasks' => function ($q) {
                $q->orderBy('schedule_date_time', 'desc');
              }])->get()->toArray();
        }
        $result = [];
        $push = [];

        foreach ($Orders as $key => $mt_value) {
            foreach ($mt_value["mt_tasks"] as $key => $value) {
                if(array_key_exists("mt_order_id", $value)){
                    $result[$value["mt_order_id"]][] = $value;
                }
            }
        }

        foreach ($result as $key => $value) {
            foreach ($value as $key => $mt_value) {
                $Orders = ApiOrders::find($mt_value["mt_order_id"]);
                $order_id = $mt_value['mt_order_id'];

                if(!array_key_exists($order_id, $push)) {
                    $push[$order_id] = [];
                }

                if($Orders->delivery_logic == 1){
                    if(count($push[$order_id]) > 0){
                        $push[$order_id][0]['multiple_delivery'][] = [
                            'schedule' => $mt_value["schedule_date_time"],
                            'order_id' => $mt_value["order_id"],
                            'delivery_notes2' => "",
                            'cust_name' => $mt_value["cust_name"],
                            'cust_phone' => $mt_value["cust_phone"],
                            'cust_email' => $mt_value["cust_email"],
                            'temp_cust_email' => "",
                            'cust_address' => $mt_value["cust_address"],
                            'loc_lat' => $mt_value["cust_address"],
                            'loc_lng' => $mt_value["loc_lng"],
                            'cust_id' => "",
                            'receiver_name' => $mt_value["receiver_name"],
                            'task_id' => $mt_value["id"]
                        ];
                    }else{
                        $task_resp[$order_id]["is_multidelivery"] = $Orders->is_multidelivery;
                        $task_resp[$order_id]["is_multipickup"] = $Orders->is_multipickup;
                        $task_resp[$order_id]["status"] = $Orders->status;
                        $task_resp[$order_id]["type"]  = "0";
                        $task_resp[$order_id]["method"] = $mt_value["method"];
                        $task_resp[$order_id]["delivery_logic"] = $Orders->delivery_logic;
                        $task_resp[$order_id]["sender_name"] = $mt_value["sender_name"];
                        $task_resp[$order_id]["sender_number"] = $mt_value["sender_number"];
                        $task_resp[$order_id]["sent_address"] = $mt_value["sent_address"];
                        $task_resp[$order_id]["is_geo_fence"] = $mt_value["is_geo_fence"];
                        $task_resp[$order_id]["schedule"] = $mt_value["schedule_date_time"];
                        $task_resp[$order_id]["picktime"] = $mt_value["picktime"];
                        $task_resp[$order_id]["loc_lat"] = $mt_value["loc_lat"];
                        $task_resp[$order_id]["loc_lng"] = $mt_value["loc_lng"];
                        $task_resp[$order_id]["pickup_ladd"] = $mt_value["pickup_ladd"];
                        $task_resp[$order_id]["pickup_long"] = $mt_value["pickup_long"];
                        $task_resp[$order_id]["geo_fence_meter"] = $mt_value["geo_fence_meter"];
                        $task_resp[$order_id]["order_id"] = $Orders->id;
                        $task_resp["multiple_pickup"] = [];
                        $task_resp[$order_id]['multiple_pickup'][0] = [
                            'picktime' => $mt_value["picktime"],
                            'pick_address' => $mt_value['pick_address'],
                            'pickup_ladd' => $mt_value['pickup_ladd'],
                            'pickup_long' => $mt_value['pickup_long'],
                            'pickup_phone' => $mt_value['pickup_phone'],
                            'task_id' => $mt_value["id"]
                        ];

                        $task_resp[$order_id]['multiple_delivery'][] = [
                            'schedule' => $mt_value["schedule_date_time"],
                            'order_id' => $mt_value["order_id"],
                            'delivery_notes2' => "",
                            'cust_name' => $mt_value["cust_name"],
                            'cust_phone' => $mt_value["cust_phone"],
                            'cust_email' => $mt_value["cust_email"],
                            'temp_cust_email' => "",
                            'cust_address' => $mt_value["cust_address"],
                            'loc_lat' => $mt_value["cust_address"],
                            'loc_lng' => $mt_value["loc_lng"],
                            'cust_id' => "",
                            'receiver_name' => $mt_value["receiver_name"],
                            'task_id' => $mt_value["id"]
                        ];
                        array_push($push[$order_id],$task_resp[$order_id]);
                    }
                }elseif($Orders->delivery_logic == 2){
                    if(count($push[$order_id]) > 0){
                        $push[$order_id][0]['multiple_pickup'][] = [
                            'picktime' => $mt_value["picktime"],
                            'pick_address' => $mt_value['pick_address'],
                            'pickup_ladd' => $mt_value['pickup_ladd'],
                            'pickup_long' => $mt_value['pickup_long'],
                            'pickup_phone' => $mt_value['pickup_phone'],
                            'delivery_notes3' => '',
                            'task_id' => $mt_value["id"]
                        ];
                    }else{
                        $task_resp[$order_id]["is_multidelivery"] = $Orders->is_multidelivery;
                        $task_resp[$order_id]["is_multipickup"] = $Orders->is_multipickup;
                        $task_resp[$order_id]["status"] = $Orders->status;
                        $task_resp[$order_id]["type"]  = "0";
                        $task_resp[$order_id]["method"] = $mt_value["method"];
                        $task_resp[$order_id]["delivery_logic"] = $Orders->delivery_logic;
                        $task_resp[$order_id]["sender_name"] = $mt_value["sender_name"];
                        $task_resp[$order_id]["sender_number"] = $mt_value["sender_number"];
                        $task_resp[$order_id]["sent_address"] = $mt_value["sent_address"];
                        $task_resp[$order_id]["is_geo_fence"] = $mt_value["is_geo_fence"];
                        $task_resp[$order_id]["schedule"] = $mt_value["schedule_date_time"];
                        $task_resp[$order_id]["picktime"] = $mt_value["picktime"];
                        $task_resp[$order_id]["loc_lat"] = $mt_value["loc_lat"];
                        $task_resp[$order_id]["loc_lng"] = $mt_value["loc_lng"];
                        $task_resp[$order_id]["pickup_ladd"] = $mt_value["pickup_ladd"];
                        $task_resp[$order_id]["pickup_long"] = $mt_value["pickup_long"];
                        $task_resp[$order_id]["geo_fence_meter"] = $mt_value["geo_fence_meter"];
                        $task_resp[$order_id]["order_id"] = $Orders->id;
                        $task_resp[$order_id]['multiple_pickup'][] = [
                            'picktime' => $mt_value["picktime"],
                            'pick_address' => $mt_value['pick_address'],
                            'pickup_ladd' => $mt_value['pickup_ladd'],
                            'pickup_long' => $mt_value['pickup_long'],
                            'pickup_phone' => $mt_value['pickup_phone'],
                            'task_id' => $mt_value["id"]
                        ];

                        $task_resp[$order_id]['multiple_delivery'][0] = [
                            'schedule' => $mt_value["schedule_date_time"],
                            'order_id' => $mt_value["order_id"],
                            'delivery_notes2' => "",
                            'cust_name' => $mt_value["cust_name"],
                            'cust_phone' => $mt_value["cust_phone"],
                            'cust_email' => $mt_value["cust_email"],
                            'temp_cust_email' => "",
                            'cust_address' => $mt_value["cust_address"],
                            'loc_lat' => $mt_value["cust_address"],
                            'loc_lng' => $mt_value["loc_lng"],
                            'cust_id' => "",
                            'receiver_name' => $mt_value["receiver_name"],
                            'task_id' => $mt_value["id"]
                        ];
                        array_push($push[$order_id],$task_resp[$order_id]);
                    }
                }elseif($Orders->delivery_logic == 3){
                    $task_resp[$order_id]["is_multidelivery"] = $Orders->is_multidelivery;
                    $task_resp[$order_id]["is_multipickup"] = $Orders->is_multipickup;
                    $task_resp[$order_id]["status"] = $Orders->status;
                    $task_resp[$order_id]["type"]  = "0";
                    $task_resp[$order_id]["method"] = $mt_value["method"];
                    $task_resp[$order_id]["delivery_logic"] = $Orders->delivery_logic;
                    $task_resp[$order_id]["sender_name"] = $mt_value["sender_name"];
                    $task_resp[$order_id]["sender_number"] = $mt_value["sender_number"];
                    $task_resp[$order_id]["sent_address"] = $mt_value["sent_address"];
                    $task_resp[$order_id]["is_geo_fence"] = $mt_value["is_geo_fence"];
                    $task_resp[$order_id]["schedule"] = $mt_value["schedule_date_time"];
                    $task_resp[$order_id]["picktime"] = $mt_value["picktime"];
                    $task_resp[$order_id]["loc_lat"] = $mt_value["loc_lat"];
                    $task_resp[$order_id]["loc_lng"] = $mt_value["loc_lng"];
                    $task_resp[$order_id]["pickup_ladd"] = $mt_value["pickup_ladd"];
                    $task_resp[$order_id]["pickup_long"] = $mt_value["pickup_long"];
                    $task_resp[$order_id]["geo_fence_meter"] = $mt_value["geo_fence_meter"];
                    $task_resp[$order_id]["order_id"] = $Orders->id;
                    $task_resp[$order_id]['multiple_pickup'][0] = [
                        'picktime' => $mt_value["picktime"],
                        'pick_address' => $mt_value['pick_address'],
                        'pickup_ladd' => $mt_value['pickup_ladd'],
                        'pickup_long' => $mt_value['pickup_long'],
                        'pickup_phone' => $mt_value['pickup_phone'],
                        'task_id' => $mt_value["id"]
                    ];

                    $task_resp[$order_id]['multiple_delivery'][0] = [
                        'schedule' => $mt_value["schedule_date_time"],
                        'order_id' => $mt_value["order_id"],
                        'delivery_notes2' => "",
                        'cust_name' => $mt_value["cust_name"],
                        'cust_phone' => $mt_value["cust_phone"],
                        'cust_email' => $mt_value["cust_email"],
                        'temp_cust_email' => "",
                        'cust_address' => $mt_value["cust_address"],
                        'loc_lat' => $mt_value["cust_address"],
                        'loc_lng' => $mt_value["loc_lng"],
                        'cust_id' => "",
                        'receiver_name' => $mt_value["receiver_name"],
                        'task_id' => $mt_value["id"]
                    ];
                    array_push($push[$order_id],$task_resp[$order_id]);
                }
            }
        }
        // dd($push);
        if (count($push) > 0) {
            array_multisort(array_map(function($element) {
                return $element[0]['schedule'];
            }, $push), SORT_DESC, $push);
        }
        return Base::touser($push,  true);
        
        
    }

    public function updateTaskStatus(Request $request, $task_id) {

        $rules = [
            'status' => 'required',
            'lat' => 'required',
            'timestamps' => 'required',
            'lng' => 'required',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {

            return Base::touser($validator->errors()->all()[0]);
        }

        $task = task::find($task_id);

        if ($data['status'] == 'Declined') {

            $temp = allocation::where('task_id', $task_id)->
                            where('emp', $this->emp_id)->delete();
            $data['status'] = 'Unallocated';
        } else {
            
        }



        $task_status = new ScheduleTaskStatus();
        $task_status->emp_id = $this->emp_id;
        $task_status->task_id = $task_id;
        $task_status->address = '';
        $task_status->lat = $data['lat'];
        $task_status->long = $data['lng'];
        $task_status->status = $data['status'];
        $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->save();

        event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        return Base::touser('Status Updated', true);
    }

    public function milagereport(Request $request) {
        $data = $request->input('data');


        $tasks = task::where('order_id', $data['order_id'])->get();
        $finaldata = [];
        $indexval = 0;

        $task_id = $tasks[0]->id;
        $order_id = $data['order_id'];
        $this->gpsData($task_id, $order_id);
        $finaldata = Session::get("key");
        $indexval++;

        $data = [];
        $data['visit_list'] = $finaldata;


        return Base::touser($data, true);
    }

    public function gpsData($task_id, $order_id, $apicall = true) {

        try {
            $task = task::where('id', $task_id)->with('all_status')->first();
            if ($task) {
                $task = $task->toArray();
            }
        } catch (\Exception $e) {
            if ($apicall) {
                return Base::touser('Task not found');
            } else {
                return 'error';
            }
        }
        if (count($task) < 1) {
            if ($apicall) {
                return Base::touser('Task not found');
            } else {
                return 'error';
            }
        } else {

            if (count($task['all_status']) < 1) {
                if ($apicall) {

                    return Base::touser('Task Status not found');
                } else {
                    return 'error';
                }
            }



            $taskStatus = array_reverse($task['all_status']);

            $Progress = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Started Ride';
                }
            });
            $Place = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'In Supplier Place';
                }
            });
            $Picked = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Products Picked up';
                }
            });

            $Delivered = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {

                    return $value['status'] == 'Delivered';
                }
            });
            $Incomplete = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Incomplete';
                }
            });

            if ($Progress) {
                if ($Delivered || $Incomplete) {

                    $data = $Delivered ? $Delivered : $Incomplete;
                    if ($data['timestamps']) {
                        $end = $data['timestamps'];
                    } else {
                        $end = $data['created_at'];
                    }
                }


                if ($Progress['timestamps']) {
                    $start = $Progress['timestamps'];
                } else {
                    $start = $Progress['created_at'];
                }
                $end = isset($end) ? $end : Base::current_client_datetime();

                //$end = isset($end) ? $end : date('Y-m-d H:i:s');
                // $end = "2017-07-04 05:35:00";
                $start = $start;
                if ($apicall) {

                    $gpsData = snapdata::
                                    orderBy('timestamp', 'asc')->
                                    where('user_id', $task['allocated_emp_id'])->
                                    where('created_at', '<=', Base::tomysqldatetime($end))->
                                    where('created_at', '>=', Base::tomysqldatetime($start))->
                                    get()->toArray();
                } else {


                    $gpsData = [];
                }

                $distInMeter = [];
                $distInMeter[] = 0;
                for ($x = 0; $x < count($gpsData) - 1; $x++) {

                    if (($gpsData[$x]['activity'] == 'Start')) {

                        $distInMeter[] = $distInMeter[count($distInMeter) - 1];
                        $distInMeter[] = 0;
                    } else {
                        $data1 = $gpsData[$x];
                        $data2 = $gpsData[$x + 1];
                        $gpsData[$x]['path'] = [$data1['lat'], $data1['lng']];
                        $gpsData[$x + 1]['path'] = [$data2['lat'], $data2['lng']];
                        $coordA = Geotools::coordinate($gpsData[$x]['path']);
                        $coordB = Geotools::coordinate($gpsData[$x + 1]['path']);
                        $distance = Geotools::distance()->setFrom($coordA)->setTo($coordB);
                        $distInMeter[count($distInMeter) - 1] = $distance->flat() + $distInMeter[count($distInMeter) - 1];
                    }
                }

                $distInMeter = array_sum($distInMeter);

                $time_taken = Base::time_elapsed_string($end, true, $start);

                if (empty($time_taken)) {
                    $time_taken = '1 min';
                }

                $distInMeter = $distInMeter / 1000;

                if ($apicall) {
                    $Summary = [
                        'time_taken' => $time_taken,
                        'start' => $start,
                        'end' => $end,
                        'order_id' => $order_id,
                        'distance' => round($distInMeter, 2) . ' kms',
                    ];

                    Session::put("key", $Summary);
                } else {

                    $Summary = [
                        'time_taken' => $time_taken,
                        'start' => $start,
                        'end' => $end,
                        'order_id' => $order_id,
                        'distance' => round($distInMeter, 2) . ' kms',
                    ];
                    return (object) $Summary;
                }
            }
        }
    }

    public function updatetask(Request $request, $task_id) {

        $rules = [
            'status' => 'required',
            'lat' => 'required',
            'timestamps' => 'required',
            'lng' => 'required',
                // 'is_cust_delivery' => 'required',
        ];

        $data = $request->input('data');
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {

            return Base::touser($validator->errors()->all()[0]);
        }

        $task = task::find($task_id);

        if (count($task) < 1) {
            return Base::touser('Task not found');
        }
        if ($task->status == 'Delivered' || $task->status == 'Incomplete') {
            return Base::touser('Task Already Completed', true);
        }



        $reqlat = $request->input('data')['lat'];
        $reqlng = $request->input('data')['lng'];
        $timestamp = isset($request->input('data')['timestamps']) ? Base::tomysqldatetime($request->input('data')['timestamps']) : date('Y-m-d H:i:s');
        $remarks = isset($request->input('data')['remarks']) ? $request->input('data')['remarks'] : '';

        $user_track = User::where('user_id', $this->emp_id)->first();

        $manager = User::where('user_id', $user_track->belongs_manager)->first();
        // print_r($manager->is_task_track);
        if ($manager->is_task_track == 'false' && $data['network_status'] == 'online') {
            if ($task->geo_fence_meter > 0) {
                $coordA = Geotools::coordinate([$reqlat, $reqlng]);
                $coordB = Geotools::coordinate([$task->loc_lat, $task->loc_lng]);
                $distance = Geotools::distance()->setFrom($coordA)->setTo($coordB);
                if ($distance->flat() > $task->geo_fence_meter) {

                    // return Base::touser('Customer Location must be within ' . $task->geo_fence_meter . ' meters'. $distance->flat());
                    return Base::touser('Customer Location must be within ' . $task->geo_fence_meter . ' meters');
                }
            }
        }
        $task->delivery_time = $timestamp;
        $task->delivery_to = isset($request->input('data')['delivery_to']) ? $request->input('data')['delivery_to'] : '';
        $task->delivery_phone = isset($request->input('data')['delivery_phone']) ? $request->input('data')['delivery_phone'] : '';
        $task->is_cust_delivery = isset($request->input('data')['is_cust_delivery']) ? $request->input('data')['is_cust_delivery'] : 1;
        $task->remarks = isset($remarks) ? $remarks : '';
        $task->lat = $reqlat;
        $task->lng = $reqlng;
        $task->signature = isset($request->input('data')['signature']) ? $request->input('data')['signature'] : '';
        $task->images = isset($request->input('data')['images']) ? json_encode($request->input('data')['images']) : '[]';


        $task->save();


        $task_status = new ScheduleTaskStatus();
        $task_status->emp_id = $this->emp_id;
        $task_status->task_id = $task->id;
        $task_status->address = '';
        $task_status->lat = $data['lat'];
        $task_status->long = $data['lng'];
        $task_status->status = $data['status'];
        $task_status->timestamps = $timestamp;
        $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->save();

        $user = \App\Models\User::find($this->emp_id);
        $notification = $user->notify(new \App\Notifications\TaskCompleted($task, $user));
        event(new \App\Events\NotificationEvent($user));
        event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));

        return Base::touser('Order has been successfully updated.', true);
    }

    public function allocateTask(Request $request, $task_id) {
        $rules = [
            'emp' => 'exists:user,user_id',
            'status' => 'required|string',
        ];

        $data = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $task = task::where('id', $task_id)->first();

        $task_status = new ScheduleTaskStatus();
        $task_status->emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
        $task_status->task_id = $task->id;
        $task_status->address = '';
        $task_status->lat = '';
        $task_status->long = '';
        $task_status->status = isset($data['status']) ? $data['status'] : 'Unallocated';
        $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->save();

        if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
            if (empty($data['emp'])) {
                return Base::touser('Employee Required');
            }

            $allocation = new allocation();
            $allocation->emp = $data['emp'];
            $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
            $allocation->task_id = $task->id;
            $allocation->save();

            $user = \App\Models\User::find($allocation->emp);
            $user->notify(new \App\Notifications\TaskAllocated($task, $user));
            // $cust = \App\Models\Customer::find($task->cust_id )->notify(new \App\Notifications\CustomerTracking($task, $user, Base::get_domin()));
        }
        return self::show($task->id);
    }

    public function latlong($location) {
        $url = htmlspecialchars_decode('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($location) . '&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk');
        return $url;
        $json = file_get_contents($url);
        $res = json_decode($json);
        if (!empty($res->results)) {
            $lat = $res->results[0]->geometry->location->lat;
            $lng = $res->results[0]->geometry->location->lng;
            return $lat . '|' . $lng;
        } else {
            return '|';
        }
    }

    public function check_latlong($location) {
        $url = htmlspecialchars_decode('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($location) . '&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk');
        //return $url;
        $json = file_get_contents($url);
        $res = json_decode($json);
        if (!empty($res->results)) {
            $lat = $res->results[0]->geometry->location->lat;
            $lng = $res->results[0]->geometry->location->lng;
            return 1;
        } else {
            return 0;
        }
    }

    public static function getAvailableDrivers($emp_id) {
        $user_id = Base::getEmpBelongsUser($emp_id);
        $ids = [];
        $free_drivers = [];
        $busy_drivers = [];
        foreach ($user_id as $key => $value) {
            $list = ScheduleTaskStatus::where("emp_id", $value)
                    ->orderBy('id', 'desc')
                    ->limit(1)
                    ->first();
            if (count($list) <= 0) {
                $free_drivers[] = $value;
            } else {
                if (($list['emp_id'] != null) && ($list["status"] == "Delivered" || $list["status"] == "Unallocated")) {
                    $free_drivers[] = $list['emp_id'];
                }

                if (($list['emp_id'] != null) && ($list["status"] == "In Supplier Place" || $list["status"] == "Allocated" || $list["status"] == "Products Picked up")) {
                    $busy_drivers[] = $list['emp_id'];
                }
                
                if ($list['emp_id'] != null && $list["status"] == "Delivered back") {
                    $data = DB::table('orders')
                            ->select(array('orders.*'))
                            ->join('emp_cust_schedule', 'emp_cust_schedule.mt_order_id', '=', 'orders.id')
                            ->where([['emp_cust_schedule.id', '=', $list["id"]], ['orders.status', '!=', 'In-Progress']])
                            ->get();
                    if (count($data) > 0) {
                        $free_drivers[] = $list['emp_id'];
                    } else {
                        $busy_drivers[] = $list['emp_id'];
                    }
                }
            }
        }
        $array = [
            "free_drivers" => $free_drivers,
            "busy_drivers" => $busy_drivers
        ];
        return $array;
    }

    public function slice_drivers($ids, $slice) {
        $id = [];
        foreach ($slice as $key => $value) {
            if ($value != $slice[$key]) {
                $id[] = $value;
            }
        }
        return $id;
    }

    public function array_stip($drivers) {
        $id = [];
        foreach ($drivers as $key => $value) {
            $id[] = $value;
        }
        return $id;
    }

    public function decline_auto_allocation(Request $request) {
        $data = $request->input('data');
        $order_id = $data["order_id"];
        $skiped_drivers = $data["skiped_drivers"];
        $latitude = $data["latitude"];
        $longitude = $data["longitude"];
        $task = emp_cust::find($order_id);
        $response = self::auto_allocation_order_status_update($order_id, $latitude, $longitude, $this->emp_id);
        return Base::touser($response, true);
    }

    public static function find_nearest_driver($distance = 0, $latitude, $longitude, $emp_id, $order_id) {

        //$skiped_drivers = [];
        //$skiped_drivers = array_map('intval', $skiped_drivers);
        $skipped_ids = [];
        $drivers = self::getAvailableDrivers($emp_id);

        $data = AutoAllocation::where('order_id', '=', $order_id)->get(['emp_id'])->toArray();
        foreach ($data as $key => $value) {

            $skipped_ids[] = $data[$key]["emp_id"];
        }

        $free_drivers = $drivers["free_drivers"];
        $busy_drivers = $drivers["busy_drivers"];

        $count = array_sum(array_map("count", $drivers));

        if ($count <= 0) {
            return false;
        }

        $drivers = array_diff($free_drivers, $skipped_ids);
        $find_drivers = [];
        foreach ($drivers as $key => $value) {
            $find_drivers[] = $value;
        }


        // $latitude = "12.9499618";
        // $longitude = "80.2377285";
        $user = User::find($emp_id);
        $find_in_km = isset($user->auto_allocation_find_in_km) ? $user->auto_allocation_find_in_km : 2;
        $init_distance = $distance + $find_in_km;

        /* $data = User::select(DB::raw('*, ( 6367 * acos( cos( radians('.$latitude.') ) * cos( radians( employee_lat ) ) * cos( radians( employee_lng ) - radians('.$longitude.') ) + sin( radians('.$latitude.') ) * sin( radians( employee_lat ) ) ) ) AS distance'))
          ->having('distance', '<', $init_distance)
          ->orderBy('distance')
          ->whereIn('user_id', $drivers)
          ->limit(1)
          ->get()->first(); */

        $data = User::select(DB::raw('*, 
                        111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(' . $latitude . '))
                         * COS(RADIANS(employee_lat))
                         * COS(RADIANS(' . $longitude . ' - employee_lng))
                         + SIN(RADIANS(' . $latitude . '))
                         * SIN(RADIANS(employee_lat)))))
                      AS distance_in_km'))
                        ->having('distance_in_km', '<', $init_distance)
                        ->orderBy('distance_in_km')
                        ->where('employee_lat', '!=', null)
                        ->where('employee_lng', '!=', null)
                        ->whereIn('user_id', $drivers)->limit(1)->get()->first();

        /* if(count($data) <= 0){
          $drivers = array_diff($busy_drivers, $skiped_drivers);
          $data = User::select(DB::raw('*, CASE
          WHEN employee_lat IS NOT NULL THEN
          111.111 * DEGREES(ACOS(LEAST(1.0, COS(RADIANS('.$latitude.'))
         * COS(RADIANS(employee_lat))
         * COS(RADIANS('.$longitude.' - employee_lng))
          + SIN(RADIANS('.$latitude.'))
         * SIN(RADIANS(employee_lat)))))
          ELSE
          0
          END AS distance_in_km'))
          ->having('distance_in_km', '<', $init_distance)
          ->orderBy('distance_in_km')
          ->where('employee_lat','!=',null)
          ->where('employee_lng','!=',null)
          ->whereIn('user_id', $drivers)->limit(1)->get()->first();
          } */

        if (count($data) <= 0) {
            $max_km_find = isset($user->auto_allocation_max_radius_in_km) ? $user->auto_allocation_max_radius_in_km : 10;
            if ($init_distance < $user->max_km_find) {
                return self::find_nearest_driver($init_distance, $latitude, $longitude, $emp_id, $order_id);
            }
        }
        return $data;
    }

    public static function auto_allocation_order_status_update($order_id, $_pickup_ladd, $_pickup_long, $emp_id) {
        /* get lat long from least task */
        /* $_task = task::where('mt_order_id','=',$order_id)->orderBy('id')->get()->first();
          $_pickup_ladd = $_task->pickup_ladd;
          $_pickup_long = $_task->pickup_long;
          $skiped_drivers = []; */
        /* get available driver */
        $_available_driver = ApiOrderScheduleController::find_nearest_driver(0, $_pickup_ladd, $_pickup_long, $emp_id, $order_id);

        if ($_available_driver == false) {
            return false;
        }

        /* store the user_id from the response */
        if (!$_available_driver) {
            /* delete those entries in auto allocation table */
            //$auto_allocation = AutoAllocation::where("order_id",'=',$order_id)->delete();
            return false;
        }
        $emp = $_available_driver->user_id;
        
        $timezone = Base::client_time($emp);

        $Orders = ApiOrders::find($order_id);
        $user_current_date = Carbon::parse($timezone);
        $user_current_date->addHours(24);
        $user_current_date = $user_current_date->format('Y-m-d H:i:s');

        $pick_time = Carbon::parse($Orders->order_start_time);
        $pick_time = $pick_time->format('Y-m-d H:i:s');
        
        if ($Orders->status == "Unallocated") {
            if (strtotime($user_current_date) < strtotime($pick_time)) {
                $Orders->is_order_confirmed = 2;
            } else {
                $Orders->is_order_confirmed = 1;
                $Orders->is_order_push_sent = 1;
            }
            $Orders->emp_id = $emp;
            $Orders->status = "Allocated";
            $Orders->save();

            /* add entry to auto allocation table */
            $auto = new AutoAllocation();
            $auto->admin_id = $emp_id;
            $auto->emp_id = $emp;
            $auto->order_id = $order_id;
            $auto->save();


            /* get all task id use order_id */
            $_task_id = Base::getTaskId($order_id);
            foreach ($_task_id as $key => $task_id) {

                $task = task::find($task_id);
                $task->status = "Allocated";
                $task->update();


                $task_status = new ScheduleTaskStatus();
                $task_status->emp_id = $emp;
                $task_status->task_id = $task_id;
                $task_status->address = '';
                $task_status->lat = isset($data['lat']) ? $data['lat'] : '';
                $task_status->long = isset($data['lng']) ? $data['lng'] : '';
                $task_status->status = "Allocated";
                $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->save();

                $allocation = new allocation();
                $allocation->emp = $emp;
                $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $emp_id;
                $allocation->task_id = $task->id;
                $allocation->save();

                $user = \App\Models\User::find($emp);
                $additional_fields = [
                    "latitude" => $_pickup_ladd,
                    "longitude" => $_pickup_long
                ];
                //$user->notify(new \App\Notifications\AutoAllocated($task, $user,$additional_fields));
            }
            $_task = task::where('mt_order_id', '=', $Orders->id)->orderBy('id')->get()->first();
            event(new \App\Events\AutoAllocatedEvent($order_id, $_task, $emp, $additional_fields, $emp_id));
            event(new \App\Events\TaskUpdateEvent($_task, $emp));
        }


        return $_available_driver;
    }

    public function sqs_response(Request $request){
        $data = $request->input('data');
        $admin_id = $data["admin_id"];
        $order_id = $data["order_id"];
        $_task = task::where('mt_order_id', '=', $order_id)->orderBy('id')->get()->first();
        $user = \App\Models\User::find($admin_id);
        $user->notify(new \App\Notifications\AutoAllocatedNoDriver($_task, $user));
        return Base::touser("Success",true);
    }

    public function socket() {
        $latitude = "12.9499618";
        $longitude = "80.2377285";
        $skiped_drivers = [];
        $additional_fields = [
            "latitude" => $latitude,
            "longitude" => $longitude,
            "skiped_drivers" => $skiped_drivers
        ];
        $task = task::find("228");
        //event(new \App\Events\AutoAllocatedEvent("169", $task, "47421", $additional_fields));
        return "hai";
    }

    public function calander_orderInfo(Request $request,$date){
        $date = isset($date) ? $date : date('Y-m-d');
        $parsed_date = Carbon::parse($date);
        $month  = $parsed_date->format('m');
        $year   = $parsed_date->format('Y');
        $num_of_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $dates = [];
        $_orders = [];
        $order_ids = [];
    
        for($x=1; $x<=$num_of_days; $x++){
            $str_pad = str_pad($x, 2, "0", STR_PAD_LEFT);
            $date = date($year.'-'.$month.'-'.$str_pad);
            $start = Base::tomysqldatetime($date . ' 00:00:00');
            $end = Base::tomysqldatetime($date . ' 23:59:00');

            if(!array_key_exists($date, $dates)){
                $dates[$date] = [
                    "Unallocated"   => [],
                    "Allocated"     => [],
                    "In-Progress"   => [],
                    "Accepted"      => [],
                    "Delivered"     => []
                ];
            }

            $increment_date = strtotime("+1 day", strtotime($date));
            $updated_date = date('Y-m-d', $increment_date);
            
            $orders = ApiOrders::where('added_by','=',$this->emp_id)
                    ->where('order_start_time', '<=', $end)
                    ->where('order_end_time', '>=', $start)
                    //->where('source','=',0)
                    ->get(['id','status','order_start_time','order_end_time','emp_id'])
                    ->toArray();
            
            foreach ($orders as $key => $value) {
                if(in_array($value['id'], $order_ids)) {
                    continue;
                }
                array_push($order_ids, $value['id']);
                if($value["status"] == "Unallocated"){
                    array_push($dates[$date]["Unallocated"], $value);
                }
                if($value["status"] == "Allocated"){
                    $_orders["Allocated"] = $value;
                    array_push($dates[$date]["Allocated"], $value);
                }
                if($value["status"] == "In-Progress"){
                    $_orders["In-Progress"] = $value;
                    array_push($dates[$date]["In-Progress"], $value);
                }
                if($value["status"] == "Accepted"){
                    $_orders["Accepted"] = $value;
                    array_push($dates[$date]["Accepted"], $value);
                }
                if($value["status"] == "Delivered"){
                    $_orders["Delivered"] = $value;
                    array_push($dates[$date]["Delivered"], $value);
                }
            }
            //die();
        }
        return Base::touser($dates,true);
    }

    public function store(Request $request) {
        $id = $this->emp_id;
        // $id=47469;

        $timezone = Base::client_time($id);
        $data = $request->input('data');
        $emps = UserPackage::where('user_id', $id)->first();
        // dd($emps);
//        $date = new \DateTime($emps['beg_date']);
//        $begin_date = $date->format('Y-m-d');
        
        $date1 = new \DateTime($emps['end_date']);
        $end_date = $date1->format('Y-m-d') . " 23:59:59";
        
        $delivery_time = $data['multiple_delivery'];
        $picktime = $data['multiple_pickup'];

        foreach ($picktime as $key => $picktime_) {
            if (strtotime($picktime_['picktime']) < strtotime($timezone)) {
                return Base::touser('Pickup Time should not be before today.');
            }
        }
        
        $tasks = DB::table('emp_cust_schedule')
                    ->select('*')
                    ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                    ->where('orders.added_by', $id)
                    ->where('orders.created_at', '>=', $emps['beg_date'])
                    ->where('orders.created_at', '<=', $end_date)
                    ->get()->count();
        $total_task = (int) $emps["no_of_task"];
        $remaining_task = $total_task - $tasks;

        $delivery_logic = $data["delivery_logic"];
        if ($delivery_logic == 1) {
            if(count($delivery_time) > $remaining_task && $total_task > 1) {
                return Base::touser('You have been reached your maximum task limit');
            }
            foreach ($delivery_time as $key => $deltime_) {
                if (strtotime($deltime_['schedule']) <= strtotime($picktime[0]['picktime'])) {
                    return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
                }
            }
        } else if ($delivery_logic == 2) {
            if(count($picktime) > $remaining_task && $total_task > 1) {
                return Base::touser('You have been reached your maximum task limit');
            }
            $end_picktime = end($data['multiple_pickup']);
            foreach ($delivery_time as $key => $deltime_) {
                if (strtotime($deltime_['schedule']) <= strtotime($end_picktime['picktime'])) {
                    return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
                }
            }
        } else if ($delivery_logic == 3) {
            if(count($delivery_time) > $remaining_task && $total_task > 1) {
                return Base::touser('You have been reached your maximum task limit');
            }
            if (strtotime($delivery_time[0]['schedule']) <= strtotime($picktime[0]['picktime'])) {
                return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
            }
        }

        $rules = [
            'emp' => 'exists:user,user_id',
            'added_by' => 'exists:user,user_id',
            'type' => 'required|string',
            'method' => 'required|string',
        ];

        $data = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        if (($data['status'] != 'Unallocated') && ($data['status'] != 'Canceled')) {
            if (empty($data['emp'])) {
                return Base::touser('Employee Required');
            }
        }
        // dd($picktime);
        foreach ($picktime as $key => $_picktime) {
            if (isset($_picktime['pick_address']) and empty(explode('|', ApiOrderScheduleController::latlong($_picktime['pick_address']))[0])) {
                return Base::touserloc('Please enter a valid pickup location', 'pickup');
            }
        }

        foreach ($delivery_time as $key => $delivery_time) {
            if(is_array($delivery_time['cust_address'])){
                if (isset($delivery_time['cust_address']) and empty(explode('|', ApiOrderScheduleController::latlong($delivery_time['cust_address']['formatted_address']))[0])) {
                    return Base::touserloc('Please enter a valid receiver location', 'delivery');
                }
            }
            else{
                if (isset($delivery_time['cust_address']) and empty(explode('|', ApiOrderScheduleController::latlong($delivery_time['cust_address']))[0])) {
                    return Base::touserloc('Please enter a valid receiver location', 'delivery');
                }
            }
            
        }

        if (isset($data['sent_address']) and empty(explode('|', ApiOrderScheduleController::latlong($data['sent_address']))[0])) {
            return Base::touserloc('Sender Location is not valid kindly use drag and drop', 'sender');
        }
        $returnData = ApiOrderScheduleController::createOrder($request->input('data'), $this->emp_id, $this->admin, $this->backend, $this->manager, 
                $request->input('sender_name'), $request->input('comments'), $request->input('mob'));
        
        if (isset($returnData['order_id'])) {
            $array = [
                "msg" => $returnData['msg'],
                "order_id" => $returnData['order_id'],
                "task_id"=>$returnData['_taskid'],
                "pickup_task_ids" => $returnData['pickup_task_ids'],
                "delivery_task_ids" => $returnData['delivery_task_ids']
            ];
            return Base::touser($array, $returnData['status']);
        }
        $result=[
            "msg" => $returnData['msg'],
            "order_id" => $returnData['_orderid'],
            "task_id"=>$returnData['_taskid']
        ];

        return Base::touser($result,$returnData['status']);
    }
    
    public function createOrder($requestData, $empId, $admin, $backend, $manager, $senderName = '', $comments = '', $mob = '') {
        $data = $requestData;
        Log::info($data);
        Log::info($data['multiple_delivery']);
        $multi_delivery = $data['multiple_delivery'];
        $multi_pickup = $data['multiple_pickup'];
        $lastindexarray = $data['multiple_delivery'];
        $cust = array();
        // dd($empId);
        $timezone = Base::client_time($empId);

        $newdate = date('Y-m-d H:i:s', strtotime($multi_pickup[0]['picktime']));

        if ($data['is_multipickup'] == null) {
            $is_multipickup = false;
        } else {
            $is_multipickup = $data["is_multipickup"];
        }

        if ($data['is_multidelivery'] == null) {
            $is_multidelivery = false;
        } else {
            $is_multidelivery = $data["is_multidelivery"];
        }

        $Orders = new ApiOrders();
        $Orders->emp_id = isset($data['emp']) ? $data['emp'] : $empId;
        $Orders->added_by = isset($data['added_by']) ? $data['added_by'] : $empId;
        $Orders->order_start_time = $newdate;
        $getLastarray = end($lastindexarray);
        $Orders->order_end_time = $getLastarray['schedule'];
        $Orders->is_multipickup = $is_multipickup;
        $Orders->is_multidelivery = $is_multidelivery;
        $Orders->delivery_logic = $data["delivery_logic"];
        $Orders->status = $data['status'];
        if ($data['status'] == "Allocated") {
            $Orders->status = 'Allocated';
            $user_current_date = Carbon::parse($timezone);
            $user_current_date->addHours(24);
            $user_current_date = $user_current_date->format('Y-m-d H:i:s');

            $pick_time = Carbon::parse($newdate);
            $pick_time = $pick_time->format('Y-m-d H:i:s');
            if (strtotime($user_current_date) < strtotime($pick_time)) {
                $Orders->is_order_confirmed = 2;
            } else {
                $Orders->is_order_confirmed = 1;
                $Orders->is_order_push_sent = 1;
            }
        } else if ($data['status'] == "In-Progress" || $data['status'] == "Started Ride" || $data['status'] == "In Supplier Place" || $data['status'] == "Products Picked up") {
            $Orders->status = 'In-Progress';
            $Orders->is_order_confirmed = 1;
            $Orders->is_order_push_sent = 1;
        } else if ($data['status'] == 'Delivered') {
            $Orders->status = 'Delivered';
        }
        if (isset($data['source'])) {
            $Orders->source = $data['source'];
        }
        $Orders->save();

        $is_multidelivery = 1;
        
        $pickup_task_ids = array();
        $delivery_task_ids = array();
        // dd($data['is_multidelivery']);
        if ($data['is_multidelivery'] == 1 || ($data['is_multidelivery'] == false && $data['is_multipickup'] == false)) {
            $j = 1;
            $multi_delivery = $data['multiple_delivery'];
            $deleteitems = ItemMap::where('order_id', $Orders->id)->delete();
            $stage = 1;
            $task_ids=[];
            // dd($multi_delivery);
            foreach ($multi_delivery as $key => $multival) {
                //return $picktime['notes'];
                $task = new task();
                // dd($multival);
                // dd(implode(", ", $multival['documents']));
                $task->schedule_date_time = isset($multival['schedule']) ? Base::tomysqldatetime($multival['schedule']) : date('Y-m-d H:i:s');

                if ($admin || $backend) {
                    if (empty($data['added_by'])) {

                        return array('msg' => 'Admin Must Provide Allocated Employee Value', 'status' => false);
                    }
                    $task->added_by = $data['added_by'];
                } elseif ($manager) {
                    $task->added_by = $empId;
                } else {
                    $task->added_by = $empId;
                }

                $count = task::count();
                $order_id = $multival["order_id"];

                if (isset($multival['cust_email'])) {
                    $multival['cust_email'] = $multival['cust_email'];
                } elseif (!isset($multival['cust_email']) && isset($multival['temp_cust_email'])) {
                    $multival['cust_email'] = $multival['temp_cust_email'];
                } else {
                    $multival['cust_email'] = '';
                }
                $data = $requestData;
                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : $empId;
                // $task->notes          = $multi_pickup[0]['notes'];

                $task->pick_address = $multi_pickup[0]['pick_address'];
                $task->order_id = $order_id;
                $task->comments = $comments;
                $task->mob = $mob;
                $task->receiver_name = isset($multival['receiver_name']) ? $multival['receiver_name']:'';
                $task->cust_email = $multival['cust_email'];

                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : $senderName;
                // $task->sender_number  = $request->input('sender_number');
                // $task->sender_number  = isset($data['sender_number'])?$data['sender_number'] : '';
                $task->picktime = isset($multi_pickup[0]['picktime']) ? Base::tomysqldatetime($multi_pickup[0]['picktime']) : date('Y-m-d H:i:s');
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : '';
                $task->sender_number = isset($data['sender_number']) ? $data['sender_number'] : '';

                $task->status = $data['status'];
                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';
                $task->cust_phone = isset($multival['cust_phone']) ? $multival['cust_phone'] : '';
                
                if(empty($multi_pickup[0]['pickup_ladd'])) {
                    $pickup_latlng = explode('|', ApiOrderScheduleController::latlong($multi_pickup[0]['pick_address']));
                    $task->pickup_long = $pickup_latlng[1];
                    $task->pickup_ladd = $pickup_latlng[0];
                } else {
                    $task->pickup_long = $multi_pickup[0]['pickup_long'];
                    $task->pickup_ladd = $multi_pickup[0]['pickup_ladd'];
                }
                
                if(empty($multival['loc_lat'])) {
                    $delivery_latlng = explode('|', ApiOrderScheduleController::latlong($multival['cust_address']));
                    $task->loc_lat = $delivery_latlng[0];
                    $task->loc_lng = $delivery_latlng[1];
                } else {
                    $task->loc_lat = $multival['loc_lat'];
                    $task->loc_lng = $multival['loc_lng'];
                }
                if(is_array($multival['cust_address'])){
                    $multival['cust_address'] = $multival['cust_address']['formatted_address'];
                }
                $task->cust_address = $multival['cust_address'];
                // $task->delivery_notes = $multival['delivery_notes'];
                $task->method = $data['method'];
                $is_new_address = false;
                $task->is_new_address = $is_new_address;
                $task->pickup_phone = isset($multi_pickup[0]['pickup_phone']) ? $multi_pickup[0]['pickup_phone'] : '';

                if ((isset($data['is_geo_fence'])) && ($data['is_geo_fence'] == 1)) {
                    if (isset($data['geo_fence_meter'])) {
                        $task->geo_fence_meter = $data['geo_fence_meter'];
                        $task->is_geo_fence = $data['is_geo_fence'];
                    } else {
                        return array('msg' => 'Geo Fence Meter Required', 'status' => false);
                    }
                }

                if ($manager) {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 2;
                        $task->approve_status = 1;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                } else {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 1;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                }

                $task->mt_order_id = $Orders->id;
                $task->priority = $j;

                $task->product_weight = isset($multi_pickup[0]['product_weight']) ? $multi_pickup[0]['product_weight'] : 0;
                $task->product_size = isset($multi_pickup[0]['product_size']) ? $multi_pickup[0]['product_size'] : null;
                $task->time_to_delivery = isset($multi_pickup[0]['time_to_delivery']) ? $multi_pickup[0]['time_to_delivery'] : null;
                $task->time_requirement = isset($multi_pickup[0]['time_requirement']) ? $multi_pickup[0]['time_requirement'] : null;

                $task->product_length = isset($multi_pickup[0]['product_length']) ? $multi_pickup[0]['product_length'] : 0;
                $task->product_height = isset($multi_pickup[0]['product_height']) ? $multi_pickup[0]['product_height'] : 0;
                $task->product_breadth = isset($multi_pickup[0]['product_breadth']) ? $multi_pickup[0]['product_breadth'] : 0;
                $task->images=isset($multival['documents'])?implode(',',$multival['documents']):"";
                $task->save();
                
                if(isset($data['source']) && $data['source'] == 3) {
                    $delivery_task_ids[] = array(
                        "esseplore_task_id" => isset($multival['delivery_id']) ? $multival['delivery_id'] : 0,
                        "mtz_task_id" => $task->id
                    );

                    $pickup_task_ids[0] = array(
                        "esseplore_task_id" => isset($multi_pickup[0]['pickup_id']) ? $multi_pickup[0]['pickup_id'] : 0,
                        "mtz_task_id" => $task->id
                    );
                }
                
                if (isset($multival['delivery_notes1'])) {
                    //item map
                    foreach ($multival['delivery_notes1'] as $key => $value) {
                        $itemmap = new ItemMap();
                        $itemmap->item_id = $value;
                        $itemmap->stage = $stage;
                        $itemmap->order_id = $Orders->id;
                        // $itemmap->quantity = $Orders->quantity;
                        $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                        $itemmap->save();
                    }
                }

                if (isset($multival['delivery_notes2'])) {
                    foreach ($multival['delivery_notes2'] as $key => $value) {
                        $checkItem = Items::where('name',$value)->where('emp_id',$empId)->get()->count();
                        if ($checkItem == 0) {
                            $Items = new Items();
                            $Items->name = $value;
                            $Items->emp_id =  $empId;
                            $Items->save();
                            $item_id = $Items->id;
                        } else {
                             $checkItem = Items::where('name', $value)->where('emp_id', $empId)->get()->first();
                             $item_id = $checkItem->id;
                        }
                        
                        $existItem = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->get()->count();
                        
                        if ($existItem == 0) {
                            $itemmap = new ItemMap();
                            $itemmap->item_id = $item_id;
                            $itemmap->stage = $stage;
                            $itemmap->order_id = $Orders->id;
                            $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                            $itemmap->save();
                        }
                    }
                }
                
                if (isset($multival['delivery_notes_item_quantity'])) {
                    //item map
                    foreach ($multival['delivery_notes_item_quantity'] as $key => $value) {
                        $checkItem = Items::where('name', $value['ItemName'])->where('emp_id', $empId)->get()->count();

                        if ($checkItem == 0) {
                            $Items = new Items();
                            $Items->name = $value['ItemName'];
                            $Items->emp_id =  $empId;
                            $Items->save();
                            $item_id = $Items->id;
                        }
                        else{
                            $checkItem = Items::where('name', $value['ItemName'])->where('emp_id', $empId)->get()->first();
                            $item_id = $checkItem->id;

                        }
                        // dd($item_id);

                        // $item_id = $checkItem->id;

                        $existItem = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->get()->count();
                        // dd($itemmap);
                        if ($existItem == 0) {
                            $itemmap = new ItemMap();
                            $itemmap->item_id = $item_id;
                            $itemmap->stage = $stage;
                            $itemmap->order_id = $Orders->id;
                            $itemmap->quantity = $value['Quantity'];
                            $itemmap->save();
                        }
                        // $itemmap->quantity = $value['Quantity'];
                        // $itemmap->save();
                    }
                }

                $stage++;
                // item map end
                //check Customer exists 
                
                $getcust = Customer::where('contact_no', '=', $multival['cust_phone'])->where('emp_id', $empId)->get()->count();
            
                if ($getcust == 0) {
                    $cust_create = new Customer();
                    $cust_create->name = $multival['receiver_name'];
                    $cust_create->emp_id = $empId;
                    $cust_create->email = $multival['cust_email'];
                    $cust_create->address = $multival['cust_address'];
                    $cust_create->loc_lat = $multival['loc_lat'];
                    $cust_create->loc_lng = $multival['loc_lng'];
                    $cust_create->contact_no = $multival['cust_phone'];
                    $cust_create->save();
                }
                // end customer exists

                $task_status = new ScheduleTaskStatus();
                $task_status->emp_id = isset($data['emp']) ? $data['emp'] : $empId;
                $task_status->task_id = $task->id;
                array_push($task_ids,$task->id);
                $task_status->address = '';
                $task_status->lat = '';
                $task_status->long = '';
                $task_status->status = isset($data['status']) ? $data['status'] : 'Unallocated';
                $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->save();

                if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
                    if (empty($data['emp'])) {
                        return array('msg' => 'Employee Required', 'status' => false);
                    }

                    $allocation = new allocation();
                    $allocation->emp = $data['emp'];
                    $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $empId;
                    $allocation->task_id = $task->id;
                    $allocation->save();

                    $user = \App\Models\User::find($allocation->emp);
                    $user->notify(new \App\Notifications\TaskAllocated($task, $user));
                }
                $j++;
            } /* end foreach */
        } else if ($data['is_multipickup'] == 1) {
            $j = 1;
            $deleteitems = ItemMap::where('order_id', $Orders->id)->delete();
            $stage = 1;
            $task_ids=[];
            foreach ($multi_pickup as $key => $picktime) {
                //return $picktime['notes'];
                $task = new task();

                $task->schedule_date_time = isset($multi_delivery[0]['schedule']) ? Base::tomysqldatetime($multi_delivery[0]['schedule']) : date('Y-m-d H:i:s');

                if ($admin || $backend) {
                    if (empty($data['added_by'])) {
                        return array('msg' => 'Admin Must Provide Allocated Employee Value', 'status' => false);
                    }
                    $task->added_by = $data['added_by'];
                } elseif ($manager) {
                    $task->added_by = $empId;
                } else {
                    $task->added_by = $empId;
                }

                $count = task::count();
                $order_id = $multi_delivery[0]['order_id'];
                $multi_delivery = $data['multiple_delivery'];

                if (isset($multi_delivery[0]['cust_email'])) {
                    $multi_delivery[0]['cust_email'] = $multi_delivery[0]['cust_email'];
                } elseif (!isset($multi_delivery[0]['cust_email']) && isset($multi_delivery[0]['temp_cust_email'])) {
                    $multi_delivery[0]['cust_email'] = $multi_delivery[0]['temp_cust_email'];
                } else {
                    $multi_delivery[0]['cust_email'] = '';
                }

                $data = $requestData;
                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : $empId;
                //$task->notes          = $picktime['notes'];

                $task->pick_address = $picktime['pick_address'];
                $task->order_id = $order_id;
                $task->comments = $comments;
                $task->mob = $mob;
                $task->receiver_name = isset($multi_delivery[0]['receiver_name']) ? $multi_delivery[0]['receiver_name'] : '';
                $task->cust_email = $multi_delivery[0]['cust_email'];
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : $senderName;
                // $task->sender_number  = $request->input('sender_number');
                // $task->sender_number  = isset($data['sender_number'])?$data['sender_number'] : '';
                $task->picktime = isset($picktime['picktime']) ? Base::tomysqldatetime($picktime['picktime']) : date('Y-m-d H:i:s');
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : '';
                $task->sender_number = isset($data['sender_number']) ? $data['sender_number'] : '';
                $task->pickup_phone = isset($picktime['pickup_phone']) ? $picktime['pickup_phone'] : '';

                $task->status = $data['status'];
                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';
                $task->cust_phone = isset($multi_delivery[0]['cust_phone']) ? $multi_delivery[0]['cust_phone'] : '';
                
                if(empty($picktime['pickup_ladd'])) {
                    $pickup_latlng = explode('|', ApiOrderScheduleController::latlong($picktime['pick_address']));
                    $task->pickup_long = $pickup_latlng[1];
                    $task->pickup_ladd = $pickup_latlng[0];
                } else {
                    $task->pickup_long = $picktime['pickup_long'];
                    $task->pickup_ladd = $picktime['pickup_ladd'];
                }
                
                if(empty($multi_delivery[0]['loc_lat'])) {
                    $delivery_latlng = explode('|', ApiOrderScheduleController::latlong($multi_delivery[0]['cust_address']));
                    $task->loc_lat = $delivery_latlng[0];
                    $task->loc_lng = $delivery_latlng[1];
                } else {
                    $task->loc_lat = $multi_delivery[0]['loc_lat'];
                    $task->loc_lng = $multi_delivery[0]['loc_lng'];
                }
                
                $task->cust_address = $multi_delivery[0]['cust_address'];
                // $task->delivery_notes = $multi_delivery[0]['delivery_notes'];
                $task->method = $data['method'];
                $is_new_address = false;
                $task->is_new_address = $is_new_address;

                if ((isset($data['is_geo_fence'])) && ($data['is_geo_fence'] == 1)) {
                    if (isset($data['geo_fence_meter'])) {
                        $task->geo_fence_meter = $data['geo_fence_meter'];
                        $task->is_geo_fence = $data['is_geo_fence'];
                    } else {
                        return array('msg' => 'Geo Fence Meter Required', 'status' => false);
                    }
                }

                if ($manager) {
                    if($data['status'] != "Unallocated"){
                        $task->task_status = 2;
                        $task->approve_status = 1;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                } else {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 1;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                }
                // $task->task_status = 1;
                //  $task->task_status = 1;

                $task->mt_order_id = $Orders->id;
                $task->priority = $j;

                $task->product_weight = isset($picktime['product_weight']) ? $picktime['product_weight'] : 0;
                $task->product_size = isset($picktime['product_size']) ? $picktime['product_size'] : null;
                $task->time_to_delivery = isset($picktime['time_to_delivery']) ? $picktime['time_to_delivery'] : null;
                $task->time_requirement = isset($picktime['time_requirement']) ? $picktime['time_requirement'] : null;

                $task->product_length = isset($picktime['product_length']) ? $picktime['product_length'] : 0;
                $task->product_height = isset($picktime['product_height']) ? $picktime['product_height'] : 0;
                $task->product_breadth = isset($picktime['product_breadth']) ? $picktime['product_breadth'] : 0;
                $task->images=isset($multi_delivery[0]['documents'])?implode(',',$multi_delivery[0]['documents']):"";

                $task->save();
                
                if(isset($data['source']) && $data['source'] == 3) {
                    $delivery_task_ids[0] = array(
                        "esseplore_task_id" => isset($multi_delivery[0]['delivery_id']) ? $multi_delivery[0]['delivery_id'] : 0,
                        "mtz_task_id" => $task->id
                    );

                    $pickup_task_ids[] = array(
                        "esseplore_task_id" => isset($picktime['pickup_id']) ? $picktime['pickup_id'] : 0,
                        "mtz_task_id" => $task->id
                    );
                }

                $task_status = new ScheduleTaskStatus();
                $task_status->emp_id = isset($data['emp']) ? $data['emp'] : $empId;
                $task_status->task_id = $task->id;
                array_push($task_ids,$task->id);
                $task_status->address = '';
                $task_status->lat = '';
                $task_status->long = '';
                $task_status->status = isset($data['status']) ? $data['status'] : 'Unallocated';
                $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->save();
                
                if (isset($picktime['delivery_notes'])) {
                     //item map
                     foreach ($picktime['delivery_notes'] as $key => $value) {
                         $itemmap = new ItemMap();
                         $itemmap->item_id = $value;
                         $itemmap->stage = $stage;
                         $itemmap->order_id = $Orders->id;
                         $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                         $itemmap->save();
                     }
                }

                if (isset($picktime['delivery_notes3'])) {
                     foreach ($picktime['delivery_notes3'] as $key => $value) {
                         $checkItem = Items::where('name',$value)->where('emp_id', $empId)->get()->count();
                         if ($checkItem == 0) {
                             $Items = new Items();
                             $Items->name = $value;
                             $Items->emp_id = $empId;
                             $Items->save();
                             $item_id = $Items->id;
                         } else {
                              $checkItem = Items::where('name', $value)->where('emp_id', $empId)->get()->first();
                              $item_id = $checkItem->id;
                         }
                         
                         $existItem = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->get()->count();
                        
                         if ($existItem == 0) {
                            $itemmap = new ItemMap();
                            $itemmap->item_id = $item_id;
                            $itemmap->stage = $stage;
                            $itemmap->order_id = $Orders->id;
                            $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                            $itemmap->save();
                         }
                     }
                }
                 
                if (isset($picktime['delivery_notes_item_quantity'])) {
                    //item map
                    foreach ($picktime['delivery_notes_item_quantity'] as $key => $value) {
                        $checkItem = Items::where('name', $value['ItemName'])->where('emp_id', $empId)->get()->first();
                        $item_id = $checkItem->id;

                        $itemmap = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->first();
                        $itemmap->quantity = $value['Quantity'];
                        $itemmap->save();
                    }
                }

                $stage++;
                // item map end
                //check Customer exists 
                $getcust = Customer::where('contact_no', '=', $multi_delivery[0]['cust_phone'])->where('emp_id', $empId)->get()->count();
                if ($getcust == 0) {
                    $cust_create = new Customer();
                    $cust_create->name = $multi_delivery[0]['receiver_name'];
                    $cust_create->emp_id = $empId;
                    $cust_create->email = $multi_delivery[0]['cust_email'];
                    $cust_create->address = $multi_delivery[0]['cust_address'];
                    $cust_create->loc_lat = $multi_delivery[0]['loc_lat'];
                    $cust_create->loc_lng = $multi_delivery[0]['loc_lng'];
                    $cust_create->contact_no = $multi_delivery[0]['cust_phone'];
                    $cust_create->save();
                }
                // end customer exists


                if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
                    if (empty($data['emp'])) {
                        return array('msg' => 'Employee Required', 'status' => false);
                    }

                    $allocation = new allocation();
                    $allocation->emp = $data['emp'];
                    $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $empId;
                    $allocation->task_id = $task->id;
                    $allocation->save();

                    $user = \App\Models\User::find($allocation->emp);
                    $user->notify(new \App\Notifications\TaskAllocated($task, $user));
                }
                $j++;
            }/* end multipickup */
        }
        // dd($task_ids);

        /* auto allocation logic */
        /*
          if AUTO_ALLOCATION == 1 means enable
          if AUTO_ALLOCATION == 2 means disable
         */
        /* check order status */
        if ($Orders->status == "Unallocated") {
            $user = User::find($empId);
            if ($user->is_auto_allocation_enable == 1) {
                $order_id = $Orders->id;
                $_task = task::where('mt_order_id', '=', $order_id)->orderBy('id')->get()->first();
                $pick_date = date('Y-m-d', strtotime($_task->picktime));
                $today_date = date('Y-m-d', strtotime($timezone));
                if ($pick_date > $today_date) {
                    if ($Orders->source == 3) {
                        return array('msg' => 'Order has been successfully created.', 'status' => true, "_orderid" => $Orders->id,"_taskid" => $task_ids);
                    } else {
                        return array('msg' => 'Order has been successfully created.', 'status' => true,"_orderid" => $Orders->id,"_taskid" => $task_ids);
                    }
                } else {
                    $_pickup_ladd = $_task->pickup_ladd;
                    $_pickup_long = $_task->pickup_long;
                    /*$resp = self::auto_allocation_order_status_update($Orders->id, $_pickup_ladd, $_pickup_long, $this->emp_id);
                    if ($resp == false) {
                        if($Orders->source == 3){
                            $array = [
                                "msg" => "No drivers available with in the km.",
                                "order_id" => $Orders->id
                            ];
                            return Base::touser($array, true);
                        }else{
                            return Base::touser('No drivers available with in the km.', true);
                        }
                    }*/
                    $send = [
                        "pickup_latitude"   => $_pickup_ladd,
                        "pickup_longitude"  => $_pickup_long,
                        "logic_type"        => 1,
                        "order_id"          => $Orders->id,
                        "admin_id"          => $empId
                    ];
                    AutoAllocationLogic::dispatch($send);
               }
            }
        }
        if ($Orders->status == "Unallocated") {
            if ($Orders->source == 3) {
                return array(
                    'msg' => 'Order has been successfully created.', 
                    'status' => true, 
                    "_orderid" => $Orders->id,
                    "_taskid" => $task_ids,
                    "pickup_task_ids" => $pickup_task_ids,
                    "delivery_task_ids" => $delivery_task_ids
                );
            } else {
                return array('msg' => 'Order has been successfully created.', 
                "_orderid" => $Orders->id,
                "_taskid" => $task_ids,
                'status' => true);
            }
        }else{
            return array('msg' => 'Order has been successfully created.',
            "_orderid" => $Orders->id,
            "_taskid" => $task_ids,
            'status' => true);
        }
    }

    public function show($id) {
        // if ($this->admin || $this->backend || $this->manager) {

        $array = task::where('id', $id)->with('all_status')->first()->toArray();

        return Base::touser($array, true);
    }

    public function getWithStatus($id) {
        return Base::touser(task::with('cust_jobs')->get()->find($id), true);
    }

    public function order_status_update(Request $request){
        $data           = $request->input('data');
        $mt_order_id    = $data["order_id"];
        $status         = $data["status"];

        $id = $this->emp_id;
        $timezone = Base::client_time($id);

        $order = ApiOrders::find($mt_order_id);
        if(count($order) <= 0){
            return Base::touser("No orders found", false);
        }

        $order = ApiOrders::find($mt_order_id);
        $task_id = Base::getTaskId($mt_order_id);
        $task = task::find($task_id);
        $__task = task::where('mt_order_id','=',$mt_order_id)->orderBy('id')->get()->first();
        
        $ServiceInfo = [
            "order_id"      => $__task->order_id,
            "task_id"       => $task_id,
            "emp_id"        => $order->emp_id,
            "mt_order_id"   => $order->id
        ];
        $integrationService = IntegrationServiceFactory::create($order->source, $order->delivery_logic);
        $integrationService->updateStatus($__task->order_id, $data['status'], $ServiceInfo);

        $Orders = ApiOrders::find($mt_order_id);
        if ($data['status'] == "Allocated") {
            $Orders->status = 'Allocated';
        } else if ($data['status'] == "In-Progress" || $data['status'] == "Started Ride" || $data['status'] == "In Supplier Place" || $data['status'] == "Products Picked up") {
            $Orders->status = 'In-Progress';
            $Orders->is_order_confirmed = 1;
            $Orders->is_order_push_sent = 1;
        } else if ($data['status'] == 'Delivered') {
            $Orders->status = 'Delivered';
        } else if($status == "Declined"){
            $Orders->status = "Unallocated";
        } else if($data["status"] == "Delivered Back"){
            $Orders->status = $data['status'];
            Base::clone_full_order($mt_order_id);
        } else {
            $Orders->status = $data['status'];
        }
        $Orders->save();

        /* get all task id use order_id */
        $_task_id = Base::getTaskId($mt_order_id);
        foreach ($_task_id as $key => $task_id) {

            $task = task::find($task_id);
            if($data['status'] == "Declined" || $data["status"] == "Unallocated"){
                $task->status = "Unallocated";
                $task->task_status = 0;
                $task->save();
                $allocation = allocation::where('task_id', $task->id)->delete();
            }else{
                $task->status = $data['status'];
            }
            $task->save();

            /*get allocated emp_id */
            $_get = ScheduleTaskStatus::where('task_id','=',$task_id)->orderBy('id','DESC')->get()->first();

            $task_status = new ScheduleTaskStatus();
            $task_status->emp_id = $_get->emp_id;
            $task_status->task_id = $task_id;
            $task_status->address = '';
            $task_status->lat = isset($data['lat']) ? $data['lat'] : '';
            $task_status->long = isset($data['lng']) ? $data['lng'] : '';
            if($data["status"] == "Declined"){
                $task_status->status = "Unallocated";
            }else{
                $task_status->status = $data['status'];
            }
            $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->save();

            $user = \App\Models\User::find($_get->emp_id);
            $user->notify(new \App\Notifications\TaskUpdated($task, $user));
        }
        return Base::touser("Status Updated",true);
    }

    public function update(Request $request, $task_id) {
        $mt_order_id = $task_id;
        $id = $this->emp_id;
        $timezone = Base::client_time($id);
        $data = $request->input('data');

        $order = ApiOrders::find($mt_order_id);
        $task_id = Base::getTaskId($mt_order_id);
        $task = task::find($task_id);
        
        $delivery_logic = $data["delivery_logic"];
        $delivery_time = $data['multiple_delivery'];
        $picktime = $data['multiple_pickup'];
        
        $emps = UserPackage::where('user_id', $id)->first();
//        $date = new \DateTime($emps['beg_date']);
//        $begin_date = $date->format('Y-m-d');
        
        $date1 = new \DateTime($emps['end_date']);
        $end_date = $date1->format('Y-m-d') . " 23:59:59";

        $tasks = DB::table('emp_cust_schedule')
                    ->select('*')
                    ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                    ->where('orders.added_by', $id)
                    ->where('orders.created_at', '>=', $emps['beg_date'])
                    ->where('orders.created_at', '<=', $end_date)
                    ->get()->count();
        $total_task = (int) $emps["no_of_task"];
        $remaining_task = $total_task - $tasks;
        
        if ($delivery_logic == 1) {
            $taskDifference = count($delivery_time) - count($task_id);
            if($taskDifference > 0 && $taskDifference > $remaining_task) {
                return Base::touser('You have been reached your maximum task limit');
            }
        } 
        
        if ($delivery_logic == 2) {
            $taskDifference = count($picktime) - count($task_id);
            if($taskDifference > 0 && $taskDifference > $remaining_task) {
                return Base::touser('You have been reached your maximum task limit');
            }
        }
                
        $__task = task::where('mt_order_id','=',$mt_order_id)->orderBy('id')->get()->first();
        // dd($__task);
        $ServiceInfo = [
            "order_id"      => $__task->order_id,
            "task_id"       => $task_id,
            "emp_id"        => $order->emp_id,
            "mt_order_id"   => $order->id
        ];
        $integrationService = IntegrationServiceFactory::create($order->source, $order->delivery_logic);
        $integrationService->updateStatus($__task->order_id, $data['status'], $ServiceInfo);

        $lastindexarray = $data['multiple_delivery'];

        if ($data['status'] == "Allocated") {
            foreach ($picktime as $key => $picktime_) {
                if (strtotime($picktime_['picktime']) < strtotime($timezone)) {
                    return Base::touser('Pickup Time should not be before today.');
                }
            }
            $delivery_logic = $data["delivery_logic"];
            if ($delivery_logic == 1) {
                foreach ($delivery_time as $key => $deltime_) {
                    if (strtotime($deltime_['schedule']) <= strtotime($picktime[0]['picktime'])) {
                        return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
                    }
                }
            } else if ($delivery_logic == 2) {
                $end_picktime = end($data['multiple_pickup']);
                foreach ($delivery_time as $key => $deltime_) {
                    if (strtotime($deltime_['schedule']) <= strtotime($end_picktime['picktime'])) {
                        return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
                    }
                }
            } else if ($delivery_logic == 3) {
                if (strtotime($delivery_time[0]['schedule']) <= strtotime($picktime[0]['picktime'])) {
                    return Base::touser('The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.');
                }
            }
        }


        //$delivery_time = ($data['schedule_date_time']!='')?date('Y-m-d H:i:s',strtotime($data['schedule_date_time'])):'';  
        //$picktime   = ($data['pick_date_time']!='')?date('Y-m-d H:i:s',strtotime($data['pick_date_time'])):'';
        // if($data['status'] != 'Delivered' || $data['status'] != 'Delivered back' || $data['status'] != 'Declined' || $data['status'] != 'Started Ride'){
        //     if(strtotime($picktime) < strtotime($timezone)){
        //         return Base::touser('Pickup Time should not be before today.');
        //      }
        //      if(strtotime($delivery_time) < strtotime($picktime)){
        //         return Base::touser('Delivery Time should be greater than Pickup Time');
        //      }
        // }

        $rules = [
            //'emp'                => 'exists:user,user_id',
            'added_by' => 'exists:user,user_id',
                //'schedule_date_time' => 'required',
                //'status'             => 'required',
                //'type'               => 'required|string',
                //'method'             => 'required',
                //'notes'              => 'required|string',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        if (($data['status'] != 'Unallocated') && ($data['status'] != 'Canceled')) {
            if (empty($data['emp'])) {
                return Base::touser('Employee Required');
            }
        }

        foreach ($picktime as $key => $picktime1) {
            if (isset($picktime1['pick_address']) and empty(explode('|', ApiOrderScheduleController::latlong($picktime1['pick_address']))[0])) {
                return Base::touserloc('Please enter a valid pickup location', 'pickup');
            }
        }

        foreach ($delivery_time as $key => $delivery_time) {
            if (isset($delivery_time['cust_address']) and empty(explode('|', ApiOrderScheduleController::latlong($delivery_time['cust_address']))[0])) {
                return Base::touserloc('Please enter a valid receiver location', 'delivery');
            }
        }

        if (isset($data['sent_address']) and empty(explode('|', ApiOrderScheduleController::latlong($data['sent_address']))[0])) {
            return Base::touserloc('Sender Location is not valid kindly use drag and drop', 'sender');
        }


        $data = $request->input('data');
        $multi_delivery = $data['multiple_delivery'];
        $multi_pickup = $data['multiple_pickup'];




        $newdate = date('Y-m-d H:i:s', strtotime($picktime[0]['picktime']));


        $j = 1;
        $taskval = task::where('mt_order_id', $mt_order_id)->
                        get()->toArray();

        if ($data['is_multipickup'] == null) {
            $is_multipickup = false;
        } else {
            $is_multipickup = $data["is_multipickup"];
        }

        if ($data['is_multidelivery'] == null) {
            $is_multidelivery = false;
        } else {
            $is_multidelivery = $data["is_multidelivery"];
        }

        $Orders = ApiOrders::find($mt_order_id);
        $Orders->emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
        $Orders->added_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
        $Orders->order_start_time = $newdate;
        $getLastarray = end($lastindexarray);
        $Orders->order_end_time = $getLastarray['schedule'];
        $Orders->is_multipickup = $is_multipickup;
        $Orders->is_multidelivery = $is_multidelivery;
        $Orders->delivery_logic = $data["delivery_logic"];
        $Orders->status = $data['status'];
        if ($data['status'] == "Allocated") {
            $Orders->status = 'Allocated';
            $user_current_date = Carbon::parse($timezone);
            $user_current_date->addHours(24);
            $user_current_date = $user_current_date->format('Y-m-d H:i:s');

            $pick_time = Carbon::parse($newdate);
            $pick_time = $pick_time->format('Y-m-d H:i:s');
            if (strtotime($user_current_date) < strtotime($pick_time)) {
                $Orders->is_order_confirmed = 2;
            } else {
                $Orders->is_order_confirmed = 1;
                $Orders->is_order_push_sent = 1;
            }
        } else if ($data['status'] == "In-Progress" || $data['status'] == "Started Ride" || $data['status'] == "In Supplier Place" || $data['status'] == "Products Picked up") {
            $Orders->status = 'In-Progress';
            $Orders->is_order_confirmed = 1;
            $Orders->is_order_push_sent = 1;
        } else if ($data['status'] == 'Delivered') {
            $Orders->status = 'Delivered';
        } else if ($data['status'] == 'Delivered Back') {
            Base::clone_full_order($mt_order_id);
        }
        $Orders->save();

        if ($data["is_multidelivery"] == 1 || ($data["delivery_logic"] == 3)) {
            // dd('ss');
            $multi_delivery = $data['multiple_delivery'];
            $deleteitems = ItemMap::where('order_id', $Orders->id)->delete();
            $stage = 1;
            foreach ($multi_delivery as $key => $multival) {


                $task = isset($multival['id']) ? task::find($multival['id']) : new task();

                $task->schedule_date_time = isset($multival['schedule']) ? Base::tomysqldatetime($multival['schedule']) : date('Y-m-d H:i:s');

                if ($this->admin || $this->backend) {
                    if (empty($data['added_by'])) {

                        return Base::touser('Admin Must Provide Allocated Employee Value');
                    }
                    $task->added_by = $data['added_by'];
                } elseif ($this->manager) {
                    $task->added_by = $this->emp_id;
                } else {
                    $task->added_by = $this->emp_id;
                }

                $data = $request->input('data');

                if (isset($multival['cust_email'])) {
                    $multival['cust_email'] = $multival['cust_email'];
                } elseif (!isset($multival['cust_email']) && isset($multival['temp_cust_email'])) {
                    $multival['cust_email'] = $multival['temp_cust_email'];
                } else {
                    $multival['cust_email'] = '';
                }


                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : $this->emp_id;
                //$task->notes          = $multi_pickup[0]['notes'];
                $task->pick_address = $multi_pickup[0]['pick_address'];
                $task->order_id = $multival['order_id'];
                //$task->notes          = $multi_pickup[0]['notes'];
                $task->comments = $request->input('comments');
                $task->mob = $request->input('mob');
                $task->cust_email = $multival['cust_email'];
                //$task->sender_name          = $request->input('sender_name');
                //$task->sender_number          = $data['sender_number'];
                $task->receiver_name = $multival['receiver_name'];
                $task->picktime = isset($multi_pickup[0]['picktime']) ? Base::tomysqldatetime($multi_pickup[0]['picktime']) : date('Y-m-d H:i:s');
                $task->pickup_long = $multi_pickup[0]['pickup_long'];
                $task->pickup_ladd = $multi_pickup[0]['pickup_ladd'];
                $task->pickup_phone = $multi_pickup[0]['pickup_phone'];
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : '';
                $task->pickup_phone = isset($multi_pickup[0]['pickup_phone']) ? $multi_pickup[0]['pickup_phone'] : '';

                if (isset($data['sender_number'])) {
                    $task->sender_number = $data['sender_number'];
                }

                $data = $request->input('data');

                //$task->status      = $data['status'];
                if ($data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                    if ($order->source != 2) {
                        $task->status = 'Unallocated';
                    } else {
                        $task->status = $data['status'];
                    }
                } else {
                    $task->status = $data['status'];
                }

                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';

                if (isset($multival['cust_phone'])) {
                    $task->cust_phone = $multival['cust_phone'];
                }

                $task->loc_lat = $multival['loc_lat'];
                $task->loc_lng = $multival['loc_lng'];
                $task->cust_address = $multival['cust_address'];
                // $task->delivery_notes = $multival['delivery_notes'];
                $task->method = $data['method'];
                $is_new_address = false;
                $task->is_new_address = $is_new_address;

                if ((isset($data['is_geo_fence'])) && ($data['is_geo_fence'] == 1)) {
                    if (isset($data['geo_fence_meter'])) {
                        $task->geo_fence_meter = $data['geo_fence_meter'];
                        $task->is_geo_fence = $data['is_geo_fence'];
                    } else {
                        return Base::touser('Geo Fence Meter Required');
                    }
                } else {
                    $task->geo_fence_meter = null;
                    $task->is_geo_fence = 0;
                }

                if ($this->manager) {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 2;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                } else {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 1;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                }

                $task->product_weight = isset($multi_pickup[0]['product_weight']) ? $multi_pickup[0]['product_weight'] : 0;
                $task->product_size = isset($multi_pickup[0]['product_size']) ? $multi_pickup[0]['product_size'] : null;
                $task->time_to_delivery = isset($multi_pickup[0]['time_to_delivery']) ? $multi_pickup[0]['time_to_delivery'] : null;
                $task->time_requirement = isset($multi_pickup[0]['time_requirement']) ? $multi_pickup[0]['time_requirement'] : null;

                $task->product_length = isset($multi_pickup[0]['product_length']) ? $multi_pickup[0]['product_length'] : 0;
                $task->product_height = isset($multi_pickup[0]['product_height']) ? $multi_pickup[0]['product_height'] : 0;
                $task->product_breadth = isset($multi_pickup[0]['product_breadth']) ? $multi_pickup[0]['product_breadth'] : 0;

                $task->mt_order_id = $mt_order_id;
                $task->priority = $j;
                $task->images=isset($multival['documents'])?implode(',',$multival['documents']):"";

                $task->save();


                //item map
                foreach ($multival['delivery_notes1'] as $key => $value) {
                    $itemmap = new ItemMap();
                    $itemmap->item_id = $value;
                    $itemmap->stage = $stage;
                    $itemmap->order_id = $Orders->id;
                    // $itemmap->quantity = $Orders->quantity;
                    $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                    $itemmap->save();
                }
                
                if (isset($multival['delivery_notes2'])) {
                    foreach ($multival['delivery_notes2'] as $key => $value) {
                        $checkItem = Items::where('name',$value)->where('emp_id', $this->emp_id)->get()->count();
                        if ($checkItem == 0) {
                            $Items = new Items();
                            $Items->name = $value;
                            $Items->emp_id =  $this->emp_id;
                            $Items->save();
                            $item_id = $Items->id;
                        } else {
                             $checkItem = Items::where('name', $value)->where('emp_id', $this->emp_id)->get()->first();
                             $item_id = $checkItem->id;
                        }

                        $itemmap = new ItemMap();
                        $itemmap->item_id = $item_id;
                        $itemmap->stage = $stage;
                        $itemmap->order_id = $Orders->id;
                        $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                        $itemmap->save();
                    }
                }
                
                if (isset($multival['delivery_notes_item_quantity'])) {
                    //item map
                    foreach ($multival['delivery_notes_item_quantity'] as $key => $value) {
                        $checkItem = Items::where('name', $value['ItemName'])->where('emp_id', $this->emp_id)->get()->first();
                        $item_id = $checkItem->id;

                        $itemmap = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->first();
                        $itemmap->quantity = $value['Quantity'];
                        $itemmap->save();
                    }
                }
                
                $stage++;
                // item map end
                //check Customer exists 
                
                $getcust = Customer::where('contact_no', '=', $multival['cust_phone'])->where('emp_id', $this->emp_id)->get()->count();
            
                if ($getcust == 0) {
                    $cust_create = new Customer();
                    $cust_create->name = $multival['receiver_name'];
                    $cust_create->emp_id = $this->emp_id;
                    $cust_create->email = $multival['cust_email'];
                    $cust_create->address = $multival['cust_address'];
                    $cust_create->loc_lat = $multival['loc_lat'];
                    $cust_create->loc_lng = $multival['loc_lng'];
                    $cust_create->contact_no = $multival['cust_phone'];
                    $cust_create->save();
                }
                // end customer exists


                $emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
                //$status = isset($data['status']) ? $data['status'] : 'Unallocated';
                $status = "Unallocated";
                if ($data["status"] == "Unallocated" || $data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                    $task->task_status = 0;
                    $task->save();
                    $allocation = allocation::where('task_id', $task->id)->delete();
                }

                if (isset($data['status'])) {
                    if ($data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                        $status = 'Unallocated';
                    } else {
                        $status = $data['status'];
                    }
                } else {
                    $status = 'Unallocated';
                }

                $task_status = ScheduleTaskStatus::where('task_id', $task->id)->orderBy('created_at', 'desc')->first();

                // if (!empty($task_status)) {
                //     if ($task_status->emp_id == $emp_id && $task_status->status == $status) {
                //         // so update
                //     } else {
                //         $task_status = new ScheduleTaskStatus();
                //     }
                // } else {
                //     $task_status = new ScheduleTaskStatus();
                // }
                $task_status = new ScheduleTaskStatus();

                $task_status->emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
                $task_status->task_id = $task->id;
                $task_status->address = '';
                $task_status->lat = '';
                $task_status->long = '';
                $task_status->status = $status;
                $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->save();

                if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
                    if (empty($data['emp'])) {
                        return Base::touser('Employee Required');
                    }

                    $allocation = allocation::where('task_id', $task->id)->first();
                    if (count($allocation) < 1) {
                        $allocation = new allocation();
                    }

                    $allocation->emp = $data['emp'];
                    $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
                    $allocation->task_id = $task->id;
                    $allocation->save();

                    $user = \App\Models\User::find($allocation->emp);
                    $user->notify(new \App\Notifications\TaskUpdated($task, $user));

                    // if ($data['status'] == "Allocated") {

                    //     $userdata = \App\Models\User::where('user_id', $this->emp_id)->get();
                    //     if ($userdata[0]['mailnote'] == true) {
                    //         foreach ($multi_delivery as $key => $multival) {
                    //             $send_mail = 0;
                    //             if (isset($multival['cust_email']) && $multival['cust_email'] != '') {
                    //                 $email = $multival['cust_email'];
                    //                 $send_mail = 1;
                    //             } elseif (!isset($multival['cust_email']) && isset($multival['temp_cust_email'])) {
                    //                 $email = $multival['temp_cust_email'];
                    //                 $send_mail = 1;
                    //             } else {
                    //                 $email = '';
                    //                 $send_mail = 0;
                    //             }

                    //             if ($send_mail == 1) {
                    //                 $data = array('name' => "Liveanywhere", 'email' => $email, 'orderInfo' => $task);
                    //                 $hashed_random_password = str_random(8);
                    //                 session::put('data', 'https://delivery.manageteamz.com/api/track-order/' . $mt_order_id);
                    //                 session::put('mail', $email);

                    //                 $mail = Mail::send(['text' => 'mail'], $data, function($message) {

                    //                             $message->to(session::get('mail'), 'Customer Tracking')->subject
                    //                                     ('Order Confirmation Link');
                    //                             $message->from('info@manageteamz.com', 'Admin');
                    //                             // $cust = \App\Models\Customer::find($task->cust_id )->notify(new \App\Notifications\CustomerTracking($task, $user, Base::get_domin(),true));
                    //                         });
                    //             }
                    //         }
                    //     }
                    // }
                } else {
                    $task = task::find($task->id);
                    $task->task_status = 0;
                    $task->save();
                    $allocation = allocation::where('task_id', $task->id)->delete();
                }
                $j++;
            }/* end foreach */
        } else if ($data["is_multipickup"] == 1) {
            $deleteitems = ItemMap::where('order_id', $Orders->id)->delete();
            $stage = 1;
            foreach ($multi_pickup as $key => $picktime) {

                $task = isset($picktime['id']) ? task::find($picktime['id']) : new task();


                $task->schedule_date_time = isset($multi_delivery[0]['schedule']) ? Base::tomysqldatetime($multi_delivery[0]['schedule']) : date('Y-m-d H:i:s');

                if ($this->admin || $this->backend) {
                    if (empty($data['added_by'])) {

                        return Base::touser('Admin Must Provide Allocated Employee Value');
                    }
                    $task->added_by = $data['added_by'];
                } elseif ($this->manager) {
                    $task->added_by = $this->emp_id;
                } else {
                    $task->added_by = $this->emp_id;
                }

                $data = $request->input('data');
                $multi_delivery = $data['multiple_delivery'];

                if (isset($multi_delivery[0]['cust_email'])) {
                    $multi_delivery[0]['cust_email'] = $multi_delivery[0]['cust_email'];
                } elseif (!isset($multi_delivery[0]['cust_email']) && isset($multi_delivery[0]['temp_cust_email'])) {
                    $multi_delivery[0]['cust_email'] = $multi_delivery[0]['temp_cust_email'];
                } else {
                    $multi_delivery[0]['cust_email'] = '';
                }

                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : $this->emp_id;
                //$task->notes          = $picktime['notes'];
                $task->pick_address = $picktime['pick_address'];
                $task->order_id = $multi_delivery[0]['order_id'];
                //$task->notes          = $picktime['notes'];
                $task->comments = $request->input('comments');
                $task->mob = $request->input('mob');
                $task->cust_email = $multi_delivery[0]['cust_email'];
                //$task->sender_name          = $request->input('sender_name');
                //$task->sender_number          = $data['sender_number'];
                $task->receiver_name = $multi_delivery[0]['receiver_name'];
                $task->picktime = isset($picktime['picktime']) ? Base::tomysqldatetime($picktime['picktime']) : date('Y-m-d H:i:s');
                $task->pickup_long = $picktime['pickup_long'];
                $task->pickup_ladd = $picktime['pickup_ladd'];
                $task->pickup_phone = $picktime['pickup_phone'];
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : '';
                $task->pickup_phone = isset($picktime['pickup_phone']) ? $picktime['pickup_phone'] : '';

                if (isset($data['sender_number'])) {
                    $task->sender_number = $data['sender_number'];
                }

                //$task->status      = $data['status'];
                if ($data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                    $task->status = 'Unallocated';
                } else {
                    $task->status = $data['status'];
                }

                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';


                if (isset($multi_delivery[0]['cust_phone'])) {
                    $task->cust_phone = $multi_delivery[0]['cust_phone'];
                }

                $task->loc_lat = $multi_delivery[0]['loc_lat'];
                $task->loc_lng = $multi_delivery[0]['loc_lng'];
                $task->cust_address = $multi_delivery[0]['cust_address'];
                // $task->delivery_notes = $multi_delivery[0]['delivery_notes'];
                $task->method = $data['method'];
                $is_new_address = false;
                $task->is_new_address = $is_new_address;

                if ((isset($data['is_geo_fence'])) && ($data['is_geo_fence'] == 1)) {
                    if (isset($data['geo_fence_meter'])) {
                        $task->geo_fence_meter = $data['geo_fence_meter'];
                        $task->is_geo_fence = $data['is_geo_fence'];
                    } else {
                        return Base::touser('Geo Fence Meter Required');
                    }
                } else {
                    $task->geo_fence_meter = null;
                    $task->is_geo_fence = 0;
                }

                if ($this->manager) {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 2;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                } else {
                    if ($data['status'] != "Unallocated") {
                        $task->task_status = 1;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                }

                $task->product_weight = isset($picktime['product_weight']) ? $picktime['product_weight'] : 0;
                $task->product_size = isset($picktime['product_size']) ? $picktime['product_size'] : null;
                $task->time_to_delivery = isset($picktime['time_to_delivery']) ? $picktime['time_to_delivery'] : null;
                $task->time_requirement = isset($picktime['time_requirement']) ? $picktime['time_requirement'] : null;

                $task->product_length = isset($picktime['product_length']) ? $picktime['product_length'] : 0;
                $task->product_height = isset($picktime['product_height']) ? $picktime['product_height'] : 0;
                $task->product_breadth = isset($picktime['product_breadth']) ? $picktime['product_breadth'] : 0;

                $task->mt_order_id = $mt_order_id;
                $task->priority = $j;
                // $task->images=isset($picktime['documents'])?implode(',',$picktime['documents']):"";


                $task->save();


                $emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
                //$status = isset($data['status']) ? $data['status'] : 'Unallocated';
                if ($data["status"] == "Unallocated" || $data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                    $task->task_status = 0;
                    $task->save();
                    $allocation = allocation::where('task_id', $task->id)->delete();
                }
                $status = "Unallocated";
                if (isset($data['status'])) {
                    if ($data['status'] == 'Delivered Back' || $data['status'] == 'Declined') {
                        $status = 'Unallocated';
                    } else {
                        $status = $data['status'];
                    }
                } else {
                    $status = 'Unallocated';
                }

                $task_status = ScheduleTaskStatus::where('task_id', $task->id)->orderBy('created_at', 'desc')->first();


                if (!empty($task_status)) {

                    if ($task_status->emp_id == $emp_id && $task_status->status == $status) {
                        // so update
                    } else {
                        $task_status = new ScheduleTaskStatus();
                    }
                } else {
                    $task_status = new ScheduleTaskStatus();
                }
                $task_status = new ScheduleTaskStatus();
                $task_status->emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
                $task_status->task_id = $task->id;
                $task_status->address = '';
                $task_status->lat = '';
                $task_status->long = '';
                $task_status->status = $status;
                $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                $task_status->save();

                //item map
                foreach ($picktime['delivery_notes'] as $key => $value) {
                    $itemmap = new ItemMap();
                    $itemmap->item_id = $value;
                    $itemmap->stage = $stage;
                    $itemmap->order_id = $Orders->id;
                    $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                    $itemmap->save();
                }
                
                if (isset($picktime['delivery_notes3'])) {
                    foreach ($picktime['delivery_notes3'] as $key => $value) {
                        $checkItem = Items::where('name',$value)->where('emp_id', $this->emp_id)->get()->count();
                        if ($checkItem == 0) {
                            $Items = new Items();
                            $Items->name = $value;
                            $Items->emp_id = $this->emp_id;
                            $Items->save();
                            $item_id = $Items->id;
                        } else {
                             $checkItem = Items::where('name', $value)->where('emp_id', $this->emp_id)->get()->first();
                             $item_id = $checkItem->id;
                        }

                        $itemmap = new ItemMap();
                        $itemmap->item_id = $item_id;
                        $itemmap->stage = $stage;
                        $itemmap->order_id = $Orders->id;
                        $itemmap->created_at = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
                        $itemmap->save();
                    }
                }
                
                if (isset($picktime['delivery_notes_item_quantity'])) {
                    //item map
                    foreach ($picktime['delivery_notes_item_quantity'] as $key => $value) {
                        $checkItem = Items::where('name', $value['ItemName'])->where('emp_id', $this->emp_id)->get()->first();
                        $item_id = $checkItem->id;

                        $itemmap = ItemMap::where('item_id', $item_id)->where('order_id', $Orders->id)->where('stage', $stage)->first();
                        $itemmap->quantity = $value['Quantity'];
                        $itemmap->save();
                    }
                }
                
                $stage++;
                // item map end

                $getcust = Customer::where('contact_no', '=', $multi_delivery[0]['cust_phone'])->where('emp_id', $this->emp_id)->get()->count();
                if ($getcust == 0) {
                    $cust_create = new Customer();
                    $cust_create->name = $multi_delivery[0]['receiver_name'];
                    $cust_create->emp_id = $this->emp_id;
                    $cust_create->email = $multi_delivery[0]['cust_email'];
                    $cust_create->address = $multi_delivery[0]['cust_address'];
                    $cust_create->loc_lat = $multi_delivery[0]['loc_lat'];
                    $cust_create->loc_lng = $multi_delivery[0]['loc_lng'];
                    $cust_create->contact_no = $multi_delivery[0]['cust_phone'];
                    $cust_create->save();
                }
                // end customer exists

                if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
                    if (empty($data['emp'])) {
                        return Base::touser('Employee Required');
                    }

                    $allocation = allocation::where('task_id', $task->id)->first();
                    if (count($allocation) < 1) {
                        $allocation = new allocation();
                    }

                    $allocation->emp = $data['emp'];
                    $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
                    $allocation->task_id = $task->id;
                    $allocation->save();

                    $user = \App\Models\User::find($allocation->emp);
                    $user->notify(new \App\Notifications\TaskUpdated($task, $user));

                    // if ($data['status'] == "Allocated") {

                    //     $userdata = \App\Models\User::where('user_id', $this->emp_id)->get();
                    //     if ($userdata[0]['mailnote'] == true) {
                    //         foreach ($multi_delivery as $key => $multival) {
                    //             $send_mail = 0;
                    //             if (isset($multival['cust_email']) && $multival['cust_email'] != '') {
                    //                 $email = $multival['cust_email'];
                    //                 $send_mail = 1;
                    //             } elseif (!isset($multival['cust_email']) && isset($multival['temp_cust_email'])) {
                    //                 $email = $multival['temp_cust_email'];
                    //                 $send_mail = 1;
                    //             } else {
                    //                 $email = '';
                    //                 $send_mail = 0;
                    //             }

                    //             if ($send_mail == 1) {
                    //                 $data = array('name' => "Liveanywhere", 'email' => $email, 'orderInfo' => $task);
                    //                 $hashed_random_password = str_random(8);
                    //                 session::put('data', 'https://delivery.manageteamz.com/api/track-order/' . $mt_order_id);
                    //                 session::put('mail', $email);

                    //                 $mail = Mail::send(['text' => 'mail'], $data, function($message) {

                    //                             $message->to(session::get('mail'), 'Customer Tracking')->subject
                    //                                     ('Order Confirmation Link');
                    //                             $message->from('info@manageteamz.com', 'Admin');
                    //                             // $cust = \App\Models\Customer::find($task->cust_id )->notify(new \App\Notifications\CustomerTracking($task, $user, Base::get_domin(),true));
                    //                         });
                    //             }
                    //         }
                    //     }
                    // }
                } else {
                    $task = task::find($task->id);
                    $task->task_status = 0;
                    $task->save();
                    $allocation = allocation::where('task_id', $task->id)->delete();
                }
                $j++;
            }/* end foreach */
        }

        event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        if ($Orders->status == "Unallocated") {
            if($Orders->source == 3){
                $array = [
                    "msg" => "Order has been successfully updated.",
                    "order_id" => $Orders->id
                ];
                return Base::touser($array, true);
            }else{
                return Base::touser('Order has been successfully updated.', true);    
            }
        }else{
            return Base::touser('Order has been successfully updated.', true);
        }

    }

    public function destroy($id) {
        emp_cust::where('emp_cust_id', '=', $id)
                ->delete();

        $api = task::find($id);
        $api->delete();
        return Base::touser('Task Deleted', true);
    }

    public function empgpsdata(Request $request) {
        $data = $request->input('data');
        $start = $data['start_date'];
        $end = $data['end_date'];
        $start = Base::tomysqldatetime($data['start_date'] . ' 00:00:00');
        $end = Base::tomysqldatetime($data['end_date'] . ' 23:59:00');
        $gpsData = snapdata::
                        orderBy('timestamp', 'asc')->
                        where('user_id', $data['emp_id'])->
                        where('created_at', '<=', $end)->
                        where('created_at', '>=', $start)->
                        get()->toArray();
        $distInMeter = [];
        $distInMeter[] = 0;
        for ($x = 0; $x < count($gpsData) - 1; $x++) {

            if (($gpsData[$x]['activity'] == 'Start')) {

                $distInMeter[] = $distInMeter[count($distInMeter) - 1];
                $distInMeter[] = 0;
            } else {
                $data1 = $gpsData[$x];
                $data2 = $gpsData[$x + 1];
                $gpsData[$x]['path'] = [$data1['lat'], $data1['lng']];
                $gpsData[$x + 1]['path'] = [$data2['lat'], $data2['lng']];
                $coordA = Geotools::coordinate($gpsData[$x]['path']);
                $coordB = Geotools::coordinate($gpsData[$x + 1]['path']);
                $distance = Geotools::distance()->setFrom($coordA)->setTo($coordB);
                $distInMeter[count($distInMeter) - 1] = $distance->flat() + $distInMeter[count($distInMeter) - 1];
            }
        }
        $distInMeter = array_sum($distInMeter);

        $distInMeter = $distInMeter / 1000;

        $Summary = [
            'start' => $start,
            'end' => $end,
            'distance' => round($distInMeter, 2) . ' kms',
        ];


        $data = [];
        $data['visit_list'] = $Summary;

        return Base::touser($data, true);
    }

    public function custom_order(Request $request) {
        $data = $request->all();

        $count = $data['count'];

        if ($count > 0) {

            foreach ($data['orders'] as $key => $value) {

                $newdate = isset($value['fulfill_at']) ? Base::tomysqldatetime($value['fulfill_at']) : date('Y-m-d H:i:s');

                $delivery_date = Carbon::parse($newdate);
                $delivery_date->addHours(2);
                $delivery_date = $delivery_date->format('Y-m-d H:i:s');

                $is_multipickup = 0;
                $is_multidelivery = 0;
                $status = "Unallocated";

                $order_id = $value["id"];

                $count = task::where('order_id', $order_id)->get()->count();

                if ($count == 0) {
                    continue;
                }

                $Orders = new ApiOrders();
                $Orders->emp_id = $this->emp_id;
                $Orders->added_by = $this->emp_id;
                $Orders->order_start_time = $newdate;
                $Orders->order_end_time = $delivery_date;
                $Orders->is_multipickup = $is_multipickup;
                $Orders->is_multidelivery = $is_multidelivery;
                $Orders->delivery_logic = 3;

                $Orders->status = $status;

                $Orders->save();

                $j = 1;

                $task = new task();

                $task->schedule_date_time = $delivery_date;

                $task->added_by = $this->emp_id;

                $count = task::count();
                $order_id = $value["id"];

                if (isset($value['client_email'])) {
                    $client_email = $value['client_email'];
                } else {
                    $client_email = '';
                }

                $task->cust_id = $this->emp_id;

                $restaurant_state = isset($value['restaurant_state']) ? ',' . $value['restaurant_state'] : "";
                $restaurant_name = isset($value['restaurant_name']) ? $value['restaurant_name'] : '';
                $restaurant_street = isset($value['restaurant_street']) ? $value['restaurant_street'] : '';
                $restaurant_city = isset($value['restaurant_city']) ? $value['restaurant_city'] : '';
                $restaurant_state = isset($value['restaurant_state']) ? $value['restaurant_state'] : '';
                $restaurant_country = isset($value['restaurant_country']) ? $value['restaurant_country'] : '';

                $pick_address = $restaurant_name . ',' . $restaurant_street . ',' . $restaurant_city . $restaurant_state . ',' . $restaurant_country;

                $task->pick_address = $pick_address;
                $task->order_id = $order_id;
                //$task->comments       = $request->input('comments');
                //$task->mob            = $request->input('mob');
                $client_first_name = isset($value['client_first_name']) ? $value['client_first_name'] : '';
                $client_last_name = isset($value['client_last_name']) ? $value['client_last_name'] : '';

                $task->receiver_name = $client_first_name . $client_last_name;
                $task->cust_email = $client_email;
                $task->sender_name = $restaurant_name;
                $task->picktime = isset($value['fulfill_at']) ? Base::tomysqldatetime($value['fulfill_at']) : date('Y-m-d H:i:s');
                $task->pickup_long = isset($value['restaurant_longitude']) ? $value['restaurant_longitude'] : '';
                $task->pickup_ladd = isset($value['restaurant_latitude']) ? $value['restaurant_latitude'] : '';
                $task->sent_address = $pick_address;
                $task->sender_number = isset($value['restaurant_phone']) ? $value['restaurant_phone'] : '';

                $task->status = $status;
                $task->customer_pickupaddr_id = '';
                $task->customer_deliveryaddr_id = '';
                $task->cust_phone = isset($value['client_phone']) ? $value['client_phone'] : '';
                $task->loc_lat = isset($value['latitude']) ? $value['latitude'] : '';
                $task->loc_lng = isset($value['longitude']) ? $value['longitude'] : '';
                $task->cust_address = isset($value['client_address']) ? $value['client_address'] : '';
                $task->method = isset($value['type']) ? $value['type'] : '';
                $is_new_address = false;
                $task->is_new_address = $is_new_address;

                $task->geo_fence_meter = 200;
                $task->is_geo_fence = 1;


                if ($this->manager) {
                    if ($status != "Unallocated") {
                        $task->task_status = 2;
                        $task->approve_status = 1;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                } else {
                    if ($status != "Unallocated") {
                        $task->task_status = 1;
                        $task->approve_status = 0;
                    } else {
                        $task->task_status = 0;
                        $task->approve_status = 0;
                    }
                }


                $task->mt_order_id = $Orders->id;
                $task->priority = $j;

                $task->product_weight = 0;
                $task->product_size = null;
                $task->time_to_delivery = null;
                $task->time_requirement = null;
                $task->product_length = 0;
                $task->product_height = 0;
                $task->product_breadth = 0;

                $task->save();

                //item map
                foreach ($value['items'] as $key => $item) {

                    if ($item['type'] == 'item') {
                        $checkItem = Items::where('name', $item['name'])->where('emp_id', $this->emp_id)->get()->count();
                        if ($checkItem == 0) {
                            $Items = new Items();
                            $Items->name = $item['name'];
                            $Items->emp_id = $this->emp_id;
                            $Items->save();
                            $item_id = $Items->id;
                        } else {
                            $checkItem = Items::where('name', $item['name'])->where('emp_id', $this->emp_id)->get()->first();
                            $item_id = $checkItem->id;
                        }

                        $itemmap = new ItemMap();
                        $itemmap->item_id = $item_id;
                        $itemmap->stage = 1;
                        $itemmap->order_id = $Orders->id;
                        $itemmap->created_at = date("Y-m-d H:i:s");
                        $itemmap->save();
                    }
                }

                // item map end
                //check Customer exists 

                $getcust = Customer::where('contact_no', '=', $value['client_phone'])->where('emp_id', $this->emp_id)->get()->count();

                if ($getcust == 0) {
                    $cust_create = new Customer();
                    $cust_create->name = $client_first_name . $client_last_name;
                    $cust_create->emp_id = $this->emp_id;
                    $cust_create->email = isset($value['client_email']) ? $value['client_email'] : '';
                    $cust_create->address = isset($value['client_address']) ? $value['client_email'] : '';
                    ;
                    $cust_create->loc_lat = isset($value['latitude']) ? $value['latitude'] : '';
                    $cust_create->loc_lng = isset($value['longitude']) ? $value['longitude'] : '';
                    $cust_create->contact_no = isset($value['client_phone']) ? $value['client_phone'] : '';
                    $cust_create->save();
                }
                // end customer exists

                $task_status = new ScheduleTaskStatus();
                $task_status->emp_id = $this->emp_id;
                $task_status->task_id = $task->id;
                $task_status->address = '';
                $task_status->lat = '';
                $task_status->long = '';
                $task_status->status = $status;
                $task_status->timestamps = date("Y-m-d H:i:s");
                $task_status->created_time = date("Y-m-d H:i:s");
                $task_status->save();

                // Auto Allocation 

                if ((isset($value['restaurant_latitude'])) && isset($value['restaurant_longitude'])) {
                    $_pickup_ladd = $value['restaurant_latitude'];
                    $_pickup_long = $value['restaurant_longitude'];
                    $resp = self::auto_allocation_order_status_update($Orders->id, $_pickup_ladd, $_pickup_long, $this->emp_id);
                    if ($resp == false) {
                        return Base::touser('No drivers available with in the km.', true);
                    }
                }

                if (($task_status->status != 'Unallocated') && ($task_status->status != 'Canceled')) {
                    if (empty($data['emp'])) {
                        return Base::touser('Employee Required');
                    }

                    $allocation = new allocation();
                    $allocation->emp = isset($data['emp']) ? $data['emp'] : $this->emp_id;
                    $allocation->add_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
                    $allocation->task_id = $task->id;
                    $allocation->save();

                    $user = \App\Models\User::find($allocation->emp);
                    $user->notify(new \App\Notifications\TaskAllocated($task, $user));
                }
                $j++;
            }
            return Base::touser("Order Created", true);
        }
        return Base::touser('Order array Empty!..', false);
    }

    public function bigcom_request(Request $request){
        $data = $request->all();
        $store_producer_arr = explode('/',$data['producer']);
        $store_hash = $store_producer_arr[1];

        $user_id = User::where('bigcomm_hash',$store_hash)->pluck('user_id')->first();
        $admin_id = EmpMapping::where('emp_id',$user_id)->pluck('admin_id')->first();
        $bigcomm_token = User::where('bigcomm_hash',$store_hash)->pluck('bigcomm_token')->first();

        // getting order info
        $auth_url = "https://api.bigcommerce.com/stores/".$store_hash."/v2/orders/".$data['data']['id'];
        $agent = $_SERVER['HTTP_USER_AGENT'];
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
                "X-Auth-Token: ".$bigcomm_token
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
        }
        $xml = simplexml_load_string($curlresponse);
        $json = json_encode($xml, true);
        $decode = json_decode($json, true);

        // store info

        $store_url = "https://api.bigcommerce.com/stores/".$store_hash."/v2/store";
        $agent1 = $_SERVER['HTTP_USER_AGENT'];
        $store_curl = curl_init();
        curl_setopt_array($store_curl, array(
            CURLOPT_URL => $store_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERAGENT => $agent1,
            CURLOPT_HTTPHEADER => array(
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
                "X-Auth-Token: ".$bigcomm_token
            ) ,
        ));
        $storecurlresponse = curl_exec($store_curl);
        $store_err = curl_error($store_curl);

        curl_close($store_curl);
        if ($store_err)
        {
            return "cURL Error #:" . $store_err;
        }
        else
        {
            $store_json = json_decode($storecurlresponse, true);
        }
        $store_xml = simplexml_load_string($storecurlresponse);
        $store_json = json_encode($store_xml, true);
        $store_decode = json_decode($store_json, true);
        Log::info($store_decode);

        //Converts address into Lat and Lng
        $client = new Client(); //GuzzleHttp\Client
        $res =(string) $client->post("https://maps.googleapis.com/maps/api/geocode/json?address=".$store_decode['address'], [ 'form_params' => ['key'=>'AIzaSyAy6TF0j10UTSGVh5bDtaDZAxKzSAf_Zz0']])->getBody();
        $json_res =json_decode($res);
        Log::info($json_res);
        $shipping_lat =$json_res->results[0]->geometry->location->lat;
        $shipping_lng =$json_res->results[0]->geometry->location->lng;

        // product info

        $product_url = "https://api.bigcommerce.com/stores/".$store_hash."/v2/store";
        $agent2 = $_SERVER['HTTP_USER_AGENT'];
        $product_curl = curl_init();
        curl_setopt_array($product_curl, array(
            CURLOPT_URL => $product_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_USERAGENT => $agent2,
            CURLOPT_HTTPHEADER => array(
                "accept: */*",
                "accept-language: en-US,en;q=0.8",
                "content-type: application/json",
                "X-Auth-Token: ".$bigcomm_token
            ) ,
        ));
        $productcurlresponse = curl_exec($product_curl);
        $product_err = curl_error($product_curl);

        curl_close($product_curl);
        if ($product_err)
        {
            return "cURL Error #:" . $product_err;
        }
        else
        {
            $product_json = json_decode($productcurlresponse, true);
        }
        $product_xml = simplexml_load_string($productcurlresponse);
        $product_json = json_encode($product_xml, true);
        $product_decode = json_decode($product_json, true);

        Log::info($product_decode);

        $requestData = [
            "status" => "Unallocated",
            "added_by" => $user_id,
              "type" => 0,
              "source" => 2,
              "method" => "Pickup",
              "is_multidelivery" => false,
              "is_multipickup" => false,
              "schedule" => date('Y-m-d H:i:s',strtotime($decode['date_created'])+(60*15)),
              "picktime" => date('Y-m-d H:i:s',strtotime($decode['date_created'])+(60*15)),
              "loc_lat" => $shipping_lat,
              "loc_lng" => $shipping_long,
              "pickup_ladd" => "",
              "pickup_long" => "",
              "sent_ladd" => "",
              "geo_fence_meter" => "200",
              "time_requirement" => "",
              "time_to_delivery" => "",
              "sent_long" => "",
              "showpick" => false,
              "showdeliv" => false,
              "multiple_delivery" => [
                array(
                  "schedule" => date('Y-m-d H:i:s',strtotime($decode['date_created'])+(60*30)),
                  "order_id" => $decode['date_created'],
                  "delivery_notes1" => [
                    
                  ],
                  "delivery_notes2" => [
                    ""
                  ],
                  "cust_name" => $decode['billing_address']['first_name'],
                  "cust_phone" => $decode['billing_address']['phone'],
                  "cust_email" => $decode['billing_address']['email'],
                  "temp_cust_email" => "",
                  "cust_address" => $decode['billing_address']['street_1'],
                  "loc_lat" => $shipping_lat,
                  "loc_lng" => $shipping_long,
                  "cust_id" => $decode['customer_id'],
                  "receiver_name" => $decode['billing_address']['first_name']
                )
                ],
                "multiple_pickup" => [
                    array(
                      "picktime" => date('Y-m-d H:i:s',strtotime($decode['date_created'])+(60*15)),
                      "pick_address" => $store_decode['address'],
                      "delivery_notes"  => [
                        
                      ],
                      "pickup_ladd" => "",
                      "pickup_long" => "",
                      "product_weight" => "",
                      "time_to_delivery" => "",
                      "time_requirement" => "",
                      "product_length" => "",
                      "product_height" => "",
                      "product_breadth" => ""
                    )
                ],
                "delivery_logic" => 3,
                "sender_name" => $store_decode['first_name'],
                "sender_number" => $store_decode['phone'],
                "sent_address" => $store_decode['address'],
                "is_geo_fence" => 1
        ];

        $returnData = $this->createOrder($requestData, $user_id, $admin_id, $this->backend, $this->manager, 'sender_name', 
                "__", $decode['billing_address']['phone']);
        Log::info('_______________________________________');
        Log::info($returnData);
        Log::info('_______________________________________');

        return Base::touser($data,true); 
    }
    public function OrderImageUpload(Request $request){

        $order_id=$request->order_id;
        $task_id=$request->task_id;

        $request->validate([
            'file' => 'required',
            'file.*' => 'required|mimes:csv,jpg,jpeg,tsv,txt,xlx,xls,xlsx,pdf,doc,docx,png|max:2048'
        ]);   
        
        foreach($request->file('file') as $file){
            $fileModel = new OrderImage();                     
            $fileName = $order_id.'_'.time().'_'.$file->getClientOriginalName();

            $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');

            $filePath=Storage::disk('s3')->url($t);

            $fileModel->order_id =$order_id;
            $fileModel->task_id =$task_id;
            $fileModel->image_name =$fileName;
            $fileModel->image_path = $filePath;
    
            $fileModel->save();      
        }
        return Base::touser('Successfully Uploaded',true); 
    
        
    }
    public function OrderImageUpdate(Request $request){

    $order_id=$request->order_id;
    $task_id=$request->task_id;
    $request->validate([
        'file' => 'required',
        'file.*' => 'required|mimes:csv,jpg,jpeg,tsv,txt,xlx,xls,xlsx,pdf,doc,docx,png|max:2048'
    ]);
    $filter_data=OrderImage::where('order_id',$order_id)->delete();
    if($filter_data==0){

        return Base::touser('Order ID cant finded',true); 

    }
    
    foreach($request->file('file') as $file){
        $fileModel = new OrderImage();                     
        $fileName = $order_id.'_'.time().'_'.$file->getClientOriginalName();
        $filePath =$file->store('order_images', ['disk' => 'public']);
        $fileModel->order_id =$order_id;
        $fileModel->task_id =$task_id;
        $fileModel->image_name =$fileName;
        $fileModel->image_path = '/uploads/' . $filePath;

        $fileModel->save();      
    }
    return Base::touser('Successfully Updated',true); 
        
    }
    public function PickupImageUpload(Request $request){

        $order_id=$request->order_id;
        $task_id=$request->task_id;
      
        // $request->validate([
        //     'file' => 'required',
        //     'file.*' => 'required|mimes:csv,jpg,jpeg,tsv,txt,xlx,xls,xlsx,pdf,doc,docx,png|max:2048'
        // ]); 
        $is_pickup=0;
        $is_delivery=0;
        if($request->input('data')=="is_pickup"){
            $is_pickup=1;
        }
        else{
            $is_delivery=1;
        }
        $filter_data=task::where('id',$task_id)->where('mt_order_id',$order_id)->get()->count();
        if($filter_data==0){
    
            return Base::touser('Order ID cant finded',true); 
    
        }
        Log::info($request);
        $fileModel=task::where('id',$task_id)->first();

        if($request->hasFile("signature")){
            $sign_file=$request->file('signature');
            Log::info("#####if");
            Log::info($sign_file);

            
            $sign = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $sign_file, 'public');
            $filePath=Storage::disk('s3')->url($sign);
            Log::info($filePath);
            $fileModel->signature=$filePath;
            // $fileModel->save();
            Log::info($fileModel);

        }
        if(!empty($request->file('file'))){
            foreach($request->file('file') as $file){
                if($is_pickup==1){
                    // $fileModel=task::find($task_id);
                    $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');    
                    $filePath=Storage::disk('s3')->url($t);
                    $fileModel->mobile_pick_images=$filePath;  
                    // $fileModel->save();      
                }
                else{
                    // $fileModel=task::find($task_id);
                    $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');    
                    $filePath=Storage::disk('s3')->url($t);
                    $fileModel->mobile_delivery_images=$filePath;  
                }

                // $fileModel=task::find($task_id);
                // $fileModl=task::where('id',$task_id)->pluck('mobile_images');
    
                // // dd($file);
                // $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');
    
                // $filePath=Storage::disk('s3')->url($t);
    
                // if($fileModl[0]==null){

                //     $fileModel->mobile_images=$filePath;  
                // }
                // else{
                //     $fileModel->mobile_images=$fileModl[0].','.$filePath;   
                // }
                
                // $fileModel->save();      
            }
        }
        $fileModel->save(); 

       
        return Base::touser('Successfully Uploaded',true); 
    
        
      }
}