<?php

namespace sateler\document;

use Yii;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use League\Flysystem\Filesystem;


class ExternalFilesystem extends BaseObject
{
    /**
     * The flysystem filesystem Yii2 component id to be used
     *
     * @var string
     */
    public $filesystemId = null;

    /**
     * The flysystem instance
     *
     * @var Filesystem
     */
    private $flysystem = null;

    public function init()
    {
        if(!$this->filesystemId || !isset(Yii::$app->{$this->filesystemId})) {
            throw new InvalidConfigException("A valid filesystem id (yii2 component) must be provided.");
        }
        $this->flysystem = Yii::$app->{$this->filesystemId};

        parent::init();
    }

    /**
     * Gets the file
     *
     * @param string $path
     * @return string
     */
    public function read(string $path) : string
    {
        return $this->flysystem->read($path);
    }

    /**
     * Writes the file
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    public function write(string $path, string $contents) : bool
    {
        return $this->flysystem->write($path, $contents);
    }

    /**
     * Deletes the file
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path) : bool
    {
        return $this->flysystem->delete($path);
    }
}