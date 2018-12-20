<?php

namespace sateler\document;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use Ramsey\Uuid\Uuid;
use \yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\base\InvalidCallException;

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
 * @property ExternalFilesystem $externalFilesystem
 * @property boolean $useExternalStorage Wheter this model uses an external storage or not
 */
class Document extends ActiveRecord
{
    const LOG_TAG = "sateler.Document";

    const SCENARIO_CREATE = 'scenario-create';

    const LOCAL_SQL_STORAGE_KEY = 'local-sql';
    const DEFAULT_FILESYSTEM_ID_PARAM_NAME = 'sateler.document.default_filesystem_id';

    /**
     * Default filesystem to use un case no param name or value is given
     *
     * @var string
     */
    public $defaultFilesystemId = self::LOCAL_SQL_STORAGE_KEY;

    /**
     * Param name to get the default filesystem id
     *
     * @var string
     */
    public $defaultFilesystemIdParam = self::DEFAULT_FILESYSTEM_ID_PARAM_NAME;

    /**
     * The External Filesystem Instance
     *
     * @var ExternalFilesystem
     */
    private $filesystem = null;

    protected $external_contents = null;
    
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
            [['contents'], 'required', 'on' => self::SCENARIO_CREATE],
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
        // Set default filsystem storage
        $this->filesystem_id = ArrayHelper::getValue(Yii::$app->params, $this->defaultFilesystemIdParam, $this->defaultFilesystemId);
        parent::__construct($config);
    }

    /**
     * Gets whether this model uses an external storage or not.
     * Calculated using it's filesystem_id
     *
     * @return void
     */
    public function getUseExternalStorage()
    {
        return $this->filesystem_id != self::LOCAL_SQL_STORAGE_KEY;
    }

    /**
     * Gets the external filesystem instance
     *
     * @return ExternalFilesystem
     */
    public function getExternalFilesystem() : ?ExternalFilesystem
    {
        if($this->useExternalStorage && !$this->filesystem) {
            $this->filesystem = new ExternalFilesystem(['filesystemId' => $this->filesystem_id]);
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
        if ($this->useExternalStorage) {
            if (is_null($this->external_contents) || $forceFetch) {
                $this->external_contents = $this->externalFilesystem->read($this->id);
            }
            return $this->external_contents;
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
        if ($this->useExternalStorage) {
            $this->external_contents = $contents;
            $this->local_contents = null;
        }
        else {
            $this->local_contents = $contents;
        }
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        if($this->isNewRecord && $this->scenario == self::SCENARIO_DEFAULT) {
            $this->scenario = self::SCENARIO_CREATE;
        }
        return parent::beforeValidate();
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

        // Save in external storage if external storage selected and file provided.
        // A file may not be provided when updating just metadata (contents is required on create scenario only).
        if($this->useExternalStorage && $this->external_contents) {
            return $this->externalFilesystem->write($this->id, $this->external_contents);
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

        // Delete from external storage if needed
        if($this->useExternalStorage && $this->deletedId) {
            $ret = $this->externalFilesystem->delete($this->deletedId);
            if(!$ret) {
                Yii::error("Unable to delete file from external Storage. File was: {$this->deletedId}", self::LOG_TAG);
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
        $prevUseExternalStorage = $this->useExternalStorage;
        $contents = $this->contents;
        $prevExternalFilesystem = $this->externalFilesystem;
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

        // Delete previous file from external storage if needed
        if($prevUseExternalStorage && !$prevExternalFilesystem->delete($prevId)) {
            Yii::warning("Move: Unable to delete previous file when moving", self::LOG_TAG);
        }

        return $ret;
    }
}
