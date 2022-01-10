<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Base;
use Closure;
use Illuminate\Http\Request;
use Log;
use App\Models\ApiAuth;
use App\Models\ApiThrottler;
use App\Models\UserThrottleSettings;
use Carbon\Carbon;

class AuthHeader
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $role = Base::role();

        if ($role == Base::guest()) {
            return Base::app_unauthorized();
        }
        // $app = Base::is_app_domain();
        // if($app){
        /*if (Base::mobile_header() == 1) {
            $user_det = Base::check_user_validity();
            // print_r($user_det);
            if ($user_det > 0) {
                return Base::app_endvalidity();
            }
        }*/
        // }

        $nonJsonRoutes = array(
            'api/upload',
        );
        $isTokenValid = Base::isTokenValid($_SERVER['HTTP_AUTHORIZATION']);

        if ($isTokenValid == true) {
            $auth_user = ApiAuth::where('auth_key', '=', $_SERVER['HTTP_AUTHORIZATION'])
                ->orWhere('api_key', '=', $_SERVER['HTTP_AUTHORIZATION'])->first();
            
            if (count($auth_user) == 0) {
                return Base::app_unauthorized();
            }
            
            $userThrottleSettings = UserThrottleSettings::where('route_name', '=', $request->route()->uri)->get()->toArray();
            
            if ($auth_user['api_key'] == $_SERVER['HTTP_AUTHORIZATION'] && count($userThrottleSettings) > 0) {
                $limit = $userThrottleSettings[0]['limit'];
                
                $current_date = date('Y-m-d H:i:s');
                $end_date = Carbon::parse($current_date);
                $end_date->addHours(1);
                $end_date = $end_date->format('Y-m-d H:i:s');
                
                $apiThrottleDataList = ApiThrottler::where([['user_id', '=', $auth_user->auth_user_id], ['start_time', '<=', $current_date], 
                    ['end_time', '>=', $current_date]])->get()->toArray();
                
                if (count($apiThrottleDataList) > 0) {
                    $apiThrottleData = $apiThrottleDataList[0];
                    
                    if (($apiThrottleData['limit'] - $apiThrottleData['hit_count']) > 0) {
                        $data = ApiThrottler::where('user_id', '=', $auth_user->auth_user_id)->first();
                        $data->hit_count = $apiThrottleData['hit_count'] + 1;
                        $data->update();
                    } else {
                        return Base::touser("Your request has been throttled. Reached your maximum threshold limit", false);
                    }
                } else {
                    ApiThrottler::where([['user_id', '=', $auth_user->auth_user_id]])->delete();
                    
                    $apiThorottler = new ApiThrottler();
                    $apiThorottler->user_id = $auth_user->auth_user_id;
                    $apiThorottler->route_name = $request->route()->uri;
                    $apiThorottler->start_time = $current_date;
                    $apiThorottler->end_time = $end_date;
                    $apiThorottler->limit = $limit;
                    $apiThorottler->hit_count = 1;
                    $apiThorottler->save();
                }
            }

            if ($request->isMethod('POST') || $request->isMethod('PUT')) {
                Log::info('Requested URL Log - ' . $request->route()->uri . "-" . $auth_user->auth_user_id);
                if (in_array($request->route()->uri, $nonJsonRoutes)) {

                } else {
                    $info = json_decode($request->getContent());

                    if (json_last_error() != JSON_ERROR_NONE) {
                        return Base::touser("Json not Valid", false);
                    }
                    if($request->route()->uri == 'api/custom_order')
                    {
                        return $next($request);
                    }
                    if (!array_key_exists('data', $info)) {
                        return Base::touser("Json data attribute null", false);
                    }
                    if (count($info) <= 0) {
                        return Base::touser("Json data attribute empty", false);
                    }
                }
            }
        }

        return $next($request);
    }
}
