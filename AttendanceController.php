<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;
use Validator;

class AttendanceController extends Controller
{
    public function index()
    {
        if ($this->admin || $this->backend) {
            $array = Attendance::all()->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = Attendance::whereIn('user_id', $belongsemp)->get()->toArray();
        } else {
            $array = Attendance::where('user_id', $this->emp_id)->get()->toArray();
        }


        return Base::touser($array, true);
    }


    public function store(Request $request)
    {
        $rules = [
'user_id' => 'exists:user,user_id'
        ];

        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $Attendance = new Attendance();


        if ($this->admin || $this->backend) {
            $Attendance->user_id = isset($data['user_id']) ? $data['user_id'] : null;
        } elseif ($this->manager) {
            $Attendance->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
        } else {
            $Attendance->user_id =  $this->emp_id;
        }

        $Attendance->in_time = isset($data['in_time']) ?  Base::tomysqldatetime($data['in_time']) : null;
        $Attendance->out_time = isset($data['out_time']) ?  Base::tomysqldatetime($data['out_time']) : null;
        $Attendance->attent_type = isset($data['attent_type']) ? $data['attent_type'] : null;
        $Attendance->leave_desc = isset($data['leave_desc']) ? $data['leave_desc'] : null;
        $Attendance->login_lat = isset($data['login_lat']) ? $data['login_lat'] : null;
        $Attendance->login_lon = isset($data['login_lon']) ? $data['login_lon'] : null;
        $Attendance->logout_lat = isset($data['logout_lat']) ? $data['logout_lat'] : null;
        $Attendance->logout_lon = isset($data['logout_lon']) ? $data['logout_lon'] : null;
        $Attendance->leave_desc = isset($data['leave_desc']) ? $data['leave_desc'] : null;
        $Attendance->save();

        return Base::touser('Attendance Created', true);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if ($this->admin || $this->backend) {
            $array = Attendance::find($id)->toArray();
        } elseif ($this->manager) {
            $belongsemp = Base::getEmpBelongsUser($this->emp_id);

            $array = Attendance::whereIn('user_id', $belongsemp)->find($id)->toArray();
        } else {
            $array = Attendance::where('user_id', $this->emp_id)->find($id)->toArray();
        }


        return Base::touser($array, true);
    }

  
    public function update(Request $request, $id)
    {
        $rules = [
'user_id' => 'exists:user,user_id'
        ];

        $data = $request->input('data');


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }

        $Attendance =Attendance::find($id);
       

        if ($this->admin || $this->backend) {
            $Attendance->user_id = isset($data['user_id']) ? $data['user_id'] : null;
        } elseif ($this->manager) {
            $Attendance->user_id = isset($data['user_id']) ? $data['user_id'] : $this->emp_id;
        } else {
            $Attendance->user_id =  $this->emp_id;
        }

        $Attendance->in_time = isset($data['in_time']) ?  Base::tomysqldatetime($data['in_time']) : null;
        $Attendance->out_time = isset($data['out_time']) ? Base::tomysqldatetime($data['out_time']) : null;
        $Attendance->attent_type = isset($data['attent_type']) ? $data['attent_type'] : null;
        $Attendance->leave_desc = isset($data['leave_desc']) ? $data['leave_desc'] : null;
        $Attendance->login_lat = isset($data['login_lat']) ? $data['login_lat'] : null;
        $Attendance->login_lon = isset($data['login_lon']) ? $data['login_lon'] : null;
        $Attendance->logout_lat = isset($data['logout_lat']) ? $data['logout_lat'] : null;
        $Attendance->logout_lon = isset($data['logout_lon']) ? $data['logout_lon'] : null;
        $Attendance->leave_desc = isset($data['leave_desc']) ? $data['leave_desc'] : null;
        $Attendance->save();


        return Base::touser('Attendance Updated', true);
    }

    public function destroy($id)
    {
        $api = Attendance::find($id);

        $api->delete();

        return Base::touser('Attendance Deleted', true);
    }
}
