<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notification_model extends CI_Model
{
    private function get_onesignalid(){
        return '644eaff5-80fc-42bd-97a1-eccb889614ed';
    }
    
    public function sendMessage($sender, $receiver_id, $type){
        if (empty($receiver_id)){
            return NULL;
        }
        $message    = '';
        $app_id     = $this->get_onesignalid();
        switch($type){
            case 'request':
                $message    = $sender.' sent you a follow request.';
                break;
            case 'accept':
                $message    = $sender.' has accepted your request.';
                break;
        }
        $content    = array(
            'en'    => $message
        );
        $fields = array(
            'app_id'    => $app_id,
            'include_player_ids'    => array($receiver_id),
            'contents'  => $content,
        );
        $fields = json_encode($fields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response   = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
