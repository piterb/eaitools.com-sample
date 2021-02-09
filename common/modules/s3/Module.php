<?php

namespace common\modules\s3;

use common\modules\s3\models\Base;

/**
 * icdph module definition class
 */
class Module extends \yii\base\Module
{
    /**
     * Region to connect to
     *
     * @var string
     * @example ams3
     */
    public $region;
    
    /**
     * The version of the web service to utilize
     *
     * @var string
     * @example latest
     */
    public $version;
    
    /**
     * The full URI of the webservice
     *
     * @var string
     * @example https://ams3.digitaloceanspaces.com
     */
    public $endpoint;
    
    /**
     * Credentials for the service
     *
     * @var array
     * @example
     * [
     *      'key' => 'XKFDSFSDFDSFSDF',
     *      'secret' => 'mM9384kdfmdsfjdsrkfedsfiodsjfsoidifjsdf',
     * ]
     */
    public $credentials;
    
    /**
     * Bucket to use
     *
     * @var string
     * @example mybucket
     */
    public $bucket;
    
    /**
     * Base URL of the bucket
     *
     * @var string
     */
    public $baseurl;
    
    /**
     * ACL for upload operation
     *
     * @var string
     * @example public-read
     */
    public $uploadACL;
    
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'common\modules\s3\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here
    }
    
    public function getAwsconfig()
    {
        $awsconfignames = array_keys(get_class_vars(Base::class));
        
        $properties_all = get_object_vars($this);
        $properties_aws = [];
        
        foreach($properties_all as $key => $property){
            if(in_array($key, $awsconfignames)){
                $properties_aws[$key] = $property;
            }
        }

        return $properties_aws;
    }
}
