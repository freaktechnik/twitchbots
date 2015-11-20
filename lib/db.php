<?php
include_once __DIR__.'/config.php';

$dbh = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8', $db_user, $db_pw);
?>
