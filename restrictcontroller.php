<?php

namespace App\Http\Controllers;

use App\Models\timezone;
use App\Models\distance;
use App\Models\timezone as timezonemang;
use App\Models\packageinfo;
use App\Http\Controllers\Base;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Toin0u\Geotools\Facade\Geotools;
use Validator;
use \DateTime;
use \DateTimeZone;
use App\Models\User;
use App\Models\EmpCustSchedule as task;
use App\Models\EmpSchedule as allocation;
use App\Models\ScheduleTaskStatus;
use App\Models\Review;
use App\Models\TravelHistory as api;
use App\Models\Customer;
use App\Models\EmpMapping;
use App\Models\UserPackage;
use App\Models\MapSettings;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use DB;

/**
 * 
 */
class restrictcontroller extends Controller {

    public function clearbase() {
        $ids = ['2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '22', '23', '24', '26', '27', '29', '30', '32', '33', '34', '36', '39', '43', '44', '45', '47', '52', '54', '56', '58', '62', '63', '79', '82', '88', '91', '110', '113', '115', '118', '119', '120', '122', '123', '124', '125', '126', '127', '128', '129', '135', '136', '137', '138', '139', '140', '141', '142', '143', '144', '145', '146', '147', '148', '149', '150', '153', '154', '155', '156', '157', '158', '159', '160', '163', '164', '165', '166', '167', '168', '169', '170', '171', '172', '173', '174', '175', '176', '177', '178', '179', '180', '181', '183', '188', '193', '194', '195', '196', '197', '198', '199', '200', '201', '202', '203', '204', '205', '206', '207', '208', '209', '210', '211', '212', '214', '215', '216', '217', '218', '219', '220', '221', '238', '239', '253', '255', '256', '259', '260', '261', '262', '263'];
        $i = 0;
        for (; $i < count($ids); $i++) {
            $allocation = allocation::where('emp', $ids[$i])->get();
            $task_ids = [];

            for ($j = 0; $j < count($allocation); $j++) {
                $task_ids [$j] = $allocation[$j]['task_id'];
            } if (count($task_ids) != 0) {
                ScheduleTaskStatus::where('emp_id', $ids[$i])->delete();
                allocation::where('emp', $ids[$i])->delete();
                Review::whereIn('task_id', $task_ids)->delete();
                api::whereIn('user_id', $ids[$i])->delete();
                task::whereIn('id', $task_ids)->delete();
            }
        }
        ScheduleTaskStatus::whereIn('emp_id', $ids)->delete();
        allocation::whereIn('emp', $ids)->delete();
        Review::whereIn('emp_id', $ids)->delete();
        api::whereIn('user_id', $ids)->delete();
        task::where('emp_id', $ids)->delete();

        $cusdata = Customer::whereIn('emp_id', $ids)->get();
        $cust_ids = [];
        for ($v = 0; $v < count($cusdata); $v++) {
            $cust_ids[$v] = $cusdata[$v]['id'];
        }
        $tasksdata = task::whereIn('cust_id', $cust_ids)->get();
        $tasks = [];
        for ($a = 0; $a < count($tasksdata); $a++) {
            $tasks[$a] = $tasksdata[$a]['id'];
        }
        allocation::whereIn('task_id', $tasks)->delete();
        ScheduleTaskStatus::whereIn('task_id', $tasks)->delete();
        Review::whereIn('task_id', $tasks)->delete();
        task::whereIn('cust_id', $cust_ids)->delete();
        Customer::whereIn('emp_id', $ids)->delete();
        User::whereIn('user_id', $ids)->delete();
    }

    public function getCountries() {
        $countries = timezone::select('country_code', 'country_name')
                        ->groupBy('country_code')
                        ->groupBy('country_name')
                        ->get()->toArray();
        return Base::touser($countries, true);
    }

    public function getTimezoneByCountryCode(Request $request, $countryCode) {
        $timezones = timezone::where('country_code', '=', $countryCode)
                        ->get()->toArray();
        return Base::touser($timezones, true);
    }

    public function getTimezone() {
        $timezone = timezone::all();
        return $timezone;
    }

    public function updatebasicinfo(Request $request) {
        $data = $request->get('data');
        try {
            if (isset($data['street']) and empty(explode('|', Base::latlong($data['street']))[0])) {
                return Base::touserloc('Location is not valid kindly use drag and drop', 'pickup');
            }
            $timedata = timezonemang::where('desc', $data['timezone'])->orWhere('country_name',$data['timezone'])->get();
            $zonename = $timedata[0]->name;
            $User = User::where('user_id', $this->emp_id)->first();
            $User->timezone = $timedata[0]->desc;
//				$User->mailnote=$data['mailnote'];
            $User->smsnote = isset($data['smsnote']) ? $data['smsnote'] : '';
            $User->street = isset($data['street']) ? $data['street'] : '';
            $User->company_lat = isset($data['lat']) ? $data['lat'] : '';
            $User->company_lng = isset($data['lng']) ? $data['lng'] : '';
            $User->is_multipick = isset($data['is_multipick']) ? $data['is_multipick'] : '';
            $User->employee_lat = isset($data['lat']) ? $data['lat'] : '';
            $User->employee_lng = isset($data['lng']) ? $data['lng'] : '';
            $User->timezonename = $zonename;
            $User->business_name = isset($data['business_name']) ? $data['business_name'] : '';
            $User->business_type = isset($data['business_type']) ? $data['business_type'] : '';
            $User->country_code = isset($data['country_code']) ? $data['country_code'] : '';
            $User->update();
            $array = [];
            $array["profile"] =  $User;
            return Base::touser($array, true);
        } catch (Exception $e) {
            return Base::touser("Unexpected error occured, please try again", false);
        }
    }

    public function getGbsData(Request $request) {
        try {
            $data = $request->get('data');
            $start = Base::tomysqldatetime($data['start_date']);
            $end = Base::tomysqldatetime($data['end_date']);

            $query = distance::query();
            if ($data['emp_id'] != 0) {
                $emp = $data['emp_id'];
                // $query->where('emp_id',$emp)
                //      ->where('start_time', '<=',$end)
                //      ->where('start_time','>=',$start);
                // $data = $query->get();

                $data = DB::table('distance')
                        ->select('*')
                        ->join('emp_cust_schedule', 'distance.task_id', '=', 'emp_cust_schedule.id')
                        ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                        ->join('user', 'user.user_id', '=', 'distance.emp_id')
                        ->where('orders.added_by', '=', $this->emp_id)
                        ->where('distance.emp_id', $emp)
                        ->where('distance.start_time', '<=', $end)
                        ->where('distance.end_time', '>=', $start)
                        ->get();
                return Base::touser($data, true);
            } else {
                // $query->where('start_time', '<=',$end)
                //      ->where('start_time','>=',$start);
                // $data = $query->get();

                $data = DB::table('distance')
                        ->select('*')
                        ->join('emp_cust_schedule', 'distance.task_id', '=', 'emp_cust_schedule.id')
                        ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                        ->join('user', 'user.user_id', '=', 'distance.emp_id')
                        ->where('orders.added_by', '=', $this->emp_id)
                        ->where('distance.start_time', '<=', $end)
                        ->where('distance.end_time', '>=', $start)
                        ->get();
                return Base::touser($data, true);
            }
        } catch (Exception $e) {
            return "No Data found";
        }
    }

    public function milagereport(Request $request, $id) {
        try {
            $data = $request->get('data');
            $order_id = $data['order_id'];
            $start = Base::tomysqldatetime($data['start_date']);
            $end = Base::tomysqldatetime($data['end_date']);


            $query = distance::query();
            if ($id != 0) {
                // $emp=$data['emp_id'];

                $data = DB::table('distance')
                        ->select('*')
                        ->join('emp_cust_schedule', 'distance.task_id', '=', 'emp_cust_schedule.id')
                        ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                        ->where('orders.added_by', '=', $this->emp_id)
                        ->where('distance.emp_id', $id)
                        ->where('orders.id', $order_id)
                        ->where('distance.start_time', '<=', $end)
                        ->where('distance.end_time', '>=', $start)
                        ->get();
                return Base::touser($data, true);
            } else {
                // $query->where('start_time', '<=',$end)
                //      ->where('start_time','>=',$start);
                // $data = $query->get();

                $data = DB::table('distance')
                        ->select('*')
                        ->join('emp_cust_schedule', 'distance.task_id', '=', 'emp_cust_schedule.id')
                        ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                        ->where('orders.added_by', '=', $this->emp_id)
                        ->where('orders.id', $order_id)
                        ->where('distance.start_time', '<=', $end)
                        ->where('distance.end_time', '>=', $start)
                        ->get();
                return Base::touser($data, true);
            }
        } catch (Exception $e) {
            return "No Data found";
        }
    }

    public function checkaddress() {
        try {
            $info = User::where('user_id', $this->emp_id)->get();
            return $info;
        } catch (Exception $e) {
            return "No Data found";
        }
    }

    public function checkusers() {
        try {
            // $totalcount = User::where('is_delete','false')->where('belongs_manager',$this->emp_id)->count();
            if($this->role == "sub_manager"){
                $EmpMapping = EmpMapping::where("manager_id", '=', $this->emp_id)->first();
                $userPackage = UserPackage::where('user_id', $EmpMapping->admin_id)->with('packageinfo')->with('mapsettings')->get();
            }
            else{
                $userPackage = UserPackage::where('user_id', $this->emp_id)->with('packageinfo')->with('mapsettings')->get();
            }
            
            if (count($userPackage) > 0) {

                return $userPackage;
            } else {
                return 0;
            }
        } catch (Exception $e) {
            return "No Data found";
        }
    }

    public function mapsettings(Request $request) {
        try {
            // $totalcount = User::where('is_delete','false')->where('belongs_manager',$this->emp_id)->count();
            // $userPackage = UserPackage::all();
            // foreach ($userPackage as $up) {
            //   $mapsettings = new MapSettings();
            //   $mapsettings->user_id=$up['user_id'];
            //   $mapsettings->save();
            // }

            $data = $request->get('data');
            // print_r($data);
            $userPackage = MapSettings::where('user_id', $this->emp_id)->get();



            return Base::touser($userPackage, true);
        } catch (Exception $e) {
            return "No Data found";
        }
    }

    public function updatecount() {
        try {
            $totalcount = User::where('is_delete', 'false')->where('belongs_manager', $this->emp_id)->count();
            $emps = UserPackage::where('user_id', $this->emp_id)->first();
            $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
            $emps['no_of_emp'];
            // $emps->no_of_emp = $emps['no_of_emp'] - $totalcount;
            $emps->save();
            return $emps;
        } catch (Exception $e) {
            return "No Data found";
        }
    }

// public function packagemode(){
//   $users = User::all();
//   foreach ($users as $user => $value) {
//     $userpack = new UserPackage();
//     $userpack->package_id;
//     $userpack->beg_date;
//     $userpack->end_date;
//     $userpack->is_trail;
//     $userpack->price
//     $userpack->no_of_emp;
//     $userpack->no_of_cust;
//     $userpack->no_of_task;
//   }
// }
    public function getOrderData() {
        $client = new Client();
        $store = $client->request('GET', 'https://www.localisbig.com/api/1/entity/ms.store_locations', [
            'headers' => ['access-key' => 'b9696cb420a19dd3f59ddbd5bd212d61'],
        ]);
        $storedata = $store->getBody();
        $stdata = json_decode($storedata)->data;


        $res = $client->request('GET', 'https://www.localisbig.com/api/1/entity/ms.orders?sort=[{"field":"updated_on",order : 1}]', [
            'headers' => ['access-key' => 'b9696cb420a19dd3f59ddbd5bd212d61'],
        ]);
        if ($res->getStatusCode() == 200) {
            $data = $res->getBody(); // { "type": "User", ....
//echo $data;
            $order_datas = json_decode($data)->data;
            echo count($order_datas);
            foreach ($order_datas as $newdata => $order_data) {
                # code...
                $receiver_address = $order_data->shipping_address;
                $receiver_phone = $receiver_address->phone;
                $receiver_name = $receiver_address->full_name;
                $receiver_address = $receiver_address->address . $receiver_address->city . $receiver_address->state;
//print_r($receiver_address);

                $pickup_point = $order_data->seller_name;
                $items = $order_data->items;
                $order_id = $order_data->order_id;
                $notes = '';
                $cmt = '';
                $mob = $order_data->payment_method->type;
                if (isset($receiver_email->$order_data->email)) {
                    $receiver_email = $order_data->email;
                } else {
                    $receiver_email = '';
                }

                foreach ($order_data->items as $item => $dataitem) {
                    # code...
                    if ($notes != '') {
                        $notes = $notes . ',' . $dataitem->name;
                    } else {
                        $notes = $dataitem->name;
                    }
                }
                echo count($order_data->items);
                if (isset($order_data->options->select_delivery_time)) {
                    $len = strlen($order_data->options->select_delivery_time);
                    $strtime = substr($order_data->options->select_delivery_time, ($len - 8));
                    $curtime = strtotime('today' . $strtime);
                    $delivery_date = date('Y-m-d H:i:s', $curtime);
                } elseif (isset($order_data->options->select_delivery)) {
                    $len = strlen($order_data->options->select_delivery);
                    $strtime = substr($order_data->options->select_delivery, ($len - 8));
                    $curtime = strtotime('today' . $strtime);
                    $delivery_date = date('Y-m-d H:i:s', $curtime);
                }
                $pickup_add = '';
                $pickup_ladd = '';
                $pickup_long = '';
                $newdata = json_encode($order_data->shipping_by_vendor);
                $ventors = json_decode($newdata, true);
                foreach ($ventors as $vent => $ventor) {
                    try {
                        if (isset($order_data->available_shipping_charges)) {
                            $strdata = $order_data->available_shipping_charges->$vent;
                        }
                    }
                    //return $vent;
                    catch (Exception $e) {
                        return $order_data->available_shipping_charges;
                        //return $strdata;
                    }
                    foreach ($stdata as $st => $stored) {
                        if ($stored->user_id == $strdata[0]->seller) {
                            if ($stored->address != '') {
                                if (isset($stored->latitude)) {
                                    if ($pickup_add != '') {
                                        $pickup_add = $pickup_add . "||," . $stored->address;
                                        $pickup_ladd = $pickup_ladd . "," . $stored->latitude;
                                        $pickup_long = $pickup_long . "," . $stored->longitude;
                                    } else {
                                        $pickup_add = $stored->address;
                                        $pickup_ladd = $stored->latitude;
                                        $pickup_long = $stored->longitude;
                                    }
                                    // echo $pickup_ladd;
                                }
                            } else {
                                echo "string";
                            }
                        }
                    }
                };

                if (isset($order_data->client_details->geo_location->ll[0])) {
                    $lat = $order_data->client_details->geo_location->ll[0];
                    $long = $order_data->client_details->geo_location->ll[1];
                } else {
                    $lat = '';
                    $long = '';
                }
                $orders = task::where('order_id', $order_id)->get();
                if (count($orders)) {
                    try {
                        $task = new task();
                        $task->schedule_date_time = $delivery_date;
                        $Customer = new Customer();
                        $Customer->name = $receiver_name;
                        $Customer->email = $receiver_email;
                        $Customer->emp_id = 1;
                        $Customer->address = $receiver_address;
                        $Customer->loc_lat = $lat;
                        $Customer->loc_lng = $long;
                        $Customer->contact_no = $receiver_phone;
                        $Customer->save();
                        $task->cust_id = $Customer->id;
                        $task->notes = $notes;
                        $task->pick_address = $pickup_add;
                        $task->order_id = $order_id;
                        $task->comments = $cmt;
                        $task->mob = $mob;
                        $task->added_by = 1;
                        $task->cust_email = $receiver_email;
                        $task->sender_name = '';
                        $task->sender_number = '';
                        $task->sender_number = '';
                        $task->picktime = Base::tomysqldatetime(date("Y-m-d H:i:s"));
                        $task->pickup_long = $pickup_ladd;
                        $task->pickup_ladd = $pickup_long;
                        $task->sent_address = '';
                        $task->status = 'Unallocated';
                        $task->method = 'pickup';
                        $task->is_new_address = true;

                        $task->cust_phone = $receiver_phone;
                        $task->loc_lat = $lat;
                        $task->loc_lng = $long;
                        $task->cust_address = $receiver_address;


                        $task->save();

                        $task_status = new ScheduleTaskStatus();
                        $task_status->emp_id = 1;
                        $task_status->task_id = $task->id;
                        $task_status->address = '';
                        $task_status->lat = '';
                        $task_status->long = '';
                        $task_status->status = 'Unallocated';
                        $task_status->timestamps = Base::tomysqldatetime(date("Y-m-d H:i:s"));
                        $task_status->save();
                    } catch (Exception $e) {
                        
                    }
                }
            }
        }
    }

    public function existaddemp() {
        $users = User::where('role_id', '2')->get();

        foreach ($users as $use => $user) {
            $userPackage = new UserPackage();

            $userPackage->user_id = $user->user_id;
            $userPackage->package_id = "1";
            $date = date('Y-m-d H:i:s');
            // $d=strtotime("+1 Months"); 
            $d = strtotime("+7 days");
            $enddate = date("Y-m-d H:i:s", $d);
            $userPackage->beg_date = $date;
            $userPackage->end_date = $enddate;
            // $userPackage->no_of_emp = 2;
            $userPackage->no_of_cust = 0;
            $userPackage->no_of_task = 0;
            $userPackage->save();
            $user = User::where('user_id', $user->user_id)->first();
            $user->current_package_id = $userPackage->id;
            $user->update();
        }
# code...
    }

}

?>
