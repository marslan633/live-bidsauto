<?php


function sendResponse($status, $status_code, $message, $data, $code){
    return response()->json([
        'status'   => $status,
        'status_code'   => $status_code,
        'message'   => $message,
        'data'      => $data,
    ], $code);
}