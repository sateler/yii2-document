<?php

namespace sateler\document\migrations;

use yii\db\Migration;

class m170208_223719_add_document_model extends Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('document', [
            'id' => $this->string(36)->notNull(),
            'name' => $this->string(255)->notNull(),
            'mime_type' => $this->string(255)->notNull(),
            'contents' => "LONGBLOB NOT NULL",
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->addPrimaryKey('PK_document_id', 'document', ['id']);
    }

    public function down()
    {
        return $this->dropTable('document');
    }
}
