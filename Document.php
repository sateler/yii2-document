<?php

namespace sateler\document;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use Ramsey\Uuid\Uuid;
use \yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\base\InvalidCallException;
use creocoder\flysystem\Filesystem;

/**
 * This is the model class for table "document".
 *
 * @property string $id
 * @property string $name
 * @property string $mime_type
 * @property string $filesystem_id
 * @property resource $contents
 * @property resource $local_contents
 * @property integer $created_at
 * @property integer $updated_at
 */
class Document extends ActiveRecord
{
    const LOG_TAG = "sateler.Document";

    /**
     * The Filesystem Instance
     *
     * @var Filesystem
     */
    private $filesystem = null;

    private $filesystem_contents = null;
    
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'document';
    }
    
    public function behaviors() {
        return [
            [
                'class' => TimestampBehavior::className(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'mime_type', 'filesystem_id'], 'required'],
            [['contents'], 'required', 'when' => function($model) { return $model->isNewRecord; }],
            [['contents'], 'safe'],
            [['id'], 'string', 'max' => 36],
            [['name', 'mime_type', 'filesystem_id'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => 'Nombre',
            'mime_type' => 'Tipo',
            'filesystem_id' => 'Filesystem Id',
            'contents' => 'Contents',
            'created_at' => 'Subido En',
            'updated_at' => 'Actualizado En',
        ];
    }

    /**
     * @inheritdoc
     * @return DocumentQuery the newly created [[DocumentQuery]] instance.
     */
    public static function find()
    {
        return new DocumentQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function __construct($config = [])
    {
        // Set default filesystem storage
        $this->filesystem_id = Yii::$app->documentManager->getFilesystemId();
        parent::__construct($config);
    }

    /**
     * Gets whether this model uses a filesystem or not.
     * Calculated using it's filesystem_id
     *
     * @return bool
     */
    private function usesFilesystem()
    {
        return $this->filesystem_id != DocumentManager::LOCAL_SQL_STORAGE_KEY;
    }

    /**
     * Gets the filesystem instance
     *
     * @return Filesystem
     */
    private function getFilesystem()
    {
        if($this->usesFilesystem() && !$this->filesystem) {
            $this->filesystem = Yii::$app->documentManager->getOrCreateFilesystem($this->filesystem_id);
        }
        return $this->filesystem;
    }

    /**
     * Var contents
     *
     * @return resource
     */
    public function getContents($forceFetch = false)
    {
        if ($this->usesFilesystem()) {
            if (is_null($this->filesystem_contents) || $forceFetch) {
                $this->filesystem_contents = $this->getFilesystem()->read($this->id);
            }
            return $this->filesystem_contents;
        }
        else {
            if (is_null($this->local_contents)) {
                $model = static::find()->where(['id' => $this->id])->one();
                $this->local_contents = ArrayHelper::getValue($model, 'local_contents');
            }
            return $this->local_contents;
        }
    }

    /**
     * Var contents
     *
     * @param resource $contents
     * @return void
     */
    public function setContents($contents)
    {
        if ($this->usesFilesystem()) {
            $this->filesystem_contents = $contents;
            $this->local_contents = null;
        }
        else {
            $this->local_contents = $contents;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if(!parent::beforeSave($insert)) {
            return false;
        }
        // If its a new record, set a new unique id
        if($insert) {
            $this->setNewUniqueId();
        }

        // Save in filesystem if defined and file provided.
        // A file may not be provided when updating just metadata (contents is required on create scenario only).
        if($this->usesFilesystem() && $this->filesystem_contents) {
            return $this->getFilesystem()->write($this->id, $this->filesystem_contents);
        }

        return true;
    }

    private $deletedId = null;
    /**
     * @inheritdoc
     */
    public function beforeDelete()
    {
        $this->deletedId = $this->id;
        return parent::beforeDelete();
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
        parent::afterDelete();

        // Delete from filesystem if needed
        if($this->usesFilesystem() && $this->deletedId) {
            $ret = $this->getFilesystem()->delete($this->deletedId);
            if(!$ret) {
                Yii::error("Unable to delete file from filesystem. File was: {$this->deletedId}", self::LOG_TAG);
            }
        }
    }

    /**
     * Make sure it's unique whithin our database
     *
     * @return void
     */
    private function setNewUniqueId()
    {
        do {
            $id = Uuid::uuid4()->toString();
            $this->id = $id;
            Yii::info("Generating document id: ".$this->id, self::LOG_TAG);
        }while (static::findOne($this->id));
    }

    /** Creates a Document model from an UploadedFile
     *
     * The model is not yet saved after creation
     *
     * @param UploadedFile $file The uploaded document
     * @return Document
     */
    public static function createFromUploadedFile(UploadedFile $file)
    {
        $doc = new Document();
        $doc->name = $file->name;
        $doc->mime_type = $file->type;
        $doc->contents = file_get_contents($file->tempName);
        return $doc;
    }
    
    /** Updates the contents of the document with the passed UploadedFile
     *
     * The model is not yet saved after update
     *
     * @param UploadedFile $file
     */
    public function updateFromUploadedFile(UploadedFile $file)
    {
        $this->name = $file->name;
        $this->mime_type = $file->type;
        $this->contents = file_get_contents($file->tempName);
    }

    /**
     * Move the document to a new storage
     *
     * @param string $destinationFilesystemId
     * @return boolean
     */
    public function moveTo(string $destinationFilesystemId) : bool
    {
        // Get previous storage info and file contents
        $prevUsesFilesystem = $this->usesFilesystem();
        $contents = $this->contents;
        $prevFilesystem = $this->getFilesystem();
        $prevId = $this->id;

        // Check if trying to move to the same filestystem it's currently stored in
        if($this->filesystem_id == $destinationFilesystemId) {
            return true;
        }

        // If no contents are found, fail. May be because contents are not available in current model.
        if(!$contents) {
            throw new InvalidCallException("Trying to move a document without its contents. Make sure you select the contents when retrieving the Model.");
        }

        // Copy file to new storage and save new storage info
        $this->filesystem_id = $destinationFilesystemId;
        $this->contents = $contents;
        $ret = $this->save();

        // Delete previous file from filesystem if needed
        if($prevUsesFilesystem && !$prevFilesystem->delete($prevId)) {
            Yii::warning("Move: Unable to delete previous file when moving", self::LOG_TAG);
        }

        return $ret;
    }
}
