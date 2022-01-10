<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\CustomerStatistic as cust_st;
use Illuminate\Http\Request;

class CustomerStatisticController extends Controller
{
    public function index()
    {

        /* ?year=1998&month=jan*/

        $request = new Request;
        if ((null !== $request->input('year')) && (null !== $request->input('month'))) {
            return Base::touser(

                cust_st::where([
                    ['year', '=', $request->input('year')],
                    ['month', '=', $request->input('month')]])

                    ->with('cust')->get(), true);
        } else {

            $data = cust_st::with('cust')->get()->toArray();
            foreach ($data as $key => $value) {

                $data[$key]['month_string'] = Base::to_month($data[$key]['month']);
                $data[$key]['cust']         = $data[$key]['cust']['name'];
            }

            return Base::touser($data, true);
        }
    }

    public function store(Request $request)
    {
        $cust_st = new cust_st;

        $data = $request->input('data');

        $cust_st->year         = $data['year'];
        $cust_st->cust_id      = $data['cust_id'];
        $cust_st->month        = $data['month'];
        $cust_st->credit_limit = $data['credit_limit'];
        $cust_st->credit_terms = $data['credit_terms'];
        $cust_st->outstandings = $data['outstandings'];
        $cust_st->last_visited = Base::tomysqldate($data['last_visited']);
        $cust_st->over_due     = $data['over_due'];

        $cust_st->save();

        return self::index();
    }

    public function show(Request $request, $id)
    {

        if ($request->input('info')) {
            try {
       

            $data = cust_st::with('cust')->find($id)->toArray();

            $data['month_string'] = Base::to_month($data['month']);

            $data['cust']         = $data['cust']['name'];

            } catch (\Exception $e) {
                return Base::throwerror();
            }

            return Base::touser($data, true);
        }

        if ((null !== $request->input('year')) && (null !== $request->input('month'))) {
            return Base::touser(cust_st::where([['cust_id', '=', $id], ['year', '=', $request->input('year')], ['month', '=', $request->input('month')]])
                    ->with(['prod_stat' => function ($query) {
                        $query->where([
                            ['year', '=', \Request::input('year')],
                            ['month', '=', \Request::input('month')]]

                        );
                    }])
                    ->with('cust')->get(), true);
        } else {

            $data = cust_st::where('cust_id', '=', $id)->with('cust')->get()->toArray();

            $data['month_string'] = Base::to_month($data['month']);
            $data['cust']         = $data['cust']['name'];

            return Base::touser($data, true);

            // return Base::touser(cust_st::where('cust_id', '=', $id)->with('cust')->get(), true);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $cust_st               = new cust_st;
        $data                  = $request->input('data');
        $cust_st               = $cust_st->find($id);
        $cust_st->year         = $data['year'];
        $cust_st->cust_id      = $data['cust_id'];
        $cust_st->month        = $data['month'];
        $cust_st->credit_limit = $data['credit_limit'];
        $cust_st->credit_terms = $data['credit_terms'];
        $cust_st->outstandings = $data['outstandings'];
        $cust_st->last_visited = Base::tomysqldate($data['last_visited']);
        $cust_st->over_due     = $data['over_due'];

        $cust_st->save();

        return self::index();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $api = new cust_st();

        $cust_st = $api->find($id)->first();

        $cust_st->delete();

        return self::index();
    }
}
