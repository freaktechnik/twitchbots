<?php

namespace Mini\Model;

use \PDO;

class TypeListDescriptor extends ListDescriptor
{
    /** @var bool */
    public $includeDisabled = false;

    protected static $idType = PDO::PARAM_INT;

    /**
     * @return string[]
     */
    protected function addWhere(): array
    {
        $where = parent::addWhere();

        if(!$this->includeDisabled) {
            $where[] = 'enabled=1';
        }

        return $where;
    }
}
