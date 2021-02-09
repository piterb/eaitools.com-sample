<?php

namespace data\modules\icdph\models\ruz;

use yii\db\ActiveRecord;
use common\modules\icdph\models\external\registeruz\Organization as RuzOrganization;

/**
 * Checkforupdates model
 *
 * @property integer $id
 * @property date $ruz_last_update
 * @property integer $ruz_last_id
 * @property integer $ruz_id_remaining_count
 * @property string $ruz_update_msg
 */
class Checkforupdates extends ActiveRecord
{
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%icdph_ruz_checkforupdates}}';
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getDb()
    {
        return \Yii::$app->get('db_dwh');
    }
    
    public static function findLast()
    {
        return self::find()
            ->orderBy(['ruz_last_update' => SORT_DESC, 'id'=>SORT_DESC])
            ->one();
    }
    
    /**
     * Check for new updates
     * @param integer $sleep Check is only available in 10000 batches, thus sleep between in check to reduce target server load.
     * 
     * @throws \Exception
     */
    public function check($sleep = 2)
    {
        \Yii::info("Checking for new updates since: {$this->ruz_last_update}.", __METHOD__);
        $start = time();
        $pocetZostavajucichIdPrev = null;
        \Yii::$app->get('db_dwh')->enableLogging = false;
        while($this->ruz_id_remaining_count > 0){
            
            \Yii::info("Downloading new id list. Last ID: {$this->ruz_last_id}", __METHOD__);
            $response = RuzOrganization::findIdByDate($this->ruz_last_update, $this->ruz_last_id);
            
            if(isset($response['pocetZostavajucichId'])){
                
                if(!is_null($pocetZostavajucichIdPrev) && $response['pocetZostavajucichId'] >= $pocetZostavajucichIdPrev) {
                    $msg = "Response parameter 'pocetZostavajucichId' is not decreasing. Possible dead loop. Response: " . json_encode($response);
                    \Yii::error($msg, __METHOD__);
                    throw new \Exception($msg);
                } else {
                    $pocetZostavajucichIdPrev = $response['pocetZostavajucichId'];
                }
                
                $this->ruz_id_remaining_count = $response['pocetZostavajucichId'];
                
                \Yii::info("Downloaded. Remaining IDs count: {$response['pocetZostavajucichId']}", __METHOD__);
                
                // Last iteration
                if($response['pocetZostavajucichId'] == 0){
                    $this->ruz_last_update = date("Y-m-d");
                } 
            } else {
                $msg = "Response parameter 'pocetZostavajucichId' must be set. Response: " . json_encode($response);
                \Yii::error($msg, __METHOD__);
                throw new \Exception($msg);
            }
                       
            if(isset($response['id'])){
                
                \Yii::info("Downloaded IDs count: ".count($response['id']), __METHOD__);
                
                // Validate response
                if(!is_array($response['id'])){
                    $msg = "Response parameter id must be an array. Response: ".json_encode($response);
                    \Yii::error($msg, __METHOD__);
                    throw new \Exception($msg);
                }
                
                $queuedforupdate = [];
                $modified_at = time();
                foreach($response['id'] as $id){
                    if(is_numeric($id)){
                        if(count(Update::findAll(['ruz_id'=>$id, 'ruz_checkforupdates_id'=>$this->id])) == 0){
                            $update = new Update(['scenario'=>Update::SCENARIO_BATCH_INSERT]);
                            $update->ruz_id = intval($id);
                            $update->ruz_checkforupdates_id = $this->id;
                            $update->ruz_status = Update::STATUS_QUEUED;
                            $update->modified_at = $modified_at;
                            $queuedforupdate[] = $update->attributes;
                            
                            $this->ruz_last_id = $update->ruz_id;
                        } else {
                            \Yii::warning("Duplicate RUZ ID for this update session: {$id}. Skipping.", __METHOD__);
                        }
                    } else {
                        \Yii::warning("Response parameter id contains non numeic value: ".json_encode($id).". Skipping.", __METHOD__);
                    }
                }

                \Yii::$app->get('db_dwh')->createCommand()
                    ->batchInsert(Update::tableName(), ['ruz_id', 'ruz_checkforupdates_id','ruz_status','modified_at'], $queuedforupdate)
                    ->execute();
                
            } else {
                \Yii::info("Response did not contain any IDs.", __METHOD__);
            }
            
            if(!$this->save()){
                \Yii::error(json_encode($this->errors), __METHOD__);
                throw new \Exception(json_encode($this->errors));
            }

            sleep($sleep);
        }
        \Yii::$app->get('db_dwh')->enableLogging = true;
        
        $finish = time();
        $msg = "Check for updates finished. Duration: ".($finish-$start)."s, Total IDs to be updated: ".Update::find()->select(['COUNT(ruz_id)'])->where(['ruz_checkforupdates_id'=>$this->id])->scalar();
        \Yii::info($msg, __METHOD__);
        $this->ruz_update_msg = $msg;
        $this->save();
    }
    
    public function getRuz_prev_update()
    {
        $lastchecks = $this->find()
            ->select(['ruz_last_update'])
            ->orderBy(['ruz_last_update'=>SORT_DESC])
            ->limit(2)
            ->all();
        
            if(count($lastchecks) >= 2){
                return $lastchecks[1]->ruz_last_update;
            } else {
                return '2000-01-01';
            }
    }
}
