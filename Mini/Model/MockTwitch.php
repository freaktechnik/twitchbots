<?php

namespace Mini\Model;

class MockTwitch
{
    public $http_code = 0;

    public function __construct() {
    }

    public function channelGet($username) {
        if($username == "zeldbot")
            $this->http_code = 404;
        else if($username == "xanbot")
            $this->http_code = 302;
        else
            $this->http_code = 200;
    }
}
?>
