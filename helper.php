<?php

//Construct the message body send to client
function messageBody($operation,$message,$params,$me,$you,$code = null) {
    $data['op'] = $operation;
    $data['message'] = $message;
    $data['param'] = $params;
    $data['me'] = $me;
    $data['you'] = $you;
    $data['code'] = $code;

    return json_encode($data,true);

//    if(is_array($message)) $message = json_encode($message,true);
//    if(empty($code)) return '{"op":'.$operation.',"args":'.$params.',"msg":'.$message.',"flagId":'.random_int(10000,99999).',"me":'.$me.',"you":'.$you.'}';
//    else return '{"op":'.$operation.',"args":'.$params.',"msg":'.$message.',"flagId":'.$code.',"me":'.$me.',"you":'.$you.'}';
}

//Deconstruct or decode the message of client
function deConsMessage($data) {
    json_decode($data,true);
}

//Convert object to array
function object_array($array) {
    if(is_object($array)) {
        $array = (array)$array;
    }
    if(is_array($array)) {
        foreach($array as $key=>$value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

//TODO: locate customer & staff
//TODO: add a city table
function location($ip) {
    return 1;
}
