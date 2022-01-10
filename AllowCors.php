<?php

namespace App\Http\Middleware;

use Closure;

class AllowCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function handle($request, Closure $next)
    {
        $input = $request->all();



        if ($input) {
            array_walk_recursive($input, function (&$item) {
                $item = trim($item);
                $item = ($item == "") ? null : $item;
            });
            $request->merge($input);
        }





        // if (function_exists('header_remove')) {
        //     header_remove('X-Powered-By');
        // } else {
        //     @ini_set('expose_php', 'off');
        // }



/*
        header("Access-Control-Allow-Origin: *");
        header("x-content-type-options: nosniff");
        header("x-frame-options: SAMEORIGIN");
        header("x-xss-protection: 1; mode=block");
        header('P3P:CP="We does not have a P3P policy."');*/


        // $headers = [
        //     'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE,PATCH',
        //     'Access-Control-Allow-Headers' => 'X-Requested-With, X-Auth-Token, Origin, Authorization',
        //    'Access-Control-Allow-Headers' => 'Authorization,x-client-data',
        // ];

        // if ($request->getMethod() == "OPTIONS") {
        //     return \Response::make('OK', 200, $headers);
        // }

         $response = $next($request);

        // foreach ($headers as $key => $value) {
        //     $response->header($key, $value);
        // }



        return $response;
    }
}
