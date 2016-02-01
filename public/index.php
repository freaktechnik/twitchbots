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
    new \Slim\Views\TwigExtension()
);

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
        'csp' => "default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self'; connect-src https://api.twitchbots.info; form-action 'self'; frame-ancestors 'none'; reflected-xss block",
        'apiUrl' => $app->request->getUrl().dirname($app->request->getRootUri(), 1).'/api'
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
        'csp' => "default-src 'none'; style-src 'self'; script-src 'self'; font-src 'self'; connect-src https://api.twitchbots.info; form-action 'self'; frame-ancestors 'none'; reflected-xss block; base-uri twitchbots.info www.twitchbots.info; referrer no-referrer-when-downgrade",
        'apiUrl' => $app->request->getScheme().'://api.'.$app->request->getHost()
    ));

    $app->view->parserOptions = array(
        'cache' => '../cache'
    );
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$lastUpdate = 1454245011;

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

$app->get('/', function () use ($app, $model, $lastUpdate) {
    $app->lastModified(max($lastUpdate, $model->getLastUpdate()));
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
$app->get('/submit', function () use ($app, $model, $lastUpdate) {
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
        'channel' => $_GET['channel']
    ));
})->name('submit');
$app->map('/check', function () use ($app, $model, $lastUpdate) {
    $bot = null;
    if(null !== $app->request->params('username')) {
        if($app->request->isPost()
           && $app->request->get('username') !== null
           && $app->request->post('username') != $app->request->get('username'))
            $app->redirect($app->request->getUrl().$app->urlFor('check').'?username='.strtolower($app->request->post('username')), 303);

        $bot = $model->getBot($app->request->params('username'));
        if($bot) {
            $app->lastModified(max($lastModified, strtotime($bot->date)));
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
$app->get('/api', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('api.twig', array(
        'apiUrl' => $app->config('apiUrl')
    ));
});
$app->get('/about', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('about.twig');
});
$app->get('/submissions', function () use ($app, $model, $lastUpdate) {
    $app->expires('+1 minute');
    $submissions = $model->getSubmissions();
    if(count($submissions) > 0) {
        $app->lastModified(max($lastUpdate, strtotime($submissions[0]->date)));
    }
    else {
        $app->lastModified(time());
    }

    $app->render('submissions.twig', array(
        'submissions' => $submissions
    ));
});

$app->group('/types', function () use ($app, $model, $lastUpdate, $getLastMod) {
    $app->get('/', function () use ($app, $model, $lastUpdate) {
        $app->expires('+1 day');

        $pageCount = $model->getPageCount(null, $model->getCount('types'));
        $page = $_GET['page'] ?? 1;
        if(!is_numeric($page))
            $page = 1;

        if($page > $pageCount || $page < 0)
            $app->notFound();

        $app->lastModified(max($lastUpdate, $model->getLastUpdate(), $model->getLastUpdate('types')));

        $types = $model->getTypes($page);

        $app->render('types.twig', array(
            'types' => $types,
            'page' => $page,
            'pageCount' => $pageCount
        ));
    });

    $app->get('/sitemap.xml', function () use ($app, $model, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $botLastUpdate = $model->getLastUpdate();
        $typeLastUpdate = $model->getLastUpdate('types');
        $app->lastModified(max($botLastUpdate, $typeLastUpdate));
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $types = $model->getAllTypes();
        foreach($types as $type) {
            $url = $sitemap->addChild('url');
            $url->addChild('loc', 'https://twitchbots.info/types/'.$type->id);
            $url->addChild('changefreq', 'daily');
            $url->addChild('priority', '0.6');
            $url->addChild('lastmod', $getLastMod(max($type->date, $model->getLastUpdate('bots', $type->id))));
        }

        echo $sitemap->asXML();
    });

    $app->get('/:id', function ($id) use ($app, $model, $lastUpdate) {
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

        $app->lastModified(max($lastUpdate, $type->date, max(array_map(function($bot) { return $bot->date; }, $bots))));
        $app->render('type.twig', array(
            'type' => $type,
            'bots' => $bots,
            'page' => $page,
            'pageCount' => $pageCount
        ));
    })->name('type');
});

$app->group('/bots', function () use ($app, $model, $lastUpdate, $getLastMod) {
    $app->get('/', function () use ($app) {
        $app->redirect($app->request->getUrl().$app->urlFor('index'), 301);
    });

    $app->get('/sitemap.xml', function () use ($app, $model, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $app->lastModified($model->getLastUpdate());
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $bots = $model->getAllRawBots(0, $model->getBotCount());
        foreach($bots as $bot) {
            $url = $sitemap->addChild('url');
            $url->addChild('loc', 'https://twitchbots.info/bots/'.$bot->name);
            $url->addChild('changefreq', 'weekly');
            $url->addChild('lastmod', $getLastMod($bot->date));
            $url->addChild('priority', '0.8');
        }

        echo $sitemap->asXML();
    });

    $app->get('/:name', function ($name) use ($app, $model, $lastUpdate) {
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
        $app->lastModified(max($lastUpdate, $bot->date));
        $app->render('bot.twig', array(
            'bot' => $bot
        ));
    })->name('bot');
});

$app->group('/lib', function ()  use ($app, $model) {
    $app->get('/check', function ()  use ($app, $model) {
        if($model->checkRunning()) {
            $app->halt(500, 'Check already running');
        }
        else {
            echo 'Checked bots. Removed: ';
            print_r($model->checkBots());
        }
    });

    $app->put('/submit', function () use ($app, $model) {
        $echoParam = function (string $name, $first = false) use ($app) {
            return ($first?'?':'&').$name.'='.$app->request->params($name);
        };
        //TODO should some of these checks be in the model?
        if($model->checkToken("submit", $app->request->params('token'))) {
            if($app->request->params('channel') && !$model->twitchUserExists($app->request->params('channel'))) {
                $correction = $app->request->params('submission-type') == "0" ? "" : "&correction";
                $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=6'.$echoParam('username').$echoParam('type').$echoParam('channel').$correction, 303);
            }
            else if((boolean)$app->request->params('submission-type')) {
                if(!$model->botSubmitted($app->request->params('username'))) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=4&correction'.$echoParam('username').$echoParam('type').$echoParam('channel'), 303);
                }
                else if($model->getBot($app->request->params('username'))->type == $app->request->params('type')) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=5&correction'.$echoParam('username').$echoParam('channel'), 303);
                }
                else {
                    $model->addCorrection(
                        $app->request->params('username'),
                        $app->request->params('type'),
                        $app->request->params('description'),
                        $app->request->params('channel')
                    );
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1&correction', 303);
                }
            }
            else {
                if(!$model->twitchUserExists($app->request->params('username'))) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=2'.$echoParam('username').$echoParam('type').$echoParam('channel'), 303);
                }
                else if($model->botSubmitted($app->request->params('username'))) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=3'.$echoParam('username').$echoParam('type').$echoParam('channel'), 303);
                }
                else {
                    $model->addSubmission(
                        $app->request->params('username'),
                        $app->request->params('type'),
                        $app->request->params('description'),
                        $app->request->params('channel')
                    );
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1', 303);
                }
            }
        }
        else {
            $correction = $app->request->params('submission-type') == "0" ? "" : "&correction";
            $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=1'.$echoParam('username').$echoParam('type').$echoParam('channel').$correction);
        }
    });
});

$app->get('/pages_map.xml', function () use ($app, $model, $lastUpdate, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');

    $subLastUpdate = $model->getLastUpdate('submissions');
    $botLastUpdate = $model->getLastUpdate();
    $typeLastUpdate = $model->getLastUpdate('types');
    $app->lastModified(max($subLastUpdate, $botLastUpdate, $typeLastUpdate));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    $lastBotUpdate = $model->getLastUpdate();
    $lastTypeUpdate = $model->getLastUpdate('types');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod($botLastUpdate));
    $url->addChild('priority', '1.0');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/types');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($botLastUpdate, $typeLastUpdate)));
    $url->addChild('priority', '0.2');

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/submit');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod());

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/check');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod());

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/api');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod());

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/about');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod());

    $url = $sitemap->addChild('url');
    $url->addChild('loc', 'https://twitchbots.info/submissions');
    $url->addChild('changefreq', 'daily');
    $url->addChild('priority', '0.2');
    $url->addChild('lastmod', $getLastMod($subLastUpdate));

    echo $sitemap->asXML();
});
$app->get('/sitemap.xml', function() use ($app, $model, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');
    $botLastUpdate = $model->getLastUpdate();
    $typeLastUpdate = $model->getLastUpdate('types');
    $subLastUpdate = $model->getLastUpdate('submissions');
    $app->lastModified(max($botLastUpdate, $typeLastUpdate, $subLastUpdate));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', 'https://twitchbots.info/pages_map.xml');
    $url->addChild('lastmod', $getLastMod(max($subLastUpdate, $typeLastUpdate, $botLastUpdate)));

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', 'https://twitchbots.info/bots/sitemap.xml');
    $url->addChild('lastmod', $getLastMod($botLastUpdate));

    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', 'https://twitchbots.info/types/sitemap.xml');
    $url->addChild('lastod', $getLastMod($typeLastUpdate));

    echo $sitemap->asXML();
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
