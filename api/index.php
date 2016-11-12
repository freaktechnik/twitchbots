<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

$mode = $_SERVER['MODE'] ?? 'development';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => $mode
));
$app->setName('api');

// The API has no real view

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
        'model' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 100
        ),
        'webUrl' => $app->request->getUrl().dirname($app->request->getRootUri(), 1).'/public/'
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
        'webUrl' => $app->request->getScheme().'://'.$m[1].'/'
    ));
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('model'), $client);

/************************************ THE ROUTES / CONTROLLERS *************************************************/

// This API only returns JSON
$app->contentType('application/json;charset=utf8');
$app->response->headers->set('Access-Control-Allow-Origin', '*');

$returnError = function(int $code, string $msg) {
    return json_encode(array(
        'error' => $msg,
        'code' => $code
    ));
};

$app->notFound(function () use ($returnError) {
    echo $returnError(404, 'Endpoint not found');
});

$app->error(function ()  use ($returnError) {
    echo $returnError(500, 'Internal server error');
});

$app->group('/v1', function ()  use ($app, $model, $returnError) {
    $lastModified = 1451582891;

    $apiUrl = function($path = null) use ($app) {
        if($path == null)
            $path = $app->request->getResourceUri();
        return $app->request->getUrl().$app->request->getRootUri().$path;
    };

    $fullUrlFor = function(string $name, array $params = []) use ($app) {
        return $app->request->getUrl().$app->urlFor($name, $params);
    };

    $app->get('/', function () use ($app, $apiUrl, $lastModified) {
        $app->lastModified($lastModified);
        $app->expires('+1 month');
        $url = $apiUrl();
        $index = array(
            '_links' => array(
                'bot' => $url.'bot/',
                'type' => $url.'type/',
                'self' => $url,
                'web' => $app->config('webUrl'),
                'documentation' => $app->config('webUrl').'api'
            )
        );
        echo json_encode($index);
    });

    $app->group('/bot', function () use ($app, $model, $apiUrl, $fullUrlFor, $lastModified, $returnError) {
        $botsModel = $model->bots;
        $mapBot = function ($bot) use ($fullUrlFor, $app) {
            $bot->username = $bot->name;
            $bot->_links = array(
                'self' => $fullUrlFor('bot', array('name' => $bot->name)),
                'web' => $app->config('webUrl').'bots/'.$bot->name
            );
            if(isset($bot->type))
                $bot->_links['type'] = $fullUrlFor('type', array('id' => $bot->type));

            unset($bot->name);
            unset($bot->date);
            return $bot;
        };

        $checkPagination = function () use ($app, $returnError) {
            if(isset($_GET['limit']) &&
               (!is_numeric($_GET['limit']) || (int)$_GET['limit'] <= 0))
                $app->halt(400, $returnError(400, 'Invalid limit specified'));
            if(isset($_GET['offset'])  &&
               (!is_numeric($_GET['offset']) || (int)$_GET['offset'] < 0))
                $app->halt(400, $returnError(400, 'Invalid offset specified'));
        };

        $app->get('/', $checkPagination, function () use ($app, $botsModel, $apiUrl, $mapBot) {
            $offset = (int)($_GET['offset'] ?? 0);
            $maxLimit = $app->config('model')['page_size'];
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], $maxLimit) : $maxLimit;
            $names = explode(',', $_GET['bots']);
            $bots = $botsModel->getBotsByNames($names, $offset, $limit);
            $url = $apiUrl();
            $json = array(
                'bots' => array_map($mapBot, $bots),
                'offset' => $offset,
                'limit' => $limit,
                '_links' => array(
                    'next' => null,
                    'prev' => null,
                    'self' => $url.'?bots='.$_GET['bots'].'&offset='.$offset.'&limit='.$limit,
                    'web' => $app->config('webUrl').'bots',
                    'documentation' => $app->config('webUrl').'api#bot'
                )
            );

            if($limit == count($bots) && $offset + $limit < count($names))
                $json['_links']['next'] = $url.'?bots='.$_GET['bots'].'&offset='.($offset + $limit).'&limit='.$limit;
            if($offset > 0)
                $json['_links']['prev'] = $url.'?bots='.$_GET['bots'].'&offset='.max($offset - $limit, 0).'&limit='.$limit;

            echo json_encode($json);
        });
        $app->get('/all', $checkPagination, function () use ($app, $botsModel, $mapBot, $apiUrl, $fullUrlFor, $lastModified, $returnError) {
            if(isset($_GET['type']) && !is_numeric($_GET['type']))
                $app->halt(400, $returnError(400, 'Invalid type speified'));

            $offset = (int)($_GET['offset'] ?? 0);
            $maxLimit = $app->config('model')['page_size'];
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], $maxLimit) : $maxLimit;
            if(isset($_GET['type'])) {
                $app->lastModified(max(array($lastModified, $botsModel->getLastUpdate($_GET['type']))));
                $bots = $botsModel->getBotsByType($_GET['type'], $offset, $limit);
                $botCount = $botsModel->getCount($_GET['type']);
                $typeParam = '&type='.$_GET['type'];
            }
            else {
                $app->lastModified(max(array($lastModified, $botsModel->getLastUpdate())));
                $bots = $botsModel->getAllRawBots($offset, $limit);
                $botCount = $botsModel->getCount();
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
                    'self' => $url.'?offset='.$offset.'&limit='.$limit.$typeParam,
                    'web' => $app->config('webUrl').'bots',
                    'documentation' => $app->config('webUrl').'api#bot_all'
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
        $app->get('/:name', function ($name) use ($app, $botsModel, $apiUrl, $fullUrlFor, $lastModified) {
            $bot = $botsModel->getBot($name);

            if(!$bot) {
                $app->notFound();
            }

            $app->lastModified(max(array($lastModified, strtotime($bot->date))));
            $app->expires('+1 week');
            unset($bot->date);

            $bot->username = $bot->name;
            $bot->_links = array(
                'self' => $apiUrl(),
                'web' => $app->config('webUrl').'bots/'.$bot->name,
                'documentation' => $app->config('webUrl').'api#bot_name'
            );
            if(isset($bot->type))
                $bot->_links['type'] = $fullUrlFor('type', array('id' => $bot->type));
            unset($bot->name);

            echo json_encode($bot);
        })->conditions(array('name' => '[a-zA-Z0-9_]+'))->name('bot');
    });

    $app->group('/type', function () use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
        $typesModel = $model->types;
        $app->get('/', function () use ($app, $apiUrl, $fullUrlFor, $lastModified) {
            $app->lastModified($lastModified);

            $json = array(
                '_links' => array(
                    'type' => $fullUrlFor('type', array('id' => '{id}')),
                    'self' => $apiUrl(),
                    'web' => $app->config('webUrl').'types',
                    'documentation' => $app->config('webUrl').'api'
                )
            );

            echo json_encode($json);
        });

        $app->get('/:id', function ($id) use ($app, $typesModel, $apiUrl, $fullUrlFor, $lastModified) {
            $type = $typesModel->getType($id);
            if(!$type) {
                $app->notFound();
            }

            $app->lastModified(max(array($lastModified, strtotime($type->date))));
            $app->expires('+1 day');
            unset($type->date);

            $type->multiChannel = $type->multichannel == "1";
            unset($type->multichannel);

            $type->_links = array(
                'self' => $apiUrl(),
                'bots' => $fullUrlFor('allbots').'?type='.$id,
                'web' => $app->config('webUrl').'types/'.$id,
                'documentation' => $app->config('webUrl').'api#type_id'
            );

            echo json_encode($type);
        })->conditions(array('id' => '[1-9][0-9]*'))->name('type');
    });
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
