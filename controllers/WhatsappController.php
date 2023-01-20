<?php


namespace app\controllers;


use app\filters\AmoAuthFilter;
use app\lib\HMB;
use app\components\whatsapp\WhatsappEvent;
use app\components\whatsapp\WababaWebhookRequest;
use app\models\Client;
use Yii;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;

class WhatsappController extends ApiController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return ArrayHelper::merge([
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'get-templates' => ['GET'],
                ],
            ],
        ], parent::behaviors());
    }

    // вызывается после создания контроллера
    public function init()
    {
        $this->setResponseFormatJSON();

        return parent::init();
    }

    // Получение от Wababa списка шаблонов и отдача на фронт

    /**
     * @return array
     */
    public function actionGetTemplates()
    {
        $whatsappEvent = new WhatsappEvent();
        $client = Client::getCurrentClient();
        $templates = '';
        try {
            $templates = $whatsappEvent->cacheTemplateData($client);
        } catch (\Throwable $e) {
            Yii::warning(['Error to get template' => $e], __METHOD__);
        }

        try {
            //Добавление вебхука
            $wababaRequest = new WababaWebhookRequest();
            $webhooksResponse = $whatsappEvent->cacheWebhookData($client->id);
            $url = 'https://' . $client->amo_domain . '/v1/whatsapp/whatsapp-web-hook?amo_id=' . $client->amo_id;
            $webhooks = HMB::json_decode($webhooksResponse);
            if ($webhooks && !in_array($url, $webhooks)) {
                $wababaRequest->addWebhook();
            }
        } catch (\Throwable $e) {
            Yii::warning(['Error to get webhooks' => $e], __METHOD__);
        }

        $templates = HMB::json_decode($templates);
        $templates = WhatsappEvent::filterTemplateTypes($templates);
        Yii::debug('-- Done --', __METHOD__);

        return $this->makeResponse($templates);
    }
}
