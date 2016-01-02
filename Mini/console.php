<?php

// Load all the things
require '../vendor/autoload.php';
include_once __DIR__.'/../lib/config.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

$console = new Application;
$model = new \Mini\Model\Model(array(
    'db_host' => 'localhost',
    'db_port' => '',
    'db_name' => $db,
    'db_user' => $db_user,
    'db_pass' => $db_pw,
    'page_size' => 50
));

$console
    ->register('check')
    ->setDefinition(array(
        new InputArgument('amount', InputArgument::OPTIONAL, 'Number of bots to check', 10)
    ))
    ->setDescription('Check a set of stored bots if they are still registered on twitch.')
    ->setCode(function($input, $output) {
        $amount = $input->getArgument('amount');
        $bots = $model->checkBots($amount);

        $output->writeln("Checked ".$amount." bots. Removed ".count($bots));
    });

$console->run();
?>
