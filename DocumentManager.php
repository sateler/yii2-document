<?php

namespace sateler\document;

use Yii;
use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\base\InvalidConfigException;
use creocoder\flysystem\Filesystem;


class DocumentManager extends BaseObject
{
    const LOCAL_SQL_STORAGE_KEY = 'local-sql';

    /**
     * Filesystem ID to be used
     *
     * @var string
     */
    public $filesystemId;

    /**
     * Available filesystems
     *
     * @var Filesystem[]
     */
    public $filesystems = [];

    /**
     * Returns the filesystem id to be used.
     * If not set, returns the local sql default.
     *
     * @return string
     */    
    public function getFilesystemId()
    {
        if (isset($this->filesystemId)) {
            if (array_key_exists($this->filesystemId, $this->filesystems)) {
                return $this->filesystemId;
            } else {
                throw new InvalidConfigException("The filesystemId specified must be defined in the filesystems array");
            }
        } else {
            return self::LOCAL_SQL_STORAGE_KEY;
        }
    }

    /**
     * Returns the filesystem of the given id
     *
     * @param string $filesystemId
     * @return Filesystem
     */    
    public function getOrCreateFilesystem($filesystemId)
    {
        if (array_key_exists($filesystemId, $this->filesystems)) {
            $filesystem =  ArrayHelper::getValue($this->filesystems, $filesystemId);
            if (!$filesystem instanceof Filesystem) {
                $this->filesystems[$filesystemId] = Yii::createObject($filesystem);
            }
            return $this->filesystems[$filesystemId];
        } else {
            throw new InvalidConfigException("The filesystemId specified must be defined in the filesystems array");
        }
    }
}