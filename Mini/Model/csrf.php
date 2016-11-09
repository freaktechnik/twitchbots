<?php
    session_cache_limiter(false);
    session_start();

    function store_in_session(string $key, string $value) {
        if(isset($_SESSION))
            $_SESSION[$key] = $value;
    }

    function unset_session(string $key) {
        $SESSION[$key] = ' ';
        unset($_SESSION[$key]);
    }

    function get_from_session(string $key) {
        if(isset($_SESSION))
            return $_SESSION[$key];
        else
            return false;
    }

    function generate_token(string $form_name): string {
        $token = hash("sha512", random_bytes(512));
        store_in_session($form_name, $token);
        return $token;
    }

    function validate_token(string $form_name, string $token_value): bool {
        $token = get_from_session($form_name);
        unset_session($form_name);
        return $token === $token_value;
    }
?>
