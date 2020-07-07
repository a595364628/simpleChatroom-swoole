<?php
//服务器代码
//创建websocket 服务器
include_once "const.php";
include_once "config.php";
include_once "Db.php";
include_once "redis.php";
include_once "CustomerService.php";
include_once "StaffService.php";
include_once "helper.php";

$ws = new swoole_websocket_server("0.0.0.0",9502);


// open
$ws->on('open',function($ws,$request){
    $request = object_array($request);

    // TODO: Check if there are some message unreceived
//    checkUnreceivedMsg();

    //Customer's request
    if(isset($request['get']['me']) && !empty($request['get']['me'])) {
        $customerService = new CustomerService();
        $user = $customerService->addOrUpdateCustomer($request);
        $sid = $request['get']['sid'];
        //Push customer's detail to assigned customer service
        $redis = new RedisSet();
        $sfd = $redis->getValue('sid'.$sid);
        $ws->push($sfd,messageBody(CUSTOMER_JOIN,$user,null,0,$request['fd'],null));
        $ws->push($request['fd'],messageBody(CUSTOMER_JOIN,'ok',$user['id'],0,$request['fd'],null));
    }
    //Staff's request
    else if(isset($request['get']['token']) && !empty($request['get']['token'])) {
        $staffService = new StaffService();
        echo 'jinlaile';
        $user = $staffService->updateStaffInfo($request,$request['get']['sid']);
        $ws->push($request['fd'],messageBody(STAFF_JOIN,$user,null,0,$request['fd'],null));
    }

});

//message
$ws->on('message',function($ws,$request){
    if(!empty($request->data)) {
        $msg = json_decode($request->data,true);
        var_dump($msg);
        if(!empty($msg) && isset($msg['op']) && !empty($msg['op'])) {
            $redis = new RedisSet();
            $staffService = new StaffService();
            $customerService = new CustomerService();
            if($msg['op'] == STAFF_CHAT_MSG) {
                $ws->push($redis->getValue($msg['you']),json_encode($msg));
            }
            else if($msg['op'] == CUSTOMER_CHAT_MSG) {
//                var_dump($redis->getValue('sid'.$msg['you']));
                $ws->push($redis->getValue('sid'.$msg['you']),json_encode($msg));
            }
            else if($msg['op'] == STAFF_ONLINE || $msg['op'] == STAFF_BUSY || $msg['op'] == STAFF_OFFLINE) {
//                var_dump($msg);
                $staffService->updateCustomerStatus($msg['me'],ONLINE);
                //Staff's status need to push to every customer assigned to him
                //TODO: this pushing job needs to be done as an async job (using Coroutine in Swoole)
                $usrList = $customerService->getUserList(['staff_id'=>$msg['me']],'uuid');
                if(!empty($usrList)) {
                    foreach ($usrList as $k=>$v) {
                        $customerFds[] = $redis->getValue($v->uuid);
                    }
                    foreach ($customerFds as $k=>$v) {
                        $ws->push($v,json_encode($msg));
                    }
                }
            }



        }
    }

});
//close
$ws->on('close',function($ws,$request){
    echo "客户端-{$request} 断开连接\n";
    unset($GLOBALS['fd'][$request]);//清楚连接仓库
});
$ws->start();