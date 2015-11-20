<?php
$page = 1;
$pagesize = 100;
if(isset($_GET['page']) && (int)$_GET['page'] > 1) {
    $page = (int)$_GET['page'];
}
$offset = ($page-1) * $pagesize;

function get_pagecount() {
    include_once __DIR__.'/db.php';
    global $dbh, $pagesize;
    $getc = $dbh->prepare("SELECT count FROM count");
    $getc->execute();
    $itemcount = $getc->fetch(PDO::FETCH_OBJ);
    $pagecount = ceil($itemcount->count / (float)$pagesize);
    return $pagecount;
}
?>
