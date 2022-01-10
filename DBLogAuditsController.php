<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Validator;

use App\Http\Controllers\Base;

class DBLogAuditsController extends Controller
{


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function show(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [

        'model' =>  'required|min:3',
        'id' =>  'required|integer',

        ]);


        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }





        $DB_Model = $request->get('model');

        $row_id = $id;

        $DB_Model = 'App\\Models\\' . $DB_Model;

        $data = $DB_Model::with('audits')->get()->find($row_id);

        if ($data) {
            $data = $data->toArray()['audits'];

            foreach ($data as $key => $value) {
                print_r($data[$key]['old_values']);
            }
        } else {
            print_r('nothing');
        }
    }
}
