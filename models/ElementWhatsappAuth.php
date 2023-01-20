<?php

namespace app\models;

/**
 * This is the model class for table "element_whatsapp_auth".
 *
 * @property string $auth_token
 * @property int $client_id
 * @property int $account_id
 */
class ElementWhatsappAuth extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'element_whatsapp_auth';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function rules()
    {
        return [
            [['auth_token'], 'required'],
            [['client_id', 'account_id'], 'integer'],
            [['auth_token'], 'string', 'max' => 4095]
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
            'client_id' => 'Client ID',
            'auth_token' => 'Wababa Access Token',
            'account_id' => 'Account amo ID'
        ];
    }

    public static function create($apiKey, $clientId, $amoId)
    {
        $model = new self();
        $model->auth_token = $apiKey;
        $model->client_id = $clientId;
        $model->account_id = $amoId;
        $model->save();
    }

}
