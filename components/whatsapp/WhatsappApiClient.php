<?php


namespace app\components\whatsapp;


use app\lib\AmoApiCommon;
use app\lib\CurlWrapper;
use app\lib\HMB;
use app\models\Client;
use app\models\Client as SenseiClient;
use app\models\ElementWhatsappAuth;
use Yii;
use yii\web\BadRequestHttpException;

class WhatsappApiClient
{
    protected $wababaClient = null;
    const WABABA_WIDGET_CODE = 'wababa';

    public function doSend($url, $domain, $params = [], $body = [], $method = 'GET', $headers = [])
    {

        $headers['Auth-Key'] = $this->getTokenAuth();
        $format = 'json';
        $timeout = 5;   // seconds
        if (strpos($url, 'http') === 0) {
            $_url = $url;
        } else {
            $_url = 'https://' . $domain . '/' . $url;
        }

        return $this->send($_url, $params, $method, $body, $headers, $format, $timeout);
    }

    private function send($url, $params, $method, $body, $headers, $format, $timeout)
    {
        $curl = new CurlWrapper('element-model-client/1.2');
        return $curl->send($url, $params, $method, $body, $headers, $format, $timeout);
    }

    private function getTokenAuth() {
        $client = Client::getCurrentClient();
        $auth = ElementWhatsappAuth::findOne(['client_id' => $client->id, 'account_id' => $client->amo_id]);
        if (!$auth) {
            //Получение токена
            $format = 'json';
            $timeout = 5;   // seconds
            $wababaApiKey = $this->initializeWababaApiKey($client);
            $body = ['service_api_key' => $wababaApiKey];
            $signature = hash_hmac('sha1', HMB::json_encode($body), Yii::$app->params['wababa']['secret_key']);
            $headers = [
                'Content-Type' => 'application/json',
                'X-Signature' => $signature
            ];
            $apiKeyResponse = $this->send(
                'https://' . Yii::$app->params['wababa']['domain'] . '/getApiKey.php',
                [],
                'POST',
                $body,
                $headers,
                $format,
                $timeout
            );
            $data = HMB::json_decode($apiKeyResponse);
            if (isset($data['api_key'])) {
                $apiKey = $data['api_key'];
            } else {
                Yii::warning(['Error get api key' => $data], __METHOD__);
                throw new BadRequestHttpException($data['errors']['title'] ?? 'Bad Request');
            }
            ElementWhatsappAuth::create($apiKey, $client->id, $client->amo_id);

            return $apiKey;
        }

        return $auth->auth_token;
    }

    private function initializeWababaApiKey($client)
    {
        $amoApi = new AmoApiCommon($client);
        $widgetSettings = $amoApi->getWidgetSettings(self::WABABA_WIDGET_CODE);
        Yii::debug([
            'widgetSettings' => $widgetSettings,
        ], __METHOD__);

        if ($widgetSettings === null || $widgetSettings === false) {
            return $widgetSettings;
        }
        if (empty($widgetSettings['api_key'])) {
            return '';
        }

        return $widgetSettings['api_key'];
    }
}
