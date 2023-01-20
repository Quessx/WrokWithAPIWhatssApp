<?php


namespace app\models\element;


use app\components\await\AwaitElementModel;
use app\components\AwaitElementFactory;
use app\components\whatsapp\IEventRequest;
use app\components\whatsapp\WhatsappEvent;
use app\lib\AmoAccountTransfer;
use app\lib\HAmoSensei;
use app\components\whatsapp\WababaWebhookRequest;
use app\lib\HMB;
use app\models\Client;
use app\models\ElementWait;
use app\models\ProcessInstance;
use app\models\ProcessItemResult;
use app\models\ElementWhatsappStatus;
use yii\web\NotFoundHttpException;
use Yii;

class Whatsapp extends AwaitElementModel
{

    /**
     * {@inheritdoc}
     */
    public function scenarios()
    {
        return parent::scenarios();
    }

    /**
     * {@inheritdoc}
     */
    /**
     * @return array<mixed>
     */
    public function rules()
    {
        return parent::rules();
    }

    // импорт конфигурации элемента при импорте процесса
    // (переопределяется наследующим классом, если нужно что-то преобразовывать)
    public function actionImportConfig()
    {

    }

    // запуск элемента
    public function actionStartElement()
    {
        $client = Client::getCurrentClient();
        $wababaRequest = new WababaWebhookRequest();
        list($data, $sendImportMessages, $is_button) = $this->setSettingToSend($client);

        $response = $wababaRequest->wababaSendTemplate($data);

        $response = HMB::json_decode($response);

        $this->checkError($response);

        ElementWhatsappStatus::create($response['mass_id'], IEventRequest::STATUS_PENDING, $client->id, $this->hash, $this->process['item_id'], $this->process['instance_id'], 'null', $this->normalizePhoneNumber($data['to']));

        $this->sendImportMessage($sendImportMessages, $wababaRequest);
        $this->checkWait();

        Yii::debug('-- Done --', __METHOD__);

        return $this->finishWorkElement($is_button);
    }

    private function sendImportMessage($data, $request) {
        if (isset($data) && $data) {
            foreach ($data as $importMessage) {
                $request->sendAmoImportMessage($importMessage);
            }
        } else {
            throw new NotFoundHttpException('Пустой запрос на импорт сообщения');    // останавливаем процесс
        }
    }

    private function finishWorkElement($is_button)
    {
        // Выбор результата элемента
        if (!$this->config['check_time'] && !$is_button) {
            // (если не ждём результатов) завершаем элемент с результатом по умолчанию
            return $this->finishWorkDefault();
        } else {
            // иначе просто останавливаем процесс
            return null;
        }
    }

    private function checkWait(): void
    {
        $continueResultId = null;
        // если у элемента задано ожидание результата
        if ($this->config['check_time']) {
            $taskResults = ProcessItemResult::find()
                ->andWhere(['process_item_id' => $this->process['item_id'], 'is_continue' => 1])
                ->all();

            foreach ($taskResults as $result) {
                //is_continue - указатель на спецстрелку
                if ($result->is_continue != 0) {
                    $continueResultId = $result->id;
                    break;
                }
            }
        }

        if ($this->config['check_time'] && !empty($this->config['wait_config'])) {
            $elementWait = new Wait();
            $elementWaitConfig = new ElementWaitConfig();
            $elementWaitConfig->config = [
                'hash' => $this->hash
            ];
            $elementWaitConfig->awaitElementType = AwaitElementFactory::AWAIT_ELEMENT_TYPE_WEBHOOK;
            $elementWait->config = [
                'day' => $this->config['wait_config']['day'] ?? 0,
                'hour' => $this->config['wait_config']['hour'] ?? 0,
                'minute' => $this->config['wait_config']['minute'] ?? 0,
            ];
            $elementWait->hash = $this->hash;
            $elementWait->addResultCode($continueResultId);

            $elementWait->actionStartElement($elementWaitConfig);
        }
    }

    /**
     * @return array<mixed>
     * @throws NotFoundHttpException
     */
    private function getUsersWithAmojoIdV4(): array
    {
        $amoUsers = $this->amoApi->amoV4Get('users', ['with' => 'amojo_id']);
        if (empty($amoUsers['_embedded']['users'])) {
            Yii::warning([
                'msg' => 'Пользователи не найдены или не получены',
                'tags' => [
                    'entity_id' => $this->entity_id,
                    'instance_id' => $this->process['instance_id'],
                    'process_id' => $this->process['process_id'],
                    'process_item_id' => $this->process['item_id'],
                ],
            ], __METHOD__);
            throw new NotFoundHttpException('Не удалось получить данные о пользователях');    // останавливаем процесс
        }
        return $amoUsers['_embedded']['users'];
    }

    /**
     * @param Client $client
     * @return array<bool, array{account_id:int, to:string, name:string, amojo_id:int, language:string, params:array{body:array<string>}}>
     * @throws NotFoundHttpException
     */
    private function setSettingToSend($client)
    {
        $config = $this->config;
        $is_button = false;
        $sendImportMessages = [];

        $lead = $this->amoApi->getLeadById($this->entity_id);
        $contact = $this->amoApi->getContactById($lead['main_contact']['id']);
        $phone = $this->amoApi->getEntityPhone($contact);

        $users = $this->getUsersWithAmojoIdV4();
        $key = array_search($lead['responsible_user_id'], array_column($users, 'id'));
        $amojo_id = $users[$key]['amojo_id'];

        $whatsappEvent = new WhatsappEvent();
        $templates = [];
        try {
            $templates = $whatsappEvent->cacheTemplateData($client);
            if ($templates) {
                $templates = HMB::json_decode($templates);
            }
        } catch (\Exception $e) {
            Yii::info(['Error to get template' => $e], __METHOD__);
        }

        if (empty($phone)) {
            $this->amoApi->addSystemNote($this->entity_id, 1, \app\lib\Locale::getAcc('back.whatsapp.error.phone'));
            throw new NotFoundHttpException('Не задан номер телефона');    // останавливаем процесс
        }



        $sendImportText = [];
        $templateMasksHeader = [];
        $templateMasksBody = [];
        $templateCurrentKey = array_search($config['templateId'], array_column($templates, 'id'));

        $wababaTemplateRequest = [
            "account_id" => $client->amo_id,
            "to" => $phone,
            "name" => $templates[$templateCurrentKey]['name'],
            "amojo_id" => $amojo_id,
            "language" => "ru",
            "params" => [
                "body" => []
            ]
        ];

        $importMessage = [
            "sender" => [
                "amojo_id" => $amojo_id,
                "name" => $users[$key]['name']
            ],
            "receiver" => [
                "name" => $contact['name'],
                "phone" => $phone,
            ],
            "message" => [
                "type" => "text",
                "text" => ""
            ],
        ];

        if (!empty($config['header']['type']) && $config['header']['type'] !== 'no_file') {
            if (empty($config['header']['image']['link'])) {
                Yii::warning([
                    'msg' => 'Получена пустая ссылка на файл',
                    'link' => $config['header']['image']['link']
                ], __METHOD__);

                $this->amoApi->addSystemNote($this->entity_id, 1, \app\lib\Locale::getAcc('back.whatsapp.error.link'));
                throw new NotFoundHttpException('Пустая ссылка на файл');    // останавливаем процесс
            }
            $type = IEventRequest::SEND_TEMPLATE_FORMAT[strtolower($config['header']['type'])];
            $config['header']['image']['link'] = trim($config['header']['image']['link']);
            $wababaTemplateRequest['params']['header'] = [
                'type' => $type,
                $type => $config['header']['image']
            ];
        } else if (!empty($config['templateMasks']['header'])) {
            $templateMasksHeader = $config['templateMasks']['header'];
            $wababaTemplateRequest['params']['header'] = [
                'type' => 'text',
                'text' => $this->getTemplateMasks($config['templateMasks']['header'])
            ];
        }

        if (!empty($config['templateMasks']['body'])) {
            $templateMasksBody = $config['templateMasks']['body'];
            $wababaTemplateRequest['params']['body'] = $this->getTemplateMasks($config['templateMasks']['body']);
        }

        if ($templates && isset($templates[$templateCurrentKey]['components'])) {
            $componentsTemplate = $templates[$templateCurrentKey]['components'];
            foreach ($componentsTemplate as $component) {
                if (isset($component['type']) && strtolower($component['type']) == 'header' && strtolower($component['format']) != 'text') {
                    if (empty($wababaTemplateRequest['params']['header'])) {
                        $wababaTemplateRequest['params']['header'] = [
                            'type' => strtolower($component['format']),
                            strtolower($component['format']) => [
                                'link' => $component['example']['header_handle'][0] ?? ''
                            ]
                        ];
                    }
                    $sendMessage = $importMessage;
                    $sendMessage['message'] = [
                        'type' => IEventRequest::FORMAT[strtolower($component['format'])] ?? strtolower($component['format']),
                        'media' => trim($config['header']['image']['link'] ?? $component['example']['header_handle'][0])
                    ];
                    $sendImportMessages[] = $sendMessage;
                    unset($sendMessage);
                } else if (isset($component['type']) && strtolower($component['type']) == 'header' && strtolower($component['format']) == 'text') {
                    $sendImportText[] = self::replaceMasks($component['text'], $templateMasksHeader);
                } else if (isset($component['type']) && strtolower($component['type']) == 'body') {
                    $sendImportText[] = self::replaceMasks($component['text'], $templateMasksBody);
                } else if (isset($component['type']) && strtolower($component['type']) == 'footer') {
                    $sendImportText[] = $component['text'];
                } else if (isset($component['type']) && strtolower($component['type']) == 'buttons') {
                    foreach ($component['buttons'] as $index => $button) {
                        $text = \app\lib\Locale::getAcc('back.whatsapp.button_name') . ' ' . $index . ' => ' . $button['text'];
                        $sendImportText[] = $text;
                    }
                    $is_button = true;
                }
            }
        }

        $wababaTemplateRequest['params']['body'] = $this->replacePatterns($wababaTemplateRequest['params']['body']);
        $sendImportText = $this->replacePatterns($sendImportText);

        if(isset($wababaTemplateRequest['params']['header']['text'])) {
            $wababaTemplateRequest['params']['header']['text'] = $this->replacePatterns($wababaTemplateRequest['params']['header']['text'])[0];
        }

        $importMessage['message'] = [
            'type' => 'text',
            'text' => implode("\n", $sendImportText)
        ];

        $sendImportMessages[] = $importMessage;

        return [$wababaTemplateRequest, $sendImportMessages, $is_button];
    }

    private static function replaceMasks($text, $masks) {
        if (isset($masks) && $masks) {
            foreach ($masks as $search => $replace) {
                $text = str_replace($search, $replace, $text);
            }
        }
        return $text;
    }

    private function getTemplateMasks($masks)
    {
        $data = [];
        foreach ($masks as $mask) {
            if (empty($mask)) {
                Yii::warning([
                    'msg' => 'Получена пустая маска',
                    'masks' => $mask
                ], __METHOD__);

                $this->amoApi->addSystemNote($this->entity_id, 1, \app\lib\Locale::getAcc('back.whatsapp.error.empty_mask', ['mask' => $mask]));
                throw new NotFoundHttpException('Пустая маска');    // останавливаем процесс
            }
            $data[] = $mask;
        }
        return $data;
    }

    /**
     * @param $data
     * @throws NotFoundHttpException
     */
    private function checkError($data): void
    {
        if (!isset($data['mass_id'])) {
            $this->amoApi->addSystemNote($this->entity_id, 1, \app\lib\Locale::getAcc('back.whatsapp.error.response'));
            throw new NotFoundHttpException('Не получен ответ от вабаба');    // останавливаем процесс
        }
    }

    public function finishAfterWaiting(ElementWait $wait, array $config)
    {
        Yii::info('___idElementWhatsapp___', __METHOD__);
        Yii::info($config, __METHOD__);
        // ищем инстанс по хэшу
        $processInstance = ProcessInstance::findByHash($this->hash);

        if (empty($processInstance)) {
            return;
        }

        $this->entity_id = $processInstance->entity_id;

        $processItem = $processInstance->item;
        if (empty($processItem)) {
            return;
        }

        $results = $processItem->results;

        if (empty($results)) {
            return;
        }

        $continueResultId = null;
        foreach ($results as $result) {
            if ($result->is_continue) {
                $continueResultId = $result->id;
            }
        }

        if (empty($continueResultId)) {
            return;
        }

        // завершаем элемент по хешу результата
        $this->finishElement($this->hash, $continueResultId);
    }

}
