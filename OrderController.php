<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\OrderBooking as order;
use App\Models\ProductOrder as item;
use Illuminate\Http\Request;
use Validator;

class OrderController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = order::with('product_info')->with('takenby')
->with('customer')
            ->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = order::with('product_info')->with('takenby')
->with('customer')->whereIn('emp_id', $belongsemp)->get()->toArray();
        } else {
            $array = order::with('product_info')->where('emp_id', $this->emp_id)->get()->toArray();
        }

        foreach ($array as $i => $item) {

 if ($this->admin || $this->backend || $this->manager) {

            if($array[$i]['customer'])
            {
                $array[$i]['customer'] = $array[$i]['customer']['name'];
            }

               if($array[$i]['takenby'])
            {
                $array[$i]['takenby'] = $array[$i]['takenby']['first_name'] .' '.$array[$i]['takenby']['last_name'] ;
            }

}

            $array[$i]['files_info'] = (Array) json_decode(stripslashes($item['files_info']));
        }

        return Base::touser($array, true);
    }

    public function store(Request $request)
    {
        $rules = [
            'cust_id'    => 'required|exists:customers,id',
            'quote_ref'  => 'required',
            'po_num'     => 'required|unique:order_bookings,po_num',
            'po_date'    => 'required',
            'order_date' => 'required',
            'ship_to'    => 'required',
            'bil_to'     => 'required',
            'emp_id'     => 'exists:user,user_id',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $order             = new order();
        $data              = $request->input('data');
        $order->remarks    = isset($data['remarks']) ? $data['remarks'] : null;
        $order->cust_id    = $data['cust_id'];
        $order->quote_ref  = $data['quote_ref'];
        $order->order_date = Base::tomysqldate($data['order_date']);
        $order->po_num     = $data['po_num'];
        $order->po_date    = Base::tomysqldate($data['po_date']);
        $order->ship_to    = $data['ship_to'];
        $order->bil_to     = $data['bil_to'];

        $order->status     = isset($data['status']) ? $data['status'] : 0;

        if ($this->admin || $this->backend) {
            $order->emp_id = isset($data['emp_id']) ? $data['emp_id'] : null;
        } elseif ($this->manager) {
            $order->emp_id = isset($data['emp_id']) ? $data['emp_id'] : $this->emp_id;
        } else {
            $order->emp_id = $this->emp_id;
        }

        $order->files_info = json_encode($data['files_info']);

        $order->save();

        $data['product_info'] = array_filter($data['product_info']);

        foreach ($data['product_info'] as $key => $value) {
            $data['product_info'][$key]['order_id']     = $order->order_booking_id;
            $data['product_info'][$key]['pro_req_date'] = Base::tomysqldate($data['product_info'][$key]['pro_req_date']);
        }

        item::insert($data['product_info']);

        return Base::touser('Order Created', true);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if ($this->admin || $this->backend) {
            $array = order::with('product_info')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = order::with('product_info')->whereIn('emp_id', $belongsemp)->find($id)->toArray();
        } else {
            $array = order::with('product_info')->where('emp_id', $this->emp_id)->find($id)->toArray();
        }

        $array['files_info'] = (Array) json_decode(stripslashes($array['files_info']));

        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'cust_id'    => 'required|exists:customers,id',
            'quote_ref'  => 'required',
            'po_num'     => 'required|unique:order_bookings,po_num,' . $id . ',order_booking_id',
            'po_date'    => 'required',
            'order_date' => 'required',
            'ship_to'    => 'required',
            'bil_to'     => 'required',
            'emp_id'     => 'exists:user,user_id',

        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        \DB::beginTransaction();

        try {
            $order             = new order();
            $data              = $request->input('data');
            $order             = $order->where('order_booking_id', '=', $id)->first();
            $order->remarks    = isset($data['remarks']) ? $data['remarks'] : null;
            $order->cust_id    = $data['cust_id'];
            $order->quote_ref  = $data['quote_ref'];
            $order->order_date = Base::tomysqldate($data['order_date']);
            $order->po_num     = $data['po_num'];
            $order->po_date    = Base::tomysqldate($data['po_date']);
            $order->ship_to    = $data['ship_to'];
            $order->bil_to     = $data['bil_to'];
            $order->status     = isset($data['status']) ? $data['status'] : 0;

            if ($this->admin || $this->backend) {
                $order->emp_id = isset($data['emp_id']) ? $data['emp_id'] : null;
            } elseif ($this->manager) {
                $order->emp_id = isset($data['emp_id']) ? $data['emp_id'] : $this->emp_id;
            } else {
                $order->emp_id = $this->emp_id;
            }

            $order->files_info = json_encode($data['files_info']);
            $order->save();
            $data['product_info'] = array_filter($data['product_info']);

            item::where('order_id', '=', $order->order_booking_id)->delete();

            foreach ($data['product_info'] as $key => $value) {
                $data['product_info'][$key]['order_id']     = $order->order_booking_id;
                $data['product_info'][$key]['pro_req_date'] = Base::tomysqldate($data['product_info'][$key]['pro_req_date']);

                item::insert($data['product_info'][$key]);
            }
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }

        \DB::commit();

        return Base::touser('Order Updated', true);
    }

    public function destroy($id)
    {
        item::where('order_id', '=', $id)->delete();

        $api = order::find($id);

        $api->delete();

        return Base::touser('Order Deleted', true);

        return self::index();
    }

    public function delete_file(Request $request)
    {
        $id     = $request->input('id');
        $column = $request->input('column');
        $file   = 'files_info';
        $api    = new order();
        $info   = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}
