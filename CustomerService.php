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

//        $redis->getValue($data['get']['me']);

        $cid = $this->addCustomerInfo($data);
        $this->addDigitInfo($data,$cid);
        $data['id'] = $cid;

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
        $this->Session->add($insert);
        return true;
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


}