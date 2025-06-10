<?php

use Illuminate\Http\Request;

interface IAuth{
    public function login(Request $request);

    public function register(Request $request);

    public function logout();

    public function refresh();
}
