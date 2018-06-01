<?php

namespace Mini\Model;

use \PDO;

class TypeListDescriptor extends ListDescriptor
{
    /** @var bool */
    public $includeDisabled = false;

    /**
     * @return string[]
     */
    protected function addWhere(): array
    {
        $where = [];

        if($this->ids && count($this->ids)) {
            foreach($this->ids as $id) {
                $this->addParam($id, PDO::PARAM_INT);
            }
            $conditions = array_fill(0, count($this->ids), '?');
            $where[] = self::$idField.' IN ('.implode(',', $conditions).')';
        }

        if(!$this->includeDisabled) {
            $where[] = 'enabled=1';
        }

        return $where;
    }
}
