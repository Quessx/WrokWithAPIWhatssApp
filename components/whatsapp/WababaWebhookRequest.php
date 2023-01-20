<?php
namespace app\components\whatsapp;
use app\lib\AmoApiCommon;
use app\lib\HMB;
use app\models\Client;
use Yii;


class WababaWebhookRequest
{
    protected ?WhatsappApiClient $whatsappClient = null;
    private ?AmoApiCommon $amoApi = null;
    private ?Client $client = null;

    const SEND_TEMPLATE = 'sendTemplate.php';
    const GET_TEMPLATES = 'api/v1/templates.php';
    const WEBHOOK = 'hooks.php';
    const IMPORT_MESSAGE = 'api/v1/import_message.php';


    public function __construct()
    {
        $this->getWhatsappClient();
    }

    public function getAuthToken()
    {}

    private function getWhatsappClient(): void
    {
        if (!$this->whatsappClient) {
            $this->whatsappClient = new WhatsappApiClient();
        }
    }

    private function getClient(): void
    {
        if ( !$this->client ) {
            $this->client = Client::getCurrentClient();
        }
    }

    private function getAmoApi(): void
    {
        if ($this->amoApi == null) {
            $this->amoApi = new AmoApiCommon($this->client);
        }
    }

    public function sendAmoImportMessage($body)
    {
        return $this->whatsappClient->doSend(self::IMPORT_MESSAGE, Yii::$app->params['wababa']['integration_domain'], [], $body, 'POST');
    }

    public function getTeamplates()
    {
        return $this->whatsappClient->doSend(self::GET_TEMPLATES, Yii::$app->params['wababa']['integration_domain']);
    }

    public function addWebhook()
    {
        $client = Client::getCurrentClient();
        $body = ['url' => 'https://' . Yii::$app->params['SERVER_DOMAIN'] . '/v1/whatsapp/whatsapp-web-hook?amo_id=' . $client->amo_id];
        return $this->whatsappClient->doSend(self::WEBHOOK, Yii::$app->params['wababa']['domain'], [], $body, 'POST');
    }

    public function getWebhooks()
    {
        return $this->whatsappClient->doSend(self::WEBHOOK, Yii::$app->params['wababa']['domain'], [], [], 'GET');
    }

    public function wababaSendTemplate($body)
    {
        return $this->whatsappClient->doSend(self::SEND_TEMPLATE, Yii::$app->params['wababa']['domain'], [], $body, 'POST');
    }
}
