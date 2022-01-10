<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Notifications\CompanyWelcome;
use App\Notifications\EmpPasswordReset;
use App\Notifications\Sendotp;
use App\Notifications\EmpWelcome;
use Illuminate\Notifications\Notification;

use App\Models\Master;
use App\Models\SuperAdmin;
use App\Notifications\TaskCompleted;


class NotificationsController extends Controller
{




    public function __construct()
    {

	}

	// public static function GeoFence($user,$data)
	// {

 //  	$user->notify(new GeoFence($data));

	// }

	public static function resetPassword($user)
	{

  	$user->notify(new EmpPasswordReset($user));

     event(new \App\Events\NotificationEvent($user));

	}


  public static function send_otp_to_email($user,$otp)
  {

    $user->notify(new Sendotp($user,$otp));

     event(new \App\Events\NotificationEvent($user));

  }

    public static function TaskCompleted($trip)
    {

    $user->notify(new TaskCompleted($trip));



    }

    public static function TrailPeriod($trip)
    {

    $user->notify(new TrailPeriod($trip));

    }



    public static function TaskAllocated($trip)
    {

    $user->notify(new TaskAllocated($trip));

    }

	public static function WelcomeEmp($user)
	{

  	$user->notify(new EmpWelcome($user));

     event(new \App\Events\NotificationEvent($user));

	}



	public static function triggers()
	{


	print_r(date('m/d/Y h:i:s a', time()));
	$data = new SuperAdmin();

	$data = $data->first();

	print_r($data);

  	$data->notify(new CompanyWelcome($data));

	}
}