<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Competitor;

use App\Http\Controllers\Base;

use Validator;

class CompetitorController extends Controller
{
    public function index()
    {
        return Base::touser(Competitor::all(), true);
    }

  
    public function store(Request $request)
    {
        $rules = [
'name' => 'required|unique:competitor',
'address' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
        ];

        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }



        $competitor = new Competitor();
        $competitor->name = $data['name'];
        $competitor->address = $data['address'];
        $competitor->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
        $competitor->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;
        $competitor->remark = isset($data['remark']) ? $data['remark'] : null ;
        $competitor->desc = isset($data['desc']) ? $data['desc'] : null ;
        $competitor->uploads = json_encode($data['uploads']);
        $competitor->save();
        return Base::touser('Competitor Created', true);
    }

  
    public function show($id)
    {
        return Base::touser(Competitor::find($id), true);
    }

  
  
    public function update(Request $request, $id)
    {
        $rules = [
'name' => 'required|unique:competitor,id,'.$id,
'address' => 'required',
'loc_lat' => 'required',
'loc_lng' => 'required',
        ];

        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
        \DB::beginTransaction();

        try {
            $competitor = new Competitor();
            $data = $request->input('data');
            $competitor = $competitor->where('id', '=', $id)->first();
            $competitor->name = $data['name'];
            $competitor->address = $data['address'];
            $competitor->loc_lat = isset($data['loc_lat']) ? $data['loc_lat'] : null;
            $competitor->loc_lng = isset($data['loc_lng']) ? $data['loc_lng'] : null;
            $competitor->remark = isset($data['remark']) ? $data['remark'] : null ;
            $competitor->desc = isset($data['desc']) ? $data['desc'] : null ;
            $competitor->uploads = json_encode($data['uploads']);
            $competitor->save();
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }

        \DB::commit();

    


        return Base::touser('Competitor Updated', true);
    }

  
    public function destroy($id)
    {
        $api = Competitor::find($id);
        $api->delete();
       

        return Base::touser('Competitor Deleted', true);
    }
}
