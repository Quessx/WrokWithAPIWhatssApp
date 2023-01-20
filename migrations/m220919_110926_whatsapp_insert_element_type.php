<?php

use yii\db\Migration;

/**
 * Class m220919_110926_whatsapp_insert_element_type
 */
class m220919_110926_whatsapp_insert_element_type extends Migration
{
    const TABLE_NAME = 'element_type';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert(self::TABLE_NAME, [
            'name' => 'Whatsapp',
            'code' => 'whatsapp',
            'version' => '0.1',
            'status' => 1,
            'action_url' => '',
            'base' => 1,
            'order_by' => '24'
        ]);

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->delete(self::TABLE_NAME, 'code=:code', array(':code' => 'whatsapp'));
    }
}
