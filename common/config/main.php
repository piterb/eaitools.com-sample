<?php
return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'formatter' => [
            'class' => 'common\components\Formatter',
            'dateFormat' => 'dd.MM.yyyy',
            'datetimeFormat' => 'dd.MM.yyyy HH:mm:ss',
            'decimalSeparator' => ',',
            'thousandSeparator' => ' ',
            'currencyCode' => 'EUR',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'assetManager' => [
            'appendTimestamp' => true,
        ],
        'i18n' => [
            'translations' => [
                'app*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en-US',
                ],
                'icdph*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/modules/icdph/messages',
                    'sourceLanguage' => 'en-US',
                ],
            	'common*' => [
            		'class' => 'yii\i18n\PhpMessageSource',
            		'basePath' => '@common/messages',
            		'sourceLanguage' => 'en-US',
            	],
            	'frontend*' => [
            		'class' => 'yii\i18n\PhpMessageSource',
            		'basePath' => '@common/messages',
            		'sourceLanguage' => 'en-US',
            	],
            	'backend*' => [
            		'class' => 'yii\i18n\PhpMessageSource',
            		'basePath' => '@common/messages',
            		'sourceLanguage' => 'en-US',
            	],
            ],
        ],
        'httpclient.registeruz' => [
            'class' => 'common\components\connectors\http\Client',
            'transport' => 'yii\httpclient\CurlTransport',
            'baseUrl' => 'https://xxxxxxxxxxxxxxxx',
            'options' => [
                'timeout' => 15,
            ],
    	],
    ],
];
