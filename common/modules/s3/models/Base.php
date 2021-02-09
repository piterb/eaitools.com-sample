<?php

namespace common\modules\s3\models;

use Aws\Sdk;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Json;

/**
 * This is the model class for the base s3 class
 */
class Base extends Model
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
     * AWS SDK
     * 
     * @var \Aws\Sdk
     */
    public $sdk;
    
    public function init()
    {
        if(!isset($this->region)){
            throw new InvalidConfigException("Property region must be set.");
        }
        
        if(!isset($this->version)){
            throw new InvalidConfigException("Property version must be set.");
        }
        
        if(!isset($this->endpoint)){
            throw new InvalidConfigException("Property endpoint must be set.");
        }
        
        if(!isset($this->credentials)){
            throw new InvalidConfigException("Property credentials must be set.");
        }
        
        if(!isset($this->credentials['key'])){
            throw new InvalidConfigException("Property credentials key must be set.");
        }
        
        if(!isset($this->credentials['secret'])){
            throw new InvalidConfigException("Property credentials secret must be set.");
        }
        
        if(!isset($this->bucket)){
            throw new InvalidConfigException("Property bucket must be set.");
        }
        
        $this->sdk = new Sdk([
            'region'   => $this->region,
            'version'  => $this->version,
            'endpoint' => $this->endpoint,
            'credentials' => $this->credentials,
        ]);
    }
    
    public function formatRequest($operation, $params)
    {
        $config = [
            'region'   => $this->region,
            'version'  => $this->version,
            'endpoint' => $this->endpoint
        ];
        
        return "{$operation}\n\nConfiguration:\n".Json::encode($config, JSON_PRETTY_PRINT)."\n\nInput:\n".Json::encode($params, JSON_PRETTY_PRINT);
    }
}