<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

use Mini\Model\BotListDescriptor;

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
//TODO load auth0 domain from db?
$cspBase = "default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' https://humanoids.be https://cdn.auth0.com https://cdn.eu.auth0.com; font-src 'self'; connect-src https://api.twitchbots.info https://api.twitch.tv https://twitchbots.eu.auth0.com 'self'; form-action 'self'; frame-ancestors 'none'; reflected-xss block; child-src https://humanoids.be; frame-src https://humanoids.be; img-src https://humanoids.be 'self' https://cdn.auth0.com data:";

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app, $cspBase) {
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
        'csp' => $cspBase,
        'apiUrl' => $app->request->getUrl().dirname($app->request->getRootUri(), 1).'/api',
        'canonicalUrl' => 'https://'.$app->request->getHost().$app->request->getRootUri().'/'
    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app, $cspBase) {
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
        'csp' => $cspBase."; base-uri twitchbots.info www.twitchbots.info",
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
$app->view->getEnvironment()->addGlobal('baseUrl', 'https://'.$app->request->getHost());

$lastUpdate = 1456073551;

$getTemplateLastMod = function(string $templateName): int
{
    return max(filemtime('../Mini/view/_base.twig'), filemtime('../Mini/view/'.$templateName));
};

$getLastMod = function(int $timestamp = 0) use ($lastUpdate): string
{
    return date('c', max($lastUpdate, $timestamp));
};

$piwikEvent = function(string $event, array $opts) use ($piwik_token, $app, $client): void
{
    $url = "https://humanoids.be/stats/piwik.php?idsite=5&rec=1&action_name=Submit/".urlencode($event)."&url=".urlencode($app->config('canonicalUrl'))."lib/submit&apiv=1&token_auth=".$piwik_token."&send_image=0&idgoal=1&rand=".random_int(0, 10000000);
    foreach($opts as $i => $val) {
        $url .=  "&dimension".$i."=".urlencode($val);
    }
    // Respect Do not track.
    if($_SERVER['HTTP_DNT'] != 1) {
        $url .= "&ua=".urlencode($_SERVER['HTTP_USER_AGENT'])."&lang=".urlencode($_SERVER['HTTP_ACCEPT_LANGUAGE'])."&cip=".urlencode($_SERVER['REMOTE_ADDR'])."&urlref=".urlencode($_SERVER['HTTP_REFERER'])."&_id=".substr(session_id(), 16);
    }
    $client->request('GET', $url, [
        'http_errors' => false,
        'synchronous' => false
    ]);
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
    $app->lastModified(max($model->bots->getLastUpdate(), $model->types->getLastUpdate(), $getTemplateLastMod('index.twig')));
    $app->expires('+1 day');

    $botCount = $model->bots->getCount();
    $typeCount = $model->types->getCount();
    $topTypes = $model->types->getTop(10);

    $app->render('index.twig', [
        'bots' => $botCount,
        'types' => $typeCount,
        'topTypes' => $topTypes
    ]);
})->name('index');

$app->get('/submit', function () use ($app, $model) {
    $token = $model->getToken("submit");
    $types = $model->types->getAllTypes();

    $app->render('submit.twig', array(
        'success' => (boolean)$_GET['success'],
        'error' => (int)$_GET['error'],
        'token' => $token,
        'types' => $types,
        'correction' => isset($_GET['correction']),
        'username' => $_GET['username'],
        'type' => (int)$_GET['type'],
        'channel' => $_GET['channel'],
        'description' => $_GET['description'],
        'clientId' => $model->getClientID()
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

        $bot = $model->bots->getBot($app->request->params('username'));
        if($bot) {
            $app->lastModified(max($lastUpdate, strtotime($bot->date)));
            $app->expires('+1 week');
            if($bot->type) {
                $type = $model->types->getType($bot->type);
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
    if(!$model->login->isLoggedIn()) {
        $app->redirect($app->request->getUrl().$app->urlFor('login'), 401);
    }
    //$app->expires('+1 minute');
    $submissions = $model->submissions->getSubmissions(\Mini\Model\Submissions::SUBMISSION);
    $corrections = $model->submissions->getSubmissions(\Mini\Model\Submissions::CORRECTION);
    if(count($submissions) + count($corrections) > 0) {
        $app->lastModified(max($getTemplateLastMod('submissions.twig'), $model->submissions->getLastUpdate()));
    }
    else {
        $app->lastModified(time());
    }

    $token = $model->getToken("submissions");

    $app->render('submissions.twig', array(
        'submissions' => $submissions,
        'corrections' => $corrections,
        'token' => $token,
        'login' => $model->login->getIdentifier(),
        'types' => $types = $model->types->getAllTypes(),
        'addedType' => $app->request->params('addedtype'),
        'success' => $app->request->params('success')
    ));
})->name('submissions');

$app->group('/types', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->get('/', function () use ($app, $model, $getTemplateLastMod) {
        $app->expires('+1 day');
        $showDisabled = $_GET['disabled'] == 1;

        $descriptor = new \Mini\Model\TypeListDescriptor();
        $descriptor->includeDisabled = $showDisabled;
        $pageCount = $model->types->getPageCount(null, $model->types->getCount($descriptor));
        $page = $_GET['page'] ?? 1;
        if(!is_numeric($page))
            $page = 1;

        if($page > $pageCount || $page < 0)
            $app->notFound();

        $app->lastModified(max($getTemplateLastMod('types.twig'), $model->bots->getLastUpdate(), $model->types->getLastUpdate()));

        $types = $model->types->getTypes($page, $showDisabled);

        $app->render('types.twig', [
            'types' => $types,
            'page' => $page,
            'pageCount' => $pageCount,
            'showDisabled' => $showDisabled
        ]);
    })->name('types');

    $app->get('/sitemap.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $botLastUpdate = $model->bots->getLastUpdate();
        $typeLastUpdate = $model->types->getLastUpdate();
        $app->lastModified(max($botLastUpdate, $typeLastUpdate));
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $types = $model->types->getAllTypes();
        $typeLastMod = $getTemplateLastMod('type.twig');
        foreach($types as $type) {
            /** @var SimpleXMLElement $url */
            $url = $sitemap->addChild('url');
            $url->addChild('loc', $app->config('canonicalUrl').'types/'.$type->id);
            $url->addChild('changefreq', 'daily');
            $url->addChild('lastmod', $getLastMod(max($typeLastMod, strtotime($type->date), $model->bots->getLastUpdateByType($type->id))));
            $url->addChild('priority', '0.6');
        }

        echo $sitemap->asXML();
    });

    $app->get('/:id', function ($id) use ($app, $model, $getTemplateLastMod) {
        $app->expires('+1 day');

        $type = $model->types->getType($id);
        if(!$type)
            $app->notFound();

        $app->lastModified(max($getTemplateLastMod('type.twig'), strtotime($type->date), max(array_map(function(\Mini\Model\Bot $bot) { return strtotime($bot->date); }, $bots))));
        $app->render('type.twig', array(
            'type' => $type
        ));
    })->conditions(array('id' => '[1-9][0-9]*'))->name('type');
});

$app->group('/bots', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->get('/', function () use ($app, $model, $getTemplateLastMod) {
        $app->lastModified(max($model->bots->getLastUpdate(), $getTemplateLastMod('bots.twig')));
        $app->expires('+1 day');

        $currentType = $_GET['type'];
        if($currentType === 'null') {
            $descriptor = new BotListDescriptor();
            $descriptor->type = -1;
            $pageCount = $model->bots->getPageCount(null, $model->bots->getCount($descriptor));
            $currentType = null;
        }
        else if($currentType == 0 || !isset($_GET['type'])) {
            $pageCount = $model->bots->getPageCount();
            $currentType = (int)$currentType;
        }
        else {
            $descriptor = new BotListDescriptor();
            $descriptor->type = $currentType;
            $pageCount = $model->bots->getPageCount(null, $model->bots->getCount($descriptor));
            $currentType = (int)$currentType;
        }
        $page = $_GET['page'] ?? 1;
        if(!is_numeric($page))
            $page = 1;

        $types = $model->types->getAllTypes('table.name', 'ASC');

        if($page <= $pageCount && $page > 0) {
            if($currentType === null) {
              $bots = $model->bots->getBotsWithoutType($model->bots->getOffset($page));
              $typeName = 'Unknown (click to correct)';
            }
            else if($currentType === 0) {
                $bots = $model->bots->getBots($page);
            }
            else {
                $bots = $model->bots->getBotsByType($currentType, $model->bots->getOffset($page));
                $typeName;
                foreach($types as $t) {
                    if($t->id == $currentType) {
                        $typeName = $t->name;
                        break;
                    }
                }
            }
            if($currentType !== 0) {
                $bots = array_map(function(\Mini\Model\Bot $bot) use ($typeName) {
                    $bot->typename = $typeName;
                    return $bot;
                }, $bots);
            }
        }
        else {
            $bots = [];
        }

        $app->render('bots.twig', array(
            'pageCount' => $pageCount,
            'page' => $page,
            'bots' => $bots,
            'currentType' => $currentType,
            'types' => $types
        ));
    })->name('bots');

    $app->get('/sitemap.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
        $app->contentType('application/xml;charset=utf8');
        $app->lastModified($model->bots->getLastUpdate());
        $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

        $bots = $model->bots->getAllRawBots(0, $model->bots->getCount());
        $botLastMod = $getTemplateLastMod('bot.twig');
        foreach($bots as $bot) {
            /** @var SimpleXMLElement $url */
            $url = $sitemap->addChild('url');
            $url->addChild('loc', $app->config('canonicalUrl').'bots/'.$bot->name);
            $url->addChild('changefreq', 'weekly');
            $url->addChild('lastmod', $getLastMod(max($botLastMod, strtotime($bot->date))));
            $url->addChild('priority', '0.8');
        }

        echo $sitemap->asXML();
    });

    $app->get('/:name', function ($name) use ($app, $model, $getTemplateLastMod) {
        $bot = $model->bots->getBot($name);
        if(!$bot)
            $app->notFound();

        if($bot->type) {
            $type = $model->types->getType($bot->type);
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

$app->group('/lib', function ()  use ($app, $model, $piwikEvent) {
    $app->get('/check', function ()  use ($app, $model) {
        if(!$model->login->isLoggedIn()) {
            $app->halt(401, 'Not logged in');
        }
        else {
            echo 'Checked bots. Removed: ';
            print_r($model->checkBots());
            echo 'Checked '.$model->checkSubmissions().' submissions.';
            echo 'Added '.$model->typeCrawl().' bots based on vendor lists';
        }
    });

    $app->put('/submit', function () use ($app, $model, $piwikEvent) {
        $echoParam = function (string $name, $first = false) use ($app) {
            return ($first?'?':'&').$name.'='.$app->request->params($name);
        };

        $correction = $app->request->params('submission-type') == "0" ? "" : "&correction";
        $piwikParams = [
            "1" => $app->request->params('username'),
            "2" => $app->request->params('type'),
            "3" => $app->request->params('description'),
            "4" => $app->request->params('channel')
        ];
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
                    $app->request->params('description')
                );
                $event = "Correction";
            }
            else {
                $model->addSubmission(
                    strtolower($app->request->params('username')),
                    $app->request->params('type'),
                    $app->request->params('description'),
                    strtolower($app->request->params('channel'))
                );
                $event = "Submission";
            }
        }
        catch(Exception $e) {
            $piwikParams['5'] = $e->getCode();
            $piwikEvent("Error", $piwikParams);
            $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error='.$e->getCode().$echoParam('username').$echoParam('type').$echoParam('channel').$echoParam('description').$correction, 303);
        }
        $piwikEvent($event, $piwikParams);
        $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1'.$correction, 303);
    });

    $app->get('/auth0.js', function () use($app, $model) {
        $app->response->headers->set('Content-Type', 'application/javascript;charset=utf8');
        $libConfig = $model->login->getConfig();

        $app->render('auth0.js.twig', $libConfig);
    });

    $app->get('/login', function () use ($app, $model) {
        if(!$model->login->isLoggedIn()) {
            $app->render('login.twig');
        }
        else {
            $app->redirect($app->request->getUrl().$app->urlFor('submissions'), 303);
        }
    })->name('login');

    $app->post('/logout', function () use ($app, $model) {
        $model->login->logout();
        $app->redirect($app->request->getUrl().$app->urlFor('index'), 303);
    })->name('logout');

    $app->get('/callback', function () use ($app, $model) {
        if($model->login->isLoggedIn()) {
            $app->redirect($app->request->getUrl().$app->urlFor('submissions'), 303);
        }
        else {
            $app->redirect($app->request->getUrl().$app->urlFor('login'), 401);
        }
    });

    $app->post('/subaction', function() use ($app, $model) {
        if($model->login->isLoggedIn() && $model->checkToken("submissions", $app->request->params('token'))) {
            $submissionId = (int)$app->request->params('id');
            $submission = $model->submissions->getSubmission($submissionId);
            $paramType = 'error';
            if($app->request->params('approve') == "1") {
                if($model->approveSubmission($submissionId)) {
                    $paramType = 'approve';
                }
            }
            else if($app->request->params('reject') == "1") {
                $model->submissions->removeSubmission($submissionId);
                $paramType = 'reject';
            }
            else if($app->request->params('person') == "1") {
                $model->markSubmissionAsPerson($submissionId);
                $paramType = 'person';
            }
            $query = '?success='.$paramType;
            if($submission->type == 1) {
                $query .= '#corrections';
            }
            $app->redirect($app->request->getUrl().$app->urlFor('submissions').$query, 303);
        }
        else {
            $app->redirect($app->request->getUrl().$app->urlFor('login'), 401);
        }
    })->name('submission-action');

    $app->post('/subedit', function() use ($app, $model) {
        if($model->login->isLoggedIn() && $model->checkToken('submissions', $app->request->params('token'))) {
            $model->updateSubmission((int)$app->request->params('id'), $app->request->params('description'), $app->request->params('channel'));
            $app->redirect($app->request->getUrl().$app->urlFor('submissions'), 303);
        }
        else {
            $app->redirect($app->request->getUrl().$app->urlFor('login'), 401);
        }
    })->name('submission-edit');

    $app->post('/addtype', function() use ($app, $model) {
        $nullableParam = function(string $param) use ($app): ?string {
            $value = $app->request->params($param);
            if(!$value) {
                return null;
            }
            return $value;
        };
        $boolParam = function(string $param) use ($app): bool {
            return $app->request->params($param) == '1';
        };
        $intParam = function(string $param) use ($app): ?int {
            $value = $app->request->params($param);
            return $value == '' ? null : (int)$value;
        };
        if($model->login->isLoggedIn() && $model->checkToken('submissions', $app->request->params('token'))) {
            $typeId = $model->types->addType(
                $app->request->params('name'),
                $boolParam('multichannel'),
                $boolParam('managed'),
                $boolParam('customUsername'),
                $nullableParam('identifyableby'),
                $nullableParam('description'),
                $nullableParam('url'),
                $nullableParam('sourceUrl'),
                $nullableParam('commandsUrl'),
                $intParam('payment'),
                $boolParam('hasFreeTier'),
                $intParam('apiVersion')
            );
            $app->redirect($app->request->getUrl().$app->urlFor('submissions').'?addedtype='.$typeId, 303);
        }
        else {
            $app->redirect($app->request->getUrl().$app->urlFor('login'), 401);
        }
    })->name('add-type');
});

$app->get('/pages_map.xml', function () use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');

    $botLastUpdate = $model->bots->getLastUpdate();
    $typeLastUpdate = $model->types->getLastUpdate();
    $templateUpdates = array(
        "index" => $getTemplateLastMod('index.twig'),
        "bots" => $getTemplateLastMod('bots.twig'),
        "types" => $getTemplateLastMod('types.twig'),
        "submit" => $getTemplateLastMod('submit.twig'),
        "check" => $getTemplateLastMod('check.twig'),
        "api" => $getTemplateLastMod('api.twig'),
        "about" => $getTemplateLastMod('about.twig')
    );
    $app->lastModified(max(max($templateUpdates), $subLastUpdate, $botLastUpdate, $typeLastUpdate));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>');

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl'));
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['index'], $botLastUpdate)));
    $url->addChild('priority', '1.0');

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'bots');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['bots'], $botLastUpdate, $typeLastUpdate)));
    $url->addChild('priority', '0.6');

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'types');
    $url->addChild('changefreq', 'daily');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['types'], $botLastUpdate, $typeLastUpdate)));
    $url->addChild('priority', '0.6');

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'submit');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['submit'], $typeLastUpdate)));

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'check');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['check']));

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'api');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['api']));

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('url');
    $url->addChild('loc', $app->config('canonicalUrl').'about');
    $url->addChild('changefreq', 'weekly');
    $url->addChild('lastmod', $getLastMod($templateUpdates['about']));

    echo $sitemap->asXML();
});
$app->get('/sitemap.xml', function() use ($app, $model, $getTemplateLastMod, $getLastMod) {
    $app->contentType('application/xml;charset=utf8');
    $botLastUpdate = $model->bots->getLastUpdate();
    $typeLastUpdate = $model->types->getLastUpdate();
    $subLastUpdate = $model->submissions->getLastUpdate();
    $templateUpdates = array(
        "type" => $getTemplateLastMod('type.twig'),
        "bot" => $getTemplateLastMod('bot.twig'),
        "pages" => max(array_map("filemtime", glob('../Mini/view/*.twig')))
    );
    $app->lastModified(max($botLastUpdate, $typeLastUpdate, $subLastUpdate, $templateUpdates['pages']));
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>');

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'pages_map.xml');
    $url->addChild('lastmod', $getLastMod(max($templateUpdates['pages'], $subLastUpdate, $typeLastUpdate, $botLastUpdate)));

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'bots/sitemap.xml');
    $url->addChild('lastmod', $getLastMod(max($botLastUpdate, $templateUpdates['bot'])));

    /** @var SimpleXMLElement $url */
    $url = $sitemap->addChild('sitemap');
    $url->addChild('loc', $app->config('canonicalUrl').'types/sitemap.xml');
    $url->addChild('lastmod', $getLastMod(max($typeLastUpdate, $templateUpdates['type'])));

    echo $sitemap->asXML();
});

$app->get('/apis.json', function () use ($app) {
    $lastApiUpdate = 1547463829;
    $app->lastModified($lastApiUpdate);
    $app->expires('+1 week');

    $app->contentType('application/json;charset=utf8');
    $app->response->headers->set('Access-Control-Allow-Origin', '*');

    $martin = [
        "FN" => "Martin Giger",
        "email" => "martin@humanoids.be",
        "url" => "https://humanoids.be",
        "X-twitter" => "freaktechnik",
        "X-github" => "freaktechnik"
    ];
    $tags = [ "twitch", "chat", "bot", "stream", "spam", "mod", "coin", "token", "vanity" ];

    $spec = [
        "name" => "Twitch Bot Directory",
        "description" => "Sadly Twitch accounts can't be marked as a bot. But many accounts are used just as a chat bot. This service provides an API to find out who's a chat bot. All bots indexed are service or moderator bots.",
        "url" => $app->config('canonicalUrl')."apis.json",
        "tags" => $tags,
        "image" => $app->config('canonicalUrl')."img/icon.svg",
        "created" => "2016-02-14",
        "modified" => date('Y-m-d', $lastApiUpdate),
        "specificationVersion" => "0.15",
        "x-common" => [
            [
                "type" => "x-github-repo",
                "url" => "https://github.com/freaktechnik/twitchbots"
            ]
        ],
        "apis" => [
            [
                "name" => "Twitch Bots API",
                "description" => "Check if a Twitch user is a bot and if it is a bot, what kind of bot it is.",
                "humanURL" => $app->config('canonicalUrl').'api',
                "baseURL" => $app->config('apiUrl').'/v2',
                "version" => "v2",
                "tags" => $tags,
                "image" => $app->config('canonicalUrl')."img/icon.svg",
                "properties" => [],
                "contact" => [ $martin ]
            ],
            [
                "name" => "Twitch Bots API",
                "description" => "Check if a Twitch user is a bot and if it is a bot, what kind of bot it is.",
                "baseURL" => $app->config('apiUrl').'/v1',
                "version" => "v1",
                "tags" => $tags,
                "image" => $app->config('canonicalUrl')."img/icon.svg",
                "properties" => [],
                "contact" => [ $martin ]
            ]
        ],
        "maintainers" => [ $martin ]
    ];

    echo json_encode($spec);
})->name('apisjson');

$app->get('/opensearch.xml', function () use ($app) {
    $app->contentType("application/opensearchdescription+xml");
    $app->render('opensearch.xml.twig');
})->name('opensearch');

/******************************************* RUN THE APP *******************************************************/

$app->run();
