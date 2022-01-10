<?php namespace App\Http\Controllers;

use Illuminate\Routing\Controller;

use App\Models\ProductStatistic as pro_stat;

use App\Http\Controllers\Base ;

class ProductStatisticController extends Controller
{
    public function index()
    {
        return Base::touser(pro_stat::all(), true);
    }

    
    public function store()
    {
    }

    public function show($id)
    {
        return Base::touser(pro_stat::find($id), true);
    }

    
    public function update($id)
    {
    }


    public function destroy($id)
    {
    }
}
