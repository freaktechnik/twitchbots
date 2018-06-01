<?php

namespace Mini\Model;

class BotListDescriptor extends ListDescriptor
{
    public $type = 0;
    public $multichannel = false;
    public $includeDisabled = false;

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
            $this->params[] = $this->type;
        }

        if($needsTypes) {
            $this->query .= ' LEFT JOIN types ON table.type = types.id';
        }

        return $where;
    }
}
