<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\TypeCrawler;
use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class ModBot extends TypeCrawler {
    /** @var int */
    protected static $crawlInterval = 86400096;
    /** @var int */
    public static $type = 28;

    __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function doCrawl(): array {
        $url = $this->storage->get('URL')."?from=".$this->storage->get('lastCrawl');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $response = json_decode(substr($json, 5, -5), true);

        $bots = array();
        foreach($response['streams'] as $bot) {
            if($bot['Channel'] !== $bot['Bot']) {
                $botObject = new stdClass;
                $botObject->name = $bot['Bot'];
                $botObject->type = $this->type;
                $botObject->channel = $bot['Channel'];
                $bots[] = $botObject;
            }
        }

        return $bots;
    }
}
