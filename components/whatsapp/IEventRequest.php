<?php

namespace app\components\whatsapp;

use app\controllers\WhatsappController;

interface IEventRequest
{
    public const STATUS_PENDING = 0;
    public const STATUS_SENT = 1;
    public const STATUS_DELIVERED = 2;
    public const STATUS_READ = 3;
    public const STATUS_ANSWER = 4;

    public const STATUS = [
        'pending' => self::STATUS_PENDING,
        'sent' => self::STATUS_SENT,
        'delivered' => self::STATUS_DELIVERED,
        'read' => self::STATUS_READ,
        'answer' => self::STATUS_ANSWER
    ];

    public const FORMAT = [
        'image' => 'picture',
        'document' => 'file',
    ];

    public const SEND_TEMPLATE_FORMAT = [
        'pdf' => 'document',
        'video' => 'video',
        'image' => 'image'
    ];

    public static function filterTemplateTypes(array $templates): array;
}