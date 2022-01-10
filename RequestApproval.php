<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Base;

use App\Models\Request as req;

class RequestApproval extends Controller
{
   

 /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function get()
    {
        return Base::touser(req::select('desc', 'date', 'cus_id', 'status')->get(), true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function post()
    {
        //
    }
}
