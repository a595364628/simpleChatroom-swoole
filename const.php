<?php


const SERVER_CONFIRM_A = 1000;

const STAFF_CHAT_MSG = 1001; //客服消息类型
const CUSTOMER_CHAT_MSG = 1002;
const GROUP_CHAT_MSG = 1003;    //群聊消息类型
const BROAD_CAST = 1004;        //广播类型（管理员通知）

const SERVER_CONFIRM = 1101;    //服务器确认收到类型
const CUSTOMER_SHAKE = 1200;     //

const CUSTOMER_JOIN = 1201;
const STAFF_ONLINE = 1202;
const STAFF_JOIN = 1202;
const STAFF_BUSY = 1203;
const STAFF_OFFLINE = 1204;
const CUSTOMER_ONLINE = 1205;
const CUSTOMER_OFFLINE = 1206;
const STAFF_RECV = 1300;


const WRITING_MSG = 2000;
const WRITING_MSG_END = 2001;


const ONLINE = 1;
const BUSY = 2;
const HIDE = 3;
const OFFLINE = 4;


// MSG STATUS
const MSG_NORMAL = 1;
const MSG_WITHDRAW = 2;
const MSG_DELETE = 3;

//MSG TYPE
const STAFF_MSG_TYPE = 2;
const CUSTOMER_MSG_TYPE = 1;

const HEART_BEAT_PING = 8888;         //HEART BEAT CLIENT PING TYPE
const HEART_BEAT_PONG = 9999;         //HEART BEAT SERVER RESPONSE TYPE

const IS_BANED = 7999;               // CUSTOMER BANED TYPE

const GOT_CHA = 2; //RECV_ED THE MSG

