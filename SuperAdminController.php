<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Base;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\packageinfo;
use App\Models\PaymentHistory;
use DB;
use Carbon\Carbon;

class SuperAdminController extends Controller {

    public function getAdminUsers(Request $request) {
        $users = new User();

        $offset = 0;
        $limit = 10;

        if ($request->input('offset') != null) {
            $offset = $request->input('offset');
        }
        if ($request->input('limit') != null) {
            $limit = $request->input('limit');
        }

        $data = DB::table('user')
                    ->select(
                            "user.first_name",
                            "user.last_name",
                            "user.phone",
                            "user.email",
                            "user.role_id",
                            "user.user_id",
                            "package_info.name",
                            "user_package_table.package_id",
                            "user_package_table.beg_date",
                            "user_package_table.end_date"
                    )
                    ->join('user_package_table', 'user_package_table.user_id', '=', 'user.user_id')
                    ->join('package_info', 'package_info.id', '=', 'user_package_table.package_id')
                    ->where('user.role_id', 2)
                    ->get()->toArray();
        return Base::touser($data, true);
    }

    public function getPackageInformation(Request $request) {
        $data = packageinfo::all()->toArray();
        return Base::touser($data, true);
    }

    public function getUserPackageInfo(Request $request, $id) {
        $user_package = UserPackage::where('user_id', $id)->with('packageinfo')->with('userinfo')->first()->toArray();
        $total_emp = 0;
        $remaining_emp = 0;
        $total = 0;
        $data = DB::table('user')
                        ->select('*')
                        ->join('emp_mapping', 'emp_mapping.emp_id', '=', 'user.user_id')
                        ->where('emp_mapping.admin_id', '=', $id)
                        ->where('emp_mapping.is_delete', '!=', true)
                        ->where('emp_mapping.is_active', '=', 1)
                        ->where('user.user_token', '!=', null)
                        ->get()->count();
        $mgr = DB::table('user')
                        ->select('*')
                        ->join('emp_mapping', 'emp_mapping.manager_id', '=', 'user.user_id')
                        ->where('emp_mapping.admin_id', '=', $id)
                        ->where('emp_mapping.is_delete', '!=', true)
                        ->where('emp_mapping.emp_id', '=', null)
                        ->where('emp_mapping.is_active', '=', 1)
                        ->where('user.user_token', '!=', null)
                        ->get()->count();
        $emps = UserPackage::where('user_id', $id)->first();
        $packageinfo = packageinfo::where('id', $emps['package_id'])->first();
        $total_emp = (int) $emps["no_of_emp"];
        $remaining_emp = (int) $total_emp - (int) $data;
        $remaining_mgr = (int) $emps["no_of_mgr"] - $mgr;
//        $date = new \DateTime($emps['beg_date']);
//        $begin_date = $date->format('Y-m-d');
        
        $date1 = new \DateTime($emps['end_date']);
        $end_date = $date1->format('Y-m-d') . " 23:59:59";
        
        $tasks = DB::table('emp_cust_schedule')
                ->select('*')
                ->join('orders', 'orders.id', '=', 'emp_cust_schedule.mt_order_id')
                ->where('orders.added_by', $id)
                ->where('orders.created_at', '>=', $emps['beg_date'])
                ->where('orders.created_at', '<=', $end_date)
                ->get()->count();
        
        $total_task = (int) $emps["no_of_task"];
        $remaining_task = $total_task - $tasks;
        $data = [
            "user_package" => $user_package,
            "total_emp" => (int) $total_emp,
            "used_emp" => (int) $data,
            "remaining_emp" => $remaining_emp,
            "total_mgr" => (int) $emps["no_of_mgr"],
            "used_mgr" => (int) $mgr,
            "remaining_mgr" => $remaining_mgr,
            "total_task" => $total_task,
            "used_task" => $tasks,
            "remaining_task" => $remaining_task,
        ];
        return Base::touser($data, true);
    }

    public function updateExpiry(Request $request) {
        $data = $request->input('data');
        $user_id = $data['user_id'];
        if (isset($data['date'])) {
            $end_date = date("Y-m-d H:i:s", strtotime($data['date']));
            $data = UserPackage::where('user_id', $user_id)->update(['end_date' => $data['date']]);
        }
        if (isset($data['no_of_task'])) {
            $data = UserPackage::where('user_id', $user_id)->update(['no_of_task' => $data['no_of_task']]);
        }
        if (isset($data['no_of_cust'])) {
            $data = UserPackage::where('user_id', $user_id)->update(['no_of_cust' => $data['no_of_cust']]);
        }
        if (isset($data['no_of_emp'])) {
            $data = UserPackage::where('user_id', $user_id)->update(['no_of_emp' => $data['no_of_emp']]);
        }
        return Base::touser("Expiry date updated successfuly", true);
    }

    public function makePayment(Request $request) {
        $data = $request->input('data');

        $working_key = env('WORKING_KEY'); //Shared by CCAVENUES
        $access_code = env('ACCESS_CODE'); //Shared by CCAVENUES
        $merchant_id = env('MERCHANT_ID');
        $merchant_data = 'merchant_id=' . $merchant_id . '&';

        foreach ($data as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }

        $encrypted_data = $this->encrypt_merchant_data($merchant_data, $working_key); // Method for encrypting the data.

        $returndata['url'] = 'https://secure.ccavenue.ae/transaction/transaction.do?command=initiateTransaction&encRequest=' . $encrypted_data . '&access_code=' . $access_code;

        return Base::touser($returndata, true);
    }
    
    public function encrypt_merchant_data($plainText, $key)
    {
        $key = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $openMode = openssl_encrypt($plainText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        $encryptedText = bin2hex($openMode);
        return $encryptedText;
    }
    
    public function hextobin($hexString) 
    {
        $length = strlen($hexString);
        $binString="";
        $count=0;
        while($count < $length)
        {
            $subString =substr($hexString,$count,2);
            $packedString = pack("H*",$subString);
            if ($count==0)
            {
                $binString=$packedString;
            }
            else {
                $binString.=$packedString;
            }
            $count += 2;
        }
        return $binString;
     }

    public function paymenthistory(Request $request) {
        $user_id = $request->input('user_id');
        if (isset($user_id)) {
            $data = PaymentHistory::where('user_id', $user_id)->with('user')->with('package')->orderBy('id', 'desc')->get();
        } else {
            $data = PaymentHistory::with('user')->with('package')->orderBy('id', 'desc')->get();
        }
        return Base::touser($data, true);
    }

    public function getpaymenthistory($id) {
        $data = PaymentHistory::where('id', $id)->with('user')->with('package')->first();
        return Base::touser($data, true);
    }
    
    public function getRecentPaymentHistory(Request $request) {
        $data = PaymentHistory::where('user_id', $this->emp_id)->with('user')->with('package')->orderBy('id', 'desc')->first();
        return Base::touser($data, true);
    }
    
    public function decrypt($encryptedText,$key)
    {
        $key = $this->hextobin(md5($key));
        $initVector = pack("C*", 0x00, 0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08, 0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f);
        $encryptedText = $this->hextobin($encryptedText);
        $decryptedText = openssl_decrypt($encryptedText, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $initVector);
        return $decryptedText;
    }
    
    public function paymentResponse(Request $request) {
        $data = $request->input('encResp');
        $working_key = env('WORKING_KEY'); //Shared by CCAVENUES
        
        $rcvdString = $this->decrypt($data, $working_key);
	$decryptValues=explode('&', $rcvdString);
        $dataSize=sizeof($decryptValues);

        $data = [];
        for($i = 0; $i < $dataSize; $i++)
        {
           $information=explode('=',$decryptValues[$i]);
           $data[$information[0]] = $information[1];
        }
        
        $userPackage = UserPackage::where('user_id', $data['merchant_param1'])->first();
        $package = packageinfo::where('id', $data['merchant_param2'])->first();
        
        $paymentHistory = new PaymentHistory();
        $paymentHistory->user_id = $data['merchant_param1'];
        $paymentHistory->package_id = $data['merchant_param2'];
        $paymentHistory->sales_order_id = $data['order_id'];
        $paymentHistory->status = $data['order_status'];
        $paymentHistory->tracking_id = $data['tracking_id'];
        $paymentHistory->amount = $data['amount'];
        $paymentHistory->currency = $data['currency'];
        $paymentHistory->bank_ref_no = $data['bank_ref_no'];
        $paymentHistory->failure_message = $data['failure_message'];
        $paymentHistory->payment_mode = $data['payment_mode'];
        $paymentHistory->status_message = $data['status_message'];
        $paymentHistory->bank_receipt_no = $data['bank_receipt_no'];
        $paymentHistory->eci_value = $data['eci_value'];
        if($data['order_status'] == 'Success') {
            $paymentHistory->due_date = Carbon::createFromFormat('Y-m-d H:i:s',$userPackage->end_date)->addDays($package['no_of_days']);
        }
        $paymentHistory->save();
        
        if($data['order_status'] == 'Success') {
            $userPackage->beg_date = date('Y-m-d H:i:s');
            
            if(strtotime(date('Y-m-d H:i:s')) > strtotime($userPackage->end_date)) {
                $userPackage->end_date = Carbon::parse(date('Y-m-d H:i:s'))->addDays($package['no_of_days']);
            } else {
                $userPackage->end_date = Carbon::createFromFormat('Y-m-d H:i:s',$userPackage->end_date)->addDays($package['no_of_days']);
            }
            
            $userPackage->no_of_emp = $package['no_of_emp'];
            $userPackage->no_of_task = $package['no_of_task'];
            $userPackage->no_of_mgr = $package['no_of_mgr'];
            $userPackage->package_id = $data['merchant_param2'];
            $userPackage->save();
        }
        
        $url = "payment-result/" . $paymentHistory->id;
        
        return redirect($url);
    }
    public function getSuperAdmin(Request $request) {

        $data = User::where('role_id','3')->get()->toArray();
        return Base::touser($data, true);
    }
}
