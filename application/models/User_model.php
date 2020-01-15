<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('normalizers');
    }

    private $preference_fields = array(
        'share_phones', 'share_emails', 'share_addresses', 'ignore_unknown',
    );

    public function hello_world(){
        return 'Hello World';
    }

    public function process_user_data(&$user_data, &$status){
        $phoneNumber = normalize_number($user_data->phoneNumber);
        $existing_user = $this->get_user_by_number($phoneNumber);
        $data   = array();
        if (!empty($existing_user)){
            $status = 'duplicate';
            return FALSE;
        } else {
            list($firstName, $lastName) = explode(' ', $user_data->fullName);
            $data['given_name'] = $firstName;
            $data['family_name'] = $lastName;
            $data['phone'] = $phoneNumber;
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
    public function update_user_playerid($user_token, $playerID){
        $user_id    = $this->get_user_id_by_token($user_token);
        if ($user_id !== FALSE){
            $data   = array('playerid' => $playerID);
            $where  = array('id'    => $user_id);
            $this->db->update('users', $data, $where);
            return TRUE;
        }
        return FALSE;
    }

    public function check_user_verification($user){
        return $user->is_verified;
    }
    public function verify_user($user, $verification_code){
        $this->load->model('Message_model');
        $status = $this->Message_model->verify_code($user->id, $user->phone, $verification_code);
        if ($status == 'success'){
            $data   = array('is_verified'   => 1);
            $where  = array('id'    => $user->id);
            $this->db->update('users', $data, $where);
        }
        return $status;
    }

    public function remove_info($userToken, $type, $label, $value){
        $user_id = $this->get_user_id_by_token($userToken);
        if ($user_id !== FALSE){
            $user = $this->get_user_by_id($user_id);
            if (!empty($user)){
                switch($type){
                    case 'phones':
                        $field = 'phones';
                        $value = normalize_number($value);
                        break;
                    case 'emails':
                        $field = 'emails';
                        break;
                    case 'addresses':
                        $field = 'addresses';
                        break;
                }
                $current_info = unserialize($user->$field);
                $new_info = array();
                                foreach($current_info as $info){
                    if ($info->label != $label || $info->value != $value){
                        array_push($new_info, $info);
                    }
                }
                /*
                $debug = array(
                    'current' => $current_info,
                    'new' => $new_info,
                    'label' => $label,
                    'value' => $value);
                print_r($debug);
                */

                $data = array($field => serialize($new_info));
                $this->db->where('id', $user_id);
                $this->db->update('users', $data);
                return $new_info;        
            }
        }
        return FALSE;
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
    public function update_info($userToken, $profile_data){
        $user_id    = $this->get_user_id_by_token($userToken);
        if ($user_id !== FALSE){
            $user   = $this->get_user_by_id($user_id);
            if (!empty($user)){
                $need_reverify = FALSE;
                if ($user->phone != $profile_data->phone){
                    $need_reverify = TRUE;
                }
                foreach($profile_data->phones as $phone){
                    $phone->value = normalize_number($phone->value);
                }
                $data   = array(
                    'phone'         => normalize_number($profile_data->phone),
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

    public function reset_user_verification_attempts($user_id, $attempts=0){
        $where  = array('id'    => $user_id);
        $data   = array('verification_attempts' => $attempts);
        $this->db->update('users', $data, $where);
        if ($attempts == 0){
            $this->db->where('user', $user_id);
            $this->db->delete('verification_numbers');
        }
    }
    public function reset_user_password_reset_attempts($user_id, $attempts=0){
        $where  = array('id'    => $user_id);
        $data   = array('password_reset_attempts' => $attempts);
        $this->db->update('users', $data, $where);
        if ($attempts == 0){
            $this->db->where('user', $user_id);
            $this->db->delete('reset_codes');
        }
    }

    public function reset_password($user, $pwd){
        $pwd    = $this->encrypt_password($pwd);
        $this->db->where('id', $user->id);
        $this->db->update('users', array('password' => $pwd));
        $this->reset_user_password_reset_attempts($user->id);
    }
    public function update_info_field($userToken, $profile_data){
        $user_id    = $this->get_user_id_by_token($userToken);
        if ($user_id !== FALSE){
            $user   = $this->get_user_by_id($user_id);
            if (!empty($user)){
                $data = NULL;
                if(!IS_NULL($profile_data->phone)){
                    $need_reverify = FALSE;
                    if ($user->phone != $profile_data->phone){
                        $need_reverify = TRUE;
                    }
                    $data = array('phone' => normalize_number($profile_data->phone));
                    $update_fields = array('phone');
                }
                if (!IS_NULL($profile_data->phones)){
                    foreach($profile_data->phones as $phone){
                        $phone->value = normalize_number($phone->value);
                    }
                    $data = array('phones' => serialize($profile_data->phones));
                    $update_fields = array('phones');
                }
                if (!IS_NULL($profile_data->emails)){
                    $data = array('emails' => serialize($profile_data->emails));
                    $update_fields = array('emails');
                }
                if (!IS_NULL($profile_data->addresses)){
                    $data = array('addresses' => serialize($profile_data->addresses));
                    $update_fields = array('addresses');
                }
                if (!IS_NULL($data)){
                    $where  = array(
                        'id'    => $user_id
                    );
                    $this->db->update('users', $data, $where);
                    $meta   = $this->create_profile_update_meta($user, $data, $update_fields);
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
        } else {
            return FALSE;
        }
    }
    public function create_profile_update_meta($user, $update_data, $update_fields = NULL){
        if (IS_NULL($update_fields)){
            $update_fields = array(
                'phone', 'phones', 'emails', 'addresses' 
            );
        }
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
        $phone_num = normalize_number($phone_num);
        $this->db->where('phone', $phone_num);
        $this->db->where('is_verified', 1);
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
    public function parse_user_info($user){
        $phones     = !empty($user->phones) ? unserialize($user->phones) : array();
        $emails     = !empty($user->emails) ? unserialize($user->emails) : array();
        $addresses  = !empty($user->addresses) ? unserialize($user->addresses) : array();
        $user->phones       = $phones;
        $user->emails       = $emails;
        $user->addresses    = $addresses; 
        $user->share_phones = ($user->share_phones == 1);
        $user->share_emails = ($user->share_emails == 1);
        $user->share_addresses = ($user->share_addresses == 1);
        $user->ignore_unknown   = ($user->ignore_unknown == 1);
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
        return $this->db->count_all_results('contacts') > 0;
    }
    public function is_invite_sent($user, $phone){
        $where = array(
                'origin_user'   => $user->id,
                'target_user'   => $phone,
                'record_type'   => 'roladex_invite',
                );
        return $this->db->count_all_results('records') > 0;
    }
    /*
    public function is_follow_request_sent($user, $contact_id){
        $where  = array(
                'origin_user'   => $user->id,
                'target_user'   => $contact_id,
                'record_type'   => 'follow_request',
                );
        return $this->db->count_all_results('records') > 0;
    }*/
    public function is_follow_request_sent($user, $contact_id){
        $where = array(
            'user' => $user->id,
            'contact'   => $contact_id
        );
        $this->db->where($where);
        $res = $this->db->get('contacts')->first_row();
        if (!empty($res)){
            return $res->status == 'requested';
        }
        return FALSE;
    }
    public function is_follow_request_pending($user, $contact_id){
        $where = array(
            'user' => $user->id,
            'contact' => $contact_id
        );
        $this->db->where($where);
        $res = $this->db->get('contacts')->first_row();
        if (!empty($res)){
            return $res->status == 'pending';
        }
        return FALSE;
    }
    public function is_friends($user, $contact_id){
        $where = array(
            'user' => $user->id,
            'contact' => $contact_id
        );
        $this->db->where($where);
        $res = $this->db->get('contacts')->first_row();
        if (!empty($res)){
            return $res->status == 'accepted';
        }
        return FALSE;
    }
    /*
    public function is_follow_request_pending($user, $contact_id){
        $where = array(
                'origin_user'   => $contact_id,
                'target_user'   => $user->id,
                'record_type'   => 'follow_request',
                );
        return $this->db->count_all_results('records') > 0;
    }
    */

    //Contacts
    public function get_contacts($args=array()){
        extract($args);
        if (isset($user)){
            $this->db->where('user', $user);
        }
        $this->db->where('status', 'accepted');
        $contacts = $this->db->get('contacts')->result();
        return $contacts;
    }

    public function get_updates($args=array()){
        extract($args);
        
        if (isset($user)){
            $this->db->where('user', $user);
        }
        if (isset($contacts)){
            if (empty($contacts)){
                return array();
            }
            $this->db->where_in('user', $contacts);
        }
        if (isset($order_by)){
            $order = (isset($order)) ? $order : 'DESC';
            $this->db->order_by($order_by, $order);
        }
        if (isset($limit)){
            $this->db->limit($limit);
        }
        $updates = $this->db->get('updates')->result();
        return $updates;
    }

    public function get_user_contact_updates($user_id, $config){
        $contacts = $this->get_contacts(array('user' => $user_id));
        $contact_ids = array_map(function($c){
            return $c->contact;
        }, $contacts);
        $config['contacts'] = $contact_ids;
        return $this->get_updates($config);
    }

    public function process_updates_for_display(&$updates, $user_id){
        $processed_updates = array();
        foreach($updates as $update){
            $this->extend_update_with_user($update);
            $this->extend_update_with_local_id($update, $user_id);
            $local_id = $update->local_id;
            $meta = json_decode($update->meta);
            $changes = array();
            foreach($meta as $item){
                $name = $update->_user->given_name.' '.$update->_user->family_name;
                $field = $item->field;
                $changes = array();

                $prev = unserialize($item->previous);
                $new  = unserialize($item->updated);
                $prev_entries = array();
                $new_entries = array();
                if (is_array($new)){
                    foreach($new as $entry){
                        $new_entries[$entry->label] = $entry->value;
                    }
                }
                if (is_array($prev)){
                    foreach($prev as $entry){
                        $prev_entries[$entry->label] = $entry->value;
                    }
                }
                foreach($new_entries as $label=>$value){
                    if (isset($prev_entries[$label])){
                        if ($prev_entries[$label] != $value){
                            $type = 'add';
                            $prev_val = $prev_entries[$label];
                            $new_val = $value;
                            $change = compact('field', 'type', 'new_val', 'label');
                            array_push($changes, $change);
                            $type = 'remove';
                            $change = compact('field', 'type', 'prev_val', 'label');
                            array_push($changes, $change);
                        }
                    } else {
                        $type = 'add';
                        $new_val = $value;
                        $change = compact('field', 'type', 'new_val', 'label');
                        array_push($changes, $change);
                    }
                }
                foreach($prev_entries as $label=>$value){
                    if (!isset($new_entries[$label])){
                        $type = 'remove';
                        $prev_val = $value;
                        $change = compact('field', 'type', 'prev_val', 'label');
                        array_push($changes, $change);
                    }
                }
            }
            $processed_update = compact('name', 'changes', 'local_id');
            array_push($processed_updates, $processed_update);
        }
        return $processed_updates;
    }

    public function get_follow_requests($args){
        extract($args);
        if (isset($user)){
            $this->db->where('user', $user);
        }
        if (isset($order_by)){  
            $this->db->order_by($order_by, $order);
        }
        $this->db->where('status', 'pending');
        $res = $this->db->get('contacts')->result();
        $requests = array();
        foreach($res as $request){
        }
        return $requests;
    }
   
    public function check_invite_status($user_id, $number){
        $number = normalize_number($number);
        $this->db->where('inviter', $user_id);
        $this->db->where('invitee_phone', $number);
        return $this->db->count_all_results('invitations') > 0;
    } 

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
        $this->db->where('status', 'accepted');
        $res = $this->db->get('contacts')->first_row();
        if (empty($res)){
            return NULL;
        } else {
            $roladex_user_id = $res->contact;
            $roladex_user = $this->get_user_by_id($roladex_user_id);
            
            return $roladex_user;
        }
    }
    public function get_contact_user_from_primary_phone($user_id, $phone_number, $local_id){
        $contact = $this->get_user_by_number($phone_number);
        if (!empty($contact)){
            $where = array(
                'user'      => $user_id,
                'contact'   => $contact->id,
                'status'    => 'accepted',
            );
            $res = $this->db->get('contacts')->first_row();
            if (empty($res)){
                return NULL;
            } else {
                if (empty($res->local_id)){
                    $data = array('local_id' => $local_id);
                    $this->db->update('contacts', $data, $where);
                }
                $roladex_user = $this->get_user_by_id($res->contact);
                return $roladex_user;
            }
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

    //check $user's share settings for $contact
    //Check specific settings first. Default is null, check general settings of $user.
    //General default value is 1 for sharing.
    function get_permission($user_id, $contact_id, $type){
        $this->db->where('user', $user_id);
        $this->db->where('contact', $contact_id);
        $res = $this->db->get('contacts')->first_row();
        //if $contact is not $user's contact. Don't update.
        if (empty($res)){
            return FALSE;
        }
        switch($type){
            case 'phone':
                $permission = $res->share_phones;
            case 'email':
                $permission = $res->share_emails;
            case 'address':
                $permission = $res->share_addresses;
        }
        if (!is_null($permission)){
            return $permission === 1;
        } else {
            //get general setting
            $user   = $this->get_user_by_id($user_id);
            switch($type){
                case 'phone':
                    $permission = $res->share_phones;
                case 'email':
                    $permission = $res->share_emails;
                case 'address': 
                    $permission = $res->share_addresses;
            }
            return $permission === 1;
        }
    }
    

    //update the info of $contact_user for the user. 
    //Check the contact for their share preferences.
    function update_contact_info(&$contact, $contact_user, $for_user){
        $userPhones     = unserialize($contact_user->phones);
        $userEmails     = unserialize($contact_user->emails);
        $userAddresses  = unserialize($contact_user->addresses);

        //Always update primary number
        $primary_number = (object)array(
            'label'     => 'Roladex',
            'number'    => $contact_user->phone
        );
        $phoneNumbers       = array($primary_number);
        
        //other phones updates
        if (!empty($userPhones)){
            if ($this->get_permission($contact_user->id, $for_user->id, 'phone')){
                foreach($userPhones as $otherPhone){
                    array_push($phoneNumbers, (object)array(
                        'label'     => $otherPhone->label,
                        'number'    => $otherPhone->value
                    ));
                }
            }
        }

        //emails updates
        $emailAddresses = array();
        if (!empty($userEmails)){
            if ($this->get_permission($contact_user->id, $for_user->id, 'email')){
                foreach($userEmails as $email){
                    array_push($emailAddresses, (object)array(
                        'label'     => $email->label,
                        'email'     => $email->value
                    ));
                }
            }
        }

        //addresses updates
        $postalAddresses = array();
        if (!empty($userAddresses)){
            if ($this->get_permission($contact_user->id, $for_user->id, 'address')){
                foreach($userAddresses as $address){
                    list($street, $city, $statezip) = explode(',', $address->value);
                    list($state, $zip) = explode(' ', trim($statezip));
                    array_push($postalAddresses, (object)array(
                        'street'    => trim($street),
                        'city'      => trim($city),
                        'state'     => $state,
                        'region'    => $state,
                        'postCode'  => $zip,
                        'country'   => 'USA',
                        'formattedAddress' => $address->value,
                        'label'     => $address->label,
                    ));
                }
            }
        }
        $contact->phoneNumbers      = $phoneNumbers;
        $contact->emailAddresses    = $emailAddresses;
        $contact->postalAddresses   = $postalAddresses;
    }
    public function update_contacts($user, $contacts){
        $updates    = array();
        foreach($contacts as $contact){
            $contact_user   = $this->get_contact_user_from_local_id($user->id, $contact->recordID);
            if (!is_null($contact_user)){
                $this->update_contact_info($contact, $contact_user, $user);
                $update = clone $contact;
                array_push($updates, $update);
            }
        }
        return $updates;
    }
    public function filter_contacts($user, $contacts){
        $regular_contacts = array();
        $roladex_contacts = array();
        $friend_contacts  = array();
        $updates          = array();
        foreach($contacts as $contact){
            $contact_user = $this->get_contact_user_from_local_id($user->id, $contact->recordID);
            $contact->last_updated = '';
            if (!is_null($contact_user)){
                $this->update_contact_info($contact, $contact_user, $user);
                $update = clone $contact;
                array_push($updates, $update);
                $contact->type = 'friend';
                $contact->roladex_id = $contact_user->id;
                $contact->last_updated = date('Y-m-d', strtotime($contact_user->m_date));
                $contact->share_phones  = $contact_user->share_phones;
                $contact->share_emails  = $contact_user->share_emails;
                $contact->share_addresses = $contact_user->share_addresses;
                $friend_contacts[$contact->roladex_id]  = $contact;
            }
            $phone_numbers = array();
            foreach($contact->phoneNumbers as $phone_obj){
                $phone = normalize_number($phone_obj->number);
                array_push($phone_numbers, $phone);
                if ($phone_obj->label == 'mobile'){
                    $contact->phone = $phone;
                }
            }
            if (!isset($contact->phone)){
                if (count($phone_numbers) > 0){
                    $contact->phone = $phone_numbers[0];
                }
            }
            $name = trim($contact->givenName.' '.$contact->familyName);
            $contact->name = $name;
            if (is_null($contact_user)){
                $roladex_user = $this->get_contact_user_from_phone_number($phone_numbers);
                if (!is_null($roladex_user)){
                    $contact->roladex_id = $roladex_user->id;
                    $contact->type = 'roladex';
                    if ($this->is_follow_request_sent($user, $contact->roladex_id)){
                        $contact->request_status = 'requested';
                    }
                    if ($this->is_follow_request_pending($user, $contact->roladex_id)){
                        $contact->request_status = 'pending';
                    }
                    if ($this->is_friends($user, $contact->roladex_id)){
                        $contact->request_status = 'accepted';
                    }
                    $roladex_contacts[$contact->roladex_id] = $contact;
                }
                else {
                    $contact->invite_sent = $this->check_invite_status($user->id, $contact->phone);
                    $contact->type = 'regular';
                    $regular_contacts[$contact->phone]  = $contact;
                }
            }
        }
        usort($regular_contacts, function($c1, $c2){
            return strtolower($c1->name) > strtolower($c2->name);
        });
        return array((object)$regular_contacts, (object)$roladex_contacts, (object)$friend_contacts, $updates);
    }
    public function get_friends($user_id){
        $this->db->where('user', $user_id);
        $this->db->where('status', 'accepted');
        $contact_ids    = $this->db->get('contacts')->result();
        return $contact_ids;
    }

    public function does_user_auto_accept($user_id){
        $user   = $this->get_user_by_id($user_id);
        return ($user->follow_approval === 0);
    }
    public function add_contact_record($user_id, $friend_id, $status, $local_id=NULL){
        $where = array('user' => $user_id, 'contact' => $friend_id);
        $res = $this->db->get_where('contacts', $where)->first_row();
        if (empty($res)){
            $data = $where;
            $data['status'] = $status;
            if (!is_null($local_id)){
                $data['local_id'] = $local_id;
            }
            $this->db->insert('contacts', $data);
        } else {
            if ($res->status != 'accepted'){
                
                $data = array('status' => $status);
                $this->db->update('contacts', $data, $where);
            }
        }
    }
    public function send_friend_request($user_id, $target_id, $local_id){
        //check for ignored requests frist
        $where = array('contact' => $user_id, 'user' => $target_id, 'status' => 'ignored');
        $res = $this->db->get_where('contacts', $where)->first_row();
        $this->load->model('Notification_model');
        $sender = $this->get_user_by_id($user_id);
        $receiver   = $this->get_user_by_id($target_id);
        if (empty($res)){
            $this->add_contact_record($user_id, $target_id, 'requested', $local_id);
            $this->add_contact_record($target_id, $user_id, 'pending'); 
            $this->Notification_model->sendMessage($sender->given_name, $receiver->playerid, 'request');
        } else {
            $this->accept_friend_request($user_id, $target_id, $local_id);
        }
    }
    public function accept_friend_request($user_id, $target_id, $local_id){
        $where  = array('user'  => $user_id, 'contact' => $target_id);
        $data   = array('status'    => 'accepted', 'local_id' => $local_id);
        $this->db->update('contacts', $data, $where);

        $where  = array('user' => $target_id, 'contact' => $user_id);
        $data   = array('status'    => 'accepted');
        $this->db->update('contacts', $data, $where);

        $sender = $this->get_user_by_id($user_id);
        $receiver   = $this->get_user_by_id($target_id);
        //$this->Notification_model->sendMessage($sender->given_name, $receiver->playerid, 'accept');        
        return TRUE;
    }
    public function ignore_friend_request($user_id, $target_id){
        $this->db->where(array('user' => $user_id, 'contact' => $target_id, 'status'=>'pending'));
        $data = array('status' => 'ignored');
        $this->db->update('contacts', $data);        
        return TRUE;
    }

    public function reject_friend_request($user_id, $target_id){
        $this->db->where(array('user' => $user_id, 'contact' => $target_id, 'status'=>'pending'));
        $this->db->delete('contacts');
        //$this->db->where(array('user'=> $target_id, 'contact' => $user_id, 'status' => 'requested'));
        return TRUE;
    }
    public function remove_friend($user_id, $target_id){
        $this->db->where('user', $user_id);
        $this->db->where('contact', $target_id);
        $this->db->delete('contacts');
        return $this->db->affected_rows() > 0;
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

    public function update_preference($user_id, $preference, $value){
        $where  = array( 'id' => $user_id);
        if (in_array($preference, $this->preference_fields)){
            $data   = array( $preference => $value);
            $this->db->update('users', $data, $where);
        }
    }
    public function update_user_preference($user_id, $contact_id, $preference, $value){
        $where = array('user' => $user_id, 'contact' => $contact_id);
        if (in_array($preference, $this->preference_fields)){
            $data   = array( $preference => $value);
            $this->db->update('contacts', $data, $where);
        }
    }

    public function delete_user($userToken){
        $id = $this->get_user_id_by_token($userToken);
        $this->db->where('id', $id);
        $this->db->delete('users');
        return $this->db->affected_rows() > 0;
    }

//Extenders

    public function extend_update_with_user(&$update){
        $user_id = $update->user;
        $user = $this->get_user_by_id($user_id);
        $update->_user = $user;
    }

    public function extend_request_with_userinfo(&$request){
        $user_id    = $request->user;
        $user       = $this->get_user_by_id($user_id);
        $contact_id = $request->contact;
        $contact_user = $this->get_user_by_id($contact_id);
        $request->phone = $contact_user->phone;
        $username = $contact_user->given_name.' '.$contact_user->family_name;
        $request->username = $username;
        $contact  = $contact_user;
        unset($contact->password);
        $this->update_contact_info($contact, $contact_user, $user);
        $request->user = $contact;
    }

    public function extend_update_with_local_id(&$update, $user_id){
        $this->db->where(array(
            'user' => $user_id,
            'contact' => $update->user));
        $res = $this->db->get('contacts')->first_row();
        $update->local_id = $res->local_id;
    }

    public function extend_contact_for_display(&$contact, $user_id){
        $this->db->where('user', $user_id);
        $this->db->where('contact', $contact->id);
        $res = $this->db->get('contacts')->first_row();
        if (empty($res)) { return; }

        $contact->last_updated = date('Y-m-d', strtotime($contact->m_date));

        $name = trim($contact->given_name.' '.$contact->family_name);
        $contact->name = $name;
      
        $contact->type = 'roladex'; 
        if ($res->status == 'accepted'){
            $contact->type  = 'friend';
        }
        
        $contact->roladex_id    = $contact->id;
        $contact->share_phones = $res->share_phones;
        $contact->share_emails = $res->share_emails;
        $contact->share_addresses = $res->share_addresses;
    }

}
