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
                        <li<?php if($_GET['site'] == "check") { ?> class="active"<?php } ?>><a href="/check">Check User</a></li>
                        <li<?php if($_GET['site'] == "api") { ?> class="active"<?php } ?>><a href="/api">API</a></li>
                        <li<?php if($_GET['site'] == "about") { ?> class="active"<?php } ?>><a href="/about">About</a></li>
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
include_once __DIR__.'/lib/page.php';
require_once __DIR__.'/lib/db.php';
$getc = $dbh->prepare("SELECT count FROM count");
$getc->execute();
$itemcount = $getc->fetch(PDO::FETCH_OBJ);
$pagecount = ceil($itemcount->count / (float)$pagesize);

if($pagecount >= $page) {
    $getq = $dbh->prepare('SELECT * FROM list LIMIT :start,:stop');
    $getq->bindValue(":start", $offset, PDO::PARAM_INT);
    $getq->bindValue(":stop", $offset + $pagesize, PDO::PARAM_INT);
    $getq->execute();
    $result = $getq->fetchAll(PDO::FETCH_OBJ);
}
else {
    $result = array();
}
if(count($result) > 0) {
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
                <ul class="pagination">
                    <li<?php if($page <= 1) echo ' class="disabled"'; ?>>
                        <a href="?page=<?php echo $page > 1 ? $page-1 : "#"; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php
                        for($i = max($page - 3, 1); $i <= $pagecount && $i < $page; ++$i) {
                            ?><li><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php
                        }
                        if($page <= $pagecount) { ?>
                        <li class="active"><a href="?page=<?php echo $page; ?>">
                            <?php echo $page; ?> <span class="sr-only">(current)</span>
                        </a></li><?php
                        }
                        else { ?>
                        <li class="disabled"><a href="?page=<?php echo $pagecount; ?>">
                            <span class="glyphicon glyphicon-remove" aria-hidden="true"></span>
                            <span class="sr-only">Invalid page</span>
                        </a></li><?php
                        }
                        for($i = $page + 1; $i <= $pagecount && $i < $page + 3; ++$i) {
                            ?><li><a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php
                        }
                    ?>
                    <li<?php if($page >= $pagecount) echo ' class="disabled"'; ?>>
                        <a href="?page=<?php echo $page < $pagecount ? $page+1: "#"; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div><?php }
else if($_GET['site'] == "submit") {
session_start();
require_once __DIR__.'/lib/csrf.php';?>
        <div class="container" id="submit">
            <div>
                <h1>Submit a new bot</h1>
                <p class="lead">If you know about a Twitch account that is used as a helpful chat bot, please tell us about it with the form below and we'll review the information. If you have a bigger dataset to submit, please contact us directly.</p>
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
                        <p class="help-block">Describe the bot type, normally the name of the software that runs it and if possible a link to the website of it.</p>
                    </div>
                    <input type="hidden" value="<?php echo generate_token("submit"); ?>" name="token">
                    <button type="submit" class="btn btn-default">Submit</button>
                </form>
            </div>
        </div><? }
else if($_GET['site'] == "api") { ?>
        <div class="container" id="api">
            <div>
                <h1>API Acess</h1>
                <p>All the API endpoints are on the base URL <code>http://api.twitchbots.info/v1/</code>. All endpoints only accept GET requests. The API always returns JSON. Feel free to reuse data returned by this API in your own services and APIs, however please consider contributing relevant data back to this service.</p>
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
    "type": 1
}</pre>
            </div>
            <div>
                <h2>/bot/all</h2>
                <p>Returns all known bots.</p>
                <h3>Parameters</h3>
                <dl class="dl-horizontal">
                    <dt><code>page</code></dt>
                    <dd>Page number, 1 by default</dd>
                    <dt><code>type</code></dt>
                    <dd>Optionally only return bots of the given type ID</dd>
                </dl>
                <h3>Response</h3>
                <code>GET http://api.twitchbots.info/v1/bot/all</code>
                <pre>
{
    "bots: [
        {
            "username": "nightbot",
            "type": 1,
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
                <p>Check multiple user's bot status. Only returns users that are registered as bots.</p>
                <h3>Parameters</h3>
                <dl class="dl-horizontal">
                    <dt><code>bots</code></dt>
                    <dd>Comma separated list of usernames to check</dd>
                    <dt><code>page</code></dt>
                    <dd>Page number, 1 by default</dd>
                </dl>
                <h3>Response</h3>
                <code>GET http://api.twitchbots.info/v1/bot?bots=nightbot</code>
                <pre>
{
    "bots: [
        {
            "username": "nightbot",
            "type": 1,
            "_link": "http://api.twitchbots.info/v1/bot/nightbot"
        }
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
                <code>GET http://api.twitchbots.info/v1/type/1</code>
                <pre>
{
    "id": 1,
    "name": "Nightbot",
    "multiChannel": true,
    "url": "https://www.nightbot.tv"
}</pre>
            </div>
        </div><? }
else if($_GET['site'] == "about") {?>
        <div class="container">
            <div>
                <h1>About this Service</h1>
                <p class="lead">twitchbots.info is a service that tries to collect all moderator and other serivce bots used in chats on <a href="https://twitch.tv">Twitch</a>. It is ran independently and developed as a hobby project.</p>
                <p>The main reason to collect accounts that are used as bots is to identify them as bots in Twitch chat clients. Another use case are bots that distribute points, which can ignore other bots using this directory.</p>
                <p>This site uses cookies to prevent submissions other than from the form on this site.</p>
                <p>Website by <a href="http://humanoids.be">Martin Giger</a> with Bootstrap. Source code lincensed under the MIT is available on <a href="https://github.com/freaktechnik/twitchbots/">GitHub</a>.</p>
            </div>
        </div><? }
else if($_GET['site'] == "check") { ?>
        <div class="container">
            <div>
                <h1>Check a User</h1>
                <p>Get the bot status for a specific username.</p>
                <form class="input-group" id="checkform">
                    <input type="text" class="form-control" placeholder="Username" id="checkuser">
                    <span class="input-group-btn">
                        <button type="submit" class="btn btn-default">Check</button>
                    </span>
                </form>
                <div class="alert" id="checkloading" hidden>
                    Loading...
                </div>
                <div class="alert" id="botuser" hidden>
                    <span class="name"></span> is a bot.
                </div>
                <div class="alert" id="realuser" hidden>
                    <span class="name"></span> is not a bot.
                </div>
            </div>
            <script src="js/check.js"></script>
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
                <p class="text-muted">This is an independent site not run by Twitch.</p>
            </div>
        </footer>

        <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
        <script src="bower_components/jquery/dist/jquery.min.js"></script>
        <!-- Include all compiled plugins (below), or include individual files as needed -->
        <script src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
    </body>
</html>
