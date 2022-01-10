<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Excel;
use App\Http\Controllers\Base;
use Validator;
use App\Imports\TaskImport;
use Session;
use App\Http\Controller\ApiOrderScheduleController;

class Bulkupload extends Controller
{

	public function parse_csv_file($csvfile) {
    $csv = Array();
    $rowcount = 0;
    if (($handle = fopen($csvfile, "r")) !== FALSE) {
        $max_line_length = defined('MAX_LINE_LENGTH') ? MAX_LINE_LENGTH : 10000;
        $header = fgetcsv($handle, $max_line_length);
        $header_colcount = count($header);
        while (($row = fgetcsv($handle, $max_line_length)) !== FALSE) {
            $row_colcount = count($row);
            if ($row_colcount == $header_colcount) {
                $entry = array_combine($header, $row);
                $csv[] = $entry;
            }
            else {
                error_log("csvreader: Invalid number of columns at line " . ($rowcount + 2) . " (row " . ($rowcount + 1) . "). Expected=$header_colcount Got=$row_colcount");
                return Base::touser("csvreader: Invalid number of columns at line " . ($rowcount + 2) . " (row " . ($rowcount + 1) . "). Expected=$header_colcount Got=$row_colcount",false);
                //return null;
            }
            $rowcount++;
        }
        //echo "Totally $rowcount rows found\n";
        fclose($handle);
    }
    else {
        error_log("csvreader: Could not read CSV \"$csvfile\"");
        return Base::touser("csvreader: Could not read CSV \"$csvfile\"",false);
    }
    return $csv;
}

    public function latlong($location) {
            $url = htmlspecialchars_decode('https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($location) . '&key=AIzaSyCuVbismP8TWSw2BSPG1Jux5xer1CQDjJk');
            //return $url;
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

    public function index(Request $request)
    {
    	$file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();

        /*multi upload*/
        $filePath = $file->getRealPath();

        $csv = self::parse_csv_file($filePath);

        $result = [];
        $task = array(
            'multiple_delivery' => array(),
            'multiple_pickup' => array()
        );
        $push = [];
        foreach ($csv as $key => $value) {
            if(array_key_exists("order_id", $value)){
                $result[$value["order_id"]][] = $value;
            }
        }

        foreach ($result as $key => $row) {
            foreach ($row as $key => $value) {
                $order_id = $value['order_id'];
                if(!array_key_exists($order_id, $push)) {
                    $push[$order_id] = [];
                }
//                $order_map = $push[$order_id];
                $deliveryLogic = $value["Delivery Logic (1 = Single Pickup and Multi Delivery 2 = Multi Pickup and Single Delivery 3 = Single Pickup and Single Delivery) *"];
                if ($deliveryLogic == 1) {
                    if(count($push[$order_id]) > 0) {
                        $push[$order_id][0]['multiple_delivery'][] = [
                            'schedule' => $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"],
                            'order_id' => $value["order_id"],
                            'delivery_notes2' => explode(",", $value["Items *"]),
                            'cust_name' => $value["Receiver Name *"],
                            'cust_phone' => $value["Receiver Phone *"],
                            'cust_email' => $value["Receiver Email"],
                            'temp_cust_email' => "",
                            'cust_address' => $value["Receiver Address *"],
                            'loc_lat' => $value["Receiver Latitude *"],
                            'loc_lng' => $value["Receiver Logitide *"],
                            'cust_id' => "",
                            'receiver_name' => $value["Receiver Name *"],
                        ];
                    } else{
                        $task["is_multidelivery"] = true;
                        $task["is_multipickup"] = false;
                        $task["status"] = "Unallocated";
                        $task["type"]  = "0";
                        $task["method"] = "Pickup";
                        $task["delivery_logic"] = $value["Delivery Logic (1 = Single Pickup and Multi Delivery 2 = Multi Pickup and Single Delivery 3 = Single Pickup and Single Delivery) *"];
                        $task["sender_name"] = $value["Sender Name"];
                        $task["sender_number"] = $value["Sender Number *"];
                        $task["sent_address"] = $value["Sender Address"];
                        $task["is_geo_fence"] = 1;
                        $task["schedule"] = $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"];
                        $task["picktime"] = $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"];
                        $task["loc_lat"] = $value["Receiver Latitude *"];
                        $task["loc_lng"] = $value["Receiver Logitide *"];
                        $task["pickup_ladd"] = $value["Pickup Latitude *"];
                        $task["pickup_long"] = $value["Pickup Logitude *"];
                        $task["sent_ladd"] = $value["Sender Latitude"];
                        $task["sent_long"] = $value["Sender Logitude"];
                        $task["geo_fence_meter"] = $value["Geo Fence Meter ( Default = 200) *"];
                        $task["sheet_order_id"] = $value["order_id"];
                        $task["multiple_pickup"] = [];
                        $task['multiple_pickup'][0] = [
                            'picktime' => $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"],
                            'pick_address' => $value['Pickup Address *'],
                            'pickup_ladd' => $value['Pickup Latitude *'],
                            'pickup_long' => $value['Pickup Logitude *'],
                            'delivery_notes3' => explode(",", $value["Items *"])
                        ];

                        $task['multiple_delivery'][] = [
                            'schedule' => $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"],
                            'order_id' => $value["order_id"],
                            'delivery_notes2' => explode(",", $value["Items *"]),
                            'cust_name' => $value["Receiver Name *"],
                            'cust_phone' => $value["Receiver Phone *"],
                            'cust_email' => $value["Receiver Email"],
                            'temp_cust_email' => "",
                            'cust_address' => $value["Receiver Address *"],
                            'loc_lat' => $value["Receiver Latitude *"],
                            'loc_lng' => $value["Receiver Logitide *"],
                            'cust_id' => "",
                            'receiver_name' => $value["Receiver Name *"],
                        ];
                        array_push($push[$order_id],$task);
                    }
                } elseif ($deliveryLogic == 2) {
                    if(count($push[$order_id]) > 0) {
                        $push[$order_id][0]['multiple_pickup'][] = [
                            'picktime' => $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"],
                            'pick_address' => $value['Pickup Address *'],
                            'pickup_ladd' => $value['Pickup Latitude *'],
                            'pickup_long' => $value['Pickup Logitude *'],
                            'delivery_notes3' => explode(",", $value["Items *"])
                        ];
                    } else {
                        $task["is_multidelivery"] = false;
                        $task["is_multipickup"] = true;
                        $task["status"] = "Unallocated";
                        $task["type"]  = "0";
                        $task["method"] = "Pickup";
                        $task["delivery_logic"] = $value["Delivery Logic (1 = Single Pickup and Multi Delivery 2 = Multi Pickup and Single Delivery 3 = Single Pickup and Single Delivery) *"];
                        $task["sender_name"] = $value["Sender Name"];
                        $task["sender_number"] = $value["Sender Number *"];
                        $task["sent_address"] = $value["Sender Address"];
                        $task["is_geo_fence"] = 1;
                        $task["schedule"] = $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"];
                        $task["picktime"] = $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"];
                        $task["loc_lat"] = $value["Receiver Latitude *"];
                        $task["loc_lng"] = $value["Receiver Logitide *"];
                        $task["pickup_ladd"] = $value["Pickup Latitude *"];
                        $task["pickup_long"] = $value["Pickup Logitude *"];
                        $task["sent_ladd"] = $value["Sender Latitude"];
                        $task["sent_long"] = $value["Sender Logitude"];
                        $task["geo_fence_meter"] = $value["Geo Fence Meter ( Default = 200) *"];
                        $task["sheet_order_id"] = $value["order_id"];
                        $task["multiple_delivery"] = [];
                        $task['multiple_pickup'][] = [
                            'picktime' => $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"],
                            'pick_address' => $value['Pickup Address *'],
                            'delivery_notes3' => explode(",", $value["Items *"]),
                            'pickup_ladd' => $value['Pickup Latitude *'],
                            'pickup_long' => $value['Pickup Logitude *']
                        ];

                        $task['multiple_delivery'][0] = [
                            'schedule' => $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"],
                            'order_id' => $value["order_id"],
                            'cust_name' => $value["Receiver Name *"],
                            'cust_phone' => $value["Receiver Phone *"],
                            'cust_email' => $value["Receiver Email"],
                            'temp_cust_email' => "",
                            'cust_address' => $value["Receiver Address *"],
                            'loc_lat' => $value["Receiver Latitude *"],
                            'loc_lng' => $value["Receiver Logitide *"],
                            'cust_id' => "",
                            'receiver_name' => $value["Receiver Name *"],
                        ];
                        array_push($push[$order_id],$task);
                    }
                } elseif ($deliveryLogic == 3) {
                    $task["multiple_pickup"] = [];
                    $task["multiple_delivery"] = [];
                    $task["is_multidelivery"] = false;
                    $task["is_multipickup"] = false;
                    $task["status"] = "Unallocated";
                    $task["type"]  = "0";
                    $task["method"] = "Pickup";
                    $task["delivery_logic"] = $value["Delivery Logic (1 = Single Pickup and Multi Delivery 2 = Multi Pickup and Single Delivery 3 = Single Pickup and Single Delivery) *"];
                    $task["sender_name"] = $value["Sender Name"];
                    $task["sender_number"] = $value["Sender Number *"];
                    $task["sent_address"] = $value["Sender Address"];
                    $task["is_geo_fence"] = 1;
                    $task["schedule"] = $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"];
                    $task["picktime"] = $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"];
                    $task["loc_lat"] = $value["Receiver Latitude *"];
                    $task["loc_lng"] = $value["Receiver Logitide *"];
                    $task["pickup_ladd"] = $value["Pickup Latitude *"];
                    $task["pickup_long"] = $value["Pickup Logitude *"];
                    $task["sent_ladd"] = $value["Sender Latitude"];
                    $task["sent_long"] = $value["Sender Logitude"];
                    $task["geo_fence_meter"] = $value["Geo Fence Meter ( Default = 200) *"];
                    $task["sheet_order_id"] = $value["order_id"];
                    $task['multiple_pickup'][0] = [
                        'picktime' => $value["Pickup Date Time (YYYY-MM-DD H:M:S) *"],
                        'pick_address' => $value['Pickup Address *'],
                        'pickup_ladd' => $value['Pickup Latitude *'],
                        'pickup_long' => $value['Pickup Logitude *']
                    ];

                    $task['multiple_delivery'][0] = [
                        'schedule' => $value["Delivery Date Time (YYYY-MM-DD H:M:S) *"],
                        'order_id' => $value["order_id"],
                        'delivery_notes2' => explode(",", $value["Items *"]),
                        'cust_name' => $value["Receiver Name *"],
                        'cust_phone' => $value["Receiver Phone *"],
                        'cust_email' => $value["Receiver Email"],
                        'temp_cust_email' => "",
                        'cust_address' => $value["Receiver Address *"],
                        'loc_lat' => $value["Receiver Latitude *"],
                        'loc_lng' => $value["Receiver Logitide *"],
                        'cust_id' => "",
                        'receiver_name' => $value["Receiver Name *"],
                    ];
                    array_push($push[$order_id],$task);
                } else {
                    Base::touser("Please give valid Delivery Logic Input",false);
                }
            }
        }


        // echo "<pre>";
        //     print_r($push);
        // echo "</pre>";
        // die();
        $id = $this->emp_id;
        $timezone = Base::client_time($id);
        $success_orders = [];
        $failed_orders  = [];
        $split_orders = [
            'success_orders' => array(),
            'failed_orders'  => array(),
        ];

        $key_check = [];
        foreach ($push as $key => $value) {
            foreach ($value[0]["multiple_pickup"] as $picktime_) {
                if (strtotime($picktime_['picktime']) < strtotime($timezone)) {
                    if(!array_key_exists($key, $failed_orders)){
                        $failed_orders[$key] = $value;
                        //$failed_orders[$key]["error_log"][] = "Pickup Time should not be before today.";
                        $failed_orders[$key][0]["error_log_1"] = "Pickup Time should not be before today.";
                    }
                }
            }
            $delivery_logic = $value[0]["delivery_logic"];
            $delivery_time = $value[0]["multiple_delivery"];
            $picktime = $value[0]["multiple_pickup"];
            if ($delivery_logic == 1) {
                foreach ($delivery_time as $deltime_) {
                    if (strtotime($deltime_['schedule']) <= strtotime($picktime[0]['picktime'])) {
                        if(!array_key_exists($key, $failed_orders)){
                            $failed_orders[$key] = $value;
                            //$failed_orders[$key]["error_log"][] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                            $failed_orders[$key][0]["error_log_2"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                        }else{
                            //$failed_orders[$key]["error_log"][] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                            $failed_orders[$key][0]["error_log_2"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                        }
                    }
                }
            } else if ($delivery_logic == 2) {
                $end_picktime = end($value[0]['multiple_pickup']);
                foreach ($delivery_time as $deltime_) {
                    if (strtotime($deltime_['schedule']) <= strtotime($end_picktime['picktime'])) {
                        if(!array_key_exists($key, $failed_orders)){
                            $failed_orders[$key] = $value;
                            //$failed_orders[$key]["error_log"][] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                            $failed_orders[$key][0]["error_log_3"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                        }else{
                            //$failed_orders[$key]["error_log"][] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                            $failed_orders[$key][0]["error_log_3"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                        }
                    }
                }
            } else if ($delivery_logic == 3) {
                if (strtotime($delivery_time[0]['schedule']) <= strtotime($picktime[0]['picktime'])) {
                    if(!array_key_exists($key, $failed_orders)){
                        $failed_orders[$key] = $value;
                        $failed_orders[$key][0]["error_log_4"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                    }else{
                        $failed_orders[$key][0]["error_log_4"] = "The given pickup and delivery time are not valid. Please make sure your delivery time should be greater than pickup time.";
                    }
                }
            }

            foreach ($picktime as $_picktime) {
                if (isset($_picktime['pick_address']) and empty(explode('|', self::latlong($_picktime['pick_address']))[0])) {
                    if(!array_key_exists($key, $failed_orders)){
                        $failed_orders[$key] = $value;
                        $failed_orders[$key][0]["error_log_5"] = "Please enter a valid pickup location";
                    }else{
                        $failed_orders[$key][0]["error_log_5"] = "Please enter a valid pickup location";
                    }
                }
            }

            foreach ($delivery_time as $delivery_time) {
                if (isset($delivery_time['cust_address']) and empty(explode('|', self::latlong($delivery_time['cust_address']))[0])) {
                    if(!array_key_exists($key, $failed_orders)){
                        $failed_orders[$key] = $value;
                        $failed_orders[$key][0]["error_log_6"] = "Please enter a valid receiver location";
                    }else{
                        $failed_orders[$key][0]["error_log_6"] = "Please enter a valid receiver location";
                    }
                    
                }
            }

            if (isset($data['sent_address']) and empty(explode('|', self::latlong($data['sent_address']))[0])) {
                if(!array_key_exists($key, $failed_orders)){
                        $failed_orders[$key] = $value;
                        $failed_orders[$key][0]["error_log_7"] = "Sender Location is not valid kindly use drag and drop";
                    }else{
                        $failed_orders[$key][0]["error_log_7"] = "Sender Location is not valid kindly use drag and drop";
                    }
            }
                
        }
        
        $write_orders = [];
        $common = [];
        foreach ($failed_orders as $key => $value) {
            if($failed_orders[$key][0]["delivery_logic"] == 1){
                foreach ($failed_orders[$key][0]["multiple_delivery"] as $value) {
                    $common["order_id"] =  $failed_orders[$key][0]["sheet_order_id"];
                    $common["Pickup Date Time (YYYY-MM-DD H:M:S) *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["picktime"];
                    $common["Pickup Address *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pick_address"];
                    $common["Pickup Latitude *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pickup_ladd"];
                    $common["Pickup Logitude *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pickup_long"];
                    $common["Delivery Date Time (YYYY-MM-DD H:M:S) *"] =  $value["schedule"];
                    $common["Items *"] =  $value["delivery_notes2"];
                    $common["Receiver Phone *"] =  $value["cust_phone"];
                    $common["Receiver Address *"] =  $value["cust_address"];
                    $common["Receiver Latitude *"] =  $value["loc_lat"];
                    $common["Receiver Logitide *"] =  $value["loc_lng"];
                    $common["Receiver Name *"] =  $value["cust_name"];
                    $common["Receiver Email"] =  $value["cust_email"];
                    $common["Sender Name"] =  $failed_orders[$key][0]["sender_name"];
                    $common["Sender Number *"] =  $failed_orders[$key][0]["sender_number"];
                    $common["Sender Address"] =  $failed_orders[$key][0]["sent_address"];
                    $common["Sender Latitude"] =  $failed_orders[$key][0]["sent_ladd"];
                    $common["Sender Logitude"] =  $failed_orders[$key][0]["sent_long"];
                    $common["Geo Fence Meter ( Default = 200) *"] =  $failed_orders[$key][0]["geo_fence_meter"];
                    $common["error_log_1"] =  isset($failed_orders[$key][0]["error_log_1"]) ? $failed_orders[$key][0]["error_log_1"] : '';
                    $common["error_log_2"] =  isset($failed_orders[$key][0]["error_log_2"]) ? $failed_orders[$key][0]["error_log_2"] : '';
                    $common["error_log_3"] =  isset($failed_orders[$key][0]["error_log_3"]) ? $failed_orders[$key][0]["error_log_3"] : '';
                    $common["error_log_4"] =  isset($failed_orders[$key][0]["error_log_4"]) ? $failed_orders[$key][0]["error_log_4"] : '';
                    $common["error_log_5"] =  isset($failed_orders[$key][0]["error_log_5"]) ? $failed_orders[$key][0]["error_log_5"] : '';
                    $common["error_log_6"] =  isset($failed_orders[$key][0]["error_log_6"]) ? $failed_orders[$key][0]["error_log_6"] : '';
                    $common["error_log_7"] =  isset($failed_orders[$key][0]["error_log_7"]) ? $failed_orders[$key][0]["error_log_7"] : '';
                    array_push($write_orders, $common);   
                }
            }elseif ($failed_orders[$key][0]["delivery_logic"] == 2) {
                foreach ($failed_orders[$key][0]["multiple_pickup"] as $value) {
                    $common["order_id"] =  $failed_orders[$key][0]["sheet_order_id"];
                    $common["Pickup Date Time (YYYY-MM-DD H:M:S) *"] =  $value["picktime"];
                    $common["Pickup Address *"] =  $value["pick_address"];
                    $common["Pickup Latitude *"] =  $value["pickup_ladd"];
                    $common["Pickup Logitude *"] =  $value["pickup_long"];
                    $common["Delivery Date Time (YYYY-MM-DD H:M:S) *"] =  $failed_orders[$key][0]["schedule"];
                    $common["Items *"] =  $value["delivery_notes3"];
                    $common["Receiver Phone *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_phone"];
                    $common["Receiver Address *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_address"];
                    $common["Receiver Latitude *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["loc_lat"];
                    $common["Receiver Logitide *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["loc_lng"];
                    $common["Receiver Name *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_name"];
                    $common["Receiver Email"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_email"];
                    $common["Sender Name"] =  $failed_orders[$key][0]["sender_name"];
                    $common["Sender Number *"] =  $failed_orders[$key][0]["sender_number"];
                    $common["Sender Address"] =  $failed_orders[$key][0]["sent_address"];
                    $common["Sender Latitude"] =  $failed_orders[$key][0]["sent_ladd"];
                    $common["Sender Logitude"] =  $failed_orders[$key][0]["sent_long"];
                    $common["Geo Fence Meter ( Default = 200) *"] =  $failed_orders[$key][0]["geo_fence_meter"];
                    $common["error_log_1"] =  isset($failed_orders[$key][0]["error_log_1"]) ? $failed_orders[$key][0]["error_log_1"] : '';
                    $common["error_log_2"] =  isset($failed_orders[$key][0]["error_log_2"]) ? $failed_orders[$key][0]["error_log_2"] : '';
                    $common["error_log_3"] =  isset($failed_orders[$key][0]["error_log_3"]) ? $failed_orders[$key][0]["error_log_3"] : '';
                    $common["error_log_4"] =  isset($failed_orders[$key][0]["error_log_4"]) ? $failed_orders[$key][0]["error_log_4"] : '';
                    $common["error_log_5"] =  isset($failed_orders[$key][0]["error_log_5"]) ? $failed_orders[$key][0]["error_log_5"] : '';
                    $common["error_log_6"] =  isset($failed_orders[$key][0]["error_log_6"]) ? $failed_orders[$key][0]["error_log_6"] : '';
                    $common["error_log_7"] =  isset($failed_orders[$key][0]["error_log_7"]) ? $failed_orders[$key][0]["error_log_7"] : '';
                    array_push($write_orders, $common);
                }
            }elseif ($failed_orders[$key][0]["delivery_logic"] == 3) {
                    $common["order_id"] =  $failed_orders[$key][0]["sheet_order_id"];
                    $common["Pickup Date Time (YYYY-MM-DD H:M:S) *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["picktime"];
                    $common["Pickup Address *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pick_address"];
                    $common["Pickup Latitude *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pickup_ladd"];
                    $common["Pickup Logitude *"] =  $failed_orders[$key][0]["multiple_pickup"][0]["pickup_long"];
                    $common["Delivery Date Time (YYYY-MM-DD H:M:S) *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["schedule"];
                    $common["Items *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["delivery_notes2"];
                    $common["Receiver Phone *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_phone"];
                    $common["Receiver Address *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_address"];
                    $common["Receiver Latitude *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["loc_lat"];
                    $common["Receiver Logitide *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["loc_lng"];
                    $common["Receiver Name *"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_name"];
                    $common["Receiver Email"] =  $failed_orders[$key][0]["multiple_delivery"][0]["cust_email"];
                    $common["Sender Name"] =  $failed_orders[$key][0]["sender_name"];
                    $common["Sender Number *"] =  $failed_orders[$key][0]["sender_number"];
                    $common["Sender Address"] =  $failed_orders[$key][0]["sent_address"];
                    $common["Sender Latitude"] =  $failed_orders[$key][0]["sent_ladd"];
                    $common["Sender Logitude"] =  $failed_orders[$key][0]["sent_long"];
                    $common["Geo Fence Meter ( Default = 200) *"] =  $failed_orders[$key][0]["geo_fence_meter"];
                    $common["error_log_1"] =  isset($failed_orders[$key][0]["error_log_1"]) ? $failed_orders[$key][0]["error_log_1"] : '';
                    $common["error_log_2"] =  isset($failed_orders[$key][0]["error_log_2"]) ? $failed_orders[$key][0]["error_log_2"] : '';
                    $common["error_log_3"] =  isset($failed_orders[$key][0]["error_log_3"]) ? $failed_orders[$key][0]["error_log_3"] : '';
                    $common["error_log_4"] =  isset($failed_orders[$key][0]["error_log_4"]) ? $failed_orders[$key][0]["error_log_4"] : '';
                    $common["error_log_5"] =  isset($failed_orders[$key][0]["error_log_5"]) ? $failed_orders[$key][0]["error_log_5"] : '';
                    $common["error_log_6"] =  isset($failed_orders[$key][0]["error_log_6"]) ? $failed_orders[$key][0]["error_log_6"] : '';
                    $common["error_log_7"] =  isset($failed_orders[$key][0]["error_log_7"]) ? $failed_orders[$key][0]["error_log_7"] : '';
                array_push($write_orders, $common);
            }

        }

        print_r($write_orders);
        die();
        
        foreach ($push as $key => $value) {
        	$send_data = $value[0];
        	
        	$returnData = ApiOrderScheduleController::createOrder($send_data, $this->emp_id, $this->admin, $this->backend, $this->manager, "", "", "");
        	if($returnData['status'] == false){
        		return Base::touser($returnData['msg'], $returnData['status']);
        	}
        }

        // $emp_id=$this->emp_id;
        // Session::put('emp_id',$emp_id);
        // $ref_file = $emp_id . '.' . $extension;
        // $file->move(base_path().'/public/files/', $ref_file );

        // Excel::import(new TaskImport, base_path().'/public/files/'.$ref_file);
        // $result=explode('|',Session::get('result'));
        // if($result[0]==0)
        // {
        //     unlink(base_path().'/public/files/'.$ref_file);
        //     return Base::touser("Uploaded file is not readable.", true);
        // }
        // elseif ($result[1]==0) {
        //     unlink(base_path().'/public/files/'.$ref_file);
        //     return Base::touser("No Records inserted kindly follow sample file format", true);
        // }
        // else
        // {
        //     unlink(base_path().'/public/files/'.$ref_file);

        //     return Base::touser('Tasks Uploaded Successfully Total Records:'.$result[0].' Inserted:'.$result[1].' Failed:'.$result[2], true);
        // }
    }
}
