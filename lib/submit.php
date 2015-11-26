<?php
session_start();
require_once __DIR__.'/csrf.php';

if(!validate_token("submit", $_POST['token']) ||
   (isset($_SERVER['HTTP_REFERER']) && preg_match("/^http://twitchbots.info/submit/", $_SERVER['HTTP_REFERER']))) {
    header('Location: http://twitchbots.info/submit?error=1');
}
else {
    require_once __DIR__.'/db.php';
    $req = $dbh->prepare("INSERT INTO submissions(name,description) VALUES (?,?)");
    $req->execute(array($_POST['username'],$_POST['existing_type'] == 0 ? $_POST['description'] : $_POST['existing_type']));
    header('Location: http://twitchbots.info/submit?success=1');
}

?>
