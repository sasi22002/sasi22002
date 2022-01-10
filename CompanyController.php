<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use App\Models\CompanyDbInfo;
use App\Models\Master;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Validator;

class CompanyController extends Controller
{
    public function index()
    {
        if ($this->admin) {
            $data = Master::with('db_info')->get()->toArray();

            foreach ($data as $key => $value) {



              $data[$key]['logo'] = json_decode($data[$key]['logo'],true);



                   $data[$key]['full_address'] = '';

                  if(trim($data[$key]['company_street']))
                  {
                     $data[$key]['full_address'] =   $data[$key]['company_street'] . ',' ;
                  }
                          if(trim($data[$key]['company_city']))
                  {
                     $data[$key]['full_address'] .=   $data[$key]['company_city'] . ',' ;
                  }

                          if(trim($data[$key]['company_state']))
                  {
                     $data[$key]['full_address'] .=   $data[$key]['company_state'] . ',' ;
                  }

                          if(trim($data[$key]['company_country']))
                  {
                     $data[$key]['full_address'] .=   $data[$key]['company_country'] . ',' ;
                  }
                          if(trim($data[$key]['company_zipcode']))
                  {
                     $data[$key]['full_address'] .=   $data[$key]['company_zipcode'];
                  }

            }
            return Base::touser($data, true);
        } else {
            Base::app_unauthorized();
        }
    }

    public function getDbinfo()
    {
        if ($this->admin) {
            return Base::touser(Master::with('domain')->get(['company_name', 'company_id']), true);
        } else {
            Base::app_unauthorized();
        }
    }

    public function store(Request $request)
    {
        if ($this->admin) {
            $data = $request->input('data');

            $rules = [
                'company_name'  => 'required|unique:master',
                // 'company_street'  => 'required',
                // 'company_city'    => 'required',
                // 'company_state'   => 'required',
                // 'company_zipcode' => 'required',
                // 'company_url'     => 'required',
                'company_phone' => 'required',
                // 'company_country' => 'required',
                'company_email' => 'required|email|unique:master,company_email',
            ];

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return Base::touser($validator->errors()->all()[0]);
            }

            $company = new Master;

            $company->company_name    = $data['company_name'];
            $company->company_zipcode = isset($data['company_zipcode']) ? $data['company_zipcode'] : '';
            $company->company_state   = isset($data['company_state']) ? $data['company_state'] : '';
            $company->company_city    = isset($data['company_city']) ? $data['company_city'] : '';
            $company->company_street  = isset($data['company_street']) ? $data['company_street'] : '';
            $company->company_phone   = $data['company_phone'];
            $company->company_url     = isset($data['company_url']) ? $data['company_url'] : '';
            $company->company_desc    = isset($data['company_desc']) ? $data['company_desc'] : '';
            $company->company_country = isset($data['company_country']) ? $data['company_country'] : '';
            $company->company_pwd     = encrypt(Hash::make(str_random(12)));
            $company->company_email   = $data['company_email'];
            $company->logo   = isset($data['logo']) ? json_encode($data['logo']) : '';
            $company->save();

            Base::create_sub_db($company->company_name, $company->company_id);

            return Base::touser('Company Created', true);
        } else {
            Base::app_unauthorized();
        }
    }

    public function show($id)
    {
        if ($this->admin) {
            try {
                $data = Master::with('db_info')->find($id);
            } catch (\Exception $e) {
                return Base::throwerror();
            }

            if (count($data) > 0) {


                $data['logo'] = json_decode($data['logo'],true);

       
                   $data['full_address'] = '';


                  if(trim($data['company_street']))
                  {
                     $data['full_address'] =   $data['company_street'] . ',' ;
                  }
                          if(trim($data['company_city']))
                  {
                     $data['full_address'] .=   $data['company_city'] . ',' ;
                  }

                          if(trim($data['company_state']))
                  {
                     $data['full_address'] .=   $data['company_state'] . ',' ;
                  }

                          if(trim($data['company_country']))
                  {
                     $data['full_address'] .=   $data['company_country'] . ',' ;
                  }
                          if(trim($data['company_zipcode']))
                  {
                     $data['full_address'] .=   $data['company_zipcode'];
                  }

                return Base::touser($data, true);
            } else {
                return Base::touser('No Data Found');
            }
        } else {
            Base::app_unauthorized();
        }
    }

    public function update(Request $request, $id)
    {
        if ($this->admin) {
            $data = $request->input('data');

            $rules = [
                'company_name'  => 'required|unique:master,company_name,' . $id . ',company_id',
                // 'company_street'  => 'required',
                // 'company_city'    => 'required',
                // 'company_state'   => 'required',
                // 'company_zipcode' => 'required',
                // 'company_url'     => 'required',
                'company_phone' => 'required',
                // 'company_country' => 'required',
                'company_email' => 'required|email|unique:master,company_email,' . $id . ',company_id',
            ];

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return Base::touser($validator->errors()->all()[0]);
            }

            try {

                 $company = Master::where('company_id', '=', $id)->first();

                $company->company_name    = $data['company_name'];
                $company->company_zipcode = isset($data['company_zipcode']) ? $data['company_zipcode'] : '';
                $company->company_state   = isset($data['company_state']) ? $data['company_state'] : '';
                $company->company_city    = isset($data['company_city']) ? $data['company_city'] : '';
                $company->company_street  = isset($data['company_street']) ? $data['company_street'] : '';
                $company->company_phone   = $data['company_phone'];
                $company->company_url     = isset($data['company_url']) ? $data['company_url'] : '';
                $company->company_desc    = isset($data['company_desc']) ? $data['company_desc'] : '';
                $company->company_country = isset($data['company_country']) ? $data['company_country'] : '';
                $company->company_email   = $data['company_email'];
                 $company->logo   = isset($data['logo']) ? json_encode($data['logo']) : '';
                $company->save();
                
            } catch (\Exception $e) {
                return Base::throwerror();
            }

            return Base::touser('Company Updated', true);
        } else {
            Base::app_unauthorized();
        }
    }

    public function destroy($id)
    {
        if ($this->admin) {
            $api = new Master();

            $api->where('company_id', '=', $id)->delete();

            $api = new CompanyDbInfo();

            $api->where('company_id', '=', $id)->delete();

            return Base::touser('Company Deleted', true);
        }
    }

    public function recover(Request $request)
    {
        $api = new Master();

        $id = $request->input('id');

        $company = $api->onlyTrashed()->where('company_id', '=', $id)->first();

        $company->restore();

        return Base::touser('Company Recovered', true);
    }
}
