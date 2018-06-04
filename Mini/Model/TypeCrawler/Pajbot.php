<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use DOMDocument;
use DOMXPath;

class Pajbot extends TypeCrawler {
    /** @var int $crawlInterval */
    protected static $crawlInterval = 604800;
    /** @var int $type */
    public static $type = 44;

    function __construct(TypeCrawlerStorage $storage)
    {
        parent::__construct($storage);
    }

    /**
     * @inheritDoc
     */
    protected function doCrawl(): array
    {
        $url = "https://pajbot.com";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $rawHTML = curl_exec($ch);
        curl_close($ch);

        $document = new DOMDocument();
        @$document->loadHTML($rawHTML);

        $xpath = new DOMXPath($document);
        $elements = $xpath->query("/html/body/div/div/div/div/div[@class='column pbot']");

        if(!empty($elements)) {
            $ret = array();
            /** @var \DOMElement $element */
            foreach($elements as $element) {
                /** @var \DOMNode $item */
                $item = $element->getElementsByTagName('h2')->item(0);
                $name = $item->textContent;
                /** @var \DOMElement $firstItem */
                $firstItem = $xpath->query("div/a[starts-with(@href, 'http://twitch.tv/')]", $element)->item(0);
                $channel = preg_replace("%https?://twitch\.tv/%", "", $firstItem->getAttribute("href"));
                $ret[] = $this->getBot($name, $channel);
            }
            return $ret;
        }
        return [];
    }
}
