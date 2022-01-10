<?php

namespace App\Http\Middleware;

use Closure;
use  App\Http\Controllers\Base;

class CheckDomain
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
        if (!Base::is_app_domain()) {
            Base::set_database_config(Base::get_domin());
        } else {
            //Base::super_admin_db();
        }


        return $next($request);
    }
}
