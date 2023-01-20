<?php

use yii\db\Migration;

/**
 * Class m221018_111003_element_whatsapp_status
 */
class m221018_111003_element_whatsapp_status extends Migration
{
    const TABLE_NAME = 'element_whatsapp_status';
    const INDEX_NAME = 'by_key_id';
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'mass_id' => $this->string(64),
            'key_id' => $this->string(64)->null(),
            'phone' => $this->string(16)->notNull(),
            'hash' => $this->string()->notNull(),
            'instance_id' => $this->integer()->notNull(),
            'item_id' => $this->integer()->notNull(),
            'client_id' => $this->integer(8)->notNull(),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'status' => $this->integer()->notNull(),
        ]);
        $this->alterColumn(self::TABLE_NAME, 'id', $this->integer(8)->notNull().' AUTO_INCREMENT');
        $this->createIndex(self::INDEX_NAME, self::TABLE_NAME, ['client_id', 'key_id'], false);   // $unique = false
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}
