<?php
namespace App\Http\Controllers;

use App\Models\CasesInfo;
use App\Models\CasesInfoDetail as item;
use Illuminate\Http\Request;
use Validator;

class CaseInfoController extends Controller
{
    public function index()
    {
        $em = 1;

        if ($this->admin || $this->backend) {
            $array = CasesInfo::with('cases_info_detail')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = CasesInfo::whereIn('taken_by', $belongsemp)->with('cases_info_detail')->get()->toArray();
        } else {
            $array = CasesInfo::where('taken_by', $this->emp_id)->with('cases_info_detail', 'closed_by')->get()->toArray();

            $em = 2;
        }

        foreach ($array as $i => $item) {
            if ($item['cases_info_detail']) {
                foreach ($item['cases_info_detail'] as $j => $item_inside) {
                    $array[$i]['cases_info_detail'][$j]['uploads'] = (Array) json_decode(stripslashes($item_inside['uploads']));
                }
            }

            if (!empty($array[$i]['closed_by']) && ($em == 2)) {
                $array[$i]['closed_by_id'] = $item['closed_by']['user_id'];
                $array[$i]['closed_by']    = $item['closed_by']['first_name'] . $item['closed_by']['last_name'];
            }
        }

        return Base::touser($array, true);
    }

    public function getCaseDetails()
    {
        $array = item::all()->toArray();

        foreach ($array as $i => $item) {
            $array[$i]['uploads'] = (Array) json_decode(stripslashes($array[$i]['uploads']));
        }

        return Base::touser($array, true);
    }

    public function store(Request $request)
    {
        $rules = [
            'cust_id'   => 'required|exists:customers,id',
            'open_dt'   => 'required',
            'taken_by'  => 'exists:user,user_id',
            'closed_by' => 'nullable|exists:user,user_id',
            'status'    => 'required',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        \DB::beginTransaction();

        try {
            $CasesInfo = new CasesInfo();

            if ($this->admin || $this->backend) {
                $CasesInfo->taken_by  = isset($data['taken_by']) ? $data['taken_by'] : null;
                $CasesInfo->closed_by = isset($data['closed_by']) ? $data['closed_by'] : null;
            } elseif ($this->manager) {
                $CasesInfo->closed_by = isset($data['closed_by']) ? $this->emp_id : null;
                $CasesInfo->taken_by  = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
            } else {
                $CasesInfo->closed_by = isset($data['closed_by']) ? $this->emp_id : null;
                $CasesInfo->taken_by  = $this->emp_id;
            }

            $CasesInfo->open_dt = isset($data['open_dt']) ? Base::tomysqldate($data['open_dt']) : date('Y-m-d');

            $CasesInfo->close_dt = isset($data['close_dt']) ? Base::tomysqldate($data['close_dt']) : null;

            $CasesInfo->cust_id = $data['cust_id'];
            $CasesInfo->status  = $data['status'];

            $CasesInfo->save();

            $data['cases_info_detail'] = array_filter($data['cases_info_detail']);

            foreach ($data['cases_info_detail'] as $key => $value) {

                $item = new item();

                $item->case_id    = $CasesInfo->case_id;
                $item->case_type  = $data['cases_info_detail'][$key]['case_type'];
                $item->product_id =
                isset($data['cases_info_detail'][$key]['product_id']) ? $data['cases_info_detail'][$key]['product_id'] : null;

                $item->batch_info =
                isset($data['cases_info_detail'][$key]['batch_info']) ? $data['cases_info_detail'][$key]['batch_info'] : null;

                $item->invoice_no =
                isset($data['cases_info_detail'][$key]['invoice_no']) ? $data['cases_info_detail'][$key]['invoice_no'] : null;

                $item->invoice_date =
                isset($data['cases_info_detail'][$key]['invoice_date']) ? Base::tomysqldate($data['cases_info_detail'][$key]['invoice_date']) : null;

                $item->site_info =
                isset($data['cases_info_detail'][$key]['site_info']) ? $data['cases_info_detail'][$key]['site_info'] : null;

                $item->deliver_from =
                isset($data['cases_info_detail'][$key]['deliver_from']) ? $data['cases_info_detail'][$key]['deliver_from'] : null;

                $item->order_no =
                isset($data['cases_info_detail'][$key]['order_no']) ? $data['cases_info_detail'][$key]['order_no'] : null;

                $item->delivery_no =
                isset($data['cases_info_detail'][$key]['delivery_no']) ? $data['cases_info_detail'][$key]['delivery_no'] : null;

                $item->delivery_date =
                isset($data['cases_info_detail'][$key]['delivery_date']) ? Base::tomysqldate($data['cases_info_detail'][$key]['delivery_date']) : null;

                $item->desc =
                isset($data['cases_info_detail'][$key]['desc']) ? $data['cases_info_detail'][$key]['desc'] : null;

                $item->notes =
                isset($data['cases_info_detail'][$key]['notes']) ? $data['cases_info_detail'][$key]['notes'] : null;

                $item->uploads = json_encode($data['cases_info_detail'][$key]['uploads']);

                $item->save();
            }

        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }

        \DB::commit();

        return Base::touser('Case Created', true);
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
            $array = CasesInfo::with('cases_info_detail')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = CasesInfo::with('cases_info_detail')->whereIn('taken_by', $belongsemp)->find($id)->toArray();
        } else {
            $array = CasesInfo::with('cases_info_detail')->where('taken_by', $this->emp_id)->find($id)->toArray();
        }

        if ($array['cases_info_detail']) {
            foreach ($array['cases_info_detail'] as $j => $item_inside) {
                $array['cases_info_detail'][$j]['uploads'] = (Array) json_decode(stripslashes($item_inside['uploads']));
            }
        }

        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'cust_id'   => 'required|exists:customers,id',
            'open_dt'   => 'required',
            'taken_by'  => 'exists:user,user_id',
            'closed_by' => 'nullable|exists:user,user_id',
            'status'    => 'required',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        \DB::beginTransaction();

        try {
            $CasesInfo = new CasesInfo();

            $CasesInfo = $CasesInfo::where('case_id', $id)->first();

            if ($this->admin || $this->backend) {
                $CasesInfo->taken_by  = isset($data['taken_by']) ? $data['taken_by'] : null;
                $CasesInfo->closed_by = isset($data['closed_by']) ? $data['closed_by'] : null;
            } elseif ($this->manager) {
                $CasesInfo->closed_by = isset($data['closed_by']) ? $this->emp_id : null;
                $CasesInfo->taken_by  = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
            } else {
                $CasesInfo->closed_by = isset($data['closed_by']) ? $this->emp_id : null;
                $CasesInfo->taken_by  = $this->emp_id;
            }

            $CasesInfo->open_dt = isset($data['open_dt']) ? Base::tomysqldate($data['open_dt']) : date('Y-m-d');

            $CasesInfo->close_dt = isset($data['close_dt']) ? Base::tomysqldate($data['close_dt']) : null;

            $CasesInfo->cust_id = $data['cust_id'];
            $CasesInfo->status  = $data['status'];

            $CasesInfo->save();

            item::where('case_id', '=', $CasesInfo->case_id)->delete();

            $data['cases_info_detail'] = array_filter($data['cases_info_detail']);

            foreach ($data['cases_info_detail'] as $key => $value) {

                $item = new item();

                $item->case_id    = $CasesInfo->case_id;
                $item->case_type  = $data['cases_info_detail'][$key]['case_type'];
                $item->product_id =
                isset($data['cases_info_detail'][$key]['product_id']) ? $data['cases_info_detail'][$key]['product_id'] : null;

                $item->batch_info =
                isset($data['cases_info_detail'][$key]['batch_info']) ? $data['cases_info_detail'][$key]['batch_info'] : null;

                $item->invoice_no =
                isset($data['cases_info_detail'][$key]['invoice_no']) ? $data['cases_info_detail'][$key]['invoice_no'] : null;

                $item->invoice_date =
                isset($data['cases_info_detail'][$key]['invoice_date']) ? Base::tomysqldate($data['cases_info_detail'][$key]['invoice_date']) : null;

                $item->site_info =
                isset($data['cases_info_detail'][$key]['site_info']) ? $data['cases_info_detail'][$key]['site_info'] : null;

                $item->deliver_from =
                isset($data['cases_info_detail'][$key]['deliver_from']) ? $data['cases_info_detail'][$key]['deliver_from'] : null;

                $item->order_no =
                isset($data['cases_info_detail'][$key]['order_no']) ? $data['cases_info_detail'][$key]['order_no'] : null;

                $item->delivery_no =
                isset($data['cases_info_detail'][$key]['delivery_no']) ? $data['cases_info_detail'][$key]['delivery_no'] : null;

                $item->delivery_date =
                isset($data['cases_info_detail'][$key]['delivery_date']) ? Base::tomysqldate($data['cases_info_detail'][$key]['delivery_date']) : null;

                $item->desc =
                isset($data['cases_info_detail'][$key]['desc']) ? $data['cases_info_detail'][$key]['desc'] : null;

                $item->notes =
                isset($data['cases_info_detail'][$key]['notes']) ? $data['cases_info_detail'][$key]['notes'] : null;

                $item->uploads = json_encode($data['cases_info_detail'][$key]['uploads']);

                $item->save();
            }

        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }

        \DB::commit();

        return Base::touser('Case Report Updated', true);
    }

    public function destroy($id)
    {
        item::where('case_id', '=', $id)->delete();

        $api = CasesInfo::find($id);

        $api->delete();

        return Base::touser('Case Report Deleted', true);
    }

    public function delete_file(Request $request)
    {
        $id     = $request->input('id');
        $column = $request->input('column');
        $file   = $request->input('file');

        $api  = new CasesInfo();
        $info = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}

//old code

// $rules = [
//     'cust_id'       => 'required|exists:customers,id',
//     'batch_details' => 'required',
//     'pro_id'        => 'required|exists:products,product_id',
//     'types'         => 'required',
//     'invoice_date'  => 'required',
//     'invoice_ref'   => 'required',
//     'prob_quantiy'  => 'required',
//     'site_info'     => 'required',
//     'del_from'      => 'required',
//     'desc'          => 'required',
//     'taken_by'      => 'exists:user,user_id',
// ];

// $data = $request->input('data');

// $validator = Validator::make($data, $rules);

// if ($validator->fails()) {
//     return Base::touser($validator->errors()->all()[0]);
// }

// \DB::beginTransaction();

// try {

//     $CasesInfo = new CasesInfo();

//     $CasesInfo = $CasesInfo::where('case_id', $id)->first();

//     if ($this->admin || $this->backend) {

//         $CasesInfo->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
//     } elseif ($this->manager) {

//         $CasesInfo->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
//     } else {

//         $CasesInfo->taken_by = $this->emp_id;
//     }

//     $CasesInfo->open_dt       = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');
//     $CasesInfo->cust_id       = $data['cust_id'];
//     $CasesInfo->batch_details = $data['batch_details'];
//     $CasesInfo->pro_id        = $data['pro_id'];
//     $CasesInfo->types         = $data['types'];
//     $CasesInfo->invoice_ref   = $data['invoice_ref'];
//     $CasesInfo->invoice_date  = Base::tomysqldate($data['invoice_date']);
//     $CasesInfo->prob_quantiy  = $data['prob_quantiy'];
//     $CasesInfo->site_info     = $data['site_info'];
//     $CasesInfo->del_from      = $data['del_from'];
//     $CasesInfo->desc          = $data['desc'];
//     $CasesInfo->close_dt      = isset($data['close_dt']) ? Base::tomysqldate($data['close_dt']) : null;
//     $CasesInfo->uploads       = json_encode($data['uploads']);
//     $CasesInfo->save();
// } catch (Exception $e) {

//     \DB::rollBack();

//     throw $e;
// }

// \DB::commit();
