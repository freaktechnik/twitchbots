#!/usr/bin/env php
<?php

// Load all the things
require __DIR__.'/../vendor/autoload.php';
include_once __DIR__.'/../lib/config.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\{InputInterface, InputOption, ArrayInputs};
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
                '--amount' => (int)$input->getOption('amount'),
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

$console
    ->register('check:bots')
    ->setDefinition(array(
        new InputOption('amount', 'a', InputOption::VALUE_OPTIONAL, 'Number of bots to check', 10),
        new InputOption('ignoreLock', 'i', InputOption::VALUE_NONE, 'If the check lock should be ignored', null)
    ))
    ->setDescription('Check a set of stored bots if they are still registered on Twitch.')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($model, $shouldRun) {
        if($shouldRun($input, $output)) {
            $amount = (int)$input->getOption('amount');
            $bots = $model->checkBots($amount);

            $date = '['.date('r').'] ';
            $output->writeln($date.'Checked '.$amount.' bots. Removed '.count($bots));
        }
    });

$defaultArguments = array(
    new InputOption('ignoreLock', 'i', InputOption::VALUE_OPTIONAL, 'If the check lock should be ignored', null)
);

$console
    ->register('check:submissions')
    ->setDefinition($defaultArguments)
    ->setDescription('Check submission meta data.')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $shouldRun) {
        if($shouldRun($input, $output)) {
            $date = '['.date('r').'] ';
            $count = $model->checkSubmissions();
            $output->writeln($date.'Checked '.$count.' submissions for being in chat');
        }
    });

$console
    ->register('check:types')
    ->setDefinition($defaultArguments)
    ->setDescription('Check known lists from bot vendors for new bots')
    ->setCode(function(InputInterface $input, OutputInterface $output) use ($model, $shouldRun) {
        if($shouldRun($input, $output)) {
            $date = '['.date('r').'] ';
            $addedCount = $model->typeCrawl();
            $output->writeln($date.'Added '.$addedCount.' bots based on lists from bot vendors');
        }
    });

$console->run();
?>
