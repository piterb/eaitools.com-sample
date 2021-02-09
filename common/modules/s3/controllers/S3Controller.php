<?php

namespace common\modules\s3\controllers;

use Yii;
use yii\console\ExitCode;
use common\modules\s3\models\UploadObject;
use common\modules\s3\models\DeleteObject;
use common\modules\s3\models\GetObject;
use common\modules\s3\models\CopyObject;

class S3Controller extends \yii\console\Controller
{
    /**
     * Get a file from the S3 storage
     * @param string $key Key of the file on S3
     * @param string $destination Absolute path were the file should be downloaded on the local machine.
     * 
     * @return number
     */
    public function actionDownload($key, $destination)
    {
        $start = microtime(true);
        
        $object = new GetObject($this->module->awsconfig);
        
        $result = $object->download($key, $destination);
        
        $finish = microtime(true);
        $duration = $finish - $start;
        
        $response = array_merge([
            "durationseconds" => sprintf("%.3f", $duration),
        ], $result->toArray());
        
        echo json_encode($response, JSON_PRETTY_PRINT)."\n";
        
        return ExitCode::OK;
    }
    
    /**
     * Upload the file to the specified S3 storage
     * @param string $file Absolute path to the file to be uploaded
     * @param string $key S3 destination file key
     * @param string $friendlyName Name under which file will be served for download
     * @param string $mime Mime type of the File
     * 
     * @return number
     */
    public function actionUpload($file, $key = null, $friendlyName = null, $mime = null)
    {
        $start = microtime(true);
        
        $basename = basename($file);
        $key = (is_null($key)) ? $basename : $key;
        $friendlyname = (is_null($friendlyName)) ? $basename : $friendlyName;
        $mime = (is_null($mime)) ? mime_content_type($file) : $mime;
        
        $object = new UploadObject($this->module->awsconfig);
        $result = $object->upload($file, $friendlyname, $key, $mime);
        
        $finish = microtime(true);
        $duration = $finish - $start;
        
        $response = array_merge([
            "durationseconds" => sprintf("%.3f", $duration),
            "Key" => urldecode(str_replace($object->baseurl, "", $result->get('ObjectURL'))),
        ], $result->toArray());
        
        echo json_encode($response, JSON_PRETTY_PRINT)."\n";
        
        return ExitCode::OK;
    }
    
    /**
     * Copy one S3 object to another
     * @param string $sourceKey Key of the source S3 object
     * @param string $targetKey Key of the target S3 object
     *
     * @return number
     */
    public function actionCopy($sourceKey, $targetKey)
    {
        $start = microtime(true);
        
        $object = new CopyObject($this->module->awsconfig);
        $result = $object->copy($sourceKey, $targetKey);
        
        $finish = microtime(true);
        $duration = $finish - $start;
        
        $response = array_merge([
            "durationseconds" => sprintf("%.3f", $duration),
            "Key" => urldecode(str_replace($object->baseurl, "", $result->get('ObjectURL'))),
        ], $result->toArray());
        
        echo json_encode($response, JSON_PRETTY_PRINT)."\n";
        
        return ExitCode::OK;
    }
    
    /**
     * Delete a file from the S3 storage
     * @param string $key Key of the file on S3
     * 
     * @return number
     */
    public function actionDelete($key)
    {
        $start = microtime(true);
        
        $object = new DeleteObject($this->module->awsconfig);
        $result = $object->delete($key);

        $finish = microtime(true);
        $duration = $finish - $start;

        $response = array_merge([
            "durationseconds" => sprintf("%.3f", $duration),
        ], $result->toArray());

        echo json_encode($response, JSON_PRETTY_PRINT)."\n";
        
        return ExitCode::OK;
    }
}