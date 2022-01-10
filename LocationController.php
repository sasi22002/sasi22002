<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Base;

use App\Models\Customer;

use DB;

class LocationController extends Controller
{
    public function GetAllLocation(Request $request)
    {
        /* "name" : 'Google'
        "loc_lat": "42.8365341",
        "loc_lng": "-88.85602030000001",

       /location?type=customer&lat='+$scope.location.latitude+'&lng=

       http://master.dev/location?type=customer&lat=13.0826802&lng=80.27071840000008

          {
        return $this
            ->select('*')
            ->select(DB::raw('( 3959 * acos( cos( radians(21.420639) ) * cos( radians( lat ) ) * cos( radians( lon ) - radians(-157.805745) ) + sin( radians(21.420639) ) * sin( radians( lat ) ) ) ) AS distance'))
            ->groupBy('id')
            ->having('distance', '<', 25)
            ->having('ratingsTotal', '>', 0)
            ->orderBy('distance')
            ->limit(5);
    }

       */

        $customer = new Customer();


        $lat =$request->input('lat');
        $lng = $request->input('lng');
        $distance = 10;

        if (null !==  $request->input('distance')) {
            $distance = $request->input('distance');
        }








        return Base::touser($customer->distance($lat, $lng, $distance)->get(['id','name','loc_lat','loc_lng']), true);
    }

    public function GetAllCompany(Request $request)
    {
        $customer = new Customer();

  

        return Base::touser($customer->get(['id','name','loc_lat','loc_lng']), true);
    }
}
