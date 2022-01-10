<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\State;
use App\Models\Country;
use App\Http\Controllers\Base;
use Illuminate\Http\Request;

class DropDownDataController extends Controller
{
    public function country()
    {
        return json_encode(Country::all());
    }

    public function state()
    {
        $country_id = $_GET['country_id'];
        $states = [];
        if ($country_id) {
            $states = State::where('country_id', '=', $country_id)->get()->toArray();
        }
        echo json_encode($states);
    }


    public function city()
    {
        $state_id = $_GET['state_id'];
        $cities = [];
        if ($state_id) {
            $cities = City::where('state_id', '=', $state_id)->get()->toArray();
        }
        echo json_encode($cities);
    }
}
