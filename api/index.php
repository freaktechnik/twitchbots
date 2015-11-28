<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => 'production'
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
        )
    ));
});

// Configs for mode "production"
$app->configureMode('production', function () use ($app) {
    // Set the configs for production environment
    include_once __DIR__.'/../lib/config.php';
    $app->config(array(
        'debug' => false,
        'model' => array(
            'db_host' => 'localhost',
            'db_port' => '',
            'db_name' => $db,
            'db_user' => $db_user,
            'db_pass' => $db_pw,
            'page_size' => 100
        )
    ));
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('model'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$app->group('/v1', function ()  use ($app, $model) {
    $lastModified = 1448745064;

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
        $url = $apiUrl();
        $index = array(
            '_links' => array(
                'bot' => $url.'bot',
                'type' => $url.'type'
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

        $app->get('/', function () use ($app, $model, $apiUrl, $mapBot) {
            $page = isset($_GET['page']) ? $_GET['page'] : 1;
            $names = explode(',', $_GET['bots']);
            $bots = $model->getBotsByNames($names, $page);
            //TODO lastModified
            $url = $apiUrl();
            $json = array(
                'bots' => array_map($mapBot, $bots),
                'page' => $page,
                '_links' => array(
                    'next' => null,
                    'prev' => null,
                    'self' => $url.'?bots='.$_GET['bots'].'&page='.$page
                )
            );

            if($page < $model->getPageCount(count($names)))
                $json['_links']['next'] = $url.'?bots='.$_GET['bots'].'&page='.($page + 1);
            if($page > 1)
                $json['_links']['prev'] = $url.'?bots='.$_GET['bots'].'&page='.($page - 1);

            echo json_encode($json);
        });
        $app->get('/all', function () use ($app, $model, $mapBot, $apiUrl, $fullUrlFor, $lastModified) {
            $app->lastModified(max(array($lastModified, $model->getLastUpdate())));

            $page = isset($_GET['page']) ? $_GET['page'] : 1;
            if(isset($_GET['type'])) {
                $bots = $model->getBotsByType($_GET['type'], $page);
                $pageCount = $model->getPageCount($model->getBotCount($type));
                $typeParam = '&type='.$_GET['type'];
            }
            else {
                $bots = $model->getAllRawBots($page);
                $pageCount = $model->getPageCount();
                $typeParam = '';
            }

            $url = $apiUrl();

            $json = array(
                'bots' => array_map($mapBot, $bots),
                'page' => $page,
                '_links' => array(
                    'next' => null,
                    'prev' => null,
                    'self' => $url.'?page='.$page.$typeParam
                )
            );

            if(isset($_GET['type']))
                $json['_links']['type'] = $fullUrlFor('type', array('id' => $_GET['type']));

            if($page < $pageCount)
                $json['_links']['next'] = $url.'?page='.($page + 1).$typeParam;
            if($page > 1)
                $json['_links']['prev'] = $url.'?page='.($page - 1).$typeParam;

            echo json_encode($json);
        })->name('allbots');
        $app->get('/:name', function ($name) use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
            $bot = $model->getBot($name);

            if(!$bot) {
                $app->lastModified(time());
                $bot = array(
                    'username' => $name,
                    'isBot' => false,
                    'type' => null,
                    '_links' => array(
                        'self' => $apiUrl()
                    )
                );
            }
            else {
                $app->lastModified(max(array($lastModified, strtotime($bot->date))));
                unset($bot->date);

                $bot->username = $bot->name;
                $bot->isBot = true;
                $bot->_links = array(
                    'self' => $apiUrl(),
                    'type' => $fullUrlFor('type', array('id' => $bot->type))
                );
                unset($bot->name);
            }

            echo json_encode($bot);
        })->conditions(array('name' => '[a-zA-Z0-9_\-]+'))->name('bot');
    });

    $app->get('/type/:id', function ($id) use ($app, $model, $apiUrl, $fullUrlFor, $lastModified) {
        $type = $model->getType($id);
        if(!$type) {
            $app->halt(404, '{ "error": "Type not found", "code": 404 }');
        }

        $app->lastModified(max(array($lastModified, strtotime($type->date))));
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

/******************************************* RUN THE APP *******************************************************/

$app->run();
