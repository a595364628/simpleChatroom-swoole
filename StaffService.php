<?php

//include_once "mysql.php";

class StaffService
{
//    public function getStaff($id = null) {
//        if(empty($id)) {
//            return null;
//        } else {
//
//        }
//
//    }

    public function updateStaffInfo($data,$sid) {
        $data['status'] = 1;

        /*Initialize the key-value set of Customer's uuid to Customer's requesting fd.
          The intention of doing this is that programmers can find user's requesting fd quickly ignoring
          the customer is in a Internet or Intranet
        */
        $redis = new RedisSet();
        $redis->setValue('sid' . $sid,$data['fd']);

        $cid = $this->addStaffInfo($data);
        $this->addDigitInfo($data,$cid);
        $data['id'] = $cid;

        return $data;
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

    public function updateCustomerStatus($sid,$status) {
        $redis = new RedisSet();
        $redis->setValue('sid'.$sid.'status',$status);
        return true;
    }


    public function addStaffInfo($data) {
        return 'shit';
    }

    public function addDigitInfo($data,$cid) {
        return 'fuck';
    }

}