<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim(array(
    'mode' => $_SERVER['MODE']
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
        'csp' => "default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src https://api.twitchbots.info; form-action 'self'; frame-ancestors 'none'; reflected-xss block",
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
        'csp' => "default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src https://api.twitchbots.info; form-action 'self'; frame-ancestors 'none'; reflected-xss block; base-uri twitchbots.info www.twitchbots.info; referrer no-referrer-when-downgrade",
        'apiUrl' => $app->request->getScheme().'://api.'.$app->request->getHostWithPort()
    ));

    $app->view->parserOptions = array(
        'cache' => '../cache'
    );
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$lastUpdate = 1451564411;

$app->response->headers->set('Content-Security-Policy', $app->config('csp'));

$app->notFound(function () use ($app) {
    $app->render('error.twig', array(
        'code' => 404,
        'name' => 'Page Not Found',
        'message' => 'The page you are looking for could not be found. Check the address bar to ensure your URL is spelled correctly. Click a link above to go back to an existing page.'
    ));
});

$app->get('/', function () use ($app, $model, $lastUpdate) {
    $app->lastModified(max(array($lastUpdate, $model->getLastUpdate())));
    $app->expires('+1 day');

    $pageCount = $model->getPageCount();
    $page = 1;
    if(isset($_GET['page']) && is_numeric($_GET['page']))
        $page = $_GET['page'];

    if($page <= $pageCount && $page > 0)
        $bots = $model->getBots($page);
    else
        $bots = array();

    $app->render('index.twig', array(
        'pageCount' => $pageCount,
        'page' => $page,
        'bots' => $bots
    ));
});
$app->get('/submit', function () use ($app, $model, $lastUpdate) {
    $app->lastModified($lastUpdate);
    // 1 day expiration because of the types list
    $app->expires('+1 day');

    $token = $model->getToken("submit");
    $types = $model->getAllTypes();

    $app->render('submit.twig', array(
        'success' => (boolean)$_GET['success'],
        'error' => (int)$_GET['error'],
        'token' => $token,
        'types' => $types,
        'correction' => isset($_GET['correction']),
        'username' => $_GET['username']
    ));
})->name('submit');
$app->get('/check', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('check.twig');
});
$app->get('/api', function () use ($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('api.twig', array(
        'apiUrl' => $app->config('apiUrl')
    ));
});
$app->get('/about', function () use($app, $lastUpdate) {
    $app->lastModified($lastUpdate);
    $app->expires('+1 week');
    $app->render('about.twig');
});
$app->get('/submissions', function () use($app, $model, $lastUpdate) {
    $app->expires('+1 minute');
    $submissions = $model->getSubmissions();
    if(count($submissions) > 0) {
        $app->lastModified(max(array($lastUpdate, strtotime($submissions[0]->date))));
    }
    else {
        $app->lastModified(time());
    }

    $app->render('submissions.twig', array(
        'submissions' => $submissions
    ));
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
        //TODO should some of these checks be in the model?
        if($model->checkToken("submit", $app->request->params('token'))) {
            if((boolean)$app->request->params('submission-type')) {
                if($model->botSubmitted($app->request->params('username'))) {
                    $model->addCorrection(
                        $app->request->params('username'),
                        $app->request->params('type'),
                        $app->request->params('description')
                    );
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1&correction', 303);
                }
                else {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=4&correction&username='.$app->request->params('username'), 303);
                }
            }
            else {
                if(!$model->twitchUserExists($app->request->params('username'))) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=2&username='.$app->request->params('username'), 303);
                }
                else if($model->botSubmitted($app->request->params('username'))) {
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=3&username='.$app->request->params('username'), 303);
                }
                else {
                    $model->addSubmission(
                        $app->request->params('username'),
                        $app->request->params('type'),
                        $app->request->params('description')
                    );
                    $app->redirect($app->request->getUrl().$app->urlFor('submit').'?success=1', 303);
                }
            }
        }
        else {
            $correction = $app->request->params('submission-type') == "0" ? "" : "&correction";
            $app->redirect($app->request->getUrl().$app->urlFor('submit').'?error=1&username='.$app->request->params('username').$correction);
        }
    });
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
