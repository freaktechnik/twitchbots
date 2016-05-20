<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

$mode = $_SERVER['MODE'] ?? 'development';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => $mode
));

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("../Mini/view");

$app->view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \Mini\Twig\Extension\GeshiExtension()
);

/********************************************* GUZZLE **********************************************************/
$stack = \GuzzleHttp\HandlerStack::create();

$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
    new \Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy(
        new \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage(
            new \Doctrine\Common\Cache\ChainCache([
                new \Doctrine\Common\Cache\ArrayCache(),
                new \Doctrine\Common\Cache\FilesystemCache('../guzzle-cache'),
            ])
        )
    )
), 'cache');

$client = new \GuzzleHttp\Client(array('handler' => $stack));

/******************************************* THE CONFIGS *******************************************************/

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {
    // Set the configs for development environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => true,
        'database' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 50
        ),
        'csp' => "default-src 'none'; style-src 'self'; script-src 'self' https://humanoids.be; font-src 'self'; connect-src https://api.twitchbots.info https://api.twitch.tv; form-action 'self'; frame-ancestors 'none'; reflected-xss block; child-src https://humanoids.be; frame-src https://humanoids.be; img-src https://humanoids.be",
        'apiUrl' => $app->request->getUrl().dirname($app->request->getRootUri(), 1).'/api',
        'canonicalUrl' => 'https://'.$app->request->getHost().$app->request->getRootUri().'/'
    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => false,
        'database' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 50
        ),
        'csp' => "default-src 'none'; style-src 'self'; script-src 'self' https://humanoids.be; font-src 'self'; connect-src https://api.twitchbots.info https://api.twitch.tv; form-action 'self'; frame-ancestors 'none'; reflected-xss block; base-uri twitchbots.info www.twitchbots.info; referrer no-referrer-when-downgrade; child-src https://humanoids.be; frame-src https://humanoids.be; img-src https://humanoids.be",
        'apiUrl' => $app->request->getScheme().'://api.'.$app->request->getHost(),
        'canonicalUrl' => 'https://'.$app->request->getHost().'/'
    ));

    $app->view->parserOptions = array(
        'cache' => '../cache'
    );
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'), $client);

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$app->view->getEnvironment()->addGlobal('canonicalUrl', $app->config('canonicalUrl'));

$lastUpdate = 1456073551;

$getTemplateLastMod = function(string $templateName) {
    return max(filemtime('../Mini/view/_base.twig'), filemtime('../Mini/view/'.$templateName));
};

$getLastMod = function($timestamp = 0) use ($lastUpdate) {
    return date('c', max($lastUpdate, $timestamp));
};

$app->response->headers->set('Content-Security-Policy', $app->config('csp'));

$app->notFound(function () use ($app) {
    $app->render('error.twig', array(
        'code' => 404,
        'name' => 'Page Not Found',
        'message' => 'The page you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly. Click a link above to go back to an existing page.'
    ));
});

$app->get('/', function () use ($app, $model, $getTemplateLastMod) {
    $app->lastModified(max($model->getLastUpdate(), $getTemplateLastMod('index.twig')));
    $app->expires('+1 day');

    $pageCount = $model->getPageCount();
    $page = $_GET['page'] ?? 1;
    if(!is_numeric($page))
        $page = 1;

    if($page <= $pageCount && $page > 0)
        $bots = $model->getBots($page);
    else
        $bots = array();

    $app->render('index.twig', array(
        'pageCount' => $pageCount,
        'page' => $page,
        'bots' => $bots
    ));
})->name('index');
$app->get('/submit', function () use ($app, $model) {
    $token = $model->getToken("submit");
    $types = $model->getAllTypes();

    $app->render('submit.twig', array(
        'success' => (boolean)$_GET['success'],
        'error' => (int)$_GET['error'],
        'token' => $token,
        'types' => $types,
        'correction' => isset($_GET['correction']),
        'username' => $_GET['username'],
        'type' => (int)$_GET['type'],
        'channel' => $_GET['channel'],
        'description' => $_GET['description']
    ));
})->name('submit');
$app->map('/check', function () use ($app, $model, $getTemplateLastMod) {
    $lastUpdate = $getTemplateLastMod('check.twig');
    $bot = null;
    if(null !== $app->request->params('username')) {
        if($app->request->isPost()
           && $app->request->get('username') !== null
           && $app->request->post('username') != $app->request->get('username'))
            $app->redirect($app->request->getUrl().$app->urlFor('check').'?username='.strtolower($app->request->post('username')), 303);

        $bot = $model->getBot($app->request->params('username'));
        if($bot) {
            $app->lastModified(max($lastUpdate, strtotime($bot->date)));
            $app->expires('+1 week');
            if($bot->type) {
                $type = $model->getType($bot->type);
                $bot->typename = $type->name;
                $bot->url = $type->url;
                $bot->multichannel = $type->multichannel;
            }
        }
        else {
            $app->lastModified($lastUpdate);
            $app->expires('+1 day');
        }
    }
    else {
        $app->lastModified($lastUpdate);
        $app->expires('+1 week');
    }

    $app->render('check.twig', array(
        'username' => $app->request->params('username'),
        'bot' => $bot
    ));
})->via('GET', 'POST')->name('check');
$app->get('/api', function () use ($app, $getTemplateLastMod) {
    $app->lastModified($getTemplateLastMod('api.twig'));
    $app->expires('+1 week');
    $app->render('api.twig', array(
        'apiUrl' => $app->config('apiUrl')
    ));
});
$app->get('/about', function () use ($app, $getTemplateLastMod) {
    $app->lastModified($getTemplateLastMod('about.twig'));
    $app->expires('+1 week');
    $app->render('about.twig');
});
$app->get('/submissions', function () use ($app, $model, $getTemplateLastMod) {
    $app->expires('+1 minute');
    $submissions = $model->getSubmissions();
    if(count($submissions) > 0) {
        $app->lastModified(max($getTemplateLastMod('submissions.twig'), strtotime($submissions[0]->date)));
    }
    else {
        $app->lastModified(time());
    }

    $app->render('submissions.twig', array(
        'submissions' => $submissions
    ));
});

$app->group('/types', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->get('/', function () use ($app, $model, $getTemplateLastMod) {
        $app->expires('+1 day');

        $pageCount = $model->getPageCount(null, $model->getCount('types'));
        $page = $_GET['page'] ?? 1;
        if(!is_numeric($page))
            $page = 1;

        if($page > $pageCount || $page < 0)
            $app->notFound();

        $app->lastModified(max($getTemplateLastMod('types.twig'), $model->getLastUpdate(), $model->getLastUpdate('types')));

        $types = $model->getTypes($page);

        $app->render('types.twig', array(
            'types' => $types,
            'page' => $page,
            'pageCount' => $pageCount
        ));
    });

    $app->get('/sitemap.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $botLastUpdate = $model->getLastUpdate();
        $typeLastUpdate = $model->getLastUpdate('types');
        $app->lastModified(max($botLastUpdate, $typeLastUpdate));
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $types = $model->getAllTypes();
        $typeLastMod = $getTemplateLastMod('type.twig');
        foreach($types as $type) {
            $url = $sitemap->addChild('url');
            $url->addChild('loc', $app->config('canonicalUrl').'types/'.$type->id);
            $url->addChild('changefreq', 'daily');
            $url->addChild('lastmod', $getLastMod(max($typeLastMod, strtotime($type->date), $model->getLastUpdate('bots', $type->id))));
            $url->addChild('priority', '0.6');
        }

        echo $sitemap->asXML();
    });

    $app->get('/:id', function ($id) use ($app, $model, $getTemplateLastMod) {
        $app->expires('+1 day');

        $type = $model->getType($id);
        if(!$type)
            $app->notFound();

        $pageCount = max(1, $model->getPageCount(null, $model->getBotCount($id)));
        $page = $_GET['page'] ?? 1;
        if(!is_numeric($page))
            $page = 1;

        if($page > $pageCount || $page < 0)
            $app->notFound();

        $bots = $model->getBotsByType($id, $model->getOffset($page));

        $app->lastModified(max($getTemplateLastMod('type.twig'), strtotime($type->date), max(array_map(function($bot) { return strtotime($bot->date); }, $bots))));
        $app->render('type.twig', array(
            'type' => $type,
            'bots' => $bots,
            'page' => $page,
            'pageCount' => $pageCount
        ));
    })->conditions(array('id' => '[1-9][0-9]*'))->name('type');
});

$app->group('/bots', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->get('/', function () use ($app) {
        $app->redirect($app->request->getUrl().$app->urlFor('index'), 301);
    });

    $app->get('/sitemap.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $app->lastModified($model->getLastUpdate());
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $bots = $model->getAllRawBots(0, $model->getBotCount());
        $botLastMod = $getTemplateLastMod('bot.twig');
        foreach($bots as $bot) {
            $url = $sitemap->addChild('url');
            $url->addChild('loc', $app->config('canonicalUrl').'bots/'.$bot->name);
            $url->addChild('changefreq', 'weekly');
            $url->addChild('lastmod', $getLastMod(max($botLastMod, strtotime($bot->date))));
            $url->addChild('priority', '0.8');
        }

        echo $sitemap->asXML();
    });

    $app->get('/:name', function ($name) use ($app, $model, $getTemplateLastMod) {
        $bot = $model->getBot($name);
        if(!$bot)
            $app->notFound();

        if($bot->type) {
            $type = $model->getType($bot->type);
            $bot->typename = $type->name;
            $bot->url = $type->url;
            $bot->multichannel = $type->multichannel;
        }

        $app->expires('+1 week');
        $app->lastModified(max($getTemplateLastMod('bot.twig'), strtotime($bot->date)));
        $app->render('bot.twig', array(
            'bot' => $bot
        ));
    })->conditions(array('name' => '[a-zA-Z0-9_]+'))->name('bot');
});

$app->group('/lib', function ()  use ($app, $model) {
    $app->get('/check', function ()  use ($app, $model) {
        if(!isset($_GET['token'])) {
            $app->halt(400, 'Token missing');
        }
        try {
            $canCheck = $model->canCheck($_GET['token']);
        }
        catch(Exception $e) {
            $canCheck = false;
        }
        if(!$canCheck) {
            $app->halt(403, 'Invalid token');
        }

        if($model->checkRunning()) {
            $app->halt(500, 'Check already running');
        }
        else {
            echo 'Checked bots. Removed: ';
            print_r($model->checkBots());
            echo 'Checked '.$model->checkSubmissions().' submissions.';
            echo 'Added '.$model->typeCrawl().' bots based on vendor lists';
        }
    });

    $app->put('/submit', function () use ($app, $model) {
        $echoParam = function (string $name, $first = false) use ($app) {
            return ($first?'?':'&').$name.'='.$app->request->params($name);
        };

        $correction = $app->request->params('submission-type') == "0" ? "" : "&correction";
        try {
            if(!$model->checkToken("submit", $app->request->params('token'))) {
                throw new Exception("CSRF token mismatch", 1);
            }
            else if(!(boolean)$app->request->params('username') || $app->request->params('type') == "") {
                throw new Exception("Required params cannot be empty", 8);
            }
            else if((boolean)$app->request->params('submission-type')) {
                $model->addCorrection(
                    strtolower($app->request->params('username')),
                    $app->request->params('type'),
                    $app->request->params('description'),
                    strtolower($app->request->params('channel'))
                );
            }
            else {
                $model->addSubmission(
                    strtolower($app->request->params('username')),
                    $app->request->params('type'),
                    $app->request->params('description'),
                    strtolower($app->request->params('channel'))
                );
            }
        }
        catch(Exception $e) {
            $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error='.$e->getCode().$echoParam('username').$echoParam('type').$echoParam('channel').$echoParam('description').$correction, 303);
        }
        $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1'.$correction, 303);
    });
});

$app->get('/pages_map.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');

    $subLastUpdate = $model->getLastUpdate('submissions');
    $botLastUpdate = $model->getLastUpdate();
    $typeLastUpdate = $model->getLastUpdate('types');
    $templateUpdates = array(
        "index" => $getTemplateLastMod('index.twig'),
        "types" => $getTemplateLastMod('types.twig'),
        "submit" => $getTemplateLastMod('submit.twig'),
        "check" => $getTemplateLastMod('check.twig'),
        "api" => $getTemplateLastMod('api.twig'),
        "about" => $getTemplateLastMod('about.twig'),
        "submissions" => $getTemplateLastMod('submissions.twig')
    );
    $app->lastModified(max(max($templateUpdates), $subLastUpdate, $botLastUpdate, $typeLastUpdate));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    $lastBotUpdate = $model->getLastUpdate();
    $lastTypeUpdate = $model->getLastUpdate('types');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl'));
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['index'], $botLastUpdate)));
    $url->addChild('priority', '1.0');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'types');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['types'], $botLastUpdate, $typeLastUpdate)));
    $url->addChild('priority', '0.3');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'submit');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['submit'], $typeLastUpdate)));

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'check');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['check']));

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'api');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['api']));

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'about');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['about']));

    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'submissions');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['submissions'], $subLastUpdate)));
    $url->addChild('priority', '0.2');

    echo $sitemap->asXML();
});
$app->get('/sitemap.xml', function() use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');
    $botLastUpdate = $model->getLastUpdate();
    $typeLastUpdate = $model->getLastUpdate('types');
    $subLastUpdate = $model->getLastUpdate('submissions');
    $templateUpdates = array(
        "type" => $getTemplateLastMod('type.twig'),
        "bot" => $getTemplateLastMod('bot.twig'),
        "pages" => max(array_map("filemtime", glob('../Mini/view/*.twig')))
    );
    $app->lastModified(max($botLastUpdate, $typeLastUpdate, $subLastUpdate, $templateUpdates['pages']));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'pages_map.xml');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['pages'], $subLastUpdate, $typeLastUpdate, $botLastUpdate)));

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'bots/sitemap.xml');
    $url->addChild('lastmod', $getLastMod(max($botLastUpdate, $templateUpdates['bot'])));

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'types/sitemap.xml');
    $url->addChild('lastmod', $getLastMod(max($typeLastUpdate, $templateUpdates['type'])));

    echo $sitemap->asXML();
});

$app->get('/apis.json', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');

    $app->contentType('application/json;charset=utf8');
    $app->response->headers->set('Access-Control-Allow-Origin', '*');

    $martin = array(
        "FN" => "Martin Giger",
        "email" => "martin@humanoids.be",
        "url" => "https://humanoids.be",
        "X-twitter" => "freaktechnik",
        "X-github" => "freaktechnik"
    );
    $tags = array("twitch", "chat", "bot", "stream", "spam", "mod", "coin", "token", "vanity");

    $spec = array(
        "name" => "Twitch Bot Directory",
        "description" => "Sadly Twitch accounts can't be marked as a bot. But many accounts are used just as a chat bot. This service provides an API to find out who's a chat bot. All bots indexed are service or moderator bots.",
        "url" => $app->config('canonicalUrl')."apis.json",
        "tags" => $tags,
        "image" => "https://i.imgur.com/oRoYDOd.png",
        "created" => "2016-02-14",
        "modified" => date('Y-m-d', $lastUpdate),
        "specificationVersion" => "0.14",
        "apis" => array(
            array(
                "name" => "Twitch Bots API",
                "description" => "Check if a Twitch user is a bot and if it is a bot, what kind of bot it is.",
                "humanURL" => $app->config('canonicalUrl').'api',
                "baseURL" => $app->config('apiUrl'),
                "version" => "v1",
                "tags" => $tags,
                "image" => "https://i.imgur.com/oRoYDOd.png",
                "properties" => array(),
                "contact" => array( $martin )
            )
        ),
        "maintainers" => array( $martin )
    );

    echo json_encode($spec);
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
