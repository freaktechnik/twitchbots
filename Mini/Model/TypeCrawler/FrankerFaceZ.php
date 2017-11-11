<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class FrankerFaceZ extends TypeCrawler {
    /** @var int */
    protected static $crawlInterval = 604800;
    /** @var int */
    public static $type = 165;

    function __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function getBot(string $name, $channel = null): \stdClass {
        $bot = parent::getBot($name, $channel);
        $bot->type = null;
        return $bot;
    }

    protected function doCrawl(): array {
        $url = "https://api.frankerfacez.com/v1/badges";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($json, true);

        $badgeId;
        foreach($response['badges'] as $badge) {
            if($badge['name'] === 'bot') {
                $badgeId = strval($badge['id']);
                break;
            }
        }

        if(!empty($response['users']) && !empty($badgeId)) {
            $ret = array();
            foreach($response['users'][$badgeId] as $element) {
                $name = $element;
                $ret[] = $this->getBot($name);
            }
            return $ret;
        }
        else {
            return array();
        }
    }
}
