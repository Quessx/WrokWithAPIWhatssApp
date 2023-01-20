<?php

use yii\db\Migration;

/**
 * Class m221014_111002_element_whatsapp_auth
 */
class m221014_111002_element_whatsapp_auth extends Migration
{
    const TABLE_NAME = 'element_whatsapp_auth';
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'client_id' => $this->integer(8)->notNull(),
            'account_id' => $this->integer(8)->notNull(),
            'auth_token' => $this->text()->notNull()
        ]);
        $this->alterColumn(self::TABLE_NAME, 'id', $this->integer(8)->notNull().' AUTO_INCREMENT');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}
