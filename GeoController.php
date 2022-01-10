<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use League\Geotools\Coordinate\Ellipsoid;

use League\Geotools\Coordinate\Coordinate;

use Toin0u\Geotools\Facade\Geotools;

use App\Http\Controllers\Base;

use Validator;

use App\Models\User;

class GeoController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = array();


        $userdata = array(
            'email'     => 'kalidass@gmail.com',
            'password'  => 'admin'
        );

        // attempt to do the login
        if (User::attempt($userdata)) {

            // validation successful!
            // redirect them to the secure section or whatever
            // return Redirect::to('secure');
            // for now we'll just echo success (even though echoing in a controller is bad)
            echo 'SUCCESS!';
        } else {

            // validation not successful, send back to form
            echo 'login';
        }

/*
        $core = new Base();

        $core->appcall(true);

        print_r(Base::role($request->input('auth')));

        $geotools   = new \League\Geotools\Geotools();
        $coordinate = new \League\Geotools\Coordinate\Coordinate('40.446195, -79.948862');
        $converted  = $geotools->convert($coordinate);
// convert to decimal degrees without and with format string
        printf("%s\n", $converted->toDecimalMinutes()); // 40 26.7717N, -79 56.93172W
        printf("%s\n", $converted->toDM('%P%D°%N %p%d°%n')); // 40°26.7717 -79°56.93172
// convert to degrees minutes seconds without and with format string
        printf("%s\n", $converted->toDegreesMinutesSeconds('<p>%P%D:%M:%S, %p%d:%m:%s</p>')); // <p>40:26:46, -79:56:56</p>
        printf("%s\n", $converted->toDMS()); // 40°26′46″N, 79°56′56″W
// convert in the UTM projection (standard format)
        printf("%s\n", $converted->toUniversalTransverseMercator()); // 17T 589138 4477813
        printf("%s\n", $converted->toUTM()); // 17T 589138 4477813 (alias)*/
    }
}
