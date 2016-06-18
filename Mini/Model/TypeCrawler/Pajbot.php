<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use DOMDocument;
use DOMXpath;

class Pajbot extends TypeCrawler {
    /** @var int */
    protected static $crawlInterval = 604800;
    /** @var int */
    public static $type = 44;

    function __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function doCrawl(): array {
        $url = "https://pajbot.com";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawHTML = curl_exec($ch);
        curl_close($ch);

        $document = DOMDocument::loadHTML($rawHTML);

        $xpath = new DOMXpath($document);
        $elements = $xpath->query("/html/body/div/div/div/div/div[@class='column pbot']");

        if(!empty($elements)) {
            $ret = array();
            foreach($elements as $element) {
                $name = $element->getElementsByTagName('h2')->item(0)->textContent;
                $channel = preg_replace("https?:\/\/twitch\.tv\/", "", $xpath->query("div/a[starts-with(@href, 'http://twitch.tv/')]", $element)->item(0)->getAttribute("href"));
                $ret[] = $this->getBot($name, $channel);
            }
            return $ret;
        }
        else {
            return array();
        }
    }
}
