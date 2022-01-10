<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TravelExp as travelexp;
use App\Models\TravelExpHotel as hotelexp;
use App\Models\TravelExpReport as report;
use Validator;

class TravelExpController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = report::with('hotel_exp', 'travel_exp')->get()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array =report::with('hotel_exp', 'travel_exp')->whereIn('user_id', $belongsemp)->get()->toArray();
        } else {
            $array = report::with('hotel_exp', 'travel_exp')->where('user_id', $this->emp_id)->get()->toArray();
        }
    
        foreach ($array as $i => $item) {
            $array[$i]['uploads']  = (Array)json_decode(stripslashes($item['uploads']));
        }

        return Base::touser($array, true);
    }

    public function store(Request $request)
    {
        $rules = [
'start_date' => 'required',
'type' => 'required',
'end_date' => 'required',
'claim_id' => 'required',
'purpose' => 'required',
'appr_status' => 'required',
'location' => 'required',
'food_exp' => 'required',
'out_pdt' => 'required',
'travel_with_mngr' => 'required',
'total_exp' => 'required',
'travel_by' => 'required',
'extra_exp' => 'required',
'travel_desc' => 'required',
'user_id' => 'exists:user,user_id'
        ];
        $data = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $Market= new report();
        $Market->travel_desc = $data['travel_desc'];
        $Market->start_date = Base::tomysqldatetime($data['start_date']);
        $Market->type = $data['type'];
        $Market->end_date = Base::tomysqldatetime($data['end_date']);
        $Market->claim_id = $data['claim_id'];
        $Market->purpose = $data['purpose'];
        $Market->appr_status = isset($data['appr_status']) ? $data['appr_status'] : null;
        $Market->location = $data['location'];
        $Market->food_exp = $data['food_exp'];
        $Market->out_pdt = $data['out_pdt'];
        $Market->total_exp = $data['total_exp'];
        $Market->travel_by = $data['travel_by'];
        $Market->extra_exp = $data['extra_exp'];
        $Market->travel_with_mngr = isset($data['travel_with_mngr']) ? $data['travel_with_mngr'] : null;

        if ($this->admin || $this->backend) {
            $Market->user_id = isset($data['user_id']) ? $data['user_id'] : null;
        } elseif ($this->manager) {
            $Market->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
        } else {
            $Market->user_id =  $this->emp_id;
        }

        $Market->uploads = json_encode($data['uploads']);


        $Market->save();

        $data['hotel_exp'] = array_filter($data['hotel_exp']);

        foreach ($data['hotel_exp'] as  $key => $value) {
            $data['hotel_exp'][$key]['travel_report_id'] = $Market->id;
            $data['hotel_exp'][$key]['check_in'] =Base::tomysqldatetime($data['hotel_exp'][$key]['check_in']);
            $data['hotel_exp'][$key]['check_out'] =Base::tomysqldatetime($data['hotel_exp'][$key]['check_out']);
        }

        $data['travel_exp'] = array_filter($data['travel_exp']);

        foreach ($data['travel_exp'] as  $key => $value) {
            $data['travel_exp'][$key]['travel_report_id'] = $Market->id;
            $data['travel_exp'][$key]['start'] =Base::tomysqldatetime($data['travel_exp'][$key]['start']);
            $data['travel_exp'][$key]['end'] =Base::tomysqldatetime($data['travel_exp'][$key]['end']);
        }

        hotelexp::insert($data['hotel_exp']);
        travelexp::insert($data['travel_exp']);

        return Base::touser('Expenses Report Created', true);
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
            $array = report::with('hotel_exp', 'travel_exp')->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array =report::with('hotel_exp', 'travel_exp')->whereIn('user_id', $belongsemp)->find($id)->toArray();
        } else {
            $array = report::with('hotel_exp', 'travel_exp')->where('user_id', $this->emp_id)->find($id)->toArray();
        }
    
      
        $array['uploads']  = (Array)json_decode(stripslashes($array['uploads']));

    
        return Base::touser($array, true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
'start_date' => 'required',
'type' => 'required',
'end_date' => 'required',
'claim_id' => 'required',
'purpose' => 'required',
'appr_status' => 'required',
'location' => 'required',
'food_exp' => 'required',
'out_pdt' => 'required',
'travel_with_mngr' => 'required',
'total_exp' => 'required',
'travel_by' => 'required',
'extra_exp' => 'required',
'travel_desc' => 'required',
'user_id' => 'exists:user,user_id'
        ];
        $data = $request->input('data');
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        \DB::beginTransaction();


        try {
            $Market= report::find($id);
            $Market->travel_desc = $data['travel_desc'];
            $Market->start_date = Base::tomysqldatetime($data['start_date']);
            $Market->type = $data['type'];
            $Market->end_date = Base::tomysqldatetime($data['end_date']);
            $Market->claim_id = $data['claim_id'];
            $Market->purpose = $data['purpose'];
            $Market->appr_status = isset($data['appr_status']) ? $data['appr_status'] : null;
            $Market->location = $data['location'];
            $Market->food_exp = $data['food_exp'];
            $Market->out_pdt = $data['out_pdt'];
            $Market->total_exp = $data['total_exp'];
            $Market->travel_by = $data['travel_by'];
            $Market->extra_exp = $data['extra_exp'];
            $Market->travel_with_mngr = isset($data['travel_with_mngr']) ? $data['travel_with_mngr'] : null;

            if ($this->admin || $this->backend) {
                $Market->user_id = isset($data['user_id']) ? $data['user_id'] : null;
            } elseif ($this->manager) {
                $Market->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
            } else {
                $Market->user_id =  $this->emp_id;
            }

            $Market->uploads = json_encode($data['uploads']);


            $Market->save();

            $data['hotel_exp'] = array_filter($data['hotel_exp']);


            hotelexp::where('travel_report_id', '=', $Market->id)->delete();
            travelexp::where('travel_report_id', '=', $Market->id)->delete();



            foreach ($data['hotel_exp'] as  $key => $value) {
                $data['hotel_exp'][$key]['travel_report_id'] = $Market->id;
                $data['hotel_exp'][$key]['check_in'] =Base::tomysqldatetime($data['hotel_exp'][$key]['check_in']);
                $data['hotel_exp'][$key]['check_out'] =Base::tomysqldatetime($data['hotel_exp'][$key]['check_out']);
            }

            $data['travel_exp'] = array_filter($data['travel_exp']);

            foreach ($data['travel_exp'] as  $key => $value) {
                $data['travel_exp'][$key]['travel_report_id'] = $Market->id;
                $data['travel_exp'][$key]['start'] =Base::tomysqldatetime($data['travel_exp'][$key]['start']);
                $data['travel_exp'][$key]['end'] =Base::tomysqldatetime($data['travel_exp'][$key]['end']);
            }

            hotelexp::insert($data['hotel_exp']);
            travelexp::insert($data['travel_exp']);
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }
        \DB::commit();
        return Base::touser('Expenses Report Updated', true);
    }

   
    public function destroy($id)
    {
        hotelexp::where('travel_report_id', '=', $id)->delete();
        travelexp::where('travel_report_id', '=', $id)->delete();

        $api = report::find($id);

        $api->delete();

        return Base::touser('Expenses Report Deleted', true);
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
