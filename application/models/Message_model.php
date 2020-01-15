<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require __DIR__.'/../../vendor/autoload.php';
use Twilio\Rest\Client;

class Message_model extends CI_Model
{
    private function get_sid(){
        //ENVREPLACE
        return 'ACf5e00d2c0dfc4838a5b6ebfe2d9d1535';
    }
    private function get_token(){
        //ENVREPLACE
        return '9a149e176e49b018f95cb1d99a1aa5ed';
    }
    private function get_twilio_number(){
        //EVNREPLACE
        return '+16193136820';
    }
    private function get_max_attempt($type){
        switch($type){
            case 'password_reset':
                return 5;
            case 'verification':
                return 8;
        }
    }

    public function generate_verification_number(){
        $len = 6;
        $random_num = rand(0, pow(10, $len) -1);
        $padded_num = str_pad($random_num, $len, '0', STR_PAD_LEFT);
        return $padded_num;
    }

    public function verification_message($verification_code){
        $message    =  'Your Roladex verification code is:'.$verification_code;
        return $message;
    }
    public function password_reset_message($reset_code){
        $message    = 'Your Roladex password reset code is:'.$reset_code;
        return $message;
    }
    public function invite_to_join_message($name){
        $message    = $name.' would like to invite you to join Roladex, an app that allows to control your own contact info and let your friends alway have your most up-to-date contact information. Download the app on Google play (link) or Apple store (link)';
        return $message;
    }

    public function create_verification($user_id, $phone, &$error_msg=NULL){
        $this->load->model('User_model');
        $user   = $this->User_model->get_user_by_id($user_id);
        if ($this->User_model->check_user_verification($user)){
            $error_msg  = 'The user is already verified.';
            return FALSE;
        }
        //invalidate existing verification numbers
        $where  = array('user'  => $user_id,
                        'phone' => $phone);
        $res    = $this->db->get_where('verification_numbers', $where);
        if ($res->num_rows() > 2){
            $error_msg  = 'You\'ve made too many verification code requests. Sometimes it may take up to a minute before the code is sent. Please wait for the code or try again in a few hours.';
            return FALSE;
        }
        //create new verification number
        $data   = $where;
        $code   = $this->generate_verification_number();
        $data['code']   = $code;
        $this->db->insert('verification_numbers', $data);
        if ($this->db->insert_id()){
            return $this->send_verification_message($phone, $code);
        }
        return FALSE;
    }
    public function create_password_reset($user_id, &$error_msg){
        $this->load->model('User_model');
        $user   = $this->User_model->get_user_by_id($user_id);
        $phone  = $user->phone;
        $where  = array('user'  => $user_id);
        $res    = $this->db->get_where('reset_codes', $where);
        if ($res->num_rows() > 2){
            $error_msg  = 'You\'ve made too many password reset code requests. Sometimes it may take up to a minute before the code is sent. Please wait for the code or try again in a few hours.';
            return FALSE;
        }
        $code   = $this->generate_verification_number();
        $data   = array(
            'user'  => $user_id,
            'code'  => $code,
        );
        $this->db->insert('reset_codes', $data);
        if ($this->db->insert_id()){
            return $this->send_reset_code($phone, $code);
        }
        $error_msg  = 'An error has occurred';
        return FALSE;
    }
    public function create_invite_to_join($user, $invite_number, &$error_msg){
        $this->load->model('User_model');
        $user_name  = $user->given_name.' '.$user->family_name;
        $where  = array('inviter'  => $user->id,
                        'invitee_phone' => $invite_number);
        $this->db->where($where);
        if ($this->db->count_all_results('invitations') == 0){
            $this->db->insert('invitations', $where);
            if ($this->db->affected_rows() > 0){
                return $this->send_invite_to_join($invite_number, $user_name);
            }
        }
    }

    public function is_code_sent($user_id, $phone){
        $where  = array('user'  => $user_id,
                        'phone' => $phone,
                        'is_active' => 1);
        $this->db->where($where);   
        $code_sent = ($this->db->count_all_results('verification_numbers') > 0);
        return $code_sent;
    } 

    public function send_message($target, $code, $type){
        switch($type){
            case 'verification':
                $message    = $this->verification_message($code);
                break;
            case 'password_reset':
                $message    = $this->password_reset_message($code);
                break;
            case 'invite_to_join':
                $message    = $this->invite_to_join_message($code);
                break;
        }
        $sid        = $this->get_sid();
        $token      = $this->get_token();
        $twilio_num = $this->get_twilio_number();
        $client     = new Client($sid, $token);
       
        $target = '16193098258'; 
        $client->messages->create(
            $target,
            array(
                'from'  => $twilio_num,
                'body'  => $message
            )
        ); 
        return TRUE;
    }
    public function send_verification_message($target, $code){
        return $this->send_message($target, $code, 'verification');
    }
    public function send_reset_code($target, $code){
        return $this->send_message($target, $code, 'password_reset');
    }
    public function send_invite_to_join($target, $name){
        return $this->send_message($target, $name, 'invite_to_join');
    }
    
    public function verify_code($user_id, $phone, $code){
        $this->load->model('User_model');
        $user = $this->User_model->get_user_by_id($user_id);
        $max_attempts   = $this->get_max_attempt('verification');
        if ($user->verification_attempts > $max_attempts){
            return 'too_many_attempts';
        }
        $where  = array('user'  => $user_id,
                        'phone' => $phone,
                        'code'  => $code,
                        'is_active' => 1);
        $this->db->where($where);
        $status = NULL;
        if ($this->db->count_all_results('verification_numbers') > 0){
            $attempts_count = 0;
            $status = 'success';
        } else {
            $attempts_count = $user->verification_attempts + 1;
            $status = 'failed';
        }
        $this->User_model->reset_user_verification_attempts($user_id, $attempts_count);
        return $status;
    }
    public function verify_reset($user_id, $code){
        $this->load->model('User_model');
        $user   = $this->User_model->get_user_by_id($user_id);
        $max_attempts   = $this->get_max_attempt('password_reset');
        if ($user->password_reset_attempts > $max_attempts){
            return 'too_many_attempts';
        }
        $where  = array('user'  => $user_id,
                        'code'  => $code);
        $this->db->where($where);
        if ($this->db->count_all_results('reset_codes') > 0){
            return 'success';
        } else {
            $attempts_count = $user->password_reset_attempts + 1;
            $this->User_model->reset_user_password_reset_attempts($user_id, $attempts_count);
            return 'failed';
        }
    }

    public function reset_codes(){
        $this->reset_verification_codes();
        $this->reset_password_reset_codes();
    }

    public function reset_verification_codes(){
        $two_hours_ago  = date('Y-m-d H:i:s', time() - 7200);
        $where  = array('c_date <', $two_hours_ago);
        $this->db->where($where);
        $res    = $this->db->get('verification_numbers')->result();
        $involved_users = array();
        foreach($res as $record){
            $user   = $record->user;
            array_push($involved_users, $user);
        }
        $this->db->where($where);
        $this->db->delete('verification_numbers');
        $this->db->where_in('id', $involved_users);
        $this->db->update('users', array('verification_attempts' => 0));
    }

    public function reset_password_reset_codes(){
        $two_hours_ago  = date('Y-m-d H:i:s', time() - 7200);
        $where  = array('c_date <', $two_hours_ago);
        $this->db->where($where);
        $res    = $this->db->get('reset_codes')->result();
        $involved_users = array();
        foreach($res as $record){
            $user   = $record->user;
            array_push($involved_users, $user);
        }
        $this->db->where($where);
        $this->db->delete('reset_codes');
        $this->db->where_in('id', $involved_users);
        $this->db->update('users', array('password_reset_attempts' => 0));
    }
}
