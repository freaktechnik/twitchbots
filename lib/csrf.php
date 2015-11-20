<?php
    function store_in_session($key, $value) {
        if(isset($_SESSION))
            $_SESSION[$key] = $value;
    }

    function unset_session($key) {
        $SESSCION[$key] = ' ';
        unset($_SESSION[$key]);
    }

    function get_from_session($key) {
        if(isset($_SESSION))
            return $_SESSION[$key];
        else
            return false;
    }

    function generate_token($form_name) {
        if(function_exists("random_bytes"))
            $token = hash("sha512", random_bytes(512));
        else
            $token = hash("sha512", mt_rand(0, mt_getrandmax()));
        store_in_session($form_name, $token);
        return $token;
    }

    function validate_token($form_name, $token_value) {
        $token = get_from_session($form_name);
        unset_session($form_name);
        return $token === $token_value;
    }
?>
