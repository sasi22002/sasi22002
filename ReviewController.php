<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Customer;
use App\Models\ApiAuth;
use App\Models\EmpCustSchedule as task;
use App\Http\Controllers\Base;
use Validator;
use DB;

class ReviewController extends Controller {

    public function index() {
        if ($this->admin) {
            $data = Review::all();
            return Base::touser($data, true);
        }

        if ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);
            $array = Review::with('cust', 'emp_info')->whereIn('emp_id', $belongsemp)->get()->toArray();

            foreach ($array as $key => $value) {
                $array[$key]['emp_info'] = $array[$key]['emp_info']['first_name'] . ' ' . $array[$key]['emp_info']['last_name'];
            }
            return Base::touser($array, true);
        }
    }

    public function employeereview() {
        $user_id = $this->emp_id;
        $belongsemp = DB::table('emp_mapping')->where('manager_id', $user_id)->orWhere('admin_id', $user_id)->pluck('emp_id');

        $data = DB::table('user')->whereIn('user_id', $belongsemp)->get()->toArray();

        foreach ($data as $key => $value) {
            $order_ids = DB::table('orders')->where('emp_id', $value->user_id)->where('added_by',$user_id)->pluck('id');
//                 $task_ids = task::where('added_by',$value->user_id)->pluck('mt_order_id')->toArray();
            $array = Review::where('emp_id', $value->user_id)->whereIn('task_id', $order_ids)->pluck('stars')->toArray();
            if (array_sum($array) > 0) {
                $stars = array_sum($array) / count($array);
            } else {
                $stars = 0;
            }
            $data[$key]->stars = $stars;
        }
        return Base::touser($data, true);
    }

    public function customer_review($id) {
        $order_ids = DB::table('orders')->where('emp_id', $id)->where('added_by',$this->emp_id)->pluck('id');
        $array = Review::with('cust', 'emp_info')->where('emp_id', $id)->whereIn('task_id', $order_ids)->get()->toArray();
        foreach ($array as $key => $value) {
            $array[$key]['emp_info'] = $array[$key]['emp_info']['first_name'] . ' ' . $array[$key]['emp_info']['last_name'];
        }
        return Base::touser($array, true);
    }

    public function CustomerReview(Request $request, $order_id) {
        $data = $request->input('data');
        $value = decrypt($order_id);
        $arr = explode('-', $value);
        $order_id = $arr[0];
        $cust_id = $arr[1];

        $rules = [
            'stars' => 'required|int',
            'review' => 'required|string',
        ];

        $validator = Validator::make($data, $rules);
        // dd($data);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }


        // $order_id = decrypt($order_id);


        $orders = DB::table('orders')->where('id', $order_id)->get()->toArray()[0];
        $order_info = \App\Models\EmpCustSchedule::where('mt_order_id', $order_id)->get()->toArray()[0];
        $review = Review::where('task_id', $order_id)->where('cust_id', $cust_id)->first();



        if (!$review) {
            $review = new Review();
        }
        // dd($orders->emp_id);
        // $customer = Customer::where('contact_no',$cust_phone)->where('emp_id',$order_info['added_by'])->first();
        // dd($order_info['cust_id']);

        $review->cust_id = $cust_id;
        $review->date = date('Y-m-d');
        $review->task_id = $order_id;
        $review->emp_id = $orders->emp_id;
        $review->stars = $data['stars'];
        $review->review = $data['review'];
        $review->save();

        return Base::touser('Thanks for your Review', true);
    }

    public function show($id) {
        return Base::touser(Review::find($id), true);
    }

}
