<?php

namespace common\modules\s3\models;

use yii\helpers\Json;

/**
 * This is the model class for the UploadObject s3 class
 */
class UploadObject extends Base
{
    public function upload($source, $friendlyname, $dest, $mime)
    {
        $client = $this->sdk->createS3();
        
        $params = [
            'ACL' => $this->uploadACL,
            'ContentType' => $mime,
            'ContentDisposition' => "attachment; filename=\"{$friendlyname}\"",
            'Bucket' => $this->bucket,
            'Key'    => $dest,
            'SourceFile' => $source
            ];
        
        \Yii::info($this->formatRequest("putObject",$params), __METHOD__);
        
        $result = $client->putObject($params);

        \Yii::info(Json::encode($result, JSON_PRETTY_PRINT));
        
        \Yii::info("Verifying uploaded file integrity.",__METHOD__);
        
        $md5_local = md5_file($source);
        $md5_remote = str_replace('"', '', $result->get("ETag"));
        
        if($md5_local !== $md5_remote){
            throw new IntegrityCheckException("Integrity check failed while uploading file {$source}. Local and remote file are not identical.\nMD5 local:  {$md5_local}\nMD5 remote: {$md5_remote}\n");
        }
        
        \Yii::info("Integrity verification successfull.",__METHOD__);
        
        return $result;
    }
}