#!/usr/bin/env php
<?php

// Load all the things
require __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/../lib/config.php';

use Symfony\Component\Console\Application;

use Symfony\Component\Console\Input\{InputInterface, InputArgument, ArrayInput};
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
    ->setDescription('Run all check tasks')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($model, $console) {
        $bots = $console->find('check:bots');
        $arguments = array(
            'command' => 'check:bots'
        );
        $inputArr = new ArrayInput($arguments);
        $bots->run($inputArr, $output);

        $submissions = $console->find('check:submissions');
        $arguments = array(
            'command' => 'check:submissions'
        );
        $inputArr = new ArrayInput($arguments);
        $submissions->run($inputArr, $output);

        $types = $console->find('check:types');
        $arguments = array(
            'command' => 'check:types'
        );
        $inputArr = new ArrayInput($arguments);
        $types->run($inputArr, $output);
    });

$log = function(string $msg, OutputInterface $output) {
    $date = '['.date('r').'] ';
    $output->writeln($date.$msg);
};

$console
    ->register('check:bots')
    ->setDescription('Check a set of stored bots if they are still registered on Twitch.')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($model, $log) {
        $bots = $model->checkBots();

        $log('Checked bots. Removed '.count($bots), $output);
        if(count($bots) > 0) {
          $log('Removed '.implode(array_column($bots, 'name')));
        }
    });

$console
    ->register('check:submissions')
    ->setDescription('Check submission meta data.')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $log) {
        $count = $model->checkSubmissions();
        $log('Checked '.$count.' submissions for being in chat', $output);
    });

$console
    ->register('check:types')
    ->setDescription('Check known lists from bot vendors for new bots')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $log) {
        $addedCount = $model->typeCrawl();
        $log('Added '.$addedCount.' bots based on lists from bot vendors', $output);
    });

$console
    ->register('estimate:all')
    ->setDescription('Estimate usage counts for all types')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $log) {
        $types = $model->types->getAllTypes();
        foreach($types as $type) {
            $log('Estimating active channels for '.$type->name, $output);
            $model->estimateActiveChannels($type->id);
        }
    });

$console
    ->register('estimate')
    ->setDefinition([
        new InputArgument('type', InputARgument::REQUIRED, 'ID of type to check')
    ])
    ->setDescription('Estimate usage count for a type')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model) {
        $typeID = $input->getArgument('type');
        $model->estimateActiveChannels($typeID);
    });

$console->run();
?>
