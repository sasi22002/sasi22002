<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Requests;
use App\Models\RequestInfo as info;
use App\Http\Controllers\FileController as file;
use Validator;

class RequestsController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = Requests::with('request_info')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = Requests::with('request_info')->whereIn('taken_by', $belongsemp)->get()->toArray();
        } else {
            $array = Requests::with('request_info')->where('taken_by', $this->emp_id)->get()->toArray();
        }
    
        foreach ($array as $i => $item) {
            $array[$i]['uploads']  = (Array)json_decode(stripslashes($item['uploads']));
        }

        return Base::touser($array, true);
    }

    public function store(Request $request)
    {
        $rules = [
'desc' => 'required',
'date' => 'required',
'cust_id' => 'required|exists:customers,id',
'taken_by' => 'exists:user,user_id'
        ];


        $data = $request->input('data');
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }



        $Request= new Requests();


        $Request->desc = $data['desc'];
        $Request->cust_id = $data['cust_id'];
        $Request->date = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');


        if ($this->admin || $this->backend) {
            $Request->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
        } elseif ($this->manager) {
            $Request->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
        } else {
            $Request->taken_by =  $this->emp_id;
        }

        $Request->status = isset($data['status']) ? $data['status'] : 0;

        $Request->uploads = json_encode($data['uploads']);

        $Request->save();

        $data['request_info'] = array_filter($data['request_info']);

        foreach ($data['request_info'] as  $key => $value) {
            $data['request_info'][$key]['request_id'] = $Request->id;
        }

        info::insert($data['request_info']);

        return Base::touser('Request Report Created', true);
    }

    public function show($id)
    {
        if ($this->admin || $this->backend) {
            $array = Requests::with('request_info')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = Requests::with('request_info')->whereIn('taken_by', $belongsemp)->find($id)->toArray();
        } else {
            $array = Requests::with('request_info')->where('taken_by', $this->emp_id)->find($id)->toArray();
        }
        


        $array['uploads']  = (Array)json_decode(stripslashes($array['uploads']));
     

        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
'desc' => 'required',
'date' => 'required',
'cust_id' => 'required|exists:customers,id',
'taken_by' => 'exists:user,user_id'
        ];





        $data = $request->input('data');
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }


        \DB::beginTransaction();

        try {
            $Request= new Requests();

        // 'desc',
        // 'date',
        // 'cust_id',
        // 'uploads',
        // 'taken_by'


        $data = $request->input('data');
            $Request = $Request::where('id', '=', $id)->first();
            $Request->desc = $data['desc'];
            $Request->status = isset($data['status']) ? $data['status'] : $Request->status;

            if ($this->admin || $this->backend) {
                $Request->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
            } elseif ($this->manager) {
                $Request->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
            } else {
                $Request->taken_by =  $this->emp_id;
            }

            $Request->date = isset($data['date']) ? Base::tomysqldate($data['date']) :  $Request->date;
            $Request->uploads = json_encode($data['uploads']);
            $Request->save();

            info::where('request_id', '=', $Request->id)->delete();
            $data['request_info'] = array_filter($data['request_info']);

            foreach ($data['request_info'] as  $key => $value) {
                $data['request_info'][$key]['request_id'] = $Request->id;
                info::insert($data['request_info'][$key]);
            }
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }




        \DB::commit();

        
        return Base::touser('Request Report Updated', true);
    }

   
    public function destroy($id)
    {
        info::where('request_id', '=', $id)->delete();

        $api = Requests::find($id);

        $api->delete();

        return Base::touser('Request Report Deleted', true);
    }

    public function delete_file(Request $request)
    {
        $id = $request->input('id');
        $column =  $request->input('column');
        $file =  $request->input('file');

        $api = new req();
        $info = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}
