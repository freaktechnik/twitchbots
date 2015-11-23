<?php
require_once __DIR__.'/../../twitchbots/lib/db.php';
$apiurl = "http://api.twitchbots.info/v1/";

function jsonize_key($key) {
    if($key == "multichannel")
        return "multiChannel";
        return $key;
}

function jsonize_value($key, $value) {
    if($key == "multichannel")
        return $value == 1;
    else
        return $value;
}

function set_link($value) {
    global $apiurl;
    $value['username'] = $value['name'];
    $value['_link'] = $apiurl."bot/".$value['name'];
    unset($value['name']);
    return $value;
}

function add_pagination_links($page, $pagecount, $baseurl, &$target) {
    $target['page'] = $page;
    if($page > 1)
        $target['_prev'] = $baseurl."page=".($page-1);
    else
        $target['_prev'] = null;

    if($page < $pagecount)
        $target['_next'] = $baseurl."page=".($page+1);
    else
        $target['_next'] = null;
}

$target = array();

if($_GET['endpoint'] == 'type') {
    $getq = $dbh->prepare('SELECT * FROM types WHERE id=:id');
    $getq->bindValue(":id", (int)$_GET['id'], PDO::PARAM_INT);
    $getq->execute();
    $result = $getq->fetch(PDO::FETCH_ASSOC);
    foreach($result as $key => $value) {
        $target[jsonize_key($key)] = jsonize_value($key, $value);
    }
}
else if($_GET['endpoint'] == 'bot' && isset($_GET['id'])) {
    $id = strtolower($_GET['id']);
    $getq = $dbh->prepare('SELECT * FROM bots WHERE name=?');
    $getq->execute(array($id));
    $result = $getq->fetch(PDO::FETCH_OBJ);
    if(!$result) {
        $result->username = $id;
        $result->isBot = false;
        $result->type = null;
    }
    else {
        $result->username = $result->name;
        $result->isBot = true;
        unset($result->name);
    }
    $target = $result;
}
else if($_GET['endpoint'] == 'bot/all') {
    require_once __DIR__.'/../../twitchbots/lib/page.php';
    $pagecount = get_pagecount($_GET['type']);

    if($pagecount >= $offset / $pagesize) {
        $getq = $dbh->prepare('SELECT * FROM bots '.(isset($_GET['type']) ? 'WHERE type=:type ': '').'LIMIT :start,:stop');
        $getq->bindValue(":start", $offset, PDO::PARAM_INT);
        $getq->bindValue(":stop", $offset + $pagesize, PDO::PARAM_INT);
        if(isset($_GET['type']))
            $getq->bindValue(":type", (int)$_GET['type'], PDO::PARAM_INT);
        $getq->execute();
        $result = $getq->fetchAll(PDO::FETCH_ASSOC);
        $target['bots'] = array_map("set_link", $result);
    }
    else {
        $target['bots'] = array();
    }

    add_pagination_links($page, $pagecount, $apiurl."bot/all?", $target);
}
else if($_GET['endpoint'] == 'bot' && isset($_GET['bots'])) {
    require_once __DIR__.'/../../twitchbots/lib/page.php';
    $names = explode(",", $_GET['bots']);
    $namesCount = count($names);
    $pagecount = ceil($namesCount / $pagesize);

    if($namesCount <= ($page - 1) * $pagesize) {
        $target['bots'] = array();
    }
    else {
        $getq = $dbh->prepare('SELECT * FROM bots WHERE name IN ('.implode(',',array_fill(1, count($names),'?')).') LIMIT ?,?');
        foreach($names as $i => $n) {
            $getq->bindValue($i + 1, $n, PDO::PARAM_STR);
        }

        $getq->bindValue($namesCount + 1, $offset, PDO::PARAM_INT);
        $getq->bindValue($namesCount + 2, $offset + $pagesize, PDO::PARAM_INT);
        $getq->execute();

        $result = $getq->fetchAll(PDO::FETCH_ASSOC);

        $target['bots'] = array_map('set_link', $result);

        // Wonky workaround to cut off the _next link if we're apparently on the last page.
        if($getq->rowCount() < $pagesize && $page < $pagecount)
            $pagecount = $page;
    }
    add_pagination_links($page, $pagecount, $apiurl."bot?bots=".$_GET['bots']."&", $target);
}
else {
    header("Status: 404 Not Found");
    $target['error'] = "Not Found";
    $target['code'] = 404;
    return;
}

echo json_encode($target, JSON_NUMERIC_CHECK);
?>
