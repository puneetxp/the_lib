<?php

namespace App\TheDep;

/**
 * Description of Response with JSON and with HTTP response code
 *
 * @author puneetxp
 */
class Response
{

    public static function json($data = '')
    {
        header('Content-Type: application/json; charset=utf-8');

        return json_encode($data);
    }
    public static function not_found($data = '')
    {

        http_response_code(404);
        return json_encode($data);
    }

    public static function not_authorised($data = '')
    {

        http_response_code(403);
        return json_encode($data);
    }

    public static function unprocessable($data = '')
    {

        http_response_code(422);
        return json_encode($data);
    }

    public static function bad_req($data = '')
    {

        http_response_code(400);
        return json_encode($data);
    }

    public static function why($data = ["erro" => "your should not be here"])
    {

        return json_encode($data);
    }
}
