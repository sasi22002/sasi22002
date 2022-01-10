<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\Customer;
use App\Models\User;
use App\Models\EmpCustSchedule as task;
use App\Models\EmpSchedule as allocation;
use App\Models\ScheduleTaskStatus;
use App\Models\TravelHistory as api;
use App\Models\SnapData as snapdata;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Toin0u\Geotools\Facade\Geotools;
use Validator;
use \DateTime;
use \DateTimeZone;
use Session;
use Mail;
use App\Models\timezone;
use App\Jobs\TaskDuration;
use App\Models\ApiOrders;
use App\Models\ItemMap;
use App\Models\AutoAllocation;
use App\Jobs\AutoAllocationLogic;
use App\Http\Services\IntegrationServiceFactory;
use App\Models\EmpMapping;
use App\Models\OrderImage;
use Carbon\Carbon;
use App\Models\Questions;

class ApiEmpScheduleController extends Controller {

    public function order_details(Request $request, $order_id){
        $result = [];
        $orders_data = ApiOrders::where('id',$order_id)->get()->first();
            
        $array = task::where('mt_order_id', '=', $orders_data['id'])
                        ->with('cust', 'emp_info')->get()->toArray();

        $stage = count($array);
        for ($i = 0; $i < count($array); $i++) {
            $getitems = ItemMap::where('order_id', $array[$i]['mt_order_id'])->where('stage', $i + 1)->with('Items')->get()->toArray();
            $c = Questions::where('id','=',$array[$i]["reason_id"])->get()->count();
            if($c > 0){
                $reason = Questions::where('id','=',$array[$i]["reason_id"])->get()->first();
            }

            $array[$i]['items'] = $getitems;
            if(($array[$i]['images']!="") and ($array[$i]['images']!="[]")){
                $pics= explode(',',$array[$i]['images']);
            }
            else{
                $pics=[];
            }
            $array[$i]["images"] = $pics;

            if ($orders_data['delivery_logic'] == 1) {
                $array[$i]["source"] = "Delivery Address";
            } else if ($orders_data['delivery_logic'] == 2) {
                $array[$i]["source"] = "Pickup Address";
            } else if ($orders_data['delivery_logic'] == 3) {
                $array[$i]["source"] = "Delivery Address";
            }

            $_emp_id = $array[$i]['allocated_emp_id'];
            if (!empty($_emp_id)) {

                $array[$i]['allocated_emp_id'] = $_emp_id;
                $user = User::where('user_id', '=', $_emp_id)->first();
                $array[$i]["driver_name"] = $user["first_name"];
                $array[$i]["driver_phone_no"] = $user["phone"];
                $array[$i]["allocated_emp"] = $user["first_name"];
                $array[$i]["profile_image"] = $user["profile_image"];
                $array[$i]["driver_employee_lat"] = $user["employee_lat"];
                $array[$i]["driver_employee_lng"] = $user["employee_lng"];
                $array[$i]["driver_email"] = $user["email"];
                $array[$i]["reasons"] = isset($reason["questions"])?$reason["questions"]:'';
                $array[$i]["other_reasons"] = $array[$i]["reason"];
                $array[$i]["remarks"] = $array[$i]["remarks"];
                $orders_data["driver_name"] = $user["first_name"];
                $orders_data["driver_phone_no"] = $user["phone"];
                $orders_data["allocated_emp"] = $user["first_name"];
                $orders_data["profile_image"] = $user["profile_image"];
                $orders_data["driver_employee_lat"] = $user["employee_lat"];
                $orders_data["driver_employee_lng"] = $user["employee_lng"];
                $orders_data["driver_email"] = $user["email"];

            }

            $stage--;
        }
        
        $ord = ["order" => array()];
        if (!empty($array)) {
            #$merge = array('order' => $array);
            $ord = $orders_data;
            $ord["order"] = $array;
            #$order_push = array_merge($merge, $orders_data);
            // $merge = $merge[$orders_data];
            return Base::touser($ord, true);
            #array_push($result, $ord);
        }
        
        return Base::touser([], true); 
    }

    public function index(Request $request) {
        $result = [];
        // dd($this);
        if ($this->admin || $this->backend) {
            $array = task::with('cust', 'emp_info')->orderBy('picktime', 'asc')->all()->toArray();
        } elseif ($this->manager) {
            $belongs_emp = Base::getEmpBelongsUser($this->emp_id);

            $start = Base::tomysqldatetime($request->input('date') . ' 00:00:00');
            $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');

            if ($request->input('date')) {
                $start = Base::tomysqldatetime($request->input('date') . ' 00:00:00');
                $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');

                $array = task::where('cust_id', $this->emp_id)
                        ->where('picktime', '<=', $end)
                        ->where('picktime', '>=', $start)
                        ->with('cust', 'emp_info')
                        ->orderBy('picktime', 'desc')
                        ->get()
                        ->toArray();
            } elseif ($request->input('start') && $request->input('end') && $request->input('emp')) {
                $start = Base::tomysqldate($request->input('start')) . ' 00:00:00';
                $end = Base::tomysqldate($request->input('end')) . ' 23:59:00';
                
                $orders = ApiOrders::where('emp_id', $request->input('emp'))
                    ->where('added_by','=',$this->emp_id)
                    ->where([['order_start_time', '>=', $start], ['order_end_time', '<=', $end]])
                    ->whereIn('status', ['In-Progress', 'Allocated', 'Accepted', 'Delivered'])
                    //->where('order_start_time','<=',$start)
                    ->get()
                    ->toArray();
                    $datas = [];
                    $_input_id = $request->input('emp');
                    if(!array_key_exists($_input_id, $datas)){
                        $datas[$_input_id] = [
                            "Allocated"     => 0,
                            "In_Progress"   => 0,
                            "Accepted"      => 0,
                            "Delivered"     => 0
                        ];
                    }

                    foreach ($orders as $key => $value) {
                        
                        if($value["status"] == "Allocated"){
                            $datas[$_input_id]["Allocated"] = $datas[$_input_id]["Allocated"]+1;
                        }
                        if($value["status"] == "In-Progress"){
                            $datas[$_input_id]["In_Progress"] = $datas[$_input_id]["In_Progress"]+1;
                        }
                        if($value["status"] == "Accepted"){
                            $datas[$_input_id]["Accepted"] = $datas[$_input_id]["Accepted"]+1;
                        }
                        if($value["status"] == "Delivered"){
                            $datas[$_input_id]["Delivered"] = $datas[$_input_id]["Delivered"]+1;
                        }
                    }
                    return Base::touser($datas, true);
            }else{
                $array = task::where('emp_id', $this->emp_id)->with('cust', 'emp_info')->get()->toArray();
            }
        } else {
            $today = date('Y-m-d');
            if ($request->input('date')) {
                $value = Base::tomysqldate($request->input('date'));
            } else {
                $value = date('Y-m-d');
            }
            $start = Base::tomysqldatetime($value . ' 00:00:00');
            $end = Base::tomysqldatetime($request->input('date') . ' 23:59:00');
            
            $filter_status = '';
            
            if ($request->input('filterStatus') != '') {
                $filter_status = $request->input('filterStatus');
            }
            
            if ($value == $today && $filter_status == '') {
                $start = Carbon::parse($start);
                $start->addHours(-48);
                $start = $start->format('Y-m-d H:i:s');
            }

            $orders = ApiOrders::where('emp_id', $this->emp_id)
                    /* ->where(function($query) use ($start, $end) {
                      return $query->where([['order_start_time', '<=', $end], ['order_start_time', '>=', $start]])
                      ->orWhere('status', '=', 'Allocated');
                      }) */
                    //->where([['order_start_time', '<=', $end], ['order_start_time', '>=', $start]])
                    ->whereIn('status', ['In-Progress', 'Allocated', 'Accepted', 'Delivered'])
                    ->where([['order_end_time', '>=', $start], ['order_start_time', '<=', $end]])
                    //->where('order_start_time','<=',$start)
                    ->get()
                    ->toArray();

            $Allocated = [];
            $InProgress = [];
            $Incomplete = [];
            $Delivered = [];
            foreach ($orders as $orders_data) {
                $orderEndTime = Carbon::parse($orders_data["order_end_time"]);
                $orderEndDate = $orderEndTime->format('Y-m-d H:i:s');
                
                if ($orders_data["status"] == "Delivered" && strtotime($today) > strtotime($orderEndDate) && $filter_status == "") {
                    continue;
                }
                
                $array = task::where('mt_order_id', '=', $orders_data['id'])
                                // ->where('picktime', '<=', $end)
                                // ->where('picktime', '>=', $start)
                                ->where('schedule_date_time', '>=', $orders_data["order_start_time"])
                                // ->where('schedule_date_time','>=',$start)
                                //->whereIn('task_status',[2])
                                //->whereIn('approve_status',[1])
                                ->whereIn('status', ['Allocated', 'In-Progress', 'Started Ride', 'In Supplier Place', 'Products Picked up', 'In-Progress', 'Delivered'])
                                ->with('cust', 'emp_info')->get()->toArray();
                $stage = count($array);
                for ($i = 0; $i < count($array); $i++) {
                    $getitems = ItemMap::where('order_id', $array[$i]['mt_order_id'])->where('stage', $i + 1)->with('Items')->get()->toArray();

                    $array[$i]['items'] = $getitems;
                    $images=$array[$i]['images'];
                    // $mobile_pick_images=$array[$i]['mobile_pick_images'];
                    // $mobile_del_images=$array[$i]['mobile_delivery_images'];

                    // $array[$i]['images']=explode(',',$images);
                    if(($images!="") and ($images!="[]")){
                        // $pics= explode(',',$array['images']);
                        $array[$i]['images']=explode(',',$images);

                    }
                    else{
                        $array[$i]['images']=[];
                    }
                    
                    // $array[$i]['mobile_pick_images']=explo;
                    
                    if ($orders_data['delivery_logic'] == 1) {
                        $array[$i]["source"] = "Delivery Address";
                    } else if ($orders_data['delivery_logic'] == 2) {
                        $array[$i]["source"] = "Pickup Address";
                    } else if ($orders_data['delivery_logic'] == 3) {
                        $array[$i]["source"] = "Delivery Address";
                    }

                    $_emp_id = $array[$i]['allocated_emp_id'];
                    if (!empty($_emp_id)) {
                        $array[$i]['allocated_emp_id'] = $_emp_id;
                        $user = User::where('user_id', '=', $_emp_id)->first();
                        $array[$i]["driver_name"] = $user["first_name"];
                        $array[$i]["driver_phone_no"] = $user["phone"];
                        $array[$i]["allocated_emp"] = $user["first_name"];
                        $array[$i]["profile_image"] = $user["profile_image"];
                        $array[$i]["driver_employee_lat"] = $user["employee_lat"];
                        $array[$i]["driver_employee_lng"] = $user["employee_lng"];
                        $orders_data["driver_name"] = $user["first_name"];
                        $orders_data["driver_phone_no"] = $user["phone"];
                        $orders_data["allocated_emp"] = $user["first_name"];
                        $orders_data["profile_image"] = $user["profile_image"];
                        $orders_data["driver_employee_lat"] = $user["employee_lat"];
                        $orders_data["driver_employee_lng"] = $user["employee_lng"];
                    }

                    $stage--;
                }
                if (!empty($array)) {
                    $merge = array('order' => $array);
                    $order_push = array_merge($merge, $orders_data);
                    array_push($result, $order_push);
                }
            }

            // foreach ($result as $key => $data) {
            //     return $data["order"][$key];
            //     if ($data['order'][$key]['status'] == "Allocated") {
            //      $Allocated[] = $data;
            //     } elseif ($data['order'][$key]['status'] == "In-Progress") {
            //         $InProgress[] = $data;
            //     }elseif ($data['order'][$key]['status'] == "Started Ride") {
            //         $InProgress[] = $data;
            //     } elseif ($data['order'][$key]['status'] == "In Supplier Place") {
            //         $InProgress[] = $data;
            //     } elseif ($data['order'][$key]['status'] == "Products Picked up") {
            //         $InProgress[] = $data;
            //     } elseif ($data['order'][$key]['status'] == "In-Progress") {
            //         $InProgress[] = $data;
            //     } 
            //      elseif ($data['order'][$key]['status'] == "Incomplete") {
            //       $Summary = self::gpsData($data['order'][$key]['id'],false);
            //         if($Summary == 'error')
            //         {
            //                $data['order'][$key]['task_info'] = new \stdClass;
            //         }
            //         else
            //         {
            //                   $data['order'][$key]['task_info'] = $Summary;
            //         }
            //         $Incomplete[] = $data;
            //     } elseif ($data['order'][$key]['status'] == "Delivered") {
            //                      $Summary = self::gpsData($data['order'][$key]['id'],false);
            //         if($Summary == 'error')
            //         {
            //                $data['order'][$key]['task_info'] = new \stdClass;
            //         }
            //         else
            //         {
            //                   $data['order'][$key]['task_info'] = $Summary;
            //         }
            //         $Delivered[] = $data;
            //     } else {
            //     }
            // }
            // if($request->input('filterStatus') == 'deliveries')
            // {
            //     $dataBag = array_merge(
            //     $Incomplete,
            //     $Delivered);
            // }
            // else
            // {
            // $dataBag = array_merge(
            //     $InProgress,
            //     $Allocated,
            //     $Incomplete,
            //     $Delivered);
            // }

            if (\Request::get('page')) {
                $perPage = 10;
                $pageStart = \Request::get('page', 1);
                $offSet = ($pageStart * $perPage) - $perPage;
                $itemsForCurrentPage = array_slice($result, $offSet, $perPage);

                $paginator = new LengthAwarePaginator($itemsForCurrentPage, count($result), $perPage);

                $paginator->withPath(url()->current() . '?date=' . $value);

                return $paginator;
            }

            return Base::touser($result, true);
        }
        return Base::touser($result, true);
    }

    public function testorder(Request $request, $order_id) {
        $new_orders = [];
        $orders = ApiOrders::where('id', '=', $order_id)
                        ->get()->toArray();

        foreach ($orders as $orders_data) {

            $array = task::where('mt_order_id', '=', $orders_data['id'])
                            //->where('added_by', $this->emp_id)-
                            ->with('cust', 'emp_info')->orderBy('picktime', 'desc')->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {
                $_emp_id = $array[$i]['allocated_emp_id'];
                if ($orders_data['delivery_logic'] == 1) {
                    $array[$i]["source"] = "Pickup Address";
                } else if ($orders_data['delivery_logic'] == 2) {
                    $array[$i]["source"] = "Delivery Address";
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
            }

            if (count($array) > 0) {
                $merge = array('order' => $array);
                $order_push = array_merge($merge, $orders_data);
                array_push($new_orders, $order_push);
            }
        }


        return $new_orders;
    }

    public function get_socket_order($order_id) {
        $new_orders = [];
        $orders = ApiOrders::where('id', '=', $order_id)
                        ->get()->toArray();

        foreach ($orders as $orders_data) {

            $array = task::where('mt_order_id', '=', $orders_data['id'])
                            //->where('added_by', $this->emp_id)-
                            ->with('cust', 'emp_info')->orderBy('picktime', 'desc')->get()->toArray();

            for ($i = 0; $i < count($array); $i++) {
                $_emp_id = $array[$i]['allocated_emp_id'];
                if ($orders_data['delivery_logic'] == 1) {
                    $array[$i]["source"] = "Pickup Address";
                } else if ($orders_data['delivery_logic'] == 2) {
                    $array[$i]["source"] = "Delivery Address";
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
            }

            if (count($array) > 0) {
                $merge = array('order' => $array);
                $order_push = array_merge($merge, $orders_data);
                array_push($new_orders, $order_push);
            }
        }
        return $new_orders;
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

        $_order_id = Base::getOrderId($task_id);
        $order = ApiOrders::find($_order_id);  

        if ($data['status'] == 'Started Ride') {
            $_order_id = Base::getOrderId($task_id);

            $Orders = ApiOrders::find($_order_id);
            $Orders->status = "In-Progress";
            $Orders->save();

            $_task_id = Base::getTaskId($_order_id);
            foreach ($_task_id as $key => $task_id) {
                $task = task::find($task_id);
//                if ($data['status'] == 'Declined') {
//
//                    $temp = allocation::where('task_id', $task_id)->
//                                    where('emp', $this->emp_id)->delete();
//                    $data['status'] = 'Unallocated';
//                } else {
//                    
//                }
                $task = task::where('id', $task_id)->first();
                $task->status = $data['status'];
                $task->update();
                
                $order_id = $task->order_id;

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
                if ($data['status'] == 'Delivered') {
                    TaskDuration::dispatch($this->emp_id, $task_id);
                }
                event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
            }
            $ServiceInfo = [
                "order_id"      => $order_id,
                "task_id"       => $_task_id,
                "emp_id"        => $Orders->emp_id,
                "mt_order_id"   => $Orders->id
            ];
            $integrationService = IntegrationServiceFactory::create($Orders->source, $Orders->delivery_logic);
            $integrationService->updateStatus($task->order_id, $data['status'], $ServiceInfo);
            return Base::touser('Status Updated', true);
        } else {
            $__task = task::where('mt_order_id','=',$order->id)->orderBy('id')->get()->first();
            $ServiceInfo = [
                "order_id"      => $__task->order_id,
                "task_id"       => $task_id,
                "emp_id"        => $order->emp_id,
                "mt_order_id"   => $order->id
            ];
            $integrationService = IntegrationServiceFactory::create($order->source, $order->delivery_logic);
            $integrationService->updateStatus($__task->order_id, $data['status'], $ServiceInfo);
        }

        $task = task::find($task_id);
        if ($data['status'] == 'Declined') {

            $temp = allocation::where('task_id', $task_id)->
                            where('emp', $this->emp_id)->delete();
            $data['status'] = 'Unallocated';
        } else {
            
        }


        $task = task::where('id', $task_id)->first();
        $task->status = $data['status'];
        $task->update();

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
        if ($data['status'] == 'Delivered') {
            TaskDuration::dispatch($this->emp_id, $task_id);
        }
        event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        return Base::touser('Status Updated', true);
    }

    public function getOrderSummary(Request $request, $order_id) {
        return self::gpsorderData($order_id);
    }

    public function gpsorderData($order_id, $apicall = true) {

        $gpsorders = array();

        try {
            $task_id = Base::getTaskId($order_id);
            $tasks = task::whereIn('id', $task_id)->with('all_status')->get()->toArray();
        } catch (\Exception $e) {
            if ($apicall) {
                return Base::touser('Task not found');
            } else {
                return 'error';
            }
        }

        if (count($tasks) < 1) {
            if ($apicall) {
                return Base::touser('Task not found');
            } else {
                return 'error';
            }
        } else {

            $summary_data = array();
            foreach ($tasks as $key => $task) {
                if (count($task['all_status']) < 1) {
                    if ($apicall) {
                        return Base::touser('Task Status not found');
                    } else {
                        return 'error';
                    }
                }

                // if ($apicall) {
                //     if ((Base::mobile_header() == 1) && ($task['allocated_emp_id'] != $this->emp_id)) {
                //         return Base::touser('Task Status not belongs to you');
                //     }
                // }



                $taskStatus = array_reverse($task['all_status']);


                $picks = array_first($taskStatus, function ($value, $key) use ($task) {
                    if ($value['emp_id'] == $task['allocated_emp_id']) {
                        return $value['status'] == 'Products Picked up';
                    }
                });

                $ride = array_first($taskStatus, function ($value, $key) use ($task) {
                    if ($value['emp_id'] == $task['allocated_emp_id']) {
                        return $value['status'] == 'Started Ride';
                    }
                });
                $allocated = array_first($taskStatus, function ($value, $key) use ($task) {

                    if ($value['emp_id'] == $task['allocated_emp_id']) {
                        return $value['status'] == 'In Supplier Place';
                    }
                });
                $Delivered = array_first($taskStatus, function ($value, $key) use ($task) {

                    if ($value['emp_id'] == $task['allocated_emp_id']) {

                        return $value['status'] == 'Delivered';
                    }
                });
                $Incomplete = array_first($taskStatus, function ($value, $key) use ($task) {

                    if ($value['emp_id'] == $task['allocated_emp_id']) {
                        return $value['status'] == 'Delivered back';
                    }
                });


                if ($picks || $ride) {
                    if ($Delivered || $Incomplete) {

                        $data = $Delivered ? $Delivered : $Incomplete;

                        if ($data['timestamps']) {
                            $endtime = $data['timestamps'];
                            $end = strtotime("+3 minutes", strtotime($endtime));
                            $end = date('h:i:s', $end);
                        } else {
                            $endtime = $data['created_at'];
                            $end = strtotime("+3 minutes", strtotime($endtime));
                            $end = date('h:i:s', $end);
                        }
                    } else {
                        $endtime = isset($end) ? $end : Base::current_client_datetime();

                        $end = '';
                    }

                    if ($ride) {
                        $Progress = $ride;
                    } else {
                        $Progress = $picks;
                    }

                    if ($Progress['timestamps']) {
                        $start = $Progress['timestamps'];
                        #$start = $Progress["created_time"];
                    } else {
                        $start = $Progress['created_at'];
                    }
                    $start = $start;

                    $orders_data = ApiOrders::where('id', $order_id)->get()->first();
                    /* started ride date */
                    $start_date = $ride["created_at"];
                    $t = array_reverse($tasks);
                    $k = array_reverse($t[0]["all_status"]);

                    $h = isset($k[0]["created_at"]) ? $k[0]["created_at"] : $data["created_at"];
                    $data = $Delivered ? $Delivered : $Progress;

                    //$tme = isset($t[0]["created_at"]) ? $t[0]["created_at"] : $t[0]["updated_at"];
                    $tme = $data["created_at"];

                    $data = User::where('user_id', '=', $this->emp_id)->first();
                    $id = $data->belongs_manager;
                    $timedata = User::where('user_id', '=', $id)->get();
                    $toTz = $data->timezone = $timedata[0]['timezone'];
                    $datetime = $tme;
                    $tz_from = $toTz;
                    $tz_to = 'UTC';
                    $format = 'Y-m-d H:i:s';

                    $dt = new DateTime($datetime, new DateTimeZone($tz_from));
                    $dt->setTimeZone(new DateTimeZone($tz_to));
                    //return $dt->format($format)."/".$tme."/".$orders_data["order_start_time"];
                    //return Base::tomysqldatetime($start_date)."/".Base::tomysqldatetime($h);
                    if ($apicall) {
                        $gpsData = snapdata::where('user_id', $task['allocated_emp_id'])
                                        //->where('created_at', '>=', Base::tomysqldatetime($start_date))
                                        //->where('created_at', '<=', Base::tomysqldatetime($h))
                                        ->where("order_id", '=', $order_id)
                                        ->orderBy('timestamp')
                                        ->get()->toArray();
                    } else {
                        $gpsData = [];
                    }


                    $distInMeter = [];
                    $distInMeter[] = 0;
                    for ($x = 0; $x < count($gpsData) - 1; $x++) {

                        if (($gpsData[$x]['activity'] == 'Start')) {

                            $distInMeter[0] = $distInMeter[count($distInMeter) - 1];
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

                    $time_taken = Base::time_elapsed_string($endtime, true, $start);
                    if (empty($time_taken)) {
                        $time_taken = '1 min';
                    }
                    $distInMeter = $distInMeter / 1000;

                    if ($apicall) {
                        $data = User::where('user_id', '=', $this->emp_id)->first();
                        $id = $data->belongs_manager;
                        $timedata = User::where('user_id', '=', $id)->get();
                        $toTz = $data->timezone = $timedata[0]['timezone'];
                        $date = new DateTime($start, new DateTimeZone('UTC'));
                        $date->setTimezone(new DateTimeZone($toTz));
                        $time = $date->format('Y-m-d H:i:s');
                        $edate = new DateTime($endtime, new DateTimeZone('UTC'));
                        $edate->setTimezone(new DateTimeZone($toTz));
                        $etime = $edate->format('Y-m-d H:i:s');
                        $Summary = [
                            'time_taken' => $time_taken,
                            'start' => $time,
                            'end' => $etime,
                            'gpsData' => $gpsData,
                            'distance' => round($distInMeter, 2) . ' kms',
                            'emp_id' => $task['allocated_emp_id'],
                        ];

                        array_push($summary_data, $Summary);
                    } else {

                        $Summary = [
                            'time_taken' => $time_taken,
                            'start' => $start,
                            'end' => $end,
                            'distance' => round($distInMeter, 2) . ' kms',
                        ];
                        return (object) $Summary;
                    }
                }

                /* For loop */
            }


            $_reverse_order = array_reverse($summary_data);

            $new_summary = array();
            $_gps = [];
            if ($summary_data) {
                // foreach ($summary_data as $key => $value) {
                //     foreach ($value['gpsData'] as $key => $gps) {
                //         $_gps[]=$gps;
                //     }
                // }

                $summary = [
                    'time_taken' => $_reverse_order[0]['time_taken'],
                    'start' => $_reverse_order[0]['start'],
                    'end' => $_reverse_order[0]['end'],
                    'distance' => $_reverse_order[0]['distance'],
                    'emp_id' => $_reverse_order[0]['emp_id'],
                    'gpsData' => $_reverse_order[0]['gpsData']
                ];
                return Base::touser($summary, true);
            } else {
                return Base::touser('Task Status Error');
            }
        }
    }

    public function getTaskSummary(Request $request, $task_id) {

        return self::gpsData($task_id);
    }

    public function gpsData($task_id, $apicall = true) {

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

            if ($apicall) {
                if ((Base::mobile_header() == 1) && ($task['allocated_emp_id'] != $this->emp_id)) {

                    return Base::touser('Task Status not belongs to you');
                }
            }

            $taskStatus = array_reverse($task['all_status']);

            $picks = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Products Picked up';
                }
            });

            $ride = array_first($taskStatus, function ($value, $key) use ($task) {
                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Started Ride';
                }
            });
            $allocated = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'In Supplier Place';
                }
            });
            $Delivered = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {

                    return $value['status'] == 'Delivered';
                }
            });
            $Incomplete = array_first($taskStatus, function ($value, $key) use ($task) {

                if ($value['emp_id'] == $task['allocated_emp_id']) {
                    return $value['status'] == 'Delivered back';
                }
            });

            if ($picks || $ride) {
                if ($Delivered || $Incomplete) {

                    $data = $Delivered ? $Delivered : $Incomplete;
                    if ($data['timestamps']) {
                        $endtime = $data['timestamps'];
                        $end = strtotime("+3 minutes", strtotime($endtime));
                        $end = date('h:i:s', $end);
                    } else {
                        $endtime = $data['created_at'];
                        $end = strtotime("+3 minutes", strtotime($endtime));
                        $end = date('h:i:s', $end);
                    }
                } else {
                    $endtime = isset($end) ? $end : Base::current_client_datetime();

                    $end = '';
                }
                $Progress = "";
                if ($ride) {
                    $Progress = $ride;
                }

                if ($ride['timestamps']) {
                    $start = $ride['timestamps'];
                    #$start = $ride["created_time"];
                } else {
                    $start = $ride['created_at'];
                }


                //$end = isset($end) ? $end : date('Y-m-d H:i:s');
                // $end = "2017-07-04 05:35:00";
                $start = $start;
//			print_r($start);
//			print_r($endtime);
                if ($apicall) {
                    $gpsData = snapdata::
                                    where('user_id', $task['allocated_emp_id'])->
                                    where('created_at', '<=', Base::tomysqldatetime($endtime))->
                                    where('created_at', '>=', Base::tomysqldatetime($start))->
                                    orderBy('timestamp')->
                                    get()->toArray();
                    //  return $gpsData;
                } else {


                    $gpsData = [];
                }

                $distInMeter = [];
                $distInMeter[] = 0;
                for ($x = 0; $x < count($gpsData) - 1; $x++) {

                    if (($gpsData[$x]['activity'] == 'Start')) {

                        $distInMeter[0] = $distInMeter[count($distInMeter) - 1];
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

                // $time_taken = DB::Table('snapped_data')
                // where('user_id', $task['allocated_emp_id'])->
                // where('created_at', '<=', Base::tomysqldatetime($endtime))->
                // where('created_at', '>=', Base::tomysqldatetime($start))->
                // ->selectRaw('SEC_TO_TIME(SUM(TIME_TO_SEC(timestamp))) as total')
                // ->get();
                // return $no_of_hours;


                $time_taken = Base::time_elapsed_string($endtime, true, $start);

                if (empty($time_taken)) {
                    $time_taken = '1 min';
                }

                $distInMeter = $distInMeter / 1000;

                if ($apicall) {
                    $data = User::where('user_id', '=', $this->emp_id)->first();
                    $id = $data->belongs_manager;
                    $timedata = User::where('user_id', '=', $id)->get();
                    $toTz = $data->timezone = $timedata[0]['timezone'];
                    $date = new DateTime($start, new DateTimeZone('UTC'));
                    $date->setTimezone(new DateTimeZone($toTz));
                    $time = $date->format('Y-m-d H:i:s');
                    $edate = new DateTime($endtime, new DateTimeZone('UTC'));
                    $edate->setTimezone(new DateTimeZone($toTz));
                    $etime = $edate->format('Y-m-d H:i:s');
                    $Summary = [
                        'time_taken' => $time_taken,
                        'start' => $time,
                        'end' => $etime,
                        'gpsData' => $gpsData,
                        'distance' => round($distInMeter, 2) . ' kms',
                        'emp_id' => $task['allocated_emp_id'],
                    ];

                    return Base::touser($Summary, true);
                } else {

                    $Summary = [
                        'time_taken' => $time_taken,
                        'start' => $start,
                        'end' => $end,
                        'distance' => round($distInMeter, 2) . ' kms',
                    ];
                    return (object) $Summary;
                }
            }

            if ($apicall) {

                return Base::touser('Task Status Error');
            } else {
                return 'error';
            }
        }
    }

    public function decline_task(Request $request, $order_id) {
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
        $_task_id = Base::getTaskId($order_id);
        $check = allocation::whereIn("task_id", $_task_id)->where("emp", "=", $this->emp_id)->orderBy("id", "desc")->count();
        $order = ApiOrders::find($order_id);
        if ($check <= 0) {
            return Base::touser('Sorry.. This Order allocated to another driver', false);
        }
        if ($order->status == "Unallocated") {
            return Base::touser('Sorry.. This Order not allocated to you', false);
        }
        $order = ApiOrders::find($order_id);
        if (count($order) < 1) {
            return Base::touser('Order not found');
        }

        $latitude = $data["lat"];
        $longitude = $data["lng"];


        $task_order = task::where('mt_order_id', '=', $order_id)->get()->toArray();

        foreach ($task_order as $key => $value) {
            if ($value['status'] == 'Delivered' || $value['status'] == 'Incomplete') {
                return Base::touser('Task Already Completed', true);
            }

            $task = task::find($value['id']);
            $reqlat = $request->input('data')['lat'];
            $reqlng = $request->input('data')['lng'];
            $timestamp = isset($request->input('data')['timestamps']) ? Base::tomysqldatetime($request->input('data')['timestamps']) : date('Y-m-d H:i:s');
            $remarks = isset($request->input('data')['remarks']) ? $request->input('data')['remarks'] : '';

            $user_track = User::where('user_id', $this->emp_id)->first();

            $manager = User::where('user_id', $user_track->belongs_manager)->first();

            if ($data['network_status'] == 'online') {
                if ($task->is_geo_fence == 1) {
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
            $task->status = $data['status'];
            $task->save();


            $task_status = new ScheduleTaskStatus();
            $task_status->emp_id = $this->emp_id;
            $task_status->task_id = $task->id;
            $task_status->address = '';
            $task_status->lat = $data['lat'];
            $task_status->long = $data['lng'];
            if ($data['status'] == 'Declined') {
                $task_status->status = 'Unallocated';
                $task->task_status = 0;
                $task->status = 'Unallocated';
                $task->save();
                $task_status->save();
                /* update order status */
                $order->status = 'Unallocated';
                $order->save();
                $allocation = allocation::where('task_id', $task->id)->delete();
                /* decline and auto logic */
                /* add entry to auto allocation table */
                $auto = new AutoAllocation();
                $auto->admin_id = $this->emp_id;
                $auto->emp_id = $this->emp_id;
                $auto->order_id = $order_id;
                $auto->save();

                $additional_fields = [
                    "latitude" => $task->pickup_ladd,
                    "longitude" => $task->pickup_long
                ];

                $user = \App\Models\User::find($this->emp_id);
                //event(new \App\Events\AutoAllocatedEvent($order_id,$task, $this->emp_id,$additional_fields,$this->emp_id));

                ApiOrderScheduleController::auto_allocation_order_status_update($order_id, $task->pickup_ladd, $task->pickup_long, $this->emp_id);

                /* if($resp == false){
                  return Base::touser('No drivers available with in the km.', false);
                  } */
            } else {
                $task_status->status = 'Unallocated';
                $task->task_status = 0;
                $task_status->status = 'Unallocated';
            }
            //$task_status->status     = $data['status'];
            $task_status->timestamps = $timestamp;
            $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->save();

            /* if($data['status'] == 'Declined'){
              $order->status = 'Unallocated';
              $order->save();
              } */


            $user = \App\Models\User::find($this->emp_id);
            $notification = $user->notify(new \App\Notifications\TaskCompleted($task, $user));
            event(new \App\Events\NotificationEvent($user));
            event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        }

        return Base::touser('Order has been successfully updated.', true);
    }

    public function approve_order(Request $request, $order_id) {
        $rules = [
            'status' => 'required',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
        $_task_id = Base::getTaskId($order_id);
        $check = allocation::whereIn("task_id", $_task_id)->where("emp", "=", $this->emp_id)->orderBy("id", "desc")->count();
        $order = ApiOrders::find($order_id);
        if ($check <= 0) {
            $array = ["is_order_confirmed" => $order->is_order_confirmed, "msg" => "Sorry.. This Order allocated to another driver"];
            return Base::touser($array, false);
        }
        if ($order->status == "Unallocated") {
            $array = ["is_order_confirmed" => $order->is_order_confirmed, "msg" => "Sorry.. This Order not allocated to you."];
            return Base::touser($array, false);
        }
        if (count($order) < 1) {
            $array = ["is_order_confirmed" => $order->is_order_confirmed, "msg" => "Order not found."];
            return Base::touser($array, false);
        }

        $task_order = task::where('mt_order_id', '=', $order_id)->get()->toArray();

        $cust_phone = [];
        
        if(env('SES_EMAIL_SEND') == 1){
            foreach ($task_order as $key => $value) {
                if ($value['status'] == 'Delivered' || $value['status'] == 'Incomplete') {
                    return Base::touser('Task Already Completed', true);
                } else {
                    if ($value["cust_email"] != "" && $data["status"] != "Confirmed" && !in_array($value["cust_phone"], $cust_phone)) {
                        // if ($value["cust_email"] != "" && $data["status"] != "Confirmed") {
                        $datas = array('name' => "Liveanywhere", 'email' => $value["cust_email"], 'orderInfo' => $value);
                        $cust_id = Customer::where('contact_no', '=', $value['cust_phone'])->where('emp_id', $value['added_by'])->pluck('id')->first();
                        session::put('data', env('LIVE_TRACKING_URL') . "/" . encrypt($value["mt_order_id"].'-'.$cust_id));
                        array_push($cust_phone, $value["cust_phone"]);
                        session::put('mail', $value["cust_email"]);

                        $mail = Mail::send(['text' => 'mail'], $datas, function($message) {
                                    $message->to(session::get('mail'), 'Customer Tracking')->subject('Order Confirmation Link');
                                    $message->from('info@manageteamz.com', 'Admin');
                                });
                    }
                }
            }
        }

        if ($data['status'] == "Confirmed") {
            $order->is_order_confirmed = 1;
        } else {
            /* update order status */
            $order->status = $data['status'];
        }
        $order->save();
        $_task = task::where('mt_order_id', '=', $order->id)->orderBy('id')->get()->first();
        /*esseplore service code*/
        $ServiceInfo = [
            "order_id"      => $_task->order_id,
            "task_id"       => $_task->id,
            "emp_id"        => $order->emp_id,
            "mt_order_id"   => $order->id
        ];
        $integrationService = IntegrationServiceFactory::create($order->source, $order->delivery_logic);
        $integrationService->updateStatus($_task->order_id, "Accepted", $ServiceInfo);

        event(new \App\Events\TaskUpdateEvent($_task, $this->emp_id));
        $array = ["is_order_confirmed" => $order->is_order_confirmed, "msg" => "Order has been successfully updated."];

        return Base::touser($array, true);
    }

    public function multipickup_updatetask(Request $request, $order_id) {
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
        $order = ApiOrders::find($order_id);
        if (count($order) < 1) {
            return Base::touser('Order not found');
        }

        $task_order = task::where('mt_order_id', '=', $order_id)->get()->toArray();
        
    if($data['status'] == 'Delivered back' || $data['status'] == 'Delivered Back') {
            Base::clone_full_order($order_id);
        }

        foreach ($task_order as $key => $value) {
            // if($value['status'] == 'Delivered' || $value['status'] == 'Incomplete'){
            //     return Base::touser('Task Already Completed', true);
            // }

            $task = task::find($value['id']);
            $reqlat = $request->input('data')['lat'];
            $reqlng = $request->input('data')['lng'];
            $timestamp = isset($request->input('data')['timestamps']) ? Base::tomysqldatetime($request->input('data')['timestamps']) : date('Y-m-d H:i:s');
            $remarks = isset($request->input('data')['remarks']) ? $request->input('data')['remarks'] : '';

            $user_track = User::where('user_id', $this->emp_id)->first();

            $manager = User::where('user_id', $user_track->belongs_manager)->first();

            if ($data['network_status'] == 'online') {
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
            $task->idproof = isset($request->input('data')['idproof']) ? $request->input('data')['idproof'] : '';
            $task->images = isset($request->input('data')['images']) ? json_encode($request->input('data')['images']) : '[]';
            $task->status = $data['status'];
            $task->save();
            
            $ServiceInfo = [
                "order_id"      => $task->order_id,
                "task_id"       => $task->id,
                "emp_id"        => $order->emp_id,
                "mt_order_id"   => $order_id
            ];
            $integrationService = IntegrationServiceFactory::create($order->source, $order->delivery_logic);
            $integrationService->updateStatus($task->order_id, $data['status'], $ServiceInfo);

            $task_status = new ScheduleTaskStatus();
            $task_status->emp_id = $this->emp_id;
            $task_status->task_id = $task->id;
            $task_status->address = '';
            $task_status->lat = $data['lat'];
            $task_status->long = $data['lng'];
            if ($data['status'] == 'Delivered back' || $data['status'] == 'Delivered Back') {
                //$task_status->status     = $data['status'];                
                $task->reason_id = isset($data['reason_id']) ? $data['reason_id'] : 0;
                $task->reason = isset($data['reason']) ? $data['reason'] : null;
                $task_status->status = $data['status'];
            } else {
                $task_status->status = $data['status'];
            }
            //$task_status->status     = $data['status'];
            $task_status->timestamps = $timestamp;
            $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->save();

            if ($data['status'] == 'Delivered' || $data['status'] == 'Delivered back' || $data['status'] == 'Delivered Back') {
                /* update order status */
                $order = ApiOrders::find($order_id);
                $order->status = 'Delivered';
                $order->save();
            }

            // if(($data['status']=='Delivered') || ($data['status']=='Delivered back')){
            if ($data['status'] == 'Delivered') {
                TaskDuration::dispatch($this->emp_id, $task->id);
            }
            $user = \App\Models\User::find($this->emp_id);
            $notification = $user->notify(new \App\Notifications\TaskCompleted($task, $user));
            event(new \App\Events\NotificationEvent($user));
            event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        }

        return Base::touser('Order has been successfully updated.', true);
    }

    public function check_least_task($order_id) {

        $order_list = task::where("mt_order_id", $order_id)->orderBy('id', 'desc')->limit(1)->first();
        $task_id = $order_list->id;

        $list = ScheduleTaskStatus::where("task_id", $task_id)
                ->orderBy('id', 'desc')
                ->limit(1)
                ->first();
        if (count($list) <= 0) {
            return false;
        } else {
            if ($list["status"] == "Delivered back" || $list["status"] == "Delivered") {
                return true;
            } else {
                return false;
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

        $order_id = Base::getOrderId($task_id);
        $Orders = ApiOrders::find($order_id);
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

        if ($data['network_status'] == 'online') {
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
        // $task->signature = isset($request->input('data')['signature']) ? $request->input('data')['signature'] : '';
        // $task->idproof = isset($request->input('data')['idproof']) ? $request->input('data')['idproof'] : '';
        // $task->images = isset($request->input('data')['images']) ? json_encode($request->input('data')['images']) : '[]';
        $task->status = $data['status'];
        $task->save();

        $__task = task::where('mt_order_id','=',$Orders->id)->orderBy('id')->get()->first();
        $ServiceInfo = [
            "order_id"      => $__task->order_id,
            "task_id"       => $task_id,
            "emp_id"        => $Orders->emp_id,
            "mt_order_id"   => $Orders->id
        ];
        $integrationService = IntegrationServiceFactory::create($Orders->source, $Orders->delivery_logic);
        $integrationService->updateStatus($__task->order_id, $data['status'], $ServiceInfo);

        $task_status = new ScheduleTaskStatus();
        $task_status->emp_id = $this->emp_id;
        $task_status->task_id = $task->id;
        $task_status->address = '';
        $task_status->lat = $data['lat'];
        $task_status->long = $data['lng'];
        if ($data['status'] == 'Delivered back' || $data['status'] == 'Delivered Back') {
            /* close order */
            Base::clone_order($task_id);
            $task_status->status = $data['status'];
            $task = task::find($task_id);
            $task->reason_id = isset($data['reason_id']) ? $data['reason_id'] : 0;
            $task->reason = isset($data['reason']) ? $data['reason'] : null;
            $task->save();
            //$task_status->status     =  'Unallocated';

            /* update order */
            $order_id = Base::getOrderId($task_id);
            $_status_ = Base::check_all_task_status($order_id);
            if ($_status_ == true) {
                $Orders = ApiOrders::find($order_id);
                $Orders->status = ($Orders->delivery_logic == 3) ? $data['status'] : 'Delivered';;
                $Orders->save();
            }
        } else {
            /* update order */
            $order_id = Base::getOrderId($task_id);
            $_status_ = Base::check_all_task_status($order_id);
            if ($_status_ == true) {
                $Orders = ApiOrders::find($order_id);
                $Orders->status = 'Delivered';
                $Orders->save();
            }
            
            $task_status->status = $data['status'];
        }
        //$task_status->status     = $data['status'];
        $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->timestamps = $timestamp;
        $task_status->save();

        /* $_status = Base::delivery_status_check($task_id);
          if($_status == true){
          $order_id = Base::getOrderId($task_id);
          $Orders = ApiOrders::find($order_id);
          $Orders->status = 'Delivered';
          $Orders->save();
          } */

        // if(($data['status']=='Delivered') || ($data['status']=='Delivered back')){
        if ($data['status'] == 'Delivered') {
            TaskDuration::dispatch($this->emp_id, $task->id);
        }
        $user = \App\Models\User::find($this->emp_id);
        event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        $notification = $user->notify(new \App\Notifications\TaskCompleted($task, $user));
        event(new \App\Events\NotificationEvent($user));


        return Base::touser('Order has been successfully updated.', true);
    }

    public function allocateTask(Request $request, $task_id) {
        $id = $this->emp_id;
        $timezone = Base::client_time($id);
        $rules = [
            'emp' => 'exists:user,user_id',
            'status' => 'required|string',
        ];

        $data = $request->input('data');
        $taskdata = $data['taskdata'];


        foreach ($taskdata as $key => $value) {
            if (strtotime($value['picktime']) < strtotime($timezone)) {
                return Base::touser('Pickup Time should not be before today.', false);
            }
            $validator = Validator::make($value, $rules);
            if ($validator->fails()) {
                return Base::touser($validator->errors()->all()[0]);
            }

            $newdate = date('Y-m-d H:i:s', strtotime($value['picktime']));

            $Orders = ApiOrders::find($value["mt_order_id"]);
            $Orders->emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
            $Orders->added_by = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
            $Orders->order_start_time = $value['picktime'];
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
                $ServiceInfo = [
                    "order_id"      => $value["order_id"],
                    "task_id"       => $value["id"],
                    "emp_id"        => $Orders->emp_id,
                    "mt_order_id"   => $Orders->id
                ];
                $integrationService = IntegrationServiceFactory::create($Orders->source, $Orders->delivery_logic);
                $integrationService->updateStatus($value["order_id"], $data['status'], $ServiceInfo);
            } else if ($data['status'] == "In-Progress" || $data['status'] == "Started Ride" || $data['status'] == "In Supplier Place" || $data['status'] == "Products Picked up") {
                $Orders->status = 'In-Progress';
                $Orders->is_order_confirmed = 1;
                $Orders->is_order_push_sent = 1;
            } else if ($data['status'] == 'Delivered') {
                $Orders->status = 'Delivered';
            }
            $Orders->save();

            $task = task::where('id', $value['id'])->first();

            if ($data['status'] == 'Delivered back' || $data['status'] == 'Declined') {
                $task->status = 'Unallocated';
            } else {
                $task->status = $data['status'];
            }

            $task->save();
            $status = "Unallocated";

            if (isset($data['status'])) {
                if ($data['status'] == 'Delivered back' || $data['status'] == 'Declined') {
                    $status = 'Unallocated';
                } else {
                    $status = $data['status'];
                }
            } else {
                $status = 'Unallocated';
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
        }
        return self::show($task->id);
    }

    public function accept_auto_allocation(Request $request) {
        $data = $request->input('data');
        $task_id_ = $data["task_id"];
        $_order_id = Base::getOrderId($task_id_);

        $Orders = ApiOrders::find($_order_id);
        $Orders->status = "In-Progress";
        $Orders->save();

        $_task_id = Base::getTaskId($_order_id);
        foreach ($_task_id as $key => $task_id) {

            $task = task::find($task_id);
            $task->status = "Allocated";
            $task->update();


            $task_status = new ScheduleTaskStatus();
            $task_status->emp_id = $this->emp_id;
            $task_status->task_id = $task_id;
            $task_status->address = '';
            $task_status->lat = isset($data['lat']) ? $data['lat'] : '';
            $task_status->long = isset($data['lng']) ? $data['lng'] : '';
            $task_status->status = "Allocated";
            $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
            $task_status->save();
            event(new \App\Events\TaskUpdateEvent($task, $this->emp_id));
        }
    }

    public function store(Request $request) {
        $id = $this->emp_id;
        $timezone = Base::client_time($id);
        $data = $request->input('data');

        $delivery_time = $data['multiple_delivery'];
        $picktime = $data['multiple_pickup'];
        $lastindexarray = $data['multiple_delivery'];

        foreach ($picktime as $key => $picktime_) {
            if (strtotime($picktime_['picktime']) < strtotime($timezone)) {
                return Base::touser('Pickup Time should not be before today.');
            }
        }



        $rules = [
            'emp' => 'exists:user,user_id',
            'added_by' => 'exists:user,user_id',
            //'schedule_date_time' => 'required',
            'type' => 'required|string',
            'method' => 'required|string',
                //'notes'              => 'required|string',
                //'order_id'              => 'required|string',
        ];
        $data = $request->input('data');
        //print_r($data);exit
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        if (($data['status'] != 'Unallocated') && ($data['status'] != 'Canceled')) {
            if (empty($data['emp'])) {
                return Base::touser('Employee Required');
            }
        }

        foreach ($picktime as $key => $_picktime) {
            if (isset($_picktime['pick_address']) and empty(explode('|', ApiOrderScheduleController::latlong($_picktime['pick_address']))[0])) {
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

        $cust = array();

        $newdate = date('Y-m-d H:i:s', strtotime($data['picktime']));

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
        } else if ($data['status'] == "In-Progress" || $data['status'] == "Started Ride" || $data['status'] == "In Supplier Place" || $data['status'] == "Products Picked up") {
            $Orders->status = 'In-Progress';
        } else if ($data['status'] == 'Delivered') {
            $Orders->status = 'Delivered';
        }
        $Orders->save();

        $is_multidelivery = 1;

        if ($data['is_multidelivery'] == 1 || ($data['is_multidelivery'] == false && $data['is_multipickup'] == false)) {
            $j = 1;
            foreach ($multi_delivery as $key => $multival) {
                //return $picktime['notes'];
                $task = new task();

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

                $count = task::count();
                $order_id = Base::generateID($count);


                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : '';
                $task->notes = $multi_pickup[0]['notes'];
                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : '';
                $task->pick_address = $multi_pickup[0]['pick_address'];
                $task->order_id = $order_id;
                $task->comments = $request->input('comments');
                $task->mob = $request->input('mob');
                $task->receiver_name = isset($multival['receiver_name']) ? $multival['receiver_name'] : '';
                $task->cust_email = isset($multival['cust_email']) ? $multival['cust_email'] : $request->input('cust_email');
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : $request->input('sender_name');
                // $task->sender_number  = $request->input('sender_number');
                // $task->sender_number  = isset($data['sender_number'])?$data['sender_number'] : '';
                $task->picktime = isset($multi_pickup[0]['picktime']) ? Base::tomysqldatetime($multi_pickup[0]['picktime']) : date('Y-m-d H:i:s');
                $task->pickup_long = $multi_pickup[0]['pickup_long'];
                $task->pickup_ladd = $multi_pickup[0]['pickup_ladd'];
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';

                $task->status = $data['status'];
                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';
                $task->cust_phone = $multival['cust_phone'];
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
                // $task->task_status = 1;
                //  $task->task_status = 1;

                $task->mt_order_id = $Orders->id;
                $task->priority = $j;

                $task->product_weight = isset($multi_pickup[0]['product_weight']) ? $multi_pickup[0]['product_weight'] : 0;
                $task->product_size = isset($multi_pickup[0]['product_size']) ? $multi_pickup[0]['product_size'] : null;
                $task->time_to_delivery = isset($multi_pickup[0]['time_to_delivery']) ? $multi_pickup[0]['time_to_delivery'] : null;
                $task->time_requirement = isset($multi_pickup[0]['time_requirement']) ? $multi_pickup[0]['time_requirement'] : null;

                $task->product_length = isset($multi_pickup[0]['product_length']) ? $multi_pickup[0]['product_length'] : 0;
                $task->product_height = isset($multi_pickup[0]['product_height']) ? $multi_pickup[0]['product_height'] : 0;
                $task->product_breadth = isset($multi_pickup[0]['product_breadth']) ? $multi_pickup[0]['product_breadth'] : 0;

                $task->save();

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
                }
                $j++;
            } /* end foreach */
        } else if ($data['is_multipickup'] == 1) {
            $j = 1;
            foreach ($multi_pickup as $key => $picktime) {
                //return $picktime['notes'];
                $task = new task();

                $task->schedule_date_time = isset($multival[0]['schedule']) ? Base::tomysqldatetime($multival[0]['schedule']) : date('Y-m-d H:i:s');

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

                $count = task::count();
                $order_id = Base::generateID($count);


                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : '';
                $task->notes = $picktime['notes'];
                $task->cust_id = isset($data['cust_id']) ? $data['cust_id'] : '';
                $task->pick_address = $picktime['pick_address'];
                $task->order_id = $order_id;
                $task->comments = $request->input('comments');
                $task->mob = $request->input('mob');
                $task->receiver_name = isset($multi_delivery[0]['receiver_name']) ? $multi_delivery[0]['receiver_name'] : '';
                $task->cust_email = isset($multi_delivery[0]['cust_email']) ? $multi_delivery[0]['cust_email'] : $request->input('cust_email');
                $task->sender_name = isset($data['sender_name']) ? $data['sender_name'] : $request->input('sender_name');
                // $task->sender_number  = $request->input('sender_number');
                // $task->sender_number  = isset($data['sender_number'])?$data['sender_number'] : '';
                $task->picktime = isset($picktime['picktime']) ? Base::tomysqldatetime($picktime['picktime']) : date('Y-m-d H:i:s');
                $task->pickup_long = $picktime['pickup_long'];
                $task->pickup_ladd = $picktime['pickup_ladd'];
                $task->sent_address = isset($data['sent_address']) ? $data['sent_address'] : '';

                $task->status = $data['status'];
                $task->customer_pickupaddr_id = isset($data['pick_id']) ? $data['pick_id'] : '';
                $task->customer_deliveryaddr_id = isset($data['deliv_id']) ? $data['deliv_id'] : '';
                $task->cust_phone = $multi_delivery[0]['cust_phone'];
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

                $task->save();

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
                }
                $j++;
            }/* end multipickup */
        }





        $userdata = \App\Models\User::where('user_id', $this->emp_id)->get();
        if ($userdata[0]['mailnote'] == 1) {
            if ($request->input('cust_email')) {
                //$email = "kalidass@way2smile.com";
                $email = $request->input('cust_email');
            } else {
                $email = "abinayah@way2smile.com";
            }
            $data = array('name' => "Liveanywhere", 'email' => $email, 'orderInfo' => $task);
            $hashed_random_password = str_random(8);
            session::put('data', 'https://logistics.manageteamz.com/api/track-order/' . encrypt($task->id));
            session::put('mail', $email);
            // $mail = Mail::send(['text'=>'mail'], $data, function($message) {
            //  $message->to(session::get('mail'), 'Customer Tracking')->subject
            //     ('Order ConformationLink');
            //    $message->cc('bd@manageteamz.com','BD Admin');
            //  $message->from('info@manageteamz.com','Admin');
            // $cust = \App\Models\Customer::find($task->cust_id )->notify(new \App\Notifications\CustomerTracking($task, $user, Base::get_domin(),true));
            // });
        }


        return Base::touser('Task Created', true);
    }

    public function show($id) {
        // if ($this->admin || $this->backend || $this->manager) {
        $response = task::where('mt_order_id', $id)->with('all_status')->get()->toArray();
        $multiple_delivery = array();
        $multiple_pickup = array();
        $task_resp = array(
            'multiple_delivery' => array(),
            'multiple_pickup' => array()
        );

        $Orders = ApiOrders::where('id', $id)->get()->first();
        $is_multidelivery = $Orders['is_multidelivery'];
        $is_multipickup = $Orders['is_multipickup'];
        $delivery_logic = $Orders['delivery_logic'];
        // return $Orders;
        // $order_images=OrderImage::where('order_id',$id)->get()->toArray();
        // $pic=[];
        // foreach($order_images as $images){
        //     $image=url($images['image_path']);
        //     array_push($pic,$image);
        // }

        foreach ($response as $key => $array) {
            // return count($array);
            $pic=[];

            // $images=OrderImage::where('task_id',$array['id'])->where('order_id',$id)->get(); 
            // dd(url($images[0]->image_path));
            // dd($images);
            // foreach($images as $data){
            //     $image=url($data->image_path);
            //     array_push($pic,$image);

            // }
            $getcustomer_id = Customer::where('contact_no', $array['cust_phone'])->get()->first();

            if (count(ItemMap::where('order_id', $array['mt_order_id'])->where('stage', $key + 1)->with('Items')->get()) > 0) {
                $getitemmap = ItemMap::where('order_id', $array['mt_order_id'])->where('stage', $key + 1)->with('Items')->get()->toArray();
            } else {
                $getitemmap = [];
            }

            $task_resp['status'] = $array['status'];
            $task_resp['type'] = $array['type'];
            $task_resp['method'] = $array['method'];
            $task_resp['schedule'] = $array['schedule_date_time'];
            $task_resp['picktime'] = $array['picktime'];
            $task_resp['loc_lat'] = $array['lat'];
            $task_resp['loc_lng'] = $array['lng'];
            $task_resp['pickup_ladd'] = $array['pickup_ladd'];
            $task_resp['pickup_long'] = $array['pickup_long'];
            $task_resp['pickup_phone'] = $array['pickup_phone'];
            $task_resp['sent_ladd'] = $array['pickup_ladd'];
            $task_resp['sent_long'] = $array['pickup_long'];
            $task_resp['geo_fence_meter'] = $array['geo_fence_meter'];
            $task_resp['sender_name'] = $array['sender_name'];
            $task_resp['sender_number'] = $array['sender_number'];
            $task_resp['sent_address'] = $array['sent_address'];
            $task_resp['emp'] = $array['allocated_emp_id'];
            $task_resp['is_geo_fence'] = $array['is_geo_fence'];
            $task_resp['mt_order_id'] = $array['mt_order_id'];
            $task_resp['cust_id'] = $array['cust_id'];
            $task_resp["is_multidelivery"] = $is_multidelivery;
            $task_resp["is_multipickup"] = $is_multipickup;
            $task_resp["delivery_logic"] = $delivery_logic;
            if(($array['images']!="") and ($array['images']!="[]")){
                $pics= explode(',',$array['images']);
            }
            else{
                $pics=[];
            }
            if ($is_multidelivery == true) {
               
                $task_resp['multiple_delivery'][] = array(
                    'schedule' => $array['schedule_date_time'],
                    'order_id' => $array['order_id'],
                    'cust_name' => $array['cust_name'],
                    'cust_phone' => $array['cust_phone'],
                    'cust_email' => $array['cust_email'],
                    'cust_address' => $array['cust_address'],
                    'cust_id' => $getcustomer_id['id'],
                    'loc_lat' => $array['loc_lat'],
                    'loc_lng' => $array['loc_lng'],
                    'id' => $array['id'],
                    'mt_order_id' => $array['mt_order_id'],
                    'receiver_name' => $array['receiver_name'],
                    'delivery_notes1' => $getitemmap,
                    'images' => $pics,
                   
                );
            } else {
                $task_resp['multiple_delivery'][0] = array(
                    'schedule' => $array['schedule_date_time'],
                    'order_id' => $array['order_id'],
                    'cust_name' => $array['cust_name'],
                    'cust_phone' => $array['cust_phone'],
                    'cust_email' => $array['cust_email'],
                    'cust_address' => $array['cust_address'],
                    'cust_id' => $getcustomer_id['id'],
                    'loc_lat' => $array['loc_lat'],
                    'loc_lng' => $array['loc_lng'],
                    'id' => $array['id'],
                    'mt_order_id' => $array['mt_order_id'],
                    'receiver_name' => $array['receiver_name'],
                    'delivery_notes1' => $getitemmap,
                    // 'images' => $pic,
                    'images' =>$pics


                );
            }

            if ($is_multipickup == true) {
                $task_resp['multiple_pickup'][] = array(
                    'picktime' => $array['picktime'],
                    'pick_address' => $array['pick_address'],
                    'notes' => $array['notes'],
                    'pickup_ladd' => $array['pickup_ladd'],
                    'pickup_long' => $array['pickup_long'],
                    'pickup_phone' => $array['pickup_phone'],
                    'id' => $array['id'],
                    'mt_order_id' => $array['mt_order_id'],
                    'time_requirement' => $array['time_requirement'],
                    'time_to_delivery' => $array['time_to_delivery'],
                    'product_weight' => $array['product_weight'],
                    'product_size' => $array['product_size'],
                    'product_length' => $array['product_length'],
                    'product_height' => $array['product_height'],
                    'product_breadth' => $array['product_breadth'],
                    'delivery_notes' => $getitemmap,
                    // 'images' => $pic,
                    

                );
            } else {
                $task_resp['multiple_pickup'][0] = array(
                    'picktime' => $array['picktime'],
                    'pick_address' => $array['pick_address'],
                    'notes' => $array['notes'],
                    'pickup_ladd' => $array['pickup_ladd'],
                    'pickup_long' => $array['pickup_long'],
                    'pickup_phone' => $array['pickup_phone'],
                    'id' => $array['id'],
                    'mt_order_id' => $array['mt_order_id'],
                    'time_requirement' => $array['time_requirement'],
                    'time_to_delivery' => $array['time_to_delivery'],
                    'product_weight' => $array['product_weight'],
                    'product_size' => $array['product_size'],
                    'product_length' => $array['product_length'],
                    'product_height' => $array['product_height'],
                    'product_breadth' => $array['product_breadth'],
                    'delivery_notes' => $getitemmap,
                    // 'images' => $pic,

                );
            }
        }
        // $task_resp['order_images']=$pic;

        return Base::touser($task_resp, true);
    }

    public function getWithStatus($id) {
        return Base::touser(task::with('cust_jobs')->get()->find($id), true);
    }

    public function update(Request $request, $task_id) {
        $rules = [
            'emp' => 'exists:user,user_id',
            'added_by' => 'exists:user,user_id',
            'schedule_date_time' => 'required',
            'status' => 'required',
            'type' => 'required|string',
            'method' => 'required',
            'notes' => 'required|string',
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

        $data = $request->input('data');
        $task = task::find($task_id);
        $task->schedule_date_time = isset($data['schedule_date_time']) ? Base::tomysqldatetime($data['schedule_date_time']) : date('Y-m-d H:i:s');

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

        $is_new_address = false;




        if (empty($data['cust_id'])) {
            $data = $request->input('data');

            $cust = array(
                'name' => $data['cust_name'],
                'contact_no' => $data['cust_phone'],
                'address' => $data['cust_address'],
                'email' => $data['cust_email'],
                'loc_lat' => $data['loc_lat'],
                'loc_lng' => $data['loc_lat'],
            );

            $rules = [
                'name' => 'required',
                'contact_no' => 'required',
                'loc_lat' => 'required',
                'loc_lng' => 'required',
                'email' => 'required|email|unique:customers',
                'address' => 'required',
            ];
            $validator = Validator::make($cust, $rules);

            if ($validator->fails()) {

                if (($validator->errors()->all()[0] == 'The email has already been taken.')) {
                    $Customer = new Customer();
                    $Customer = $Customer->where('email', $data['cust_email'])->first();

                    goto checkaddress;
                } else {
                    return Base::touser($validator->errors()->all()[0]);
                }
            }

            $Customer = new Customer();
            $Customer->name = $data['cust_name'];
            $Customer->email = $data['cust_email'];
            $Customer->emp_id = isset($data['added_by']) ? $data['added_by'] : $this->emp_id;
            $Customer->address = isset($data['cust_address']) ? $data['cust_address'] : null;
            $Customer->loc_lat = $data['loc_lat'];
            $Customer->loc_lng = $data['loc_lng'];
            $Customer->contact_no = $data['cust_phone'];
            $Customer->save();
            $data['cust_id'] = $Customer->id;
        } else {
            $Customer = new Customer();
            $Customer = $Customer->where('id', $data['cust_id'])->first();
            checkaddress:

            if ($Customer->contact_no != $data['cust_phone'] || $Customer->address != $data['cust_address'] || $Customer->loc_lat != $data['loc_lng'] || $Customer->loc_lng != $data['loc_lng']) {
                $is_new_address = true;
            }
        }

        if ($Customer->contact_no != $data['cust_phone'] || $Customer->address != $data['cust_address'] || $Customer->loc_lat != $data['loc_lng'] || $Customer->loc_lng != $data['loc_lng']) {
            $is_new_address = true;
        }

        $task->cust_id = $Customer->id;
        $task->type = $data['type'];
        $task->notes = $data['notes'];
        $task->method = $data['method'];
        $task->is_new_address = $is_new_address;

        if ($is_new_address) {
            $task->cust_phone = $data['cust_phone'];
            $task->loc_lat = $data['loc_lat'];
            $task->loc_lng = $data['loc_lng'];
            $task->cust_address = $data['cust_address'];
        } else {
            $task->cust_phone = $Customer->contact_no;
            $task->loc_lat = $Customer->loc_lat;
            $task->loc_lng = $Customer->loc_lng;
            $task->cust_address = $Customer->address;
        }

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

        $task->save();
        $emp_id = isset($data['emp']) ? $data['emp'] : $this->emp_id;
        $status = isset($data['status']) ? $data['status'] : 'Unallocated';

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

        $task_status->emp_id = $emp_id;
        $task_status->task_id = $task->id;
        $task_status->address = '';
        $task_status->lat = '';
        $task_status->long = '';
        $task_status->status = $status;
        $task_status->timestamps = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->created_time = isset($data['timestamps']) ? Base::tomysqldatetime($data['timestamps']) : date("Y-m-d H:i:s");
        $task_status->save();

        if (($data['status'] != 'Unallocated') && ($data['status'] != 'Canceled')) {
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


            $cust = \App\Models\Customer::find($task->cust_id)->notify(new \App\Notifications\CustomerTracking($task, $user, Base::get_domin(), true));

            print_r('hi');
        } else {
            $allocation = allocation::where('task_id', $task->id)->delete();
        }

        return Base::touser('Order has been successfully updated.', true);
    }

    public function destroy($id) {
        emp_cust::where('emp_cust_id', '=', $id)
                ->delete();

        $api = task::find($id);
        $api->delete();
        return Base::touser('Task Deleted', true);
    }

}
