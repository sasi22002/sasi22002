<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\EmpCustSchedule as emp_cust;
use App\Models\TravelHistory as api;
use App\Models\ApiOrders;
use App\Models\ItemMap;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerLiveTrackingController extends Controller
{

    public function show(Request $request, $order_id)
    {
        $order_id = decrypt($order_id);
        $arr = explode('-', $order_id);
        $order_id = $arr[0];
        $cust_id = $arr[1];
        // try {
            $order  = ApiOrders::where('id',$order_id)->get()->toArray()[0];

            $customer = Customer::find($cust_id);

            $arr = emp_cust::where('mt_order_id', $order_id)->pluck('cust_phone');
            $i=0;
            $stage=0;
            foreach($arr as $val){
                $i++;
                if ($val == $customer->contact_no) {
                    $stage = $i;
                    break;
                }
            }

            $data     = emp_cust::where('mt_order_id', $order_id)->where('cust_phone',$customer->contact_no)->get()->toArray();

            if ($data) {

                $tracking_order = $data;
                $tracking_order[0]['delivery_logic'] = $order['delivery_logic'];
                // if(($order['delivery_logic']==1) || ($order['delivery_logic']==3))
                // {
                      foreach ($data as $key => $multidelivery) {

                        $items     = ItemMap::where('order_id', $order_id)->where('stage',$stage)->with('Items')->get()->toArray();

                        $getdelivery_logic = ApiOrders::where('id',$order_id)->select('delivery_logic')->first()->toArray();
                        
                        if ($data[$key]['allocated_emp_id']) {
                        $tracking_order[$key]['emp_info']                  = \App\Models\User::where('user_id', $data[0]['allocated_emp_id'])->get(['user_id', 'first_name', 'last_name', 'phone', 'profile_image'])->toArray()[0];
                        $tracking_order[$key]['emp_info']['profile_image'] = json_decode($tracking_order[$key]['emp_info']['profile_image']);
                        $tracking_order[$key]['emp_info']['geo']           = api::where('user_id', $data[0]['allocated_emp_id'])->get(['lat', 'lng', 'timestamp'])->last();
                        $tracking_order[$key]['emp_info']['socket_id']     = $data[0]['allocated_emp_id'];
                        $tracking_order[$key]['review']                    = \App\Models\Review::where('task_id', $order_id)->where('cust_id',$cust_id)->get()->toArray();
                        $tracking_order[$key]['items'] =$items;
                        $tracking_order[$key]['delivery_logic'] = $getdelivery_logic['delivery_logic'];
                        // $tracking_order[0]['cust_phone'] = $value['cust_phone'];
                        unset($tracking_order[$key]['emp_info']['user_id']);
                       } 
                       $stage ++;
                   }
                // }
                
                // if ($data['allocated_emp_id']) {
                //     $tracking_order['emp_info']                  = \App\Models\User::where('user_id', $data['allocated_emp_id'])->get(['user_id', 'first_name', 'last_name', 'phone', 'profile_image'])->toArray()[0];
                //     $tracking_order['emp_info']['profile_image'] = json_decode($tracking_order['emp_info']['profile_image']);
                //     $tracking_order['emp_info']['geo']           = api::where('user_id', $data['allocated_emp_id'])->get(['lat', 'lng', 'timestamp'])->last();
                //     $tracking_order['emp_info']['socket_id']     = $data['allocated_emp_id'];
                //     $tracking_order['review']                    = \App\Models\Review::where('task_id', $order_id)->get()->toArray();
                //     unset($tracking_order['emp_info']['user_id']);
                // }

                // print_r($tracking_order);
                // print_r($tracking_order);
                return view('customer_tracking_view', ['order_info' => json_encode($tracking_order, true)]);

            } else {
                Base::app_unauthorized();
            }
        // } catch (\Exception $e) {

        //     Base::app_unauthorized();

        // }

    }
}
