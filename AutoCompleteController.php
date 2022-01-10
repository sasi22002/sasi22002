<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Base;
use Validator;
use App\Models\Customer;

class AutoCompleteController extends Controller
{


 
    public function show(Request $request,$query)
    {
        $request->input('type');


        switch ($request->input('type')) {
            case 'customers':
                return Base::touser(Customer::where('name', 'like', '%'.$query.'%')->get(['id','name']), true);
                break;
            case 'customers':
                return Base::touser(Customer::where('name', 'like', '%'.$query.'%')->get(['id','name']), true);
                break;
            case 'customers':
                return Base::touser(Customer::where('name', 'like', '%'.$query.'%')->get(['id','name']), true);
                break;
            case 'customers':
                return Base::touser(Customer::where('name', 'like', '%'.$query.'%')->get(['id','name']), true);
                break;
            
            default:
                 return Base::touser([], false);
                break;
        }



    }



   

}
