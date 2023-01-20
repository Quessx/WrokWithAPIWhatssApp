<?php

namespace app\components\whatsapp;

use app\lib\HMB;
use app\lib\YiiCache;
use Yii;
use yii\web\HttpException;

class WhatsappEvent implements IEventRequest
{
    const DURATION_CACHE = 180;

    public static function filterTemplateTypes(array $templates): array
    {
        foreach ($templates as $index => $value) {
            if (!isset($value['status']) || $value['status'] !== 'approved' && $value['status'] !== 'moderate') {
                unset($templates[$index]);
                continue;
            }
        }
        sort($templates);
        return $templates;
    }

    public function cacheTemplateData($client)
    {
        $internalCache = Yii::$app->dbCache;
        $cache = new YiiCache($internalCache);
        $requestKey = 'whatsapp_cache' . $client->id;

        // коллбэк для кэширования
        $callbackRetrieveData = function () use ($client) {
            // если мы здесь, значит в кэше нет данных (или не кэшируем), получаем данные
            $wababaRequest = new WababaWebhookRequest();

            $apiResponse = $wababaRequest->getTeamplates();

            $dataToCache = HMB::json_decode($apiResponse);
            // в случае ошибки отдаём её напрямую, минуя кэш и всё остальное
            if (isset($dataToCache['status']) && !$dataToCache['status']) {
                $message = 'Cannot get account summary: [' . $dataToCache['errors']['code'] . '] ' . $dataToCache['errors']['title'];
                throw new HttpException(503, $message);  // 503 Service Unavailable
            }

            return $apiResponse;
            // результат будет кэширован если !empty($requestKey) и возвращён из YiiCache::query()
        };

        return $cache->query($requestKey, self::DURATION_CACHE, $callbackRetrieveData);
    }

    public function cacheWebhookData(int $id)
    {
        $internalCache = Yii::$app->dbCache;
        $cache = new YiiCache($internalCache);
        $requestKey = 'whatsapp_cache_webhooks' . $id;

        // коллбэк для кэширования
        $callbackRetrieveData = function () use ($id) {
            // если мы здесь, значит в кэше нет данных (или не кэшируем), получаем данные
            $wababaRequest = new WababaWebhookRequest();

            $apiResponse = $wababaRequest->getWebhooks();

            $dataToCache = HMB::json_decode($apiResponse);
            // в случае ошибки отдаём её напрямую, минуя кэш и всё остальное
            if (isset($dataToCache['status']) && !$dataToCache['status']) {
                $message = 'Cannot get account summary: [' . $dataToCache['errors']['code'] . '] ' . $dataToCache['errors']['title'];
                throw new HttpException(503, $message);  // 503 Service Unavailable
            }

            return $apiResponse;
            // результат будет кэширован если !empty($requestKey) и возвращён из YiiCache::query()
        };

        return $cache->query($requestKey, self::DURATION_CACHE, $callbackRetrieveData);
    }
}