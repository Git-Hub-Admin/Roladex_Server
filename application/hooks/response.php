<?php

class Response
{
    public function output(){
        $CI =& get_instance();
        $content    = trim($CI->output->get_output());
      
        $response   = $CI->Template_model->get_json();
        $response['payload']    = $content;
        header("Content-Type: application/json");
        $content    = json_encode($response);
        echo $content;
    }
}

?>
