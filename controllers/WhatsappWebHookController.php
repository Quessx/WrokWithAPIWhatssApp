<?php


namespace app\controllers;


use app\components\whatsapp\IEventRequest;
use app\lib\HMB;
use app\models\Client;
use app\models\Client as SenseiClient;
use app\models\ProcessInstance;
use app\models\ElementWhatsappStatus;
use app\models\ProcessParamValue;
use yii\filters\Cors;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\Controller;
use Yii;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

class WhatsappWebHookController extends ApiController
{
    private $client;
    private $accountId;

    public function behaviors()
    {

        $origin = Yii::$app->getRequest()->getHeaders()->get('origin');
        $origin = [$origin];
        Yii::$app->controller->enableCsrfValidation = false;

        if ($amo_id = strstr(Yii::$app->getRequest()->getUrl(), 'amo_id')) {
            $this->accountId = explode('=', $amo_id)[1];
        }

        $behaviors = [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'whatsapp-web-hook' => ['GET', 'POST']
                ],
            ],
            [
                'class' => Cors::className(),
                'cors' => [
                    'Origin' => $origin,
                    'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Max-Age' => 86400,
                    'Access-Control-Expose-Headers' => [],
                ]
            ],
        ];
        //return ArrayHelper::merge($behaviors, parent::behaviors());
        return $behaviors;  //20190222: не используем behaviors ApiController, чтобы не подключать авторизацию
    }

    // вызывается после создания контроллера
    public function init()
    {
        $this->addAccountFeaturesToResponse = false;    //210608: в ответе любого метода фичи аккаунта не выдаём

        $this->setResponseFormatJSON();

        return parent::init();
    }

    // лучше вызывать этот метод из init(), но для этого нужно проверить совместимость остального кода
    protected function setResponseFormatJSON()
    {
        // переключаем формат вывода на JSON (влияет и на формат вывода исключения)
        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_JSON;
    }

    private function getCurrentClient()
    {
        if ($this->accountId !== null) {
            // по id аккаунта находим пользователя
            return SenseiClient::findIdentityByAmoAccountId($this->accountId);  // 220701: ищем через новый метод
        }

        return null;
    }

    public function actionWhatsappWebHook()
    {
        $this->client = $this->getCurrentClient();
        $hook = Yii::$app->request->post();
        Yii::debug(['$hook' => $hook], __METHOD__);
        if (isset($hook['inbound_message']['button']['text'])) {
            $this->workWithInboundButtonMessage($hook['inbound_message']);
        } else if (isset($hook['outbound_message'])) {
            $this->workWithOutboundMessage($hook['outbound_message']);
        } else if (isset($hook['inbound_message'])) {
            $this->workWithInboundOtherwiseMessage($hook['inbound_message']);
        }

        $response = [
            'status' => 200,
            'message' => 'Success',
            'data' => null,
        ];
        return $response;
    }

    private function workWithInboundButtonMessage($data)
    {
        if (isset($data['status']) && $data['status'] === 'error' || !isset($data['context']['id'])) {
            Yii::warning($data['errors']);
            $message = $data['errors'][0]['details'];
            throw new HttpException(503, $message);  // 503 Service Unavailable
        }

        $this->finishElement($data['context']['id'], $data['button']['text'], $data['wa_id'], false);
    }

    private function workWithOutboundMessage($data): void
    {
        if ($data['status'] === 'error') {
            Yii::warning($data['errors']);
            $message = $data['errors'][0]['details'];
            ElementWhatsappStatus::deleteData($data['mass_id'], $this->client->id);
            throw new HttpException(503, $message);  // 503 Service Unavailable
        } else if ($data['status'] === 'pending') {
            Yii::debug(['$data' => $data], __METHOD__);
            ElementWhatsappStatus::updateKeyId($data['mass_id'], $data['id'], IEventRequest::STATUS[$data['status']], $this->client->id);
            return;
        }

        $model = ElementWhatsappStatus::findOne(['key_id' => $data['id'], 'client_id' => $this->client->id]);

        if (!$model) {
            throw new NotFoundHttpException('Whatsapp Не найден по ИД ' . $data['id'], 404);
        }

        ElementWhatsappStatus::create($model->mass_id, IEventRequest::STATUS[$data['status']], $model->client_id, $model->hash, $model->item_id, $model->instance_id, $data['id'], $data['wa_id']);
    }

    private function workWithInboundOtherwiseMessage($data)
    {
        if (isset($data['status']) && $data['status'] === 'error') {
            Yii::warning($data['errors']);
            $message = $data['errors'][0]['details'];
            throw new HttpException(503, $message);  // 503 Service Unavailable
        }

        $text = $data['text'] ?? '';

        if (isset($data['context']['id'])) {
            $this->finishElement($data['context']['id'], $text, $data['wa_id'], true);
        } else {
            $this->finishElement(null, $text, $data['wa_id'], true);
        }
    }

    private function finishElement($key_id, $caption, $phone, $is_otherwise) {

        $model = $is_otherwise && !$key_id
            ?
            ElementWhatsappStatus::find()
            ->where(['client_id' => $this->client->id, 'phone' => $phone])
            ->orderBy(['created_at' => SORT_DESC])
            ->one()
            :
            ElementWhatsappStatus::findOne(['key_id' => $key_id, 'client_id' => $this->client->id, 'phone' => $phone]);


        if (!$model) {
            throw new NotFoundHttpException('Whatsapp Не найден по ИД ' . $key_id, 404);
        }

        //Записываем статус "отвечено"
        ElementWhatsappStatus::create($model->mass_id, IEventRequest::STATUS_ANSWER, $model->client_id, $model->hash, $model->item_id, $model->instance_id, $model->key_id, $model->phone);

        try {
            $processInstance = ProcessInstance::findByHash($model->hash);
        } catch (\Exception $e) {
            Yii::warning([
                'msg' => 'element/result Exception',
                'headers' => Yii::$app->request->getHeaders()->toArray(),
                'hash' => $model->hash,
                'caption' => $caption,
                'Exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    //'file' => $e->getFile(),
                    //'line' => $e->getLine(),
                    //'trace' => $e->getTraceAsString(),
                ],
            ], __METHOD__);

            throw $e;
        }

        if ($processInstance == null) {
            throw new NotFoundHttpException('Не найден экземпляр процесса по instance_id', 404);
        }

        $processItem = $processInstance->item;
        if (empty($processItem)) {
            return;
        }
        $localParameterId = HMB::json_decode($processItem->config)['localParameterId'];
        if ($localParameterId && $caption) {
            ProcessParamValue::updateItem($this->client->id, $processInstance->process_id, $processInstance->id, $localParameterId, $caption);
        }

        if (!$is_otherwise) {
            $processInstance->finishElement(null, $caption);
        } else {

            $results = $processItem->results;

            if (empty($results)) {
                return;
            }

            $continueResultId = null;
            foreach ($results as $result) {
                if ($result->is_otherwise) {
                    $continueResultId = $result->id;
                }
            }

            //Есть отвтет от пользователи и нету стрелки "другой ответ"
            if (empty($continueResultId)) {
                foreach ($results as $result) {
                    if (!$result->is_continue) {
                        $continueResultId = $result->id;
                    }
                }
            }

            Yii::debug(['$continueResultId' => $continueResultId], __METHOD__);
            // завершаем элемент по хешу результата
            $processInstance->finishElement($continueResultId);
        }
    }
}