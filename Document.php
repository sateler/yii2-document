<?php

namespace sateler\document;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use Ramsey\Uuid\Uuid;
use \yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "document".
 *
 * @property string $id
 * @property string $name
 * @property string $mime_type
 * @property string $external_bucket_name
 * @property resource $contents
 * @property resource $local_contents
 * @property integer $created_at
 * @property integer $updated_at
 */
class Document extends ActiveRecord
{
    const LOG_TAG = "sateler.Document";

    const NO_BUCKET_LOCAL_SQL_STORAGE = "local-sql";

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
            [['name', 'mime_type', 'external_bucket_name'], 'required'],
            [['contents'], 'safe'],
            [['id'], 'string', 'max' => 36],
            [['name', 'mime_type', 'external_bucket_name'], 'string', 'max' => 255],
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
            'external_bucket_name' => 'External Storage Bucket Name',
            'contents' => 'Contents',
            'created_at' => 'Subido En',
            'updated_at' => 'Actualizado En',
        ];
    }

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new DocumentQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        // Set default storage from params
        if($this->isNewRecord && !$this->external_bucket_name) {
            $this->external_bucket_name = ArrayHelper::getValue(Yii::$app->params, 'yii2-document.default_external_bucket_name', self::NO_BUCKET_LOCAL_SQL_STORAGE);
        }
        parent::init();
    }

    /**
     * Var contents
     *
     * @return resource
     */
    public function getContents($forceFetch = false)
    {
        $bucket = $this->getBucket();
        if ($bucket) {
            if (is_null($this->external_contents) || $forceFetch) {
                $this->external_contents = $bucket->getFileContent($this->id);
            }
            return $this->external_contents;
        }
        else {
            if (is_null($this->local_contents)) {
                $model = static::find()->where(['id' => $this->id])->withContents()->one();
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
        $bucket = $this->getBucket();
        if ($bucket) {
            $this->external_contents = $contents;
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

        // Save in external storage if needed
        $bucket = $this->getBucket();
        if($bucket) {
            return $bucket->saveFileContent($this->id, $this->external_contents);
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
        $bucket = $this->getBucket();
        if($bucket && $this->deletedId) {
            $ret = $bucket->deleteFile($this->deletedId);
            if(!$ret) {
                Yii::error("Unable to delete file from external Storage. File was: {$this->deletedId}", self::LOG_TAG);
            }
        }
    }

    /**
     * Gets the external bucket instance or null
     *
     * @return \yii2tech\filestorage\BucketInterface
     */
    protected function getBucket()
    {
        if($this->external_bucket_name == self::NO_BUCKET_LOCAL_SQL_STORAGE) {
            return null;
        }
        return Yii::$app->fileStorage->getBucket($this->external_bucket_name);
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
}
