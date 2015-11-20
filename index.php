<?php
session_start();
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/csrf.php';

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Twitch Bots Directory</title>

        <!-- Bootstrap -->
        <link href="bower_components/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body>
        <nav class="navbar navbar-default">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-nav" aria-expanded="false">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="/">Twitch Bot Directory</a>
                </div>

                <div class="collapse navbar-collapse" id="main-nav">
                    <ul class="nav navbar-nav">
                        <li<?php if($_GET['site'] == "index") { ?> class="active"<?php } ?>><a href="/">Known Bots <span class="sr-only">(current)</span></a></li>
                        <li<?php if($_GET['site'] == "submit") { ?> class="active"<?php } ?>><a href="/submit">Submit Bot</a></li>
                        <li<?php if($_GET['site'] == "api") { ?> class="active"<?php } ?>><a href="/api">API</a></li>
                    </ul>
                </div>
            </div>
        </nav>
<?php
if($_GET['site'] == "index") {
?>        <div class="container">
            <div>
                <h1>Find out if a Twitch user is a Chat Bot</h1>
                <p class="lead">Sadly Twitch accounts can't be marked as a bot. But many accounts are used just as a chat bot. This service provides an API to find out who's a chat bot. All bots listed are service or moderator bots.</p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Multi-channel</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
<?php
$page = 1;
$pagesize = 100;
if((int)$_GET['page'] > 1) {
    $page = (int)$_GET['page'];
}
$offset = ($page-1) * $pagesize;
$dbh = new PDO('mysql:host=localhost;dbname='.$db.';charset=utf8', $db_user, $db_pw);
$getq = $dbh->prepare('SELECT * FROM list LIMIT :start,:stop');
$getq->bindValue(":start", $offset, PDO::PARAM_INT);
$getq->bindValue(":stop", $offset + $pagesize, PDO::PARAM_INT);
$getq->execute();
$result = $getq->fetchAll(PDO::FETCH_OBJ);
if(!is_null($result)) {
    foreach($result as $r) { ?>
                        <tr>
                            <td><?php echo $r->name; ?></td>
                            <td><?php echo $r->multichannel == 1 ? "Yes": "No"; ?></td>
                            <td><a href="<?php echo $r->url; ?>"><?php echo $r->typename; ?></a></td>
                        </tr><?php
    }
}
?>
                    </tbody>
                </table>
            </div>
            <nav class="text-center">
                <?php
                    $otherpages = 2;
                    if($page > 1) {
                    }
                    $getc = $dbh->prepare("SELECT count FROM count");
                    $getc->execute();
                    $itemcount = $getc->fetch(PDO::FETCH_OBJ);
                    $pagecount = ceil($itemcount->count / (float)$pagesize);
                ?><ul class="pagination">
                    <li<?php if($page <= 1) echo ' class="disabled"'; ?>>
                        <a href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php
                        for($i = $page - 1; $i > 0 && $i > $page - 3; --$i) {
                            ?><li><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php
                        }
                        ?><li class="active"><a href="?page=<?php echo $page; ?>"><?php echo $page; ?> <span class="sr-only">(current)</span></a></li><?php
                        for($i = $page + 1; $i < $pagecount && $i < $page + 3; ++$i) {
                            ?><li><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php
                        }
                    ?>
                    <li<?php if($page >= $pagecount) echo ' class="disabled"'; ?>>
                        <a href="?page=<?php echo $page+1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div><?php }
else if($_GET['site'] == "submit") {
?>        <div class="container" id="submit">
            <div>
                <h1>Submit a new bot</h1>
                <p class="lead">If you know about a Twitch account that is used as a helpful chat bot, please tell use about it with the form below and we'll review the information.</p>
            </div>
<?php if($_GET['success']) { ?>
            <div class="alert alert-success" role="alert">
                <span class="glyphicon glyphicon-ok" aria-hidden="true"></span>
                <span class="sr-only">Success:</span>
                Your submission has been saved. We will review it as soon as possible.
            </div>
<?php } ?>
<?php if($_GET['error']) { ?>
            <div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign aria-hidden="true"></span>
                <span class="sr-only">Error:</span>
                Something went wrong while submitting.
            </div>
<?php } ?>
            <div class="panel panel-default">
                <form class="panel-body" method="post" action="lib/submit.php">
                    <div class="form-group">
                        <label for="username">Twitch Username</label>
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username">
                    </div>
                    <div class="form-group">
                        <label for="type">Bot Type</label>
                        <input type="text" class="form-control" id="type" name="description" placeholder="Type">
                        <p class="help-block">Describe the type of the bot, normally the name of the software that runs it and if possible a link to the website of it.</p>
                    </div>
                    <div hidden>
                        <input type="text" value="<?php echo generate_token("submit"); ?>" name="token">
                    </div>
                    <button type="submit" class="btn btn-default">Submit</button>
                </form>
            </div>
        </div><? }
else if($_GET['site'] == "api") {
?>        <div class="container" id="api">
            <div class="alert alert-warning" role="alert">The API documented here is not yet implemented.</div>
            <div>
                <h1>API Acess</h1>
                <p>All the API endpoints are on the base URL <code>http://api.twitchbots.info/v1/</code>. All endpoints only accept GET requests. The API always returns JSON.</p>
            </div>
            <div>
                <h2>/bot/:name</h2>
                <p>Replace <code>:name</code> with the username of the Twitch user to check.</p>
                <h3>Response</h3>
                <p><code>type</code> is <code>null</code> if the user is not a bot.</p>
                <code>GET http://api.twitchbots.info/v1/bot/nightbot</code>
                <pre>
{
    "username": "nightbot",
    "isBot": true,
    "type": 0
}</pre>
            </div>
            <div>
                <h2>/bot/all</h2>
                <p>Returns all known bots.</p>
                <h3>Parameters</h3>
                <dl class="dl-horizontal">
                    <dt><code>page</code></dt>
                    <dd>Page number, 1 by default</dd>
                </dl>
                <h3>Response</h3>
                <code>GET http://api.twitchbots.info/v1/bot/all</code>
                <pre>
{
    "bots: [
        {
            "username": "nightbot",
            "type": 0,
            "_link": "http://api.twitchbots.info/v1/bot/nightbot"
        },
        ...
    ],
    "page": 1,
    "_next": "http://api.twitchbots.info/v1/bot/all?page=2",
    "_prev": null
}</pre>
            </div>
            <div>
                <h2>/bot</h2>
                <p>Check multiple user's bot status.</p>
                <h3>Parameters</h3>
                <dl class="dl-horizontal">
                    <dt><code>bots</code></dt>
                    <dd>Comma separated list of usernames to check</dd>
                    <dt><code>page</code></dt>
                    <dd>Page number, 1 by default</dd>
                </dl>
                <h3>Response</h3>
                <code>GET http://api.twitchbots.info/bot?bots=nightbot</code>
                <pre>
{
    "bots: [
        {
            "username": "nightbot",
            "isBot": true,
            "type": 0,
            "_link": "http://api.twitchbots.info/v1/bot/nightbot"
        },
        ...
    ],
    "page": 1,
    "_next": null,
    "_prev": null
}</pre>
            </div>
            <div>
                <h2>/type/:id</h2>
                <p>Replace <code>:id</code> with the id of the type you want to get.</p>
                <h3>Response</h3>
                <code>GET http://api.twitchbots.info/v1/type/0</code>
                <pre>
{
    "id": 0,
    "name": "Nightbot",
    "multiChannel": true,
    "url": "https://www.nightbot.tv"
}</pre>
            </div>
        </div><? }
else {
?>
        <div class="container">
            <div>
                <h1>This page doesn't exist</h1>
                <p class="lead">Yeah, I know, that's a paradox. Just click on one of the links above or below to fix that.</p>
            </div>
        </div><?php } ?>

        <footer class="footer">
            <div class="container">
                <p class="text-muted">Website by <a href="http://humanoids.be">Martin Giger</a> with Bootstrap. Source code lincensed under the MIT is available on <a href="https://github.com/freaktechnik/twitchbots/">GitHub</a>.</p>
            </div>
        </footer>

        <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
        <script src="bower_components/jquery/dist/jquery.min.js"></script>
        <!-- Include all compiled plugins (below), or include individual files as needed -->
        <script src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    </body>
</html>
