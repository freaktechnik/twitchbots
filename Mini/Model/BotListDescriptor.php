<?php

namespace Mini\Model;

use \PDO;

class BotListDescriptor extends ListDescriptor
{
    public $type = 0;
    public $multichannel = false;
    public $includeDisabled = false;
    public $channelID = 0;

    protected static $idField = 'twitch_id';

    /**
     * @return string[]
     */
    protected function addWhere(): array
    {
        $needsTypes = false;
        $where = parent::addWhere();

        if(!$this->includeDisabled) {
            $where[] = '(types.enabled=1 OR `type` IS null)';
            $needsTypes = true;
        }

        if($this->multichannel) {
            $where[] = '(types.multichannel=1 OR `type` IS null)';
            $needsTypes = true;
        }

        if($this->type) {
            if($this->type > 0) {
                $where[] = '`type` = ?';
                $this->addParam($this->type, PDO::PARAM_INT);
            }
            else {
                $where[] = '`type` IS null';
            }
        }

        if($this->channelID) {
            $where[] = 'channel_id=?';
            $this->addParam($this->channelID);
        }

        if($needsTypes) {
            $this->query .= ' LEFT JOIN types ON table.`type` = types.id';
        }

        return $where;
    }
}
