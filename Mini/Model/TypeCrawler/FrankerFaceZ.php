<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use \Mini\Model\Bot;

class FrankerFaceZ extends TypeCrawler {
    /** @var int $crawlInterval */
    protected static $crawlInterval = 604800;
    /** @var int $type */
    public static $type = 165;

    function __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function getBot(string $name, string $channel = null): Bot {
        $bot = parent::getBot($name, $channel);
        $bot->type = null;
        return $bot;
    }

    /**
     * @inheritDoc
     */
    protected function doCrawl(): array {
        $badgeId = "2";
        $url = "https://api.frankerfacez.com/v1/badge/".$badgeId;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($json, true);

        if(!empty($response['users'][$badgeId])) {
            $ret = array();
            foreach($response['users'][$badgeId] as $element) {
                $name = $element;
                $ret[] = $this->getBot($name);
            }
            return $ret;
        }
        return [];
    }
}
