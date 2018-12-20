<?php

namespace sateler\document;

use yii\db\ActiveQuery;


class DocumentQuery extends ActiveQuery
{
    public function noContents()
    {
        // Do not include the file contents.
        // Useful when joining this model from other related models
        $attributes = (new Document())->attributes();
        $keys = array_flip($attributes);
        unset($keys['local_contents']);
        $select = array_keys($keys);

        return $this->select($select);
    }
}