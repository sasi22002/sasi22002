<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Base;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        $this->role_info = Base::getRole();
        $this->admin = false;
        $this->backend = false;
        $this->manager = false;
        $this->role = Base::guest();
        $this->emp_id = null;
                
        if (is_array($this->role_info)) {
            $this->emp_id = $this->role_info[1];
            $this->role = $this->role_info[0];

            if ($this->role  == Base::super_admin()) {
                $this->admin = true;
            }

            if ($this->role  == Base::manager()) {
                $this->manager = true;
            }

            if ($this->role  == Base::backendadmin()) {
                $this->backend = true;
            }
        }
    }
}