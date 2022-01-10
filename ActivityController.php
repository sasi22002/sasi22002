<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Activity;
use App\Http\Controllers\Base;

use Validator;

class ActivityController extends Controller
{
    public function index()
    {
        $data = Activity::all();
 
        return Base::touser($data, true);
    }

  
    public function store(Request $request)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:activities',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      

        $activity = new Activity();
        $activity->name = $data['name'];
        $activity->desc = $data['desc'];
        $activity->save();

        return Base::touser('Activity Created', true);
    }

 
    public function show($id)
    {
        return Base::touser(Activity::find($id), true);
    }

    
    public function update(Request $request, $id)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:activities,name,'.$id.',id'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      


        $activity = new Activity();
  
        $activity = $activity->where('id', '=', $id)->first();
        $activity->name = $data['name'];
        $activity->desc = $data['desc'];
        $activity->save();
        return Base::touser('Activity Updated', true);
    }

   
    public function destroy($id)
    {
        try {
            $api = new Activity();

            $api = $api->where('id', '=', $id)->first();

            $api->forcedelete();

            return Base::touser('Activity Deleted', true);
        } catch (\Exception $e) {
            return Base::throwerror();
        }
    }




    public function recover(Request  $request)
    {
        $api = new Activity();

        $id = $request->input('id');

        $api = $api->where('id', '=', $id)->first();

        $company->restore();


        return Base::touser('Activity Recovered', true);
    }
}
