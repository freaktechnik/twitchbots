<?php

namespace Mini\Model;

use PDO;

/* CREATE TABLE IF NOT EXISTS bots (
    twitch_id varchar(20) DEFAULT NULL COMMENT `Twitch user ID`,
    name varchar(255) CHARACTER SET utf8 NOT NULL COMMENT `Twitch username of the bot`,
    type int(10) unsigned DEFAULT NULL COMMENT `Type of the bot`,
    date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT `Last content modification ts`,
    cdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT `Last crawl or update ts`,
    channel varchar(535) CHARACTER SET utf8 DEFAULT NULL COMMENT `Channel the bot is in`,
    channel_id varchar(20) DEFAULT NULL COMMENT `Channel Twitch ID`,
    PRIMARY KEY (twitch_id),
    FOREIGN KEY (type) REFERENCES types(id),
    UNIQUE KEY name (name)
) DEFAULT CHARSET=utf8 */

class Bots extends PaginatingStore {
    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "bots", $pageSize);
    }

    public function getLastUpdateByType(int $type = 0): int
    {
        $condition = "";
        if($type != 0) {
            $condition = "type=?";
        }
        return $this->getLastUpdate($condition, array($type));
    }

    public function getBots(int $page = 1): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $query = $this->prepareSelect("*", "LIMIT :start,:stop", "list");
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
        }
        return [];
    }

    /**
     * @return Bot[]
     */
    public function getAllRawBots(int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        if($limit > 0 && $offset < $this->getCount()) {
            $query = $this->prepareSelect("*", "LIMIT :start,:stop");
            $this->doPagination($query, $offset, $limit);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
        }
        return [];
    }

    /**
     * @return Bot[]
     */
    public function getBotsByNames(array $names, int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        $namesCount = count($names);
        if($limit > 0 && $offset < $namesCount) {
            $tempTable = $this->createTempTable($names);

            $query = $this->prepareSelect('`table`.*', 'INNER JOIN '.$tempTable.' ON table.name = '.$tempTable.'.value LIMIT ?,?');
            $this->doPagination($query, $offset, $limit, 1, 2);
            $query->execute();

            $result = $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
            $this->cleanUpTempTable($tempTable);

            return $result;
        }
        return [];
    }

    /**
     * @return Bot[]
     */
    public function getBotsByType(int $type, int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        //TODO should these bounds checks be in the controller?
        $descriptor = new BotListDescriptor();
        $descriptor->type = $type;
        $count = $this->getCount($descriptor);
        if($limit > 0 && $offset < $count) {
            $query = $this->prepareSelect("*", "WHERE type=:type ORDER BY name, channel, cdate LIMIT :start,:stop");
            $this->doPagination($query, $offset, $limit);
            $query->bindValue(":type", $type, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
        }
        return [];
    }

    /**
     * @return Bot[]
     */
    public function getBotsWithoutType(int $offset = 0, ?int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        $descriptor = new BotListDescriptor();
        $descriptor->type = -1;
        if($limit > 0 && $offset < $this->getCount($descriptor)) {
            $query = $this->prepareSelect("*", "WHERE type IS NULL ORDER BY name, channel, cdate LIMIT :start,:stop");
            $this->doPagination($query, $offset, $limit);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
        }
        return [];
    }

    /**
     * @return Bot|bool
     */
    public function getBot(string $name)
    {
        $query = $this->prepareSelect("*", "WHERE name=?");
        $query->execute([ $name ]);

        $query->setFetchMode(PDO::FETCH_CLASS, Bot::class);
        return $query->fetch();
    }

    public function getBotOrThrow(string $name): Bot
    {
        $bot = $this->getBot($name);
        if(!$bot) {
            throw new \Exception("Bot does not exist");
        }
        return $bot;
    }


    public function removeBot(string $username): void
    {
        $bot = $this->getBot($username);
        $query = $this->prepareDelete("WHERE name=?");
        $query->execute(array($username));
        $this->addInactive([ $bot ]);
    }

    public function removeBots(array $ids): void
    {
        $tempTable = $this->createTempTable($ids);
        $where = 'INNER JOIN '.$tempTable.' AS t ON t.value = `table`.twitch_id';
        $desc = new BotListDescriptor();
        $desc->ids = $ids;
        $bots = $this->list($desc);
        $query = $this->prepareDelete($where);
        $query->execute();
        $this->cleanUpTempTable($tempTable);
        $this->addInactive($bots);
    }

    public function addBot(Bot $bot): void
    {
        if(!empty($bot->channel)) {
            $bot->channel = strtolower($bot->channel);
        }
        $structure = "(twitch_id,name,type,channel,channel_id,date) VALUES (?,?,?,?,?,NOW())";
        $query = $this->prepareInsert($structure);
        $query->bindValue(1, $bot->twitch_id, PDO::PARAM_INT);
        $query->bindValue(2, strtolower($bot->name), PDO::PARAM_STR);
        $query->bindValue(3, $bot->type, PDO::PARAM_INT);
        $query->bindValue(4, $bot->channel, PDO::PARAM_STR);
        $query->bindValue(5, $bot->channel_id, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @return Bot[]
     */
    public function getBotsByChannel(string $channel): array
    {
        $where = "WHERE channel=?";
        $query = $this->prepareSelect("*", $where);
        $query->execute([ $channel ]);

        return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
    }

    /**
     * @return Bot[]
     */
    public function getOldestBots(int $count = 10): array
    {
        $query = $this->prepareSelect("*", "WHERE cdate < DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY cdate ASC LIMIT ?");
        $query->bindValue(1, $count, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
    }

    /**
     * @param $id: User ID of the bot to touch.
     * @param $hard: If there are content modifications of the bot this should
     *               be true.
     * @return void
     */
    public function touchBot(string $id, bool $hard = false): void
    {
        $sql = "cdate=NOW() WHERE twitch_id=?";
        if($hard) {
            $sql = "date=NOW(), ".$sql;
        }
        $query = $this->prepareUpdate($sql);
        $query->execute([ $id ]);
    }

    public function updateBotByID(Bot $updatedBot): void
    {
        $sql = "name=?, type=?, date=NOW(), channel=?, channel_id=? WHERE twitch_id=?";
        $query = $this->prepareUpdate($sql);
        $query->execute([
            strtolower($updatedBot->name),
            $updatedBot->type,
            strtolower($updatedBot->channel),
            $updatedBot->channel_id,
            $updatedBot->twitch_id
        ]);
    }

    /**
     * @return Bot|bool
     */
    public function getBotByID(string $id)
    {
        $query = $this->prepareSelect("*", "WHERE twitch_id=?");
        $query->execute([ $id ]);

        $query->setFetchMode(PDO::FETCH_CLASS, Bot::class);
        return $query->fetch();
    }

    public function getBotByIDOrThrow(string $id): Bot
    {
        $bot = $this->getBotByID($id);
        if(!$bot) {
            throw new \Exception("Bot does not exist");
        }
        return $bot;
    }

    /**
     * @return Bot[]
     */
    public function getBotsByChannelID(string $channelID): array
    {
        $where = "WHERE channel_id=?";
        $query = $this->prepareSelect("*", $where);
        $query->execute([ $channelID ]);

        return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
    }

    /**
     * @param BotListDescriptor $descriptor
     * @return Bot[]
     */
    public function list(BotListDescriptor $descriptor): array
    {
        $query = $this->prepareList($descriptor);

        return $query->fetchAll(PDO::FETCH_CLASS, Bot::class);
    }

    /**
     * @param Bot[] $bots
     */
    private function addInactive(array $bots)
    {
        $structure = "(twitch_id,type,channel_id,date) VALUES (?,?,?,NOW())";
        $query = $this->prepareQuery("INSERT INTO `inactive_bots` ".$structure);
        foreach($bots as $bot) {
            $query->bindValue(1, $bot->twitch_id, PDO::PARAM_INT);
            $query->bindValue(2, $bot->type, PDO::PARAM_INT);
            $query->bindValue(3, $bot->channel_id, PDO::PARAM_INT);
            $query->execute();
        }
    }
}
