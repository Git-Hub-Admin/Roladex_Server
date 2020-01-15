<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Web extends CI_Controller
{
    public function __construct(){
        parent::__construct();
        $this->load->model('Template_model');
        $this->load->model('Auth_model');
        $this->load->model('User_model');
        $this->data = json_decode(file_get_contents('php://input'));
    }

    public function login(){
        $this->Template_model->show('login', $this->data);
    }

    public function register(){
        if (FALSE){
            $fullName   = $this->input->post('name');
            list($given_name, $family_name) = explode(' ', $name);
            $password   = $this->input->post('password');
            $phoneNumber      = $this->input->post('phone');
            $is_verified    = 1;
            $user_data  = compact('fullName', 'password', 'phoneNumber');
            $status = NULL;
            $user_id = $this->User_model->create_user($user_data, $status);
            if ($status != 'success'){
                die($status);
            } else {
                $this->db->where('id', $user_id);
                $user   = $this->db->get('users')->first_row();
                $this->Auth_model->authenticate_user($user->phone, $user->password);
            }
        } else {
            $this->Template_model->show('register', $this->data);
        }
    }
}

?>
