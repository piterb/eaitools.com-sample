<?php
$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-data',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'data\controllers',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => 'yii\console\controllers\FixtureController',
            'namespace' => 'common\fixtures',
          ],
    ],
    'components' => [
        'log' => [
            'flushInterval' => 1,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning','info'],
                    'logVars' => [], // Do not log context
                    'exportInterval' => 20
                ],
            ],
        ],
        'mutex' => [
            'class' => 'yii\mutex\MysqlMutex',
        ],
    ],
    'modules' => [
        'icdph' => [
            'class' => 'data\modules\icdph\Module',
        ],
        'address' => [
            'class' => 'data\modules\address\Module',
        ],
        's3-eaitools-ams3' => [
            'class' => 'common\modules\s3\Module',
            
            'region' => 'ams3',
            'version' => 'latest',
            'endpoint' => 'https://s3bucket',
            'credentials' => [
                'key' => 'REPLACE_WITH_KEY',
                'secret' => 'REPLACE_WITH_SECRET',
            ],
            'bucket' => 'eaitools-test',
            'baseurl' => 'https://s3bucket/',
            'uploadACL' => 'private'
        ],
    ],
    'params' => $params,
];
