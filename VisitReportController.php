<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\VisitReport;
use App\Models\RepIndCmp;
use App\Http\Controllers\FileController as file;
use Validator;

class VisitReportController extends Controller
{
    public function visit_rep_cmp()
    {

    //      if($this->admin || $this->backend)
    //     {
    //         $array = RepIndCmp::get()->toArray();
    //     }
    //     elseif($this->manager)
    //     {

    //     $belongsemp = Base::getEmpBelongsUser($this->emp_id);

    //     $array = RepIndCmp::get()->whereIn('user_id', $belongsemp)->get()->toArray();

    //     }
    //     else
    //     {

    //  $array =  RepIndCmp::get()->where('user_id', $this->emp_id)->get()->toArray();

    //     }
        
    //     foreach ($array as $i => $item)
    //     {
    //      $array[$i]['uploads']  = (Array)json_decode(stripslashes($item['uploads']));
    //     }

    //      return Base::touser($array,true);
    // }


           $array = RepIndCmp::all();

        return Base::touser($array, true);
    }


    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = VisitReport::with('rep_cmp')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = VisitReport::with('rep_cmp')->whereIn('user_id', $belongsemp)->get()->toArray();
        } else {
            $array = VisitReport::with('rep_cmp')->where('user_id', $this->emp_id)->get()->toArray();
        }
        
        foreach ($array as $i => $item) {
            $array[$i]['uploads']  = (Array)json_decode(stripslashes($item['uploads']));
        }

        return Base::touser($array, true);
    }

 
    public function store(Request $request)
    {
        $rules = [
'remarks' => 'required',
'cust_id' => 'required|exists:customers,id',
'met_with' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
'user_id' => 'exists:user,user_id'
        ];

        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

  
        $VisitReport = new VisitReport();
        $data = $request->input('data');
        $VisitReport->remarks = $data['remarks'];
        $VisitReport->cust_id = $data['cust_id'];
        $VisitReport->met_with = $data['met_with'];
   
        $VisitReport->issue_discussed = isset($data['issue_discussed']) ? $data['issue_discussed'] : ''; 
        $VisitReport->issues = isset($data['issues']) ? $data['issues'] : ''; 


        $VisitReport->uploads = json_encode($data['uploads']);

        $VisitReport->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
        $VisitReport->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;
        $VisitReport->is_approved = isset($data['is_approved']) ? $data['is_approved'] : 0;


        if ($this->admin || $this->backend) {
            $VisitReport->user_id = isset($data['user_id']) ? $data['user_id'] : null;
        } elseif ($this->manager) {
            $VisitReport->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
        } else {
            $VisitReport->user_id =  $this->emp_id;
        }




        $VisitReport->date = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');


        $VisitReport->save();


        $data['reps_info'] = array_filter($data['reps_info']);

        foreach ($data['reps_info'] as  $key => $value) {
            $data['reps_info'][$key]['visit_report_id'] = $VisitReport->report_id;
        }


        RepIndCmp::insert($data['reps_info']);

        return Base::touser('Visit Report Created', true);
    }

  
    public function show($id)
    {
        if ($this->admin || $this->backend) {
            $array = VisitReport::with('rep_cmp')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = VisitReport::with('rep_cmp')->whereIn('user_id', $belongsemp)->find($id)->toArray();
        } else {
            $array = VisitReport::with('rep_cmp')->where('user_id', $this->emp_id)->find($id)->toArray();
        }
        

        $array['uploads']  = (Array) json_decode(stripslashes($array['uploads']));


        return Base::touser($array, true);
    }

   
    public function update(Request $request, $id)
    {
        $rules = [
'remarks' => 'required',
'cust_id' => 'required|exists:customers,id',
'met_with' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
'user_id' => 'exists:user,user_id'
        ];



        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        \DB::beginTransaction();

        try {
            $VisitReport = new VisitReport();

            $VisitReport = $VisitReport->where('report_id', '=', $id)->first();

            $VisitReport->remarks = $data['remarks'];
            $VisitReport->cust_id = $data['cust_id'];
            $VisitReport->met_with = $data['met_with'];
             $VisitReport->issue_discussed = isset($data['issue_discussed']) ? $data['issue_discussed'] : ''; 
              $VisitReport->issues = isset($data['issues']) ? $data['issues'] : ''; 
            $VisitReport->uploads = isset($data['loc_lat']) ?  json_encode($data['uploads'], true) : $VisitReport->uploads;

            $VisitReport->is_approved = isset($data['is_approved']) ? $data['is_approved'] : $VisitReport->is_approved;
            $VisitReport->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
            $VisitReport->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;

            if ($this->admin || $this->backend) {
                $VisitReport->user_id = isset($data['user_id']) ? $data['user_id'] : null;
            } elseif ($this->manager) {
                $VisitReport->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
            } else {
                $VisitReport->user_id =  $this->emp_id;
            }



            $VisitReport->date = isset($data['date']) ? date('Y-m-d', strtotime($data['date'])) : $VisitReport->date;

            $VisitReport->save();


            $data['reps_info'] = array_filter($data['reps_info']);

            $api = RepIndCmp::where('visit_report_id', '=', $VisitReport->report_id);

            $api->delete();


            foreach ($data['reps_info'] as  $key => $value) {
                $data['reps_info'][$key]['visit_report_id'] = $VisitReport->report_id;

                RepIndCmp::insert($data['reps_info'][$key]);
            }
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }



        \DB::commit();

        
        return Base::touser('Visit Report Updated', true);
    }

   
    public function destroy($id)
    {
        $api = RepIndCmp::where('visit_report_id', '=', $id);
        $api->delete();
        $api = VisitReport::find($id);
        $api->delete();

        return Base::touser('Visit Report Deleted', true);
    }

    public function delete_file(Request $request)
    {
        $id = $request->input('id');
        $column =  $request->input('column');
        $file =  $request->input('file');

        $api = new VisitReport();
        $info = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}
