<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Base;
class DemoController extends Controller
{


    public  function getAllUrlSeen(Request $request)
    {
       return Base::touser(User::where('user_id', $this->emp_id)->get(['demo_links'])[0],true);
    }


     public  function AddUrlToSeenList(Request $request)
    {
        $url = $request->input('data')['url'];

        $data = User::where('user_id', $this->emp_id)->first();

        $allurl =json_decode($data->demo_links,true);

        if($data->demo_links)
        {

             $allurl[] = $url;


        }
        else
        {
                  $allurl = array();
           $allurl[] = $url;
        }


        $data->demo_links = json_encode($allurl);

        $data->save();


  return Base::touser(User::where('user_id', $this->emp_id)->get(['demo_links'])[0],true);
    }

     public function getContentbyHash(Request $request)
    {


// user
// customer
// schedule
// report
// map
// travelmap
// mobileapp
// profile


  switch ($request->input('url')) {
    case 'admin.user':

       $title = 'Delivery';
      $body = '<p>
Welcome to Delivery Management Dashboard.
</p>


<p style="    font-size: 16px;
    line-height: 40px;">
 <b> Employee (Delivery Agent) : </b> <br/>

List view of all Employee. You shall edit or create a new one. Add Employee allows you to add a Employee along with the following details.

<br/>
<b> GPS Tracking : </b> <br/>

If you set It as ON, Employee can not turn off GPS Tracking from his app. <br/> It\'s always ON
If It\'s set as OFF, Employee has the option to turn ON or OFF the tracking from their device.
<br/>
It\'s good to remind that Employee can only access from mobile app ( iPhone and Android ).
<br/>
<b>Employee Status : </b> <br/> Employee can be activated or deactivated using Status drop down.
</p>


<h4>Left Menu provides you the following options  : </h4>

<h4>Employee:  </h4>
<p>
         Create and Edit your Employee (Delivery Agent) . Should be able to activate / deactivate them.
</p>
<h4>Customer:  </h4>
<p>
         Manage all your Customers location. Mention their address and name. Will be super easy to manage.
</p>

<h4>Delivery Schedule: </h4>
<p>
    Here you connect a Customers with a Driver. This schedule tasks for Delivery Agent for the day.
  </p>

<h4>Delivery Report:   </h4>
<p>
        Detailed view of what\'s going on in your Delivery business. Collective view of all.
</p>

<h4> GPS Tracking :</h4>
</p>Live Monitoring of your delivery agent and delivery status.


<h4>GPS - History: </h4>
<p>
        Map view of their travel history. Path route can be seen here.
</p>

<h4>Download App: </h4>
<p>
   Download our Android and iPhone App. Its cool to monitor.
</p>

<h4>My Account : </h4>
<p>
        Manages your profile and account.
</p>

<h4>Notifications: </h4>
<p>
        Right top corner will show you all notifications happening in your business.
</p>

';
      break;


//  case 'admin.customer':

//        $title = 'Customer Management';
//       $body = '


// <p style="    font-size: 16px;
//     line-height: 40px;">
//  <b> Employee (Delivery Person) : </b> <br/>

// List view of all Employee. You shall edit or create a new one. Add Employee allows you to add a Employee along with the following details.

// <br/>
// <b> GPS Tracking : </b> <br/>

// If you set It as ON, Employee can not turn off GPS Tracking from his app. <br/> It\'s always ON
// If It\'s set as OFF, Employee has the option to turn ON or OFF the tracking from their device.
// <br/>
// It\'s good to remind that Employee can only access from mobile app ( iPhone and Android ).
// <br/>
// <b>Employee Status : </b> <br/> Employee can be activated or deactivated using Status drop down.
// </p>';
//  break;




case 'admin.customer':

       $title = 'Customer Management';
      $body = '


<p style="    font-size: 16px;
    line-height: 40px;">
Create, edit and delete all your customers.

    </p>';
 break;
 case 'admin.customer-review':

       $title = 'Customer Review Management';
      $body = '


<p style="    font-size: 16px;
    line-height: 40px;">

Your can view the all reviews given by customers for your delivery service.

    </p>';
 break;


 case 'admin.schedule':

       $title = 'Trip Schedule';
      $body = '
<p style="    font-size: 16px;
    line-height: 40px;">
It connects your order along with a delivery agent.
</p>';
 break;

 case 'admin.map':

       $title = 'Live Tracking';
      $body = '
<p style="    font-size: 16px;
    line-height: 40px;">
Show the live status of your Delivery Agent and Delivery Status.
</p>';
 break;

 case 'admin.report':

       $title = 'Report';
      $body = '
<p style="    font-size: 16px;
    line-height: 40px;">
Detailed view of what\'s going on in your delivery business. Collective view of all.

</p>';
 break;



 case 'admin.profile':

       $title = 'My Account';
      $body = '
<p style="    font-size: 16px;
    line-height: 40px;">
Manages your profile and account.

</p>';
 break;






 case 'admin.mobileapp':

       $title = 'Download App';
      $body = '
<p style="    font-size: 16px;
    line-height: 40px;">
 Download our Android and iPhone App. Its cool to monitor !.



</p>';
 break;








    default:
       $title = 'Delivery';
      $body = '<h5> We are working on documentations for this Section</h1>';
      break;
  }





      return  '<md-dialog aria-label=Employee Management">
  <form ng-cloak>
    <md-toolbar>
      <div class="md-toolbar-tools">
        <h2>'.$title.'
        </h2>
        <span flex></span>
        <md-button class="md-icon-button" ng-click="cancel()">

        </md-button>
      </div>
    </md-toolbar>

    <md-dialog-content>
      <div class="md-dialog-content">




      '.$body.'

      </div>
    </md-dialog-content>

    <md-dialog-actions layout="row">
      <span flex>
      </span>
      <md-button ng-click="close()">
       Got it
      </md-button>
    </md-dialog-actions>
  </form>
</md-dialog>';
    }

}
