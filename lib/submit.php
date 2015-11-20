<?php
session_start();
require_once __DIR__.'/config.php';
require_once __DIR__.'/csrf.php';

if(!validate_token("submit", $_POST['token']) ||
   (isset($_SERVER['HTTP_REFERER']) && preg_match("/^http://twitchbots.info/submit/", $_SERVER['HTTP_REFERER']))) {
    header('Location: http://twitchbots.info/submit?error=1');
}
else {
    $dbh = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8', $db_user, $db_pw);
    $req = $dbh->prepare("INSERT INTO submissions(name,description) VALUES (?,?)");
    $req->execute(array($_POST['username'],$_POST['description']));
    header('Location: http://twitchbots.info/submit?success=1');
}

?>
