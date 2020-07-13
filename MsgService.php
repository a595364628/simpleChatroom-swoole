<?php

class msgService
{
    private $Db;
    public function __construct() {
        $this->Db = Mysql::getMysql("mf_chat");
    }

    public function addMsg($msg,$type) {
        $msg['create_time'] = date("Y-m-d m:i:s",time());
        $msg['msg_type'] = $msg['type'];
        $msg['type'] = $type;
        $id = $this->Db->add($msg);
        return $id;
    }

    public function updateMsg($msg,$where) {
        $msg['update_time'] = date("Y-m-d m:i:s",time());
        $this->Db->where($where)->update($msg);
    }
}