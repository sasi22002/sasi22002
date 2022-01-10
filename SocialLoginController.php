<?php

namespace App\Http\Controllers;
use Socialite;

use Illuminate\Http\Request;
use Facedes\App\Controller\Base;
class SocialLoginController extends Controller
{
    
    	/**
     * Redirect the user to the GitHub authentication page.
     *
     * @return Response
     */
    public function redirectToProvider()
    {
    	\Session::put('domain', 'api');

        return Socialite::driver('github')->redirect('/');
    }

    /**
     * Obtain the user information from GitHub.
     *
     * @return Response
     */
    public function handleProviderCallback()
    {
    	$user = Socialite::driver('github')->stateless()->user();

    	echo \Session::get('domain');
    	
    	\Session::forget('domain');
		return $user->getEmail();
    }



}
