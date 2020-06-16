<?php
//服务器代码
//创建websocket 服务器
$ws = new swoole_websocket_server("0.0.0.0",9502);
// open
$ws->on('open',function($ws,$request){
    echo "新用户 $request->fd 加入。\n";
    $GLOBALS['fd'][$request->fd]['id'] = $request->fd;//设置用户id
    $GLOBALS['fd'][$request->fd]['name'] = '匿名用户';//设置用户名

    $myfile = fopen("users.txt", "a+") or die("Unable to open file!");
    $txt = "$request->fd" . "*";
    fwrite($myfile, $txt);
    fclose($myfile);


//    var_dump($GLOBALS['fd']);
//    $ws->push($request->fd, "hello, welcome\n");
});

//message
$ws->on('message',function($ws,$request){
    $msg = $GLOBALS['fd'][$request->fd]['name'].":".$request->data."\n";
    if(strstr($request->data,"#name#")){//用户设置昵称
        $GLOBALS['fd'][$request->fd]['name'] = str_replace("#name#",'',$request->data);

    }else{//进行用户信息发送
        //发送每一个客户端
//        var_dump($GLOBALS['fd']);

        $myfile = fopen("users.txt", "r") or die("Unable to open file!");

        $users = explode('*',fread($myfile,filesize("users.txt")));

        foreach($users as $i){
            $ws->push(intval($i),$msg);
        }
    }
});
//close
$ws->on('close',function($ws,$request){
    echo "客户端-{$request} 断开连接\n";
    unset($GLOBALS['fd'][$request]);//清楚连接仓库
});
$ws->start();