<?php
    function normalize_number($number){
        $number = preg_replace('/[^0-9]/', '', $number);
        if (strlen($number) == 10){
            $number = '1'.$number;
        }
        return $number;
    }
?>
