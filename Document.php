<?php

namespace sateler\document;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use Ramsey\Uuid\Uuid;
use \yii\db\ActiveRecord;

/**
 * This is the model class for table "document".
 *
 * @property string $id
 * @property string $name
 * @property string $mime_type
 * @property resource $contents
 * @property integer $created_at
 * @property integer $updated_at
 */
class Document extends ActiveRecord
{
    const TAG = "sateler.Document";
    
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
            [['name', 'mime_type', 'contents'], 'required'],
            [['contents'], 'string'],
            [['id'], 'string', 'max' => 36],
            [['name', 'mime_type'], 'string', 'max' => 255],
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
            'contents' => 'Contents',
            'created_at' => 'Subido En',
            'updated_at' => 'Actualizado En',
        ];
    }
    
    // Set new document's id as a UUID
    public function beforeSave($insert) {
        if(!parent::beforeSave($insert)) {
            return false;
        }
        if($insert) {
            do {
                $id = Uuid::uuid4()->toString();
                $this->id = $id;
                Yii::info("Generating document id: ".$this->id, self::TAG);
            }while (static::findOne($this->id));
        }
        return true;
    }

    /** Creates a Document model from an UploadedFile
     *
     * The model is not yet saved after creation
     *
     * @param UploadedFile $file The uploaded document
     * @return Document
     */
    public static function createFromUploadedFile(UploadedFile $file) {
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
    public function updateFromUploadedFile(UploadedFile $file) {
        $this->name = $file->name;
        $this->mime_type = $file->type;
        $this->contents = file_get_contents($file->tempName);
    }
}
