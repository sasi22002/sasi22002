<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


use App\Models\Category;

use App\Http\Controllers\Base;
use Validator;

class CategoryController extends Controller
{
    public function index()
    {
        $data = Category::all();
        return Base::touser($data, true);
    }

  
    public function store(Request $request)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:category',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      

        $prospect = new Category();
        $prospect->name = $data['name'];
        $prospect->desc = $data['desc'];
        $prospect->save();

        return Base::touser('Category Created', true);
    }

 
    public function show($id)
    {
        return Base::touser(Category::find($id), true);
    }

    
    public function update(Request $request, $id)
    {
        $data = $request->input('data');

        $rules = [
'desc' => 'required',
'name' => 'required|unique:category,name,'.$id.',id'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      


        $prospect = new Category();
  
        $prospect = $prospect->where('id', '=', $id)->first();
        $prospect->name = $data['name'];
        $prospect->desc = $data['desc'];
        $prospect->save();
        return Base::touser('Category Updated', true);
    }

   
    public function destroy($id)
    {
        try {
            $api = new Category();

            $api = $api->where('id', '=', $id)->first();

            $api->delete();

            return Base::touser('Category Deleted', true);
        } catch (\Exception $e) {

            return Base::touser("Can't able to delete category  its connected to Products !" );
            //return Base::throwerror();
        }
    }




    public function recover(Request  $request)
    {
        $api = new Category();

        $id = $request->input('id');

        $api = $api->where('id', '=', $id)->first();

        $company->restore();


        return Base::touser('Category Recovered', true);
    }
}
