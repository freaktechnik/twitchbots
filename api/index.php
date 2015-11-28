<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim();
$app->setName('api');

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("../Mini/view", array(
    'cache' => '../cache'
));

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

$apiUrl = function($path = null) use ($app) {
    if($path == null)
        $path = $app->request->getResourceUri();
    return 'http://api.twichbots.info'.$path;
};

$app->group('/v1', function ()  use ($app, $model, $apiUrl) {
    // This API only returns JSON
    $app->contentType('application/json;charset=utf8');
    $app->response()->headers->set('Access-Control-Allow-Origin', '*');

    $app->get('/', function () use ($app, $apiUrl) {
        $url = $apiUrl($app);
        $index = array(
            '_links' => array(
                'bot' => $url.'bot',
                'type' => $url.'type'
            )
        );
        echo json_encode($index);
    });

    $root = "/v1/";

    $app->group('/bot', function () use ($app, $model, $apiUrl, $root) {
        $mapBot = function ($bot) use ($apiUrl, $app, $root) {
            $url = $apiUrl($root);
            $bot->username = $bot->name;
            $bot->_links = array(
                'self' => $url.'bot/'.$bot->name,
                'type' => $url.'type/'.$bot->type
            );
            unset($bot->name);
            return $bot;
        };

        $app->get('/', function () use ($app, $model, $apiUrl, $mapBot) {
            $page = isset($_GET['page']) ? $_GET['page'] : 1;
            $names = explode(',', $_GET['bots']);
            $bots = $model->getBotsByNames($names, $page);
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
        $app->get('/all', function () use ($app, $model, $mapBot, $apiUrl, $root) {
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
                $json['_links']['type'] = $apiUrl($root).'type/'.$_GET['type'];

            if($page < $pageCount)
                $json['_links']['next'] = $url.'?page='.($page + 1).$typeParam;
            if($page > 1)
                $json['_links']['prev'] = $url.'?page='.($page - 1).$typeParam;

            echo json_encode($json);
        });
        $app->get('/:name', function ($name) use ($app, $model, $apiUrl, $root) {
            $bot = $model->getBot($name);

            if(!$bot) {
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
                $bot->username = $bot->name;
                $bot->isBot = true;
                $bot->_links = array(
                    'self' => $apiUrl(),
                    'type' => $apiUrl($root).'type/'.$bot->type
                );
                unset($bot->name);
            }

            echo json_encode($bot);
        })->conditions(array('name' => '[a-zA-Z0-9_\-]+'));
    });

    $app->get('/type/:id', function ($id) use ($app, $model, $apiUrl, $root) {
        $type = $model->getType($id);
        if(!$type) {
            $app->halt(404, "Type not found");
        }

        $type->multiChannel = !!$type->multichannel;
        unset($type->multichannel);

        $type->_links = array(
            'self' => $apiUrl(),
            'bots' => $apiUrl($root).'bot/all?type='.$id
        );

        echo json_encode($type);
    })->conditions(array('id' => '[1-9][0-9]*'));
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
