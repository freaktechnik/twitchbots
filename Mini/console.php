#!/usr/bin/env php
<?php

// Load all the things
require __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/../lib/config.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\{InputInterface, InputOption, ArrayInput};
use Symfony\Component\Console\Output\OutputInterface;

/********************************************* GUZZLE **********************************************************/
$stack = \GuzzleHttp\HandlerStack::create();

$stack->push(new \Kevinrob\GuzzleCache\CacheMiddleware(
    new \Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy(
        new \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage(
            new \Doctrine\Common\Cache\ChainCache([
                new \Doctrine\Common\Cache\ArrayCache(),
                new \Doctrine\Common\Cache\FilesystemCache(__DIR__.'/../guzzle-cache'),
            ])
        )
    )
), 'cache');

$client = new \GuzzleHttp\Client(array('handler' => $stack));

$console = new Application;
$model = new \Mini\Model\Model(array(
    'db_host' => 'localhost',
    'db_port' => '',
    'db_name' => $db,
    'db_user' => $db_user,
    'db_pass' => $db_pw,
    'page_size' => 50
), $client);

$console
    ->register('check:all')
    ->setDefinition(array(
        new InputOption('amount', 'a', InputOption::VALUE_OPTIONAL, 'Number of bots to check', 10)
    ))
    ->setDescription('Run all check tasks')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($model, $console) {
        if($model->checkRunning()) {
            $date = '['.date('r').'] ';
            $output->writeln($date.'Check already running. No action taken');
        }
        else {
            $bots = $console->find('check:bots');
            $arguments = array(
                'command' => 'check:bots',
                '--ignoreLock' => true
            );
            $inputArr = new ArrayInput($arguments);
            $bots->run($inputArr, $output);

            $submissions = $console->find('check:submissions');
            $arguments = array(
                'command' => 'check:submissions',
                '--ignoreLock' => true
            );
            $inputArr = new ArrayInput($arguments);
            $submissions->run($inputArr, $output);

            $types = $console->find('check:types');
            $arguments = array(
                'command' => 'check:types',
                '--ignoreLock' => true
            );
            $inputArr = new ArrayInput($arguments);
            $types->run($inputArr, $output);
            $model->checkDone();
        }
    });

$shouldRun = function (InputInterface $input, OutputInterface $output) use ($model): bool {
    if(!(bool)$input->getOption('ignoreLock') && $model->checkRunning()) {
        $date = '['.date('r').'] ';
        $output->writeln($date.'Check already running. No action taken.');
        return false;
    }
    else {
        return true;
    }
};

$ran = function (InputInterface $input) use ($model) {
    if(!(bool)$input->getOption('ignoreLock'))
        $model->checkDone();
};

$log = function(string $msg, OutputInterface $output) {
    $date = '['.date('r').'] ';
    $output->writeln($date.$msg);
};

$console
    ->register('check:bots')
    ->setDefinition(array(
        new InputOption('ignoreLock', 'i', InputOption::VALUE_NONE, 'If the check lock should be ignored', null)
    ))
    ->setDescription('Check a set of stored bots if they are still registered on Twitch.')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($model, $shouldRun, $ran, $log) {
        if($shouldRun($input, $output)) {
            $bots = $model->checkBots();

            $log('Checked bots. Removed '.count($bots), $output);
            $ran($input);
        }
    });

$defaultArguments = array(
    new InputOption('ignoreLock', 'i', InputOption::VALUE_OPTIONAL, 'If the check lock should be ignored', null)
);

$console
    ->register('check:submissions')
    ->setDefinition($defaultArguments)
    ->setDescription('Check submission meta data.')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $shouldRun, $ran, $log) {
        if($shouldRun($input, $output)) {
            $count = $model->checkSubmissions();
            $log('Checked '.$count.' submissions for being in chat', $output);
            $ran($input);
        }
    });

$console
    ->register('check:types')
    ->setDefinition($defaultArguments)
    ->setDescription('Check known lists from bot vendors for new bots')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $shouldRun, $ran, $log) {
        if($shouldRun($input, $output)) {
            $addedCount = $model->typeCrawl();
            $log('Added '.$addedCount.' bots based on lists from bot vendors', $output);
            $ran($input);
        }
    });

$console->run();
?>
