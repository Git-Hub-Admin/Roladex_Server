<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Template_model extends CI_Model
{
    protected $json_data    = array();

    public function __construct()
    {
        parent::__construct();
    }

    public function add_json($k=NULL, $v=NULL){
        if (is_null($k)){
            $k  = $this->json;
        }
        if (is_array($k)){
            $this->json_data    = array_merge_recursive($this->json_data, $k);
        } elseif (!is_null($v)){
            $this->json_data[$k]    = $v;
        }
    }
    
    public function get_json() {
        return $this->json_data;
    }

    public function show($view, $data=NULL){
        $this->data = $data;
        $this->load->vars($data);
        $view_data  = array('view'  => $view);
        $this->load->view('layout', $view_data);
    }
}
