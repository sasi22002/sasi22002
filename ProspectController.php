<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


use App\Models\ProspectType;

use App\Http\Controllers\Base;
use Validator;

class ProspectController extends Controller
{
    public function index()
    {
        $data = ProspectType::all();
        return Base::touser($data, true);
    }

  
    public function store(Request $request)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:prospect_types',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      

        $prospect = new ProspectType();
        $prospect->name = $data['name'];
        $prospect->desc = $data['desc'];
        $prospect->save();

        return Base::touser('Prospect Type Created', true);
    }

 
    public function show($id)
    {
        return Base::touser(ProspectType::find($id), true);
    }

    
    public function update(Request $request, $id)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:prospect_types,name,'.$id.',id'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      


        $prospect = new ProspectType();
  
        $prospect = $prospect->where('id', '=', $id)->first();
        $prospect->name = $data['name'];
        $prospect->desc = $data['desc'];
        $prospect->save();
        return Base::touser('Prospect Type Updated', true);
    }

   
    public function destroy($id)
    {
        try {
            $api = new ProspectType();

            $api = $api->where('id', '=', $id)->first();

            $api->delete();

            return Base::touser('Prospect Type Deleted', true);
        } catch (\Exception $e) {
            return Base::throwerror();
        }
    }




    public function recover(Request  $request)
    {
        $api = new ProspectType();

        $id = $request->input('id');

        $api = $api->where('id', '=', $id)->first();

        $company->restore();


        return Base::touser('Prospect Type Recovered', true);
    }
}
