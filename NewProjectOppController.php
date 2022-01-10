<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NewProjectOpp;
use App\Models\NewProjectOppProd;
use App\Http\Controllers\FileController as file;
use Validator;

class NewProjectOppController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = NewProjectOpp::with('products')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = NewProjectOpp::with('products')->whereIn('taken_by', $belongsemp)->get()->toArray();
        } else {
            $array = NewProjectOpp::with('products')->where('taken_by', $this->emp_id)->get()->toArray();
        }
    
        foreach ($array as $i => $item) {
            $array[$i]['uploads']  = (Array)json_decode(stripslashes($item['uploads']));
        }

        return Base::touser($array, true);
    }

    public function store(Request $request)
    {
        $rules = [
'name' => 'required',
'site_name' => 'required',
'contact_name' => 'required',
'contact_no' => 'required',
'contact_email' => 'required|email',
'lead_id' => 'required',
'exp_date' => 'required',
'price_exp' => 'required',
'cement_used' => 'required',
'desc' => 'required',
'prospect_type' => 'required',
'location' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
'taken_by' => 'exists:user,user_id'
        ];

        $data = $request->input('data');
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $Market= new NewProjectOpp();
        $Market->desc = $data['desc'];
        $Market->name = $data['name'];
        $Market->site_name = $data['site_name'];
        $Market->contact_name = $data['contact_name'];
        $Market->contact_no = $data['contact_no'];
        $Market->contact_email = $data['contact_email'];
        $Market->cement_used = $data['cement_used'];
        $Market->lead_id = $data['lead_id'];
        $Market->price_exp = $data['price_exp'];
        $Market->prospect_type = $data['prospect_type'];
        $Market->exp_date = Base::tomysqldate($data['exp_date']);
        $Market->location = $data['location'];
        $Market->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
        $Market->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;



        if ($this->admin || $this->backend) {
            $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
        } elseif ($this->manager) {
            $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
        } else {
            $Market->taken_by =  $this->emp_id;
        }



        $Market->uploads = json_encode($data['uploads']);

        $Market->date = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');

        $Market->save();

        $data['products'] = array_filter($data['products']);

        foreach ($data['products'] as  $key => $value) {
            $data['products'][$key]['new_project_opp_id'] = $Market->id;
        }

        NewProjectOppProd::insert($data['products']);

        return Base::touser('New Lead Created', true);
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
            $array = NewProjectOpp::with('products')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = NewProjectOpp::with('products')->whereIn('taken_by', $belongsemp)->find($id)->toArray();
        } else {
            $array = NewProjectOpp::with('products')->where('taken_by', $this->emp_id)->find($id)->toArray();
        }
        


        $array['uploads']  = (Array)json_decode(stripslashes($array['uploads']));
     

        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
'name' => 'required',
'site_name' => 'required',
'contact_name' => 'required',
'contact_no' => 'required',
'contact_email' => 'required|email',
'lead_id' => 'required',
'exp_date' => 'required',
'price_exp' => 'required',
'cement_used' => 'required',
'desc' => 'required',
'prospect_type' => 'required',
'location' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
'taken_by' => 'exists:user,user_id'
        ];


        $data = $request->input('data');
        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }


        \DB::beginTransaction();

        try {
            $Market= new NewProjectOpp();
            $Market = $Market::where('id', '=', $id)->first();
            $Market->desc = $data['desc'];
            $Market->name = $data['name'];
            $Market->site_name = $data['site_name'];
            $Market->contact_name = $data['contact_name'];
            $Market->contact_no = $data['contact_no'];
            $Market->contact_email = $data['contact_email'];
            $Market->cement_used = $data['cement_used'];
            $Market->lead_id = $data['lead_id'];
            $Market->price_exp = $data['price_exp'];
            $Market->prospect_type = $data['prospect_type'];
            $Market->exp_date = Base::tomysqldate($data['exp_date']);
            $Market->location = $data['location'];
            $Market->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
            $Market->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;



            if ($this->admin || $this->backend) {
                $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
            } elseif ($this->manager) {
                $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
            } else {
                $Market->taken_by =  $this->emp_id;
            }



            $Market->uploads = json_encode($data['uploads']);

            $Market->date = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');

            $Market->save();

            $data['products'] = array_filter($data['products']);

            NewProjectOppProd::where('new_project_opp_id', '=', $Market->id)->delete();

            foreach ($data['products'] as  $key => $value) {
                $data['products'][$key]['new_project_opp_id'] = $Market->id;
            }

            NewProjectOppProd::insert($data['products']);
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }




        \DB::commit();

        
        return Base::touser('Lead Updated', true);
    }

   
    public function destroy($id)
    {
        NewProjectOppProd::where('new_project_opp_id', '=', $id)->delete();

        $api = NewProjectOpp::find($id);

        $api->delete();

        return Base::touser('Lead Deleted', true);
    }

    public function delete_file(Request $request)
    {
        $id = $request->input('id');
        $column =  $request->input('column');
        $file =  $request->input('file');

        $api = new NewProjectOpp();
        $info = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}
