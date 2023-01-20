<?php

namespace app\models;

use yii\web\NotFoundHttpException;

/**
 * This is the model class for table "element_whatsapp_status".
 * @property string $mass_id
 * @property string $key_id
 * @property string $instance_id
 * @property string $item_id
 * @property string $hash
 * @property integer $status
 * @property integer $phone
 * @property timestamp $created_at
 * @property int $client_id
 */
class ElementWhatsappStatus extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'element_whatsapp_status';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function rules()
    {
        return [
            [['key_id', 'status', 'mass_id', 'hash', 'item_id', 'instance_id'], 'required'],
            [['client_id', 'status', 'phone', 'item_id', 'instance_id'], 'integer'],
            [['key_id', 'mass_id', 'hash'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string>
     */
    public function attributeLabels()
    {
        return [
            'key_id' => 'Whatsapp message ID',
            'mass_id' => 'Wababa message ID',
            'status' => 'Status message',
            'created_at' => 'Create at',
            'client_id' => 'Client ID',
            'hash' => 'Sensei hash',
            'item_id' => 'Element ID',
            'instance_id' => 'Instance ID',
            'phone' => 'Phone'
        ];
    }

    public static function create(string $mass_id, int $status, int $client_id, string $hash, int $item_id, int $instance_id, ?string $key_id, int $phone): void
    {
        $model = self::find()->where(['mass_id' => $mass_id, 'status' => $status, 'client_id' => $client_id, 'phone' => $phone, 'item_id' => $item_id, 'instance_id' => $instance_id])->one();

        if (null === $model) {
            // не нашли, создаём
            $model = new self();
            $model->mass_id = $mass_id;
            $model->status = $status;
            $model->client_id = $client_id;
            $model->key_id = $key_id;
            $model->phone = $phone;
            $model->hash = $hash;
            $model->instance_id = $instance_id;
            $model->item_id = $item_id;
            $model->save();
        }
    }

    public static function updateKeyId(string $mass_id, string $key_id, int $status, int $client_id): void
    {
        $model = self::find()->where(['mass_id' => $mass_id, 'status' => $status, 'client_id' => $client_id])->one();

        if (null !== $model) {
            $model->key_id = $key_id;
        }
        $model->save();
    }

    /**
     * @throws \Exception
     */
    public static function deleteData(string $mass_id, int $client_id): void
    {
        $model = self::find()->where(['mass_id' => $mass_id, 'client_id' => $client_id])->one();

        if (null !== $model) {
            try {
                $model->delete();
            } catch (\Exception $e) {
                throw new \Exception('Не удалось удалить, такого не должно быть.');
            }
        }
    }
}
