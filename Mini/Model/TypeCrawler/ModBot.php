<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\TypeCrawler;
use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class ModBot extends TypeCrawler {
    /** @var int $crawlInterval */
    protected static $crawlInterval = 86400;
    /** @var int $type */
    public static $type = 28;

    function __construct(TypeCrawlerStorage $storage)
    {
        parent::__construct($storage);
    }

    /**
     * @inheritDoc
     */
    protected function doCrawl(): array
    {
        $url = $this->storage->get('URL')."?from=".$this->storage->get('lastCrawl');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);
        curl_close($ch);

        $json = preg_replace('/,"Title":"[^}]+"/', "", $json);
        $response = json_decode(substr($json, 5, -6), true);

        $bots = [];
        foreach($response['streams'] as $bot) {
            if($bot['Channel'] !== $bot['Bot']) {
                $bots[] = $this->getBot($bot['Bot'], $bot['Channel']);
            }
        }

        return $bots;
    }
}
