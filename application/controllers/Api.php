<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller
{
    public function __construct(){
        parent::__construct();
        $this->load->model('User_model');
    }
    public function hello_world(){
        $json   = array();
        $msg =  $this->User_model->hello_world();
        $json['msg']    = $msg;
        header("Content-Type: application/json");
        $content = json_encode($json);
        echo $content;
    }
    public function hi_world(){
        echo 'hi what!';
    }

    public function register_user(){
        $this->load->model('Auth_model');
        $json   = array();
        $userdata = json_decode(file_get_contents('php://input'));
        $status = '';
        $user_id = $this->User_model->create_user($userdata, $status);
        $json['status'] = $status;
        if(!is_null($user_id)){
            $json['user_id'] = $user_id;
            $token = $this->Auth_model->authenticate_user($userdata->phoneNumber, $userdata->password);
            $json['userToken'] = $token;
        }
        $content = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }

    public function send_verification_code(){
        $json   = array();
        $userdata   = json_decode(file_get_contents('php://input'));
        $user_id    = $userdata->userId;
        $phone_number   = $userdata->phoneNumber;
        $status = $this->User_model->send_verification_code($user_id, $phone_number);
        $json['status'] = $status;
        $content    = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }

    public function check_user_verification(){
        $json = array();
        $userdata   = json_decode(file_get_contents('php://input'));
        $userToken  = $userdata->userToken;
        $user       = $this->User_model->get_user_by_token($userToken);
        $is_verified = $this->User_model->check_user_verification($user);
        $json['is_verified'] = $is_verified;
        if (!$is_verified){
            $json['user_number'] = $user->phone;
            $json['user_id']    = $user->id;
        }
        $content    = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }

    public function verify_user(){
        $json = array();
        $verification_data   = json_decode(file_get_contents('php://input'));
        $userToken  = $verification_data->userToken;
        $verification_code = $verification_data->verification_code;
        $user   = $this->User_model->get_user_by_token($userToken);
        $is_verified    = $this->User_model->verify_user($user);
        $json['is_verified'] = $is_verified;
        $content    = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }

    public function login(){
        $this->load->model('Auth_model');
        $json = array();
        $logindata = json_decode(file_get_contents('php://input'));
        if (empty($logindata)){
            $phone = 666;
            $pwd    = 'aaa';
        } else {
            $phone  = $logindata->login;
            $pwd    = $logindata->password;
        }
        
        $token = $this->Auth_model->authenticate_user($phone, $pwd);

        if ($token === FALSE){
            $json['status'] = 'error';
        } else {
            $json['status'] = 'success';
            $json['userToken'] = $token;
        }
        $content = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }

    public function update_profile(){
        $profile_data   = json_decode(file_get_contents('php://input'));
        $this->User_model->update_profile($userToken, $profile_data);
    }

    public function get_contacts(){
        $input_data = json_decode(file_get_contents('php://input'));
        $userToken  = $input_data->userToken;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
            $json['status'] = 'error';
        } else {
            $contacts_data = $input_data->contacts;
            list($reg, $rol, $fri) = $this->User_model->filter_contacts($user, $contacts_data);
            $json['regular'] = $reg;
            $json['roladex'] = $rol;
            $json['friends'] = $fri;
            $json['status']  = 'success';
        }
        $content = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }
    //Friends
    public function add_friend(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $json       = array();
        $userToken  = $post_data->userToken;
        $friendPhone    = $post_data->friendPhone;
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $friend_id  = $this->User_model->get_user_by_number($friendPhone)->id;
        $this->User_model->send_friend_request($user_id, $friend_id);
        $json['status'] = 'pending';
        if ($this->User_model->does_user_auto_accept($friend_id)){
            $this->User_model->accept_friend_request($friend_id, $user_id);
            $json['status'] = 'accepted';
        }
        $content = json_encode($json);
        header("Content-Type: application/json");
        echo $content;
    }   
    public function accept_friend($userToken, $friend_id){
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $this->User_model->accept_friend_request($user_id, $friend_id);
    }
    public function remove_friend($userToken, $friendPhone){
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $friend_id  = $this->User_model->get_user_by_number($friendPhone)->id;
        $this->User_model->remove_friend($user_id, $friend_id);
    }

//TEST
    public function make_friend_users(){
        $data   = (object)array(
                            'phoneNumber' => '7149439053',
                            'fullName' => 'Dude',
                            'password' => 'pword'
                        );
        $status = '';
        $this->User_model->create_user($data, $status);
        $data   = (object)array(
                            'phoneNumber' => '7075366633',
                            'fullName' => 'Stephanie',
                            'password' => 'pword'
                        );
        $status = '';
        $this->User_model->create_user($data, $status);
        $data   = (object)array(
                            'phoneNumber' => '9518160803',
                            'fullName' => 'Jane',
                            'password' => 'pword'
                        );
        $status = '';
        $this->User_model->create_user($data, $status);
    }

    public function test_create_user($number){
        $data   = (object)array(
                            'email' => 'testtest',
                            'phoneNumber' => $number,
                            'address' => 'TEST TEST',
                            'fullName' => 'Pong',
                            'password' => 'big cheese'
                        );
        $status = '';
        $this->User_model->create_user($data, $status);
    }
    public function test_update_profile($user_id){
        $this->load->model('Auth_model');
        $token  = $this->Auth_model->create_jwt($user_id);
        $profile_data = (object)array(
                            'email' => 'blueblue',
                            'address' => 'TESTTESTTEST'
                        );
        print_r($profile_data);
        $this->User_model->update_profile($token, $profile_data);
    }
    public function test_add_friend($user_id, $friend_num){
        $this->load->model('Auth_model');
        $token  = $this->Auth_model->create_jwt($user_id);
        $this->add_friend($token, $friend_num);
    }
    public function test_accept_friend($user_id, $friend_id){
        $this->load->model('Auth_model');
        $token  = $this->Auth_model->create_jwt($user_id);
        $this->accept_friend($token, $friend_id);
    }

}
