<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\Items;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    public function index()
    {
        $loggedUser = \App\Models\User::find($this->emp_id);
        $belongsemp = [];
        if($loggedUser)
        {
            $belongsemp[] = $loggedUser->user_id;

            if($loggedUser->belongs_manager)
            {
                $temp = Base::getEmpBelongsUser($loggedUser->belongs_manager);
                $temp1 = Base::getEmpBelongsUser($loggedUser->user_id);

                $temp = array_merge($temp,$temp1);
                if(is_array($temp))
                {
                     array_push($belongsemp,$temp);
                }
            }
        }

        if ($this->role === 'user') {
            if(count($belongsemp) > 0)
            {
                return Base::touser(Items::whereIn('emp_id',$belongsemp)->get(), true);
            }          
            return Base::touser(Items::all(), true);
        }
        elseif($this->role === 'sub_manager'){
            return Base::touser(Items::where('emp_id',$this->emp_id)->get(), true);
        } else {
            if(count($belongsemp) > 0)
            {
                $data = Items::whereIn('emp_id',$belongsemp)->with('emp')->get()->toArray();
            }
            else
            {
                $data = Items::with('emp')->get()->toArray();
            }
           return Base::touser($data, true);
        }
    }

    public function store(Request $request)
    {
        $data = $request->input('data');
        
        $item_name = trim($data['name']);
        
        if (empty($item_name)) {
            return Base::touser("The Item Name should not be empty");
        }
       
        $checkItem = Items::where('name', trim($data['name']))->where('emp_id',$this->emp_id)->get()->count();
        if($checkItem>0)
        {
            return Base::touser("The Given Item Name is already Exists");
        }

        $Items        = new Items();
        $Items->name  = trim($data['name']);
        $Items->emp_id   = isset($data['emp_id']) ? $data['emp_id'] : $this->emp_id;
        $Items->save();

        return Base::touser('Item Created', true);
    }

    /**
     * Display the specified resource.
     *
     *  @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = Items::find($id)->toArray();
        return Base::touser($data, true);
    }

    public function update(Request $request, $id)
    {
        $data = $request->input('data');
        $item_name = trim($data['name']);
        
        if (empty($item_name)) {
            return Base::touser("The Item Name should not be empty");
        }
      
        $checkItem = Items::where('name', trim($data['name']))
                ->where('emp_id','=',$this->emp_id)
                ->where('id','!=',$id)
                ->get()->count();
        if($checkItem >= 1)
        {   
            return Base::touser("The Given Item Name is already Exists");         
        }

        try {
            $Items        = new Items();
            $data         = $request->input('data');
            $Items        = $Items->where('id', '=', $id)->first();
            $Items->name  = trim($data['name']);

            $Items->emp_id   = isset($data['emp_id']) ? $data['emp_id'] : $this->emp_id;
            $Items->save();
        } catch (Exception $e) {
            return Base::touser($e->getMessage(), false);
        }

        return Base::touser('Items Updated', true);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $api = new Items();
            $api = $api->find($id);
            $api->delete();
            return Base::touser('Item Deleted', true);
        } catch (\Exception $e) {
            return Base::touser("Can't able to delete Item its connected to Other Data !");
            //return Base::throwerror();
        }
    }
}
