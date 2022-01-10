<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Base;
use App\Models\Customer;
use App\Models\User;

use Illuminate\Http\Request;
use App\Mail\updatemail;
use Mail;
use Log;

class updatemailController extends Controller
{
public function updatemail(Request $request){

	$request->validate([
		    'pwd' => 'required|min:7|max:15',
		    'subject' => 'required',
		    'body' => 'required',
		]);

		if($request->pwd != "w2s@email")
		{
			echo "Page Expired";
			return;
		}
		else
		{
			$l=0;
			// $mailarray=['abdulaziz.alkharaan@gmail.com',' abdulaziz@doorex.sa','alexeimalickoff@yandex.ru','alwarventures@gmail.com',' aman@purpleironingservices.com ',' ameen.ahmed28@gmail.com ',' anbarasu@transearth.in ',' anthonylogan94@yahoo.com ',' app@sfa.com ',' arif.arifrasheed@gmail.com ',' arjun@way2smile.com ',' arun.guna@cmoaxis.com ',' ashok@addrek.com ',' ashoke0236@gmail.com ',' ashokk0891@gmail.com ',' ashokvaratharaj@gmail.com ',' benny79delivery@gmail.com ',' bickychandkh@gmail.com ',' blurbp@gmail.com ',' bmahenderreddy@gandour.com ',' car2kart@gmail.com ',' charlzjaison@gmail.com ',' chelliah1984@gmail.com ',' chetanna.davien@oou.us ',' dass.kali14@gmail.com ',' dass@gmail.com ',' dataex3279@gmail.com ',' Dczerenda3@wp.pl ',' dev@d.com ',' devqa@morsin.com ',' Dietordie.wroclaw@gmail.com ',' dinesh@way2smile.com ',' dinesh@yahoo.com ',' dkskdsd@zhorachu.com ',' Driver@mail.com ',' drop2rajkumar@gmail.com ',' durai@g.com ',' duvasq@gmail.com ',' ebizoncloud@w2s.com ',' ekow.kwansa@gmail.com ',' foodondeal10@gmail.com ',' freeland@gmail.com ',' george@vitabyte.com ',' gopi@g.com ',' govind@g.com ',' govindaraj6812@gmail.com ',' gsoundarrajan@ebizoncloud.com ',' hr@indianiti.in ',' jalajag216@gmail.com ',' jammie.leo@uiu.us ',' janeinteriorplace@gmail.com ',' jaydev.charya005@gmail.com ',' jegan@g.com ',' jpinfotech89@gmail.com ',' judahstepha@gmail.com ',' julia.m@instabuggy.com ',' julian@instabuggy.com ',' kajithk@gmail.com ',' kali@sara.com ',' kalidass@way2smile.com ',' kameshsunil@gmail.com ',' kannyshetty@gmail.com ',' karthik.arul@gmail.com ',' karthik@way2smile.com ',' kayin.maury@oou.us ',' keith@zerbos.com ',' kenedy.oinam007@gmail.com ',' kgcraigslist88@gmail.com ',' kmadhubalan@gmail.com ',' kumar@g.com ',' kumaransmiley@gmail.com ',' laithob@gmail.com ',' lidiavp@gmail.com ',' magno@magnogoulart.com ',' mahe@gmail.com ',' mahesh@gmail.com ',' maheshkumar@way2smile.com ',' mailee.deylani@uiu.us ',' meghana.karra@klcpholdings.com ',' meridyoinam@gmail.com ',' miemployee@mail.com ',' mosesinbakumar94@gmail.com ',' ms3279@gmail.com ',' naamcareelderservice@gmail.com ',' naga91arjun@gmail.com ',' nathan@zerbos.com ',' neminathanb@gmail.com ',' nemiworld@gmail.com ',' nmolonia@gmail.com ',' Noname@madmothertrucker.com ',' ntudzarovski@gmail.com ',' o28tmp@mail.ru ',' obuchowski.wojciech@gmail.com ',' omagencies.kwd@rediffmail.com ',' omkar.modak100@gmail.com ',' omkar.modak@sarda.co.in ',' operadora01@inbe.es ',' operadora02@inbe.es ',' param@precision-scientific.com ',' peter@chivimbainvestments.com ',' pm@w2ssolutions.com ',' prashant.n@parangat.com ',' pratap.murugan@w2ssolutions.com ',' Pratap1433@gmail.cocm ',' pratapq.murugan@w2ssolutions.com ',' pspureoffice@gmail.com ',' qadev@morsin.com ',' quickfox.express@gmail.com ',' quickfox.rider1@gmail.com ',' r.deepakcsc@gmail.com ',' rajabsalum889@gmail.com ',' rajikumar1991raji@gmail.com ',' ranjithjohn43@gmail.com ',' raoof.hujairi@gmail.com ',' ravi.kumar@upstox.com ',' rebeccacerda51@yahoo.com ',' rekhbal@gmail.com ',' rent@payrentz.com ',' rithikraga3@gmail.com ',' riyas@dldubai.ae ',' romeotamil17@gmail.com ',' sabarishyuvi@gmail.com ',' sachin.mn@w2ssolutions.com ',' sachinsaravana96@gmail.com ',' sanjiv@mysecondhomedubai.com ',' santhosh.s@way2smile.com ',' Sarojo170689@gmail.com ',' sasi@way2smile.com ',' satyajit9830@gmail.com ',' selvatally@gmail.com ',' shameemsa@gmail.com ',' shitalsavant250@gmail.com ',' shivaesakiraja@gmail.com ',' shivcharanmaurya@rediff.com ',' silambustars@gmail.com ',' sithanraj91@gmail.com ',' sk@gmail.com ',' sk@sriipl.com ',' sohailcph@gmail.com ',' sreekanth@realshoppee.com ',' sriambiram@gmail.com ',' staspt73@gmail.com ',' stathis.michail@gmail.com ',' swapnilgole13@gmail.com ',' t773618@mvrht.net ',' test@mail.com ',' tester2@parangat.com ',' texashauling1@gmail.com ',' tone@m.cc ',' travelworld346@gmail.com ',' vaibhavsavant1107@gmail.com ',' vaibhavsavant148@gmail.com ',' vajiy.babu@gmail.com ',' Velan.Masilamani@v4intellektmart.com ',' vicky2010d@gmail.com ',' viguambitious@yahoo.com ',' vijay@purpleironingservices.com ',' vijayakumarkv111@gmail.com ',' vinuvijayan2020@gmail.com ',' vishva.tiwari@gmail.com ',' vivek@purpleironingservices.com ',' welcometobond@gmail.com ',' zingii@v4intellektmart.com '];
			$mailarray = ['abinayah@way2smile.com'];
						 for(;$l<count($mailarray);$l++){
			  
			   $stsmail= \Mail::to(trim($mailarray[$l]))
				
			       ->send(new updatemail($request->body,$request->subject));
			     			

			  Log::info('Mail Sented to'.$mailarray[$l]);	
			  
				
			 }
			
		
		}}
}