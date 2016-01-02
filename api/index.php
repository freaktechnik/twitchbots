<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

$mode = 'development';
if(isset($_SERVER['MODE']))
    $mode = $_SERVER['MODE'];

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => $mode
));
$app->setName('api');

// The API has no real view

/******************************************* THE CONFIGS *******************************************************/
// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {
    // Set the configs for development environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => true,
        'model' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 100
        ),
        'docsUrl' => $app->request->getUrl().dirname($app->request->getRootUri(), 1).'/public/api'
    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    include_once __DIR__.'/../lib/config.php';

    preg_match("/api\.(.*)/", $app->request->getHost(), $m);

    $app->config(array(
        'debug' => false,
        'model' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 100
        ),
        'docsUrl' => $app->request->getScheme().'://'.$m[1].'/api'
    ));
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('model'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$app->group('/v1', function ()  use ($app, $model) {
    $lastModified = 1451582891;

    $apiUrl = function($path = null) use ($app) {
        if($path == null)
            $path = $app->request->getResourceUri();
        return $app->request->getUrl().$app->request->getRootUri().$path;
    };

    $fullUrlFor = function($name, $params) use ($app) {
        return $app->request->getUrl().$app->urlFor($name, $params);
    };

    // This API only returns JSON
    $app->contentType('application/json;charset=utf8');
    $app->response->headers->set('Access-Control-Allow-Origin', '*');

    $app->get('/', function () use ($app, $apiUrl, $lastModified) {
        $app->lastModified($lastModified);
        $app->expires('+1 month');
        $url = $apiUrl();
        $index = array(
            '_links' => array(
                'bot' => $url.'bot/',
                'type' => $url.'type/',
                'self' => $url,
                'documentation' => $app->config('docsUrl')
            )
        );
        echo json_encode($index);
    });

    $app->group('/bot', function () use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
        $mapBot = function ($bot) use ($fullUrlFor) {
            $bot->username = $bot->name;
            $bot->_links = array(
                'self' => $fullUrlFor('bot', array('name' => $bot->name)),
                'type' => $fullUrlFor('type', array('id' => $bot->type))
            );
            unset($bot->name);
            unset($bot->date);
            return $bot;
        };

        $checkPagination = function () use ($app) {
            if(isset($_GET['limit']) &&
               (!is_numeric($_GET['limit']) || (int)$_GET['limit'] <= 0))
                $app->halt(400, '{ "error": "Invalid limit specified", "code": 400 }');
            if(isset($_GET['offset'])  &&
               (!is_numeric($_GET['offset']) || (int)$_GET['offset'] < 0))
                $app->halt(400, '{ "error": "Invalid offset specified", "code": 400 }');
        };

        $app->get('/', $checkPagination, function () use ($app, $model, $apiUrl, $mapBot) {
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $maxLimit = $app->config('model')['page_size'];
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], $maxLimit) : $maxLimit;
            $names = explode(',', $_GET['bots']);
            $bots = $model->getBotsByNames($names, $offset, $limit);
            $url = $apiUrl();
            $json = array(
                'bots' => array_map($mapBot, $bots),
                'offset' => $offset,
                'limit' => $limit,
                '_links' => array(
                    'next' => null,
                    'prev' => null,
                    'self' => $url.'?bots='.$_GET['bots'].'&offset='.$offset.'&limit='.$limit
                )
            );

            if($limit == count($bots) && $offset + $limit < count($names))
                $json['_links']['next'] = $url.'?bots='.$_GET['bots'].'&offset='.($offset + $limit).'&limit='.$limit;
            if($offset > 0)
                $json['_links']['prev'] = $url.'?bots='.$_GET['bots'].'&offset='.max($offset - $limit, 0).'&limit='.$limit;

            echo json_encode($json);
        });
        $app->get('/all', $checkPagination, function () use ($app, $model, $mapBot, $apiUrl, $fullUrlFor, $lastModified) {
            if(isset($_GET['type']) && !is_numeric($_GET['type']))
                $app->halt(400, '{ "error": "Invalid type speified", "code": 400 }');

            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $maxLimit = $app->config('model')['page_size'];
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], $maxLimit) : $maxLimit;
            if(isset($_GET['type'])) {
                $app->lastModified(max(array($lastModified, $model->getLastUpdate("bots", $_GET['type']))));
                $bots = $model->getBotsByType($_GET['type'], $offset, $limit);
                $botCount = $model->getBotCount($_GET['type']);
                $typeParam = '&type='.$_GET['type'];
            }
            else {
                $app->lastModified(max(array($lastModified, $model->getLastUpdate())));
                $bots = $model->getAllRawBots($offset, $limit);
                $botCount = $model->getBotCount();
                $typeParam = '';
            }

            $url = $apiUrl();

            $json = array(
                'bots' => array_map($mapBot, $bots),
                'offset' => $offset,
                'limit' => $limit,
                'total' => $botCount,
                '_links' => array(
                    'next' => null,
                    'prev' => null,
                    'self' => $url.'?offset='.$offset.'&limit='.$limit.$typeParam
                )
            );

            if(isset($_GET['type']))
                $json['_links']['type'] = $fullUrlFor('type', array('id' => $_GET['type']));

            if($offset + $limit < $botCount)
                $json['_links']['next'] = $url.'?offset='.($offset + $limit).'&limit='.$limit.$typeParam;
            if($offset > 0)
                $json['_links']['prev'] = $url.'?offset='.max($offset - $limit, 0).'&limit='.$limit.$typeParam;

            echo json_encode($json);
        })->name('allbots');
        $app->get('/:name', function ($name) use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
            $bot = $model->getBot($name);

            if(!$bot) {
                $app->halt(404, '{ "error": "User not a bot", "code": 404 }');
            }

            $app->lastModified(max(array($lastModified, strtotime($bot->date))));
            $app->expires('+1 week');
            unset($bot->date);

            $bot->username = $bot->name;
            $bot->_links = array(
                'self' => $apiUrl(),
                'type' => $fullUrlFor('type', array('id' => $bot->type))
            );
            unset($bot->name);

            echo json_encode($bot);
        })->conditions(array('name' => '[a-zA-Z0-9_\-]+'))->name('bot');
    });

    $app->group('/type', function () use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
        $app->get('/', function () use ($app, $apiUrl, $fullUrlFor, $lastModified) {
            $app->lastModified($lastModified);

            $json = array(
                '_links' => array(
                    'type' => $fullUrlFor('type', array('id' => '{id}')),
                    'self' => $apiUrl(),
                    'documentation' => $app->config('docsUrl')
                )
            );

            echo json_encode($json);
        });

        $app->get('/:id', function ($id) use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
            $type = $model->getType($id);
            if(!$type) {
                $app->halt(404, '{ "error": "Type not found", "code": 404 }');
            }

            $app->lastModified(max(array($lastModified, strtotime($type->date))));
            $app->expires('+1 day');
            unset($type->date);

            $type->multiChannel = $type->multichannel == "1";
            unset($type->multichannel);

            $type->_links = array(
                'self' => $apiUrl(),
                'bots' => $fullUrlFor('allbots').'?type='.$id
            );

            echo json_encode($type);
        })->conditions(array('id' => '[1-9][0-9]*'))->name('type');
    });
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
