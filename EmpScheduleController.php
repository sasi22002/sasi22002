<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\EmpCustSchedule as emp_cust;
use App\Models\EmpSchedule as task;
use Illuminate\Http\Request;
use Validator;
use Toin0u\Geotools\Facade\Geotools;
class EmpScheduleController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = task::with('cust_jobs')->with('emp_info')->get()->toArray();

            foreach ($array as $key => $value) {

                $array[$key]['emp_info'] = $array[$key]['emp_info']['first_name'] . ' ' . $array[$key]['emp_info']['last_name'];
            }
        } elseif ($this->manager) {

            $belongsemp = Base::getEmpBelongsUser($this->emp_id);


            $array = task::with('cust_jobs','emp_info')->whereIn('emp', $belongsemp)->get()->toArray();



            foreach ($array as $key => $value) {

                $array[$key]['emp_info'] = $array[$key]['emp_info']['first_name'] . ' ' . $array[$key]['emp_info']['last_name'];
            }
        } else {
            $array = emp_cust::where('emp_id', $this->emp_id)->get()->toArray();

               foreach ($array as $key => $value) {
                        // $array[$key]['signature'] =  json_decode($array[$key]['signature']);
                        $array[$key]['images'] =  json_decode($array[$key]['images']);
               }

        }





        return Base::touser($array, true);
    }

    public function updatetask(Request $request,$id)
    {

        $rules = [
            'status'    => 'required',
            'lat'    => 'required',
            'timestamps'    => 'required',
            'lng'    => 'required',
            // 'is_cust_delivery' => 'required',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {

      return Base::touser($validator->errors()->all()[0]);
        }



//         $coordA   = Geotools::coordinate([48.8234055, 2.3072664]);
// $coordB   = Geotools::coordinate([43.296482, 5.36978]);
// $distance = Geotools::distance()->setFrom($coordA)->setTo($coordB);

        $emp = new emp_cust();
        $data = $emp->where('id',$id)->where('status','<>','completed')->first();


            if ($data === null) {
        return Base::touser('completed',true);
            }


        $req = strtolower($request->input('data')['status']);
        $reqlat = $request->input('data')['lat'];
        $reqlng = $request->input('data')['lng'];
        $timestamp = isset($request->input('data')['timestamp']) ? Base::tomysqldatetime($request->input('data')['timestamp']) :  date('Y-m-d H:i:s');
        $remarks = $request->input('data')['remarks'];
        $customer = \App\Models\Customer::find($data->cust_id);

        $coordA   = Geotools::coordinate([$reqlat, $reqlng]);
        $coordB   = Geotools::coordinate([$customer->loc_lat,$customer->loc_lng]);
        $distance = Geotools::distance()->setFrom($coordA)->setTo($coordB);

        if($distance->flat() > 300)
        {
            return Base::touser('Customer must be within 300 meters');
        }

        $data->status = isset($req) ? $req : 'waiting';
        $data->lat = isset($reqlat) ? $reqlat : '';
        $data->lng = isset($reqlng) ? $reqlng : '';
        $data->timestamp = isset($timestamp) ? $timestamp : '';

        $data->delivery_to = isset($request->input('data')['delivery_to']) ? $request->input('data')['delivery_to'] : '';
        $data->delivery_phone = isset($request->input('data')['delivery_phone']) ? $request->input('data')['delivery_phone'] : '';
        $data->is_cust_delivery = isset($request->input('data')['is_cust_delivery']) ? $request->input('data')['is_cust_delivery'] : 1;
        $data->remarks = isset($remarks) ? $remarks : '';

        $data->signature = isset($request->input('data')['signature']) ? $request->input('data')['signature'] : '';

        $data->images = isset($request->input('data')['images']) ? json_encode($request->input('data')['images']) : '[]';



        $data->save();

        $user = \App\Models\User::find($this->emp_id);
        $notification =  $user->notify(new \App\Notifications\TaskCompleted($data,$user));
        event(new \App\Events\NotificationEvent($user));


        return Base::touser($data, true);
    }


    public function store(Request $request)
    {
        $rules = [
            'emp'    => 'exists:user,user_id',
            'add_by' => 'exists:user,user_id',

        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        if (task::where('emp', $data['emp'])->where('date', Base::tomysqldate($data['date']))
            ->count() > 0) {

            return Base::touser('Already Tasks Exit for the Same Date');
        }

        $task = new task();
        $data = $request->input('data');

        $task->date = isset($data['date']) ? Base::tomysqldate($data['date']) : date('Y-m-d');

        if ($this->admin || $this->backend) {
            $task->emp = $data['emp'];

            if (empty($data['add_by'])) {

                return Base::touser('Admin Must Provide Allocated Employee Value');
            }

            $task->add_by = $data['add_by'];
        } elseif ($this->manager) {
            $task->emp    = $data['emp'];
            $task->add_by = $this->emp_id;
        } else {
            $task->emp    = $this->emp_id;
            $task->add_by = $this->emp_id;
        }

        $task->save();

        $data['cust_jobs'] = array_filter($data['cust_jobs']);

        foreach ($data['cust_jobs'] as $key => $value) {
            $data['cust_jobs'][$key]['emp_id']      = $data['emp'];
            $data['cust_jobs'][$key]['emp_cust_id'] = $task->id;
            $data['cust_jobs'][$key]['date']        = Base::tomysqldate($data['date']);

        $data['cust_jobs'][$key]['cust_id'] = isset($data['cust_jobs'][$key]['cust_id']) ?$data['cust_jobs'][$key]['cust_id'] : '';
        $data['cust_jobs'][$key]['lat'] = isset($data['cust_jobs'][$key]['lat']) ?$data['cust_jobs'][$key]['lat'] : '';
        $data['cust_jobs'][$key]['lng'] = isset($data['cust_jobs'][$key]['lng']) ?$data['cust_jobs'][$key]['lng'] : '';
        $data['cust_jobs'][$key]['delivery_to'] = isset($data['cust_jobs'][$key]['delivery_to']) ?$data['cust_jobs'][$key]['delivery_to'] : '';
        $data['cust_jobs'][$key]['type'] = isset($data['cust_jobs'][$key]['type']) ?$data['cust_jobs'][$key]['type'] : '';
        $data['cust_jobs'][$key]['notes'] = isset($data['cust_jobs'][$key]['notes']) ? $data['cust_jobs'][$key]['notes'] : '';
        $data['cust_jobs'][$key]['delivery_phone'] = isset($data['cust_jobs'][$key]['delivery_phone']) ?$data['cust_jobs'][$key]['delivery_phone'] : '';
        $data['cust_jobs'][$key]['is_cust_delivery'] = isset($data['cust_jobs'][$key]['is_cust_delivery']) ?$data['cust_jobs'][$key]['is_cust_delivery'] : 1;
        $data['cust_jobs'][$key]['remarks'] = isset($data['cust_jobs'][$key]['remarks']) ? $data['cust_jobs'][$key]['remarks'] : '';
        $data['cust_jobs'][$key]['status'] = isset($data['cust_jobs'][$key]['status']) ? $data['cust_jobs'][$key]['status'] : 'waiting';
        $data['cust_jobs'][$key]['timestamp'] = isset($data['cust_jobs'][$key]['timestamp']) ? Base::tomysqldatetime($data['cust_jobs'][$key]['timestamp']) : '';






        }

       $data =  emp_cust::insert($data['cust_jobs']);

       $user =  \App\Models\User::find($task->emp);

       $user->notify(new \App\Notifications\TaskAllocated($task,$user));

       $data = emp_cust::where('emp_cust_id',$task->id)->get(['date','id','cust_id']);

       foreach ($data as $key => $value) {


           $cust = \App\Models\Customer::find($value['cust_id'])->notify(new \App\Notifications\CustomerTracking($value,$user,Base::get_domin()));

       }



        // $user->notify(new \App\Notifications\TaskAllocated($task,$user));


        return Base::touser('Task Created', true);
    }

    public function show($id)
    {
        $val = true;

        if ($this->admin || $this->backend) {
            $data = task::with('cust_jobs')->get()->find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $data = task::with('cust_jobs')->whereIn('emp', $belongsemp)->get()->find($id)->toArray();
        } else {
            $data = emp_cust::where('emp_id', $this->emp_id)->get()->toArray();
            $val = true;
        }


        return Base::touser($data, true);
    }

    public function getWithStatus($id)
    {
        return Base::touser(task::with('cust_jobs')->get()->find($id), true);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'emp'    => 'exists:user,user_id',
            'add_by' => 'exists:user,user_id',
        ];

        $data = $request->input('data');

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        if (task::where('emp', $data['emp'])->where('date', Base::tomysqldate($data['date']))

            ->where('id', '<>', $id)

            ->count() > 0) {

            return Base::touser('Already Tasks Exit for the Same Date');
        }

        //  return Base::touser($validator->errors()->all()[0]);

        \DB::beginTransaction();

        try {
            $task         = new task();
            $data         = $request->input('data');
            $task         = $task->where('id', '=', $id)->first();
            $timestamp    = strtotime($data['date']);
            $data['date'] = date('Y-m-d', $timestamp);



            if ($this->admin || $this->backend) {
                $task->emp = $data['emp'];

                if (empty($data['add_by'])) {

                    return Base::touser('Admin must provide allocated employee value');
                }

                $task->add_by = $data['add_by'];
            } elseif ($this->manager) {
                $task->emp    = $data['emp'];
                $task->add_by = $this->emp_id;
            } else {
                $task->emp = $this->emp_id;
            }

            $task->date = $data['date'];
            $task->save();
            $data['cust_jobs'] = array_filter($data['cust_jobs']);

            $sub_tasks = [];


                $user =  \App\Models\User::find($task->emp);
        foreach ($data['cust_jobs'] as $key => $value) {

            $is_new = false;
             if(isset($data['cust_jobs'][$key]['id']))
             {
                $sub_task = emp_cust::where('id',$data['cust_jobs'][$key]['id'])->first();

             }
             else
             {
                $sub_task = new emp_cust();
                $is_new = true;
             }


        $sub_task->emp_id      = $data['emp'];
       $sub_task->emp_cust_id = $task->id;
        $sub_task->date  = Base::tomysqldate($data['date']);
       $sub_task->cust_id = isset($data['cust_jobs'][$key]['cust_id']) ?$data['cust_jobs'][$key]['cust_id'] : '';
        $sub_task->lat = isset($data['cust_jobs'][$key]['lat']) ?$data['cust_jobs'][$key]['lat'] : '';
       $sub_task->lng = isset($data['cust_jobs'][$key]['lng']) ?$data['cust_jobs'][$key]['lng'] : '';
       $sub_task->delivery_to = isset($data['cust_jobs'][$key]['delivery_to']) ?$data['cust_jobs'][$key]['delivery_to'] : '';
       $sub_task->type= isset($data['cust_jobs'][$key]['type']) ?$data['cust_jobs'][$key]['type'] : '';
      $sub_task->notes = isset($data['cust_jobs'][$key]['notes']) ? $data['cust_jobs'][$key]['notes'] : '';
       $sub_task->delivery_phone = isset($data['cust_jobs'][$key]['delivery_phone']) ?$data['cust_jobs'][$key]['delivery_phone'] : '';
       $sub_task->is_cust_delivery= isset($data['cust_jobs'][$key]['is_cust_delivery']) ?$data['cust_jobs'][$key]['is_cust_delivery'] : 1;
      $sub_task->remarks= isset($data['cust_jobs'][$key]['remarks']) ? $data['cust_jobs'][$key]['remarks'] : '';
        $sub_task->status = isset($data['cust_jobs'][$key]['status']) ? $data['cust_jobs'][$key]['status'] : 'waiting';
     $sub_task->timestamp= isset($data['cust_jobs'][$key]['timestamp']) ? Base::tomysqldatetime($data['cust_jobs'][$key]['timestamp']) : '';
     $sub_task->signature= isset($data['cust_jobs'][$key]['signature']) ? $data['cust_jobs'][$key]['signature'] : '';

     $sub_task->save();


     if($is_new)
     {

        $cust = \App\Models\Customer::find($sub_task->cust_id)->notify(new \App\Notifications\CustomerTracking($sub_task,$user,Base::get_domin()));

     }



     $sub_tasks[] = $sub_task->id;


        }

            emp_cust::where('emp_cust_id', '=', $task->id)->whereNotIn('id', $sub_tasks)
                ->delete();

            // emp_cust::insert($data['cust_jobs']);
        } catch (Exception $e) {
            \DB::rollBack();

            throw $e;
        }

        \DB::commit();



        $user->notify(new \App\Notifications\TaskUpdated($task,$user));

        return Base::touser('Task Updated', true);
    }

    public function destroy($id)
    {
        emp_cust::where('emp_cust_id', '=', $id)
            ->delete();

        $api = task::find($id);
        $api->delete();
        return Base::touser('Task Deleted', true);
    }
}
