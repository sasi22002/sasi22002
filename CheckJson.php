<?php

namespace App\Http\Middleware;

use Closure;

use App\Http\Controllers\Base;

class CheckJson
{
    public function handle($request, Closure $next)
    {
        
    


/*
if(($_SERVER['REQUEST_METHOD'] == 'PUT' || $_SERVER['REQUEST_METHOD'] == 'POST'))
{

if($request->input('data') !== null && is_array($request->input('data')))
{

}
else
{

    abort(401);
}

}

*/



    /*  $role = Base::role();


      if ($role == Base::guest()) {

        abort(401);

        }
*/

return $next($request);
    }
}
