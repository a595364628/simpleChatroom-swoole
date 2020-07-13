<?php

//Server end code
include_once "const.php";
include_once "config.php";
include_once "Db.php";
include_once "redis.php";
include_once "CustomerService.php";
include_once "StaffService.php";
include_once "MsgService.php";
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

/*
 * The mechanics of bilateral messaging confirmation is that:
 * We analogize a variable called flag_id to ack in the Tcp protocol, which is the confirmation code in that protocol.
 * Unlike the Tcp protocol's 3 handshakes there are 4/5 handshakes here and they are:
 * 1.Client A to server
 * 2.Server to client A
 * 3.Server to Client B
 * 4.Client B to server
 * 5.Server to Client A(inform Client A that Client B has already recv-ed the msg)
 *
 * Notice: Flag_id in the msg pack is an identifier intending to check the right client.
 * Once the first to fourth steps are fulfilled, update the chat msg's attribute(is_recv) to 2
 * */
$ws->on('message',function($ws,$request){
    if(!empty($request->data)) {
        $msg = json_decode($request->data,true);

        if(!empty($msg) && isset($msg['op']) && !empty($msg['op'])) {
//            var_dump($msg);
            $redis = new RedisSet();
            $staffService = new StaffService();
            $customerService = new CustomerService();
            $msgService = new MsgService();
            if($msg['op'] == STAFF_CHAT_MSG) {
                //Push to my Client that server already recv the msg
                $ws->push($redis->getValue('sid'.$msg['me']),json_encode([
                    'op'=>SERVER_CONFIRM,
                    'args'=>null,
                    'msg'=>'ok',
                    'flagId'=>$msg['flagId']],true));

                //Push to another Client
                /*
                 * U can insert this msg into the database first then send it to client B later,
                 * The good side of doing this is that u can precisely update the msg attribute(is_recv) using msg_id
                 * But this concluding a little receiving delay of client B
                 *
                 * Using this method is exactly the opposite: U get msg transport efficiency,
                 * but u sacrifice the accuracy. (As the flag_id is a random number being an identifier)
                 *
                 * So it's a preference problem
                 * */
                $ws->push($redis->getValue($msg['you']),json_encode($msg));
                //And find the right MF
                $customer = $customerService->getUser(['uuid'=>$msg['you']],'cid');
                if(!empty($customer)) $msg['you'] = $customer->cid;
                else {
                    echo 'hehe';
                    //TODO : a log function is acquired
                }
//                var_dump($msg);
                $msgService->addMsg($msg,STAFF_MSG_TYPE);
            }
            else if($msg['op'] == CUSTOMER_CHAT_MSG) {
//                var_dump($redis->getValue('sid'.$msg['you']));
                $ws->push($redis->getValue('sid'.$msg['you']),json_encode($msg));
                $msgService->addMsg($msg,CUSTOMER_MSG_TYPE);
            }
            else if($msg['op'] == WRITING_MSG || $msg['op'] == WRITING_MSG_END) {
                $ws->push($redis->getValue($msg['you']),json_encode($msg));
            }
            else if($msg['op'] == CUSTOMER_SHAKE) {
                $customer = $customerService->getUser(['uuid'=>$msg['you']],'cid');
                $msgService->updateMsg(['is_recv'=>2],['flagId'=>$msg['msg'],'me'=>$msg['me'],'you'=>$customer->cid]);
                $ws->push($redis->getValue('sid'.$msg['me']),json_encode($msg));
            }
            else if($msg['op'] == STAFF_ONLINE || $msg['op'] == STAFF_BUSY || $msg['op'] == STAFF_OFFLINE) {
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
            else if($msg['op'] == HEART_BEAT_PING) {
                if($msg['args'] == STAFF_CHAT_MSG) {
                    $ws->push($redis->getValue('sid'.$msg['me']),messageBody(HEART_BEAT_PONG,'PONG',null,null,null));
                } else if($msg['args'] == CUSTOMER_CHAT_MSG) {
                    $ws->push($redis->getValue($msg['me']),messageBody(HEART_BEAT_PONG,'PONG',null,null,null));
                }
            }

        }
    }

});
//close
$ws->on('close',function($ws,$request){
    $redis = new RedisSet();
    $customerService = new CustomerService();
    $staffService = new StaffService();
    $leaver = $redis->getValue('fd'.$request);
    //If the customer closed the session, then push his leaving msg to his customer-service
    if(!empty($leaver)) {
        if(explode('cid',$leaver)) {
            $cid = substr($leaver,-1);
            $staff_id = $customerService->getUser(['cid'=>$cid],'staff_id,uuid');
            if(!empty($staff_id)) {
                $fd = $redis->getValue('sid'.$staff_id->staff_id);
                $redis->setValue($staff_id->uuid.'status',OFFLINE);
                $ws->push($fd,messageBody(CUSTOMER_OFFLINE,null,$cid,null,null));
            } else {
                //TODO : throw exception
            }
        }
        else if(explode('sid',$leaver)) {
            echo 'djaiwoma';
            print_r($leaver);
            $sid = substr($leaver,-1);
            $allHisCustomer = $customerService->getUserList(['staff_id'=>$sid,'is_baned'=>1],'uuid');
            if(!empty($allHisCustomer)) {
                $allHisOnlineCustomer = [];
                foreach($allHisCustomer as $k=>$v) {
                    $status = $redis->getValue($v->uuid . 'status');
                    if($status == ONLINE) {
                        $allHisOnlineCustomer[] = $v->uuid;
                    }
                }
                if(!empty($allHisOnlineCustomer)) {
                    foreach($allHisOnlineCustomer as $kk=>$vv) {
                        $fd = $redis->getValue($vv);
                        $ws->push($fd,messageBody(STAFF_OFFLINE,null,$sid,null,null));
                    }
                }
            }
        }else {
            echo 'magedan';
        }

    }
    else {
        //TODO: throw exception
    }

});
$ws->start();