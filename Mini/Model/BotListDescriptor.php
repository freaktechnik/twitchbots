<?php

namespace Mini\Model;

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
            $where[] = 'types.enabled=1';
            $needsTypes = true;
        }

        if($this->multichannel) {
            $where[] = 'types.multichannel=1';
            $needsTypes = true;
        }

        if($this->type) {
            $where[] = 'table.type=?';
            if($this->type > 0) {
                $this->params[] = $this->type;
            }
            else {
                $this->params[] = NULL;
            }
        }

        if($this->channelID) {
            $where[] = 'channel_id=?';
            $this->params[] = $this->channelID;
        }

        if($needsTypes) {
            $this->query .= ' LEFT JOIN types ON table.type = types.id';
        }

        return $where;
    }
}
