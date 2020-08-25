<?php


class CustomerService
{
    private $Db;
    private $Session;
    public function __construct() {
        $this->Db = Mysql::getMysql("mf_customer");
        $this->Session = Mysql::getMysql("mf_session");
    }

    public function getUserList($where,$column) {
        $data = $this->Db->where($where)->fields($column)->select();
        return $data;
    }


    public function getUser($where,$column) {
        $user = $this->Db->where($where)->fields($column)->find();
        return $user;
    }

    public function getUser2($where,$column) {
        $user = $this->Db->where($where)->column($column);
        return $user;
    }

    public function addOrUpdateCustomer($data) {
        //set the customer online
        $data['status'] = 1;

        /*Initialize the key-value set of Customer's uuid to Customer's requesting fd.
          The intention of doing this is that program can find user's requesting fd quickly ignoring
          the customer is in a Internet or Intranet
        */
        $redis = new RedisSet();
        $redis->setValue($data['get']['me'],$data['fd'],86400);
        $redis->setValue($data['get']['me'] . 'status', ONLINE,86400);
        $cid = $this->addCustomerInfo($data);
        $sessionId = $this->addDigitInfo($data,$cid);
        $data['id'] = $cid;
        $data['session_id'] = $sessionId;

        $redis->setValue('fd'.$data['fd'],'cid'.$cid,86400);
        return $data;
    }

    private function addDigitInfo($data,$cid) {
        $insert['customer_id'] = $cid;
        $insert['visit_start_time'] = date("Y-m-d H:i:s",$data['server']['request_time']);
        $insert['browser'] = json_encode($data['header'],true);
        if(!isset($data['get']['wap'])) $insert['operation'] = 3;
        else $insert['operation'] = 4;
        if(strpos($data['header']['accept-language'],'zh-CN')) $insert['language'] = '中文';
        $insert['staff_id'] = $data['get']['sid'];
        $insert['session_type'] = 2;
        $sessionId = $this->Session->add($insert);
        return $sessionId;
    }

    private function addCustomerInfo($data) {
        // Check if the customer already exist
        $exist = $this->Db->where(['uuid'=>$data['get']['me']])->find();
        $exist = object_array($exist);
        if(empty($exist)) {
            $add['uuid'] = $data['get']['me'];
            $add['ip'] = $data['server']['remote_addr'];
            $add['status'] = ONLINE;
            $add['login_time'] = date('Y-m-d H:i:s',time());
            $add['province'] = $data['province'];
            $add['city'] = $data['city'];
            $add['temp_name'] = $data['province'] . $data['city'] . '客户';
            $id = $this->Db->add($add);
        } else {
            $update['visit_time'] = $exist['visit_time'] + 1;
            $update['status'] = ONLINE;
            $update['login_time'] = date('Y-m-d H:i:s',time());
            $update['province'] = $data['province'];
            $update['city'] = $data['city'];
            $update['temp_name'] = $data['province'] . $data['city'] . '客户';
            $this->Db->where(['uuid'=>$data['get']['me']])->update($update);
            $id = $exist['cid'];
        }
        return $id;
    }

    public function updateCustomer($where,$data) {
        $this->Db->where($where)->update($data);
        return true;
    }

    /*
     * The status of customer services or staffs, is stored in redis
     * */
    public function updateCustomerInfo($token,$status) {
        $redis = new RedisSet();
        $value = $redis->getValue($token);
        $value['status'] = $status;
        $redis->setValue($token,$value);
        return true;
    }

    public function updateCustomerStatus($token,$status) {
        $redis = new RedisSet();
        $value = $redis->getValue($token);
        $value['status'] = $status;
        $redis->setValue($token,$value);
        return true;

    }

    //TODO: add customer's digit info into digit table
    private function addCustomerDigit($data,$id) {
        return true;
    }

    public function locateCustomer($ip) {
        $ipRegionObject = new Ip2Region('./ip2region.db');
        $data   = $ipRegionObject->{'btreeSearch'}($ip);
        $data = explode("|",$data['region']);
        return $data;
    }

    // The first chat with a customer will be locked, other customer service(staff) cannot invite this customer
    // param: action : true(lock the customer) false(unlock the customer)
    //TODO 这里的redis连接是有问题的，后期要改成swoole提供的redis连接池，php语言对连接池的支持不是很好，具体参看链接 https://www.jianshu.com/p/6085c6c16df4
    public function setCustomerChatLock($cid,$sid,$action = true,$time = 3600) {
        $redis = new RedisSet();
        if($action == true) $redis->setValue($cid . 'lockStaff',$sid,$time);
        else $redis->setValue($cid . 'lockStaff',false);
        return true;
    }

    public function getCustomerChatLock($cid) {
        $redis = new RedisSet();
        return $redis->getValue($cid . 'lockStaff');
    }

    // These action include inviting customers(num), msg(num), interviewing customers(num)
    // leaving customers(num), collect customers(num)
    // U can see all these action or num in chat page navigation bar(left side)
    public function getStaffAllActionNum($sid,$field = null,$type = 'all') {
        $redis = new RedisSet();
        if($type == 'all') {
            $data['interviewing_customer_num'] = $redis->getValue('interviewing_customer_num') ? $redis->getValue('interviewing_customer_num') : 0;
//            $data['inviting_customer_num'] = $redis->getValue($sid . 'inviting_customer_num') ? $redis->getValue($sid . 'inviting_customer_num') : 0;
            $data['in_conversation_msg_num'] = $redis->getValue($sid . 'in_conversation_msg_num') ? $redis->getValue($sid . 'in_conversation_msg_num') : 0;
//            $data['offline_customer_num'] = $redis->getValue('offline_customer_num') ? $redis->getValue('offline_customer_num') : 0;
//            $data['collect_customer_num'] = $redis->getValue($sid . 'collect_customer_num') ? $redis->getValue($sid . 'collect_customer_num') : 0;
            return $data;
        } else {
            $data = $redis->getValue($field) ? $redis->getValue($field) : 0;
            return $data;
        }
    }

    public function incStaffActionNum($sid,$field) {
        $redis = new RedisSet();
        $data = $this->getStaffAllActionNum($sid,$field,'single');
        return $redis->setValue($field,$data + 1);
    }


}