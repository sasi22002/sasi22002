<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use DateTime;
use DateTimeZone;
use App\Models\timezone as timezonemang;

class MyNotificationsController extends Controller
{

    public function GetNotifications()
    {
        if ($this->manager) {
            $notifications = Notifications::where('notifiable_type', 'App\Models\User')
                ->whereIn('notifiable_id', Base::getEmpBelongsUser($this->emp_id))
                ->whereNull('read_at')
                ->get()->toArray();

            foreach ($notifications as $i => $item) {
                $notifications[$i]['data'] = json_decode($notifications[$i]['data']);
                // print_r($notifications[$i]['created_at']);
                $dt= strtotime($notifications[$i]['created_at']);
              $dt = date('Y-m-d H:i:s', $dt);   // convert UNIX timestamp to PHP DateTime
                // $dt = $dt->format('Y-m-d H:i:s');
                // print_r($dt);

                $user=User::where('user_id',$this->emp_id)->get();
                $user=User::where('user_id',$user[0]->belongs_manager)->get();
                
                if($user[0]->timezone=='')
                {
                    $zonetime="Asia/Kolkata";
                }
                else{
                    $zone=timezonemang::where('desc',$user[0]->timezone)->get();
                    // print_r($zone[0]->desc);
                    // die();
                    if($zone[0]->desc)
                    {       
                        $zonetime=$zone[0]->desc;
                    }
                }
                
                $TimeStr=$notifications[$i]['created_at'];

                $TimeZoneNameFrom="UTC";
                $TimeZoneNameTo=$zonetime;
                $notifications[$i]['created_at']= date_create($TimeStr, new DateTimeZone($TimeZoneNameFrom))
                    ->setTimezone(new DateTimeZone($TimeZoneNameTo))->format("Y-m-d H:i:s");

            }
            // print_r($notifications);
           

            $perPage             = 10;
            $pageStart           = \Request::get('page', 1);
            $offSet              = ($pageStart * $perPage) - $perPage;
            $itemsForCurrentPage = array_slice($notifications, $offSet, $perPage);

            $paginator = new LengthAwarePaginator($itemsForCurrentPage, count($notifications), $perPage);

            return $paginator;

        }

        $user = \App\Models\User::find($this->emp_id);
        $data = $user->unreadNotifications()->get()->toArray();

        $notifications = [];
        foreach ($data as $key => $value) {

            $title = '';
            switch ($value['type']) {
                case 'App\Notifications\TaskUpdated':

                    $title = "Task is updated.";

                    break;

                case 'App\Notifications\TaskAllocated':

                    $title = "New task is allocated.";

                    break;

                case 'App\Notifications\TaskCompleted':

                    $title = "Task is completed.";
                    break;
                case 'App\Notifications\AutoAllocated':
                    $title = "Task is Auto allocation.";
                    break;
                case 'App\Notifications\AutoAllocatedNoDriver':
                    $title = "No Drivers available.";
                    break;
                default:
                    break;
            }

            if (!empty($title)) {

                 $user=User::where('user_id',$this->emp_id)->get();
                 if($user[0]->timezone=='')
                 {
                    $user=User::where('user_id',$user[0]->belongs_manager)->get();
                 }
                $zone=timezonemang::where('desc',$user[0]->timezone)->get();
                // print_r($zone);
                if($zone[0])
                {       
                    $zonetime=$zone[0]->desc;
                }
                else
                {
                $zonetime="Asia/Kolkata";
                }
                $TimeStr=$value['created_at'];

                $TimeZoneNameFrom="UTC";
                $TimeZoneNameTo=$zonetime;
                $value['created_at']= date_create($TimeStr, new DateTimeZone($TimeZoneNameFrom))
                    ->setTimezone(new DateTimeZone($TimeZoneNameTo))->format("Y-m-d H:i:s");

                $info               = [];
                $info['id']         = $value['id'];
                $info['timestamps'] = $value['created_at'];
                $info['title']      = $title;

                if ($value['data']) {

                    if ($value['data']['data']) {

                        // if (array_key_exists('cust_name', $value['data']['data'])) {
                        //     $cust_name = ucwords($value['data']['data']['cust_name']);

                        // }

                        $cust_name = Base::getCustname($value['data']['data']['cust_id']);
                        
                        if (array_key_exists('schedule_date_time', $value['data']['data'])) {
                            $schedule_date_time = date('d/M/Y h:i A', strtotime($value['data']['data']['schedule_date_time']));

                        }

                        if (!empty($schedule_date_time) && !empty($cust_name)) {

                            $info['message'] = $schedule_date_time . ' / ' . $cust_name . ' ' . $title;
                            $notifications[] = $info;

                        }

                    }

                }

            }

        }

        $perPage             = 10;
        $pageStart           = \Request::get('page', 1);
        $offSet              = ($pageStart * $perPage) - $perPage;
        $itemsForCurrentPage = array_slice($notifications, $offSet, $perPage);

        $paginator = new LengthAwarePaginator($itemsForCurrentPage, count($notifications), $perPage);

        return $paginator;

    }

    public function ReadNotifications(Request $request, $id)
    {
        if ($id == '000'){
            Notifications::where('notifiable_type', 'App\Models\User')
                ->whereIn('notifiable_id', Base::getEmpBelongsUser($this->emp_id))
                ->whereNull('read_at')
                ->update(['read_at' => Carbon::now()]);     
            return Base::touser('ok', true);            
        } else {
            Notifications::where('notifiable_type', 'App\Models\User')
                    ->whereIn('notifiable_id', Base::getEmpBelongsUser($this->emp_id))
                    ->where('id', $id)
                    ->whereNull('read_at')
                    ->update(['read_at' => Carbon::now()]);
            return Base::touser('ok', true);
        }
    }
}
