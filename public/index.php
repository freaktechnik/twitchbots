<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/

// Load Composer's PSR-4 autoloader (necessary to load Slim, Mini etc.)
require '../vendor/autoload.php';

// Initialize Slim (the router/micro framework used)
$app = new \Slim\Slim();

// and define the engine used for the view @see http://twig.sensiolabs.org
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("../Mini/view");

/******************************************* THE CONFIGS *******************************************************/

// Configs for mode "development" (Slim's default), see the GitHub readme for details on setting the environment
$app->configureMode('development', function () use ($app) {
    // Set the configs for development environment
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
        )
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
        )
    ));
});

/******************************************** THE MODEL ********************************************************/

// Initialize the model, pass the database configs. $model can now perform all methods from Mini\model\model.php
$model = new \Mini\Model\Model($app->config('database'));

/************************************ THE ROUTES / CONTROLLERS *************************************************/

$app->get('/', function () use ($app, $model) {
    $pageCount = $model->getBotPageCount();
    $page = 1;
    if(isset($_GET['page']))
        $page = $_GET['page'];

    if($page <= $pageCount && $page > 0)
        $bots = $model->getBots($page);
    else
        $bots = array();

    $app->render('index.twig', array(
        'pageCount' => $pageCount,
        'page' => $_['page'],
        'bots' => $bots
    ));
});
$app->get('/submit', function () use ($app, $model) {
    $token = $model->getToken("submit");

    $app->render('submit.twig', array(
        'success' => $_GET['success'],
        'error' => $_GET['error'],
        'token' => $token
    ));
});
$app->get('/check', function () use ($app) {
    $app->render('check.twig');
});
$app->get('/api', function () use ($app) {
    $app->render('api.twig');
});
$app->get('/about', function () use($app) {
    $app->render('about.twig');
});
$app->get('/submissions', function () use($app, $model) {
    $submissions = $model->getSubmissions();

    $app->render('submissions.twig', array(
        'submissions' => $submissions
    ));
});

$app->post('/lib/submit', function () use($app, $model) {
    if($model->checkToken("submit", $_POST['token'])) {
        $model->addSubmission($_POST['username'], $_['type'], $_['description']);
        $app->redirect('/submit?success=1');
    }
    else {
        $app->redirect('/submit?error=1');
    }
});

/******************************************* RUN THE APP *******************************************************/

$app->run();
