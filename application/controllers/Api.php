<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require __DIR__.'/../../vendor/autoload.php';
use Twilio\Rest\Client;

class Api extends CI_Controller
{
    public function __construct(){
        parent::__construct();
        $this->data = json_decode(file_get_contents('php://input')); 
        $this->json = array('status' => 'error');       
        $this->load->model('User_model');
        $this->load->model('Template_model');
        $this->load->helper('normalizers');
    }
    public function hello_world(){
        $input  = $this->input->post();
        $json   = array();
        $msg    = 'hiasdfhsdiaf';
        $json['msg']    = $msg;
        $json['input']  = $input;
        $this->Template_model->add_json($json);
    }
    public function hi_world(){
        echo 'hi what!';
    }
    public function echo_test(){
        $test_data = json_decode(file_get_contents('php://input'));
        $data   = array('value' => $test_data->test);
        $this->db->insert('test_data', $data);
    }
    public function message(){
        $account_sid = 'ACf5e00d2c0dfc4838a5b6ebfe2d9d1535';
        $auth_token = '9a149e176e49b018f95cb1d99a1aa5ed';
        // In production, these should be environment variables. E.g.:
        // $auth_token = $_ENV["TWILIO_ACCOUNT_SID"]

        // A Twilio number you own with SMS capabilities
        $twilio_number = "+16193136820";

        $client = new Client($account_sid, $auth_token);
        $client->messages->create(
            // Where to send a text message (your cell phone?)
            '+16193098258',
            array(
                'from' => $twilio_number,
                'body' => 'I sent this message in under 10 minutes!'
            )
        );
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
        $this->Template_model->add_json($json);
    }

    public function delete_account(){
        $userToken  = $this->data->userToken;
        $deleted   = $this->User_model->delete_user($userToken);
        if ($deleted){
            $this->json['status']   = 'success';
        }
        $this->Template_model->add_json();
    }

    public function remove_info(){
        $data = json_decode(file_get_contents('php://input'));
        $userToken = $data->userToken;
        $json = array();
        $new_info = $this->User_model->remove_info($userToken, $data->type, $data->label, $data->value);
        if ($new_info !== FALSE){
            $json['status'] = 'success';
            $json['new_info'] = $new_info;
        } else {
            $json['status'] = 'error';
        }
        $this->Template_model->add_json($json);
    }

    public function update_info(){
        $user_data = json_decode(file_get_contents('php://input'));
        $userToken = $user_data->userToken;
        $json = array();
        if ($this->User_model->update_info($userToken, $user_data)){
            $json['status'] = 'success';
        } else {
            $json['status'] = 'error';
        }
        $this->Template_model->add_json($json);
    }

    public function update_info_field(){
        $user_data = json_decode(file_get_contents('php://input'));
        $userToken = $user_data->userToken;
        $json = array();
        if ($this->User_model->update_info_field($userToken, $user_data)){
            $json['status'] = 'success';
        } else {
            $json['status'] = 'error';
        }
        $this->Template_model->add_json($json);
    }

    public function is_verification_code_sent(){
        $this->load->model('Message_model');
        $json   = array();
        $userdata   = json_decode(file_get_contents('php://input'));
        $user_id    = $userdata->userId;
        $user       = $this->User_model->get_user_by_id($user_id);
        $phone_number   = $user->phone;
        $codeSent   = $this->Message_model->is_code_sent($user_id, $phone_number);
        $json['codeSent'] = $codeSent;
        $this->Template_model->add_json($json);
    }
   public function send_verification_code(){
        $this->load->model('Message_model');
        $json   = array();
        $error_msg = NULL;
        $userdata   = json_decode(file_get_contents('php://input'));
        $user_id  = $userdata->userId;
        $user       = $this->User_model->get_user_by_id($user_id);
        $phone_number   = $user->phone;
        $status = $this->Message_model->create_verification($user_id, $phone_number, $error_msg);
        if ($status == TRUE){
            $json['status'] = 'success';
        } else{
            $json['status'] = 'error';
            $json['error_msg']  = $error_msg;
        }
        $this->Template_model->add_json($json);
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
        $this->Template_model->add_json($json);
    }

    public function verify_user(){
        $json = array();
        $verification_data   = json_decode(file_get_contents('php://input'));
        $user_id  = $verification_data->userId;
        $user   = $this->User_model->get_user_by_id($user_id);
        $verification_code = $verification_data->verificationCode;
         
        $status    = $this->User_model->verify_user($user, $verification_code);
        $json['verification_status'] = $status;
        $this->Template_model->add_json($json);
    }

    public function send_reset_code(){
        $this->load->model('Message_model');
        $json   = array();
        $error_msg = NULL;
        $userdata   = json_decode(file_get_contents('php://input'));
        $phone   = normalize_number($userdata->phone);
        $user       = $this->User_model->get_user_by_number($phone);
        if (empty($user)){
            $json['status'] = 'error';
            $json['error_msg']  = 'User not found';
        } else {
            $status = $this->Message_model->create_password_reset($user->id, $error_msg);
            if ($status == TRUE){
                $json['status'] = 'success';
            } else{
                $json['status'] = 'error';
                $json['error_msg']  = $error_msg;
            }
        }
        $this->Template_model->add_json($json);
    }
    public function verify_reset_code(){
        $this->load->model('Message_model');
        $json       = array();
        $userdata   = json_decode(file_get_contents('php://input'));
        $phone      = normalize_number($userdata->phone);
        $user       = $this->User_model->get_user_by_number($phone);
        if (empty($user)){
            $json['status'] = 'error';
            $json['error_msg']  = 'User not found';
        } else {
            $reset_code = $userdata->resetCode;
            $status     = $this->Message_model->verify_reset($user->id, $reset_code);
            $json['verification_status']    = $status;
        }
        $this->Template_model->add_json($json);
    }
    public function reset_password(){
        $json       = array();
        $userdata   = json_decode(file_get_contents('php://input'));
        $phone      = normalize_number($userdata->phone);
        $user   = $this->User_model->get_user_by_number($phone);
        if (empty($user)){
            $json['status'] = 'error';
            $json['error_msg'] = 'No user can be found with the associated number. Please restart the password recovery process';
        } else {
            $resetCode  = $userdata->resetCode;
            $this->load->model('Message_model');
            $status = $this->Message_model->verify_reset($user->id, $resetCode);
            if ($status != 'success'){
                $json['status'] = 'error';
                $json['error_msg']  = 'The reset timeframe has passed. Please restart the password recovery process';
            } else {
                $password   = $userdata->password;
                $confirm    = $userdata->passwordConfirm;
                if ($password != $confirm){
                    $json['status'] = 'error';
                    $json['error_msg']  = 'Password and password confirm don\'t match.';
                } else {
                    $this->User_model->reset_password($user, $password);
                    $json['status'] = 'success';
                }
            }
        }
        $this->Template_model->add_json($json);
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
        $this->Template_model->add_json($json);
    }

    public function profile(){
        $user_data = json_decode(file_get_contents('php://input'));
        $userToken = $user_data->userToken;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
           $json['status'] = 'error';
        } else {
            $this->User_model->parse_user_info($user);
            $json['profile'] = $user;
        }
        $this->Template_model->add_json($json);
    }

    public function update_playerid(){
        $data   = json_decode(file_get_contents('php://input'));
        $user_token = $data->userToken;
        $playerID = $data->playerID;
        $status = $this->User_model->update_user_playerid($user_token, $playerID);
        if($status){
            $json = array('status' => 'success');
        } else {
            $json = array('status' => 'error');
        }
        $this->Template_model->add_json($json);
    }

    public function load_contact(){
        $input_data = json_decode(file_get_contents('php://input'));
        $userToken  = $input_data->userToken;
        $contact_id = $input_data->contact;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
            $json['status'] = 'error';
        } else {
            $contact = $this->User_model->get_user_by_id($contact_id);
            $this->User_model->extend_contact_for_display($contact, $user->id);
            $json['contact'] = $contact;
            $json['status']  = 'success';
        }
        $this->Template_model->add_json($json);
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
            list($reg, $rol, $fri, $updates) = $this->User_model->filter_contacts($user, $contacts_data);
            $json['regular'] = $reg;
            $json['roladex'] = $rol;
            $json['friends'] = $fri;
            $json['updates'] = $updates;
            $json['status']  = 'success';
        }
        $this->Template_model->add_json($json);
    }
    public function update_contacts(){
        $input_data = json_decode(file_get_contents('php://input'));
        $userToken  = $input_data->userToken;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
            $json['status'] = 'error';
        } else {
            $contacts_data  = $input_data->contacts;
            $updates    = $this->User_model->update_contacts($user, $contacts_data);
            $json['updates']    = $updates;
        }
        $this->Template_model->add_json($json);
    }

    public function get_updates(){
        $input_data = json_decode(file_get_contents('php://input'));
        $userToken  = $input_data->userToken;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
            $json['status'] = 'error';
        } else {
            $config = array('order_by' => 'c_date', 'order' => 'DESC', 'limit' => 5);
            $updates = $this->User_model->get_user_contact_updates($user->id, $config);
            $processed_updates = $this->User_model->process_updates_for_display($updates, $user->id);
            $json['updates'] = $processed_updates;
        }
        $this->Template_model->add_json($json);
    }
    
    public function get_requests(){
        $input_data = json_decode(file_get_contents('php://input'));
        $userToken  = $input_data->userToken;
        $user = $this->User_model->get_user_by_token($userToken);
        $json = array();
        if (is_null($user)){
            $json['status'] = 'error';
        } else {
            $config = array('order_by' => 'last_updated', 'order' => 'desc', 'user' => $user->id, 'user' => $user->id);
            $requests = $this->User_model->get_follow_requests($config);
            foreach($requests as $request){
                $this->User_model->extend_request_with_userinfo($request);
            }
            $json['requests'] = $requests;
        }
        $this->Template_model->add_json($json);
    }

    //Invite
    public function invite_friend(){
        $this->load->model('Message_model');
        /*
        $post_data  = json_decode(file_get_contents('php://input'));
        $json       = array();
        $userToken  = $post_data->userToken;
        $invite_number  = normalize_number($post_data->invite_number);
        */
        //to remove
        $invite_number  = normalize_number('6193098258');
        $user   = $this->User_model->get_user_by_id(34);
        //$user       = $this->get_user_by_token($userToken);
        $error_msg  = NULL;
        $this->Message_model->create_invite_to_join($user,$invite_number,$error_msg);
        if (is_null($error_msg)){
            $json['request_status'] = 'success';
        } else {
            $json['request_status'] = 'error';
            $json['error_msg']  = $error_msg;
        }
        $this->Template_model->add_json($json);
    }
    //Friends
    public function add_friend(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $json       = array();
        $userToken  = $post_data->userToken;
        $friend_id  = $post_data->contact_id;
        $local_id   = $post_data->local_id;
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $this->User_model->send_friend_request($user_id, $friend_id, $local_id);
        $json['request_status'] = 'pending';
        /*
        if ($this->User_model->does_user_auto_accept($friend_id)){
            $this->User_model->accept_friend_request($friend_id, $user_id);
            $json['request_status'] = 'accepted';
        }
        */
        $this->Template_model->add_json($json);
    }   
    
    public function process_friend_request(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $json       = array();
        $userToken  = $post_data->userToken;
        $contact_id = $post_data->contact_id;
        $local_id   = $post_data->local_id;
        $action     = $post_data->action;
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        if (!empty($user_id)){
            switch($action){
                case 'accept':
                    if($this->User_model->accept_friend_request($user_id, $contact_id, $local_id)){
                        $json['request_status'] = 'accepted';
                    }
                    break;
                case 'reject':
                    if($this->User_model->reject_friend_request($user_id, $contact_id)){
                        $json['request_status'] = 'rejected';
                    }
                    break;
            }
        }
        $this->Template_model->add_json($json);
    }
    public function accept_friend_request(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $json       = array();
        $userToken  = $post_data->userToken;
        $contact_id = $post_data->contact_id;
        if (empty($contact_id)){
            $contact_number = $post_data->contact_number;
            $contact_id = $this->User_model->get_user_by_number($contact_number)->id;
        }
        $local_id   = $post_data->local_id;
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        if (!empty($user_id)){
            if ($this->User_model->accept_friend_request($user_id, $contact_id, $local_id)){
                $json['request_status'] = 'accepted';
                $json['request_id'] = $user_id.'_'.$contact_id;
            }
        }
        $this->Template_model->add_json($json);
    }

    public function remove_friend(){
        $user_id    = $this->User_model->get_user_id_by_token($this->data->userToken);
        $contact_id = $this->data->contact;
        if ($this->User_model->remove_friend($user_id, $contact_id)){
            $this->json['status']   = 'success';
        }
        $this->Template_model->add_json();
    }

    public function update_preference(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $userToken  = $post_data->userToken;
        $preference = $post_data->preference;
        $value      = $post_data->value;
        $json       = array();
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $this->User_model->update_preference($user_id, $preference, $value);
        $json['status'] = 'success';
        $this->Template_model->add_json($json);
    }
    public function update_contact_preference(){
        $post_data  = json_decode(file_get_contents('php://input'));
        $userToken  = $post_data->userToken;
        $preference = $post_data->preference;
        $value      = $post_data->value;
        $contact_id = $post_data->contact;
        $user_id    = $this->User_model->get_user_id_by_token($userToken);
        $this->User_model->update_user_preference($user_id, $contact_id, $preference, $value);
        $this->json['status'] = 'success';
        $this->Template_model->add_json();
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
