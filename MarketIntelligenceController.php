<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MarketIntelligence;
use App\Models\Market;
use App\Http\Controllers\FileController as file;
use Validator;

class MarketIntelligenceController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = MarketIntelligence::with('market_info')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = MarketIntelligence::with('market_info')->whereIn('taken_by', $belongsemp)->get()->toArray();
        } else {
            $array = MarketIntelligence::with('market_info')->where('taken_by', $this->emp_id)->get()->toArray();
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



        $Market= new MarketIntelligence();
        $Market->desc = $data['desc'];
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

        $data['market_info'] = array_filter($data['market_info']);

        foreach ($data['market_info'] as  $key => $value) {
            $data['market_info'][$key]['market_id'] = $Market->id;
        }

        Market::insert($data['market_info']);

        return Base::touser('Market Report Created', true);
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
            $array = MarketIntelligence::with('market_info')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = MarketIntelligence::with('market_info')->whereIn('taken_by', $belongsemp)->find($id)->toArray();
        } else {
            $array = MarketIntelligence::with('market_info')->where('taken_by', $this->emp_id)->find($id)->toArray();
        }
        


        $array['uploads']  = (Array)json_decode(stripslashes($array['uploads']));
     

        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
'desc' => 'required',
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
            $Market= new MarketIntelligence();
            $data = $request->input('data');
            $Market = $Market::where('id', '=', $id)->first();
            $Market->desc = $data['desc'];
            $Market->location = $data['location'];
        
            if ($this->admin || $this->backend) {
                $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : null;
            } elseif ($this->manager) {
                $Market->taken_by = isset($data['taken_by']) ? $data['taken_by'] : $this->emp_id;
            } else {
                $Market->taken_by =  $this->emp_id;
            }


            $Market->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
            $Market->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;
            $Market->date = isset($data['date']) ? Base::tomysqldate($data['date']) :  $Market->date;
            $Market->uploads = json_encode($data['uploads']);
            $Market->save();
            Market::where('market_id', '=', $Market->id)->delete();
            $data['market_info'] = array_filter($data['market_info']);
            foreach ($data['market_info'] as  $key => $value) {
                $data['market_info'][$key]['market_id'] = $Market->id;
                Market::updateOrCreate($data['market_info'][$key]);
            }
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }




        \DB::commit();

        
        return Base::touser('Market Report Updated', true);
    }

   
    public function destroy($id)
    {
        Market::where('market_id', '=', $id)->delete();

        $api = MarketIntelligence::find($id);

        $api->delete();

        return Base::touser('Market Report Deleted', true);
    }

    public function delete_file(Request $request)
    {
        $id = $request->input('id');
        $column =  $request->input('column');
        $file =  $request->input('file');

        $api = new MarketIntelligence();
        $info = file::delete($id, $column, $api, $file);
        return Base::touser($info, true);
    }
}
