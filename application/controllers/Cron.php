<?php

class Cron extends MY_Controller{
    public function __construct() {
        parent::__construct();
    }
    
    public function reset_codes(){
        $this->load_model('Message_model');
        $this->Message_model->reset_codes();
    }
}

?>
