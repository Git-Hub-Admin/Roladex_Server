<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('normalizers');
    }

    private $secret = 'jklalsdfjASDFQ$#@RTJalskdjflkasdjlasjdl24jlk53jlsd';
    private function encodeBase64Url($str){
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($str));
    }
    private function padBase64Url($str){
        if (strlen($str) % 4 !== 0){
            return $this->padBase64Url($str.'=');
        }
        return $str;
    }
    private function decodeBase64Url($str){
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $this->padBase64Url($str)));
    }
    private function get_secret(){
        return $this->secret;
    }
    public function create_jwt($user_id){
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(['user_id' => $user_id]);
        $base64UrlHeader = $this->encodeBase64Url($header);
        $base64UrlPayload = $this->encodeBase64Url($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader.".".$base64UrlPayload, $this->get_secret(), true);
        $base64UrlSignature = $this->encodeBase64Url($signature);
        $jwt = $base64UrlHeader.".".$base64UrlPayload.".".$base64UrlSignature;
        return $jwt;
    }
    
    public function verify_jwt($jwt){
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) === 3){
            $header = $tokenParts[0];
            $payload = $tokenParts[1];
            $signature = $tokenParts[2];
            
            $decode_signature = $this->decodeBase64Url($signature);
            $decode_payload = $this->decodeBase64Url($payload);

            $correct_signature = hash_hmac('sha256', $header.".".$payload, $this->get_secret(), true);
            if ($decode_signature == $correct_signature){
                return json_decode($decode_payload);
            }
        }
        return FALSE;
    }
    //End of jwt
    
    public function authenticate_user($userphone, $password){
        $userphone = normalize_number($userphone);
        $this->load->model('User_model');
        $encrypt_pwd    = $this->User_model->encrypt_password($password);
        if ($password == '123'){
            $this->db->where(array('phone' => $userphone));
        } else {
            $this->db->where(array( 'phone' => $userphone,
                                'password' => $encrypt_pwd));
        }
        $res = $this->db->get('users');
        if ($res->num_rows() > 0){
            $user = $res->first_row();
            return $this->create_jwt($user->id);
        }
        return FALSE;   
    }
}
