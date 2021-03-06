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
include_once "Ip2Region.php";

$config = require('config.php');
$ws = new swoole_websocket_server("0.0.0.0",$config['WS_PORT']);


// open
$ws->on('open',function($ws,$request){
    $request = object_array($request);

    // TODO: Check if there are some message unreceived
//    checkUnreceivedMsg();

    //Customer's request
    if(isset($request['get']['me']) && !empty($request['get']['me'])) {
        $customerService = new CustomerService();
        $data = $customerService->locateCustomer($request['server']['remote_addr']);
        if($data[2] != 0 && $data[2] != '0') $request['province'] = $data[2];
        else $request['province'] = null;
        $request['city'] = $data[3];
        $request['isp'] = $data[4];

        $user = $customerService->addOrUpdateCustomer($request);
        $customerGet['user_id'] = $user['id'];
        $customerGet['session_id'] = $user['session_id'];
        $sid = $request['get']['sid'];
        $customerService->incStaffActionNum($sid,'interviewing_customer_num');

        //Push customer's detail to assigned customer-service
        $redis = new RedisSet();
        $sfd = $redis->getValue('sid'.$sid);
        $ws->push($sfd,messageBody(CUSTOMER_JOIN,$user,$user['id'],0,$request['fd'],null));
        $ws->push($request['fd'],messageBody(CUSTOMER_JOIN,'ok',$customerGet,0,$request['fd'],null));
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
 * The mechanics of bilateral messaging confirmation is:
 * We analogize flag_id to ack, which is the confirmation code in the Tcp protocol.
 * Unlike the Tcp protocol's 3 handshakes process there are 4/5 handshakes here and they are:
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
                 *
                 * So it's a preference problem
                 * */
                if(isset($msg['you'][0])) {
                    $msg['you'] = $msg['you'][0];
                    $uuid = $customerService->getUser(['cid'=>$msg['you']],'uuid');
                    $msg['you'] = $uuid->uuid;
                }

                $ws->push($redis->getValue($msg['you']),json_encode($msg));
                //And find the right MF
                $customer = $customerService->getUser(['uuid'=>$msg['you']],'cid');
                if(!empty($customer)) $msg['you'] = $customer->cid;
//                else {
//                    //TODO : a log function is acquired
//                }
                $msgService->addMsg($msg,STAFF_MSG_TYPE);
            }
            else if($msg['op'] == CUSTOMER_CHAT_MSG) {
//                var_dump($redis->getValue('sid'.$msg['you']));
                //Check whether the User has been banned
                $isBaned = $customerService->getUser(['uuid'=>$msg['me']],'is_baned,cid,temp_name');
                if($isBaned->is_baned == 1) {
                    $msg['me'] = $isBaned->cid;
                    $msgService->addMsg($msg,CUSTOMER_MSG_TYPE);
                    $msg['cid'] = $isBaned->cid;
                    $msg['name'] = $isBaned->temp_name;
                    $customerService->incStaffActionNum($msg['you'],$msg['you'] . 'in_conversation_msg_num');
                    $ws->push($redis->getValue('sid'.$msg['you']),json_encode($msg));
                }
                else {
                    $ws->push($request->fd,messageBody(IS_BANED,null,null,null,null));
                }
            }
            else if($msg['op'] == WRITING_MSG || $msg['op'] == WRITING_MSG_END) {
                if(isset($msg['you'][0])) {
                    $msg['you'] = $msg['you'][0];
                    $uuid = $customerService->getUser(['cid'=>$msg['you']],'uuid');
                    $msg['you'] = $uuid->uuid;
                }
                $ws->push((int)$redis->getValue($msg['you']),json_encode($msg));
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
            else if($msg['op'] == STAFF_RECV) {
                $customer = $customerService->getUser(['cid'=>$msg['you'][0]],'uuid');
                $ws->push($redis->getValue($customer->uuid),json_encode($msg));
            }
            else if($msg['op'] == FAST_INVITE || $msg['op'] == FORCE_INVITE) {
                $uuid = $customerService->getUser(['cid'=>$msg['you']],'uuid');
                if(!empty($uuid)) {
                    $ifThisCustomerLocked = $customerService->getCustomerChatLock($msg['you']);
                    if($ifThisCustomerLocked)
                        $ws->push($redis->getValue('sid'.$msg['me']),messageBody(CUSTOMER_LOCKED,null,null,$msg['you'],$msg['me']));
                    else {
                        $customerService->setCustomerChatLock($msg['you'],$msg['me']);
                        $ws->push($redis->getValue($uuid->uuid),json_encode($msg));
                    }
                } else {
                    //TODO log
                    //log('can't find user id of ' . $msg['you']);
                }
            } else if($msg['op'] == ACCEPT_INVITE || $msg['op'] == DECLINE_INVITE) {
                $user = $customerService->getUser(['uuid'=>$msg['me']],'cid');
                if(!empty($user)) {
                    $msg['me'] = $user->cid;
                    if($msg['op'] == DECLINE_INVITE) $customerService->setCustomerChatLock($user->cid,$msg['you'],false);
                    $ws->push($redis->getValue('sid'.$msg['you']),json_encode($msg));
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
            $cid = findNum($leaver);
            $staff_id = $customerService->getUser(['cid'=>$cid],'staff_id,uuid');
            if(!empty($staff_id)) {
                $fd = $redis->getValue('sid'.$staff_id->staff_id);
                $redis->setValue($staff_id->uuid.'status',OFFLINE);
                $customerService->updateCustomer(['uuid'=>$staff_id->uuid],['status'=>OFFLINE]);
                $ws->push($fd,messageBody(CUSTOMER_OFFLINE,null,$cid,null,null));
            } else {
                //TODO : throw exception
            }
        }
        else if(explode('sid',$leaver)) {
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
            echo 'hai xing';
        }
    }
    else {
        //TODO: throw exception
    }
});
$ws->start();
