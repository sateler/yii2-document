<?php

namespace sateler\document\migrations;

use yii\db\Migration;
use Yii;

class m180816_185140_add_document_external_storage extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->alterColumn('document', 'contents', "LONGBLOB NULL DEFAULT NULL");
        $this->renameColumn('document', 'contents', 'local_contents');
        $this->addColumn('document', 'external_bucket_name', $this->string(255)->notNull()->defaultValue('local-sql'));
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $ret = Yii::$app->db->createCommand("SELECT COUNT(*) FROM document WHERE local_contents is NULL")->queryScalar();
        if($ret > 0) {
            echo "m180816_185140_document_contents_is_nullable cannot be reverted when `document.contents` column contain NULL items.\n";
            return false;
        }
        $this->renameColumn('document', 'local_contents', 'contents');
        $this->alterColumn('document', 'contents', "LONGBLOB NOT NULL");
        $this->dropColumn('document', 'external_bucket_name');

        return true;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m180816_185140_document_contents_is_nullable cannot be reverted.\n";

        return false;
    }
    */
}
