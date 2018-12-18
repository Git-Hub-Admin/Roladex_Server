<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function hello_world(){
        return 'Hello World';
    }

    public function process_user_data(&$user_data, &$status){
        $phoneNumber = preg_replace('/\D/', '', $user_data->phoneNumber);
        $user_data->phoneNumber = $phoneNumber;
        $existing_user = $this->get_user_by_number($user_data->phoneNumber);
        $data   = array();
        if (!empty($existing_user)){
            $status = 'duplicate';
            return FALSE;
        } else {
            $data['name'] = $user_data->fullName;
            $data['phone'] = $user_data->phoneNumber;
            $data['password'] = $this->encrypt_password($user_data->password);
            $user_data  = $data;
            return TRUE;
        }
    }
    public function create_user($user_data, &$status){
        $data_process = $this->process_user_data($user_data, $status);
        if ($data_process){
            $this->db->insert('users', $user_data);
            if ($this->db->affected_rows() == 0){
                $status = 'error';
                return NULL;
            } else {
                $status = 'success';
                return $this->db->insert_id();
            }
        }
        return NULL;
    }
    public function send_verification_code($user_id, $phone_number){
        $user_by_number = $this->get_user_by_number($phone_number);
        if (!empty($user_by_number)){
            if ($user_by_number->id != $user_id){
                $status = 'duplicate';
            } else {
                $status = 'success';
            }
        } else {
            $user_by_id = $this->get_user_by_id($user_id);
            $this->db->update('users', array('phone' => $phone_number), array('id' => $user_id));
            $status = 'success';
        }
        if ($status == 'success'){
            //code to send verification code here
        }
        return $status;
    }
    public function check_user_verification($user){
        return $user->is_verified;
    }
    public function verify_user($user, $verification_code){
        
    }

    /*
    Expected profile_data format:
    {
        given_name:
        family_name:
        password:
        email:
        phone:
        emails: [{label:'',email:''}, ...]
        phones: [{label:'',phone:''}, ...]
        addresses: [{label:'',addresses:''}, ...]
    }
    */
    public function update_profile($userToken, $profile_data){
        $user_id    = $this->get_user_id_by_token($userToken);
        if ($user_id !== FALSE){
            $user   = $this->get_user_by_id($user_id);
            if (!empty($user)){
                $need_reverify = FALSE;
                if ($user->phone != $profile_data->phone){
                    $need_reverify = TRUE;
                }
                $data   = array(
                    'given_name'    => $profile_data->given_name,
                    'family_name'   => $profile_data->family_name,
                    'email'         => $profile_data->email,
                    'phone'         => $profile_data->phone,
                    'emails'        => serialize($profile_data->emails),
                    'phones'        => serialize($profile_data->phones),
                    'addresses'     => serialize($profile_data->addresses),
                );
                $where  = array(
                    'id'    => $user_id
                );
                $this->db->update('users', $data, $where);
                $meta   = $this->create_profile_update_meta($user, $data);
                $data = array(
                    'user'  => $user_id,
                    'meta'  => $meta,
                    'c_date' => date("Y-m-d H:i:s"),
                );
                $this->db->insert('updates', $data);
                return $this->db->affected_rows() > 0;
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }
    public function create_profile_update_meta($user, $update_data){
        $update_fields = array(
            'phone', 'email', 'phones', 'emails', 'addresses' 
        );
        $updates = array();
        foreach($update_fields as $field){
            $this->push_update_data($updates, $field, $user->$field, $update_data[$field]);
        }
        return json_encode($updates);
    }
    public function push_update_data(&$updates, $field, $old, $new){
        if ($old != $new){
            $update   = (object)array();
            $update->field = $field;
            $update->previous  = $old;
            $update->updated = $new;
            array_push($updates, $update);
        }
    }

    public function encrypt_password($raw_password) {
        return md5($raw_password);
    }

    //User getters
    public function get_user_by_number($phone_num){
        $this->db->where('phone', $phone_num);
        return $this->db->get('users')->first_row();
    }
    public function get_user_by_id($id){
        $this->db->where('id', $id);
        return $this->db->get('users')->first_row();
    }
    public function get_user_id_by_token($token){
        $this->load->model('Auth_model');
        $user_data = $this->Auth_model->verify_jwt($token);
        if (!$user_data){
            return FALSE;
        } else {
            $user_id = $user_data->user_id;
            return $user_id;
        }
    }
    public function get_user_by_token($token){
        $user_id = $this->get_user_id_by_token($token);
        if ($user_id){
            return $this->get_user_by_id($user_id);
        } else {
            return NULL;
        }
    }

    //Testers
    public function is_phone_roladex_user($phone){
        $this->db->where('phone', $phone);
        return $this->db->count_all_results('users') > 0;
    }
    public function is_phone_friend_user($user, $phone){
        $contact = $this->get_user_by_number($phone);
        $this->db->where('user', $user->id);
        $this->db->where('contact', $contact->id);
        return $this->db->count_all_results('user_contacts') > 0;
    }
    public function is_invite_sent($user, $phone){
        $where = array(
                'origin_user'   => $user->id,
                'target_user'   => $phone,
                'record_type'   => 'roladex_invite',
                );
        return $this->db->count_all_results('records') > 0;
    }
    public function is_follow_request_sent($user, $contact_id){
        $where  = array(
                'origin_user'   => $user->id,
                'target_user'   => $contact_id,
                'record_type'   => 'follow_request',
                );
        return $this->db->count_all_results('records') > 0;
    }
    public function is_follow_request_pending($user, $contact_id){
        $where = array(
                'origin_user'   => $contact_id,
                'target_user'   => $user->id,
                'record_type'   => 'follow_request',
                );
        return $this->db->count_all_results('records') > 0;
    }

    //Contacts
    /*
    Three types of contacts in user's contact book
    1. Regular: Do nothing
    2. Non-friend roladex user: Identify them with get_contact_user_from_phone_number
    3. Friend Roladex user: 
        ()Identify them with get_contact_user_from_local_id,
        ()Grab their info in db for update with create_updated_contact_info
        ()Return updated info for local update
    */
    public function get_contact_user_from_local_id($user_id, $local_id){
        $this->db->where('user', $user_id);
        $this->db->where('local_id', $local_id);
        $res = $this->db->get('contacts')->first_row();
        if (empty($res)){
            return NULL;
        } else {
            $roladex_user_id = $res->contact;
            $roladex_user = $this->get_user_by_id($roladex_user_id);
            return $roladex_user;
        }
    }
    public function get_contact_user_from_phone_number($phone_numbers){
        $this->db->where_in('phone', $phone_numbers);
        $user = $this->db->get('users')->first_row();
        if (!empty($user)){
            return $user;
        } else {
            /*
            foreach($phone_numbers as $phone_number){
                $this->db->like('phones', $phone_number);
                $users = $this->db->get('users')->result();
                if (!empty($users)){
                    foreach($users as $user){
                        $user_phones = unserialize($user->phones);
                        foreach($user_phones as $label->$user_phone){
                            if ($phone_number == $user_phone){
                                return $user;
                            }
                        }
                    }
                }
            }   
            */
        }
        return NULL;
    }
    public function create_updated_contact_info(&$contact_info, $user, $contact){
        $this->db->where('user', $user->id);
        $this->db->where(''
    }
    public function filter_contacts($user, $contacts){
        $regular_contacts = array();
        $roladex_contacts = array();
        $friend_contacts  = array();
        foreach($contacts as $contact){
            $phone_numbers = array();
            foreach($contact->phoneNumbers as $phone){
                $phone = preg_replace('/\D/', '', $phone);
                if (strlen($phone) == 10){
                    $phone = '1'.$phone;
                }
                array_push($phone_numbers, $phone);
            }
            $contact_user = $this->get_contact_user_from_local_id($user->id, $contact->recordID));
            if (!is_null($contact_user)){
                $contact->roladex_id = $contact_user->id;
                $update_info = $this->create_updated_contact_info($contact, $user, $contact_user);
                array_push($roladex_contacts($contact));
            } else {
                $roladex_user = $this->get_contact_user_from_phone_number($phone_numbers);
                if (!is_null($roladex_user)){
                    $contact->roladex_id = $roladex_user->id;
                    array_push($roladex_contacts($contact));
                }
                else {
                    array_push($regular_contacts($contact));
                }
            }
        }
        return array($regular_contacts, $roladex_contacts, $friend_contacts);
    }
    public function get_friends($user_id){
        $this->db->where('user', $user_id);
        $this->db->where('status', 'accepted');
        $contact_ids    = $this->db->get('user_contacts')->result();
        return $contact_ids;
    }

    public function does_user_auto_accept($user_id){
        $user   = $this->get_user_by_id($user_id);
        return ($user->follow_approval === 0);
    }
    public function send_friend_request($user_id, $target_id){
        $requester_data = array(
                    'user'      => $user_id,
                    'contact'   => $target_id,
                    'status'    => 'requested'
                );
        $friend_data = array(
                    'user'      => $target_id,
                    'contact'   => $user_id,
                    'status'    => 'pending'
                );
        $this->db->insert('user_contacts', $requester_data);
        $this->db->insert('user_contacts', $friend_data);
        $this->add_record('follow_request', $user_id, $target_id);
    }
    public function accept_friend_request($user_id, $target_id){
        $where  = array('user'  => $user_id, 'contact' => $target_id);
        $data   = array('status'    => 'accepted');
        $this->db->update('user_contacts', $data, $where);
        $where  = array('user' => $target_id, 'contact' => $user_id);
        $data   = array('status'    => 'accepted');
        $this->db->update('user_contacts', $data, $where);
        $this->add_record('follow_accept', $user_id, $target_id);
    }
    public function remove_friend($user_id, $target_id){
        $this->db->where('user', $user_id);
        $this->db->where('contact', $target_id);
        $this->db->delete('user_contacts');
    }

    public function add_record($type, $origin, $target, $meta=NULL){
        $data   = array(
                    'record_type'   => $type,
                    'origin_user'   => $origin,
                    'target_user'   => $target
                    );
        if (!empty($meta)){
            $data['meta']   = $meta;
        }
        $this->db->insert('records', $data);
    }

    

}
