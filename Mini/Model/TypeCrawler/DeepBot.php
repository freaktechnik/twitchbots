<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\TypeCrawler;
use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class DeepBot extends TypeCrawler {
    /** @var int */
    protected static $crawlInterval = 3600;
    /** @var int */
    public static $type = 22;

    function __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function doCrawl(): array {
        $url = "https://api.deepbot.tv/web/streamlist.php";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($json, true);

        $bots = array();
        foreach($response['streams'] as $bot) {
            if(strtolower($bot['user']) !== strtolower($bot['bot_name'])) {
                $bots[] = $this->getBot($bot['bot_name'], $bot['user']);
            }
        }

        return $bots;
    }
}
