<?php

namespace data\modules\icdph\models\ruz;

use yii\db\Expression;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use common\modules\icdph\models\external\registeruz\Organization as RuzOrganization;
use common\modules\icdph\models\internal\registeruz\Organization as RuzOrganizationInternal;
use yii\helpers\ArrayHelper;

/**
 * Update model
 *
 * @property integer $id
 * @property integer $ruz_id
 * @property integer $ruz_checkforupdates_id
 * @property integer $ruz_status
 * @property integer $modified_at
 */
class Update extends ActiveRecord
{
    const STATUS_QUEUED = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_ERROR = 3;
    
    const SCENARIO_BATCH_INSERT = 'batchInsert';
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'icdph_ruz_update';
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getDb()
    {
        return \Yii::$app->get('db_dwh');
    }
    
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        
        return array_merge($scenarios, [
            self::SCENARIO_BATCH_INSERT => ['ruz_id', 'ruz_checkforupdates_id', 'ruz_status', 'modified_at'],
        ]);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'modified_at',
                'updatedAtAttribute' => 'modified_at',
                'value' => new Expression('UNIX_TIMESTAMP()'),
            ],
        ];
    }
    
    public static function countQueued()
    {
        return self::find()
            ->select(['count(ruz_id)'])
            ->where(['ruz_status'=>self::STATUS_QUEUED])
            ->scalar();
    }
    
    public static function countError()
    {
        return self::find()
        ->select(['count(ruz_id)'])
        ->where(['ruz_status'=>self::STATUS_ERROR])
        ->scalar();
    }
    
    /**
     * Run update process for queued units
     * 
     * @param integer $limit Max number of units(http requests in parallel) to be updated in a single run.
     * @param boolean $retry Wheteher to retry failed updates first. Default false.
     * @return null|number Number of updated units. Null if no updates were planned.
     */
    public static function run($limit, $retry = 0)
    {
        if($retry){
            $where = ['ruz_status'=>Update::STATUS_ERROR];
            //$orderBy = ['modified_at'=>SORT_ASC, 'ruz_id'=>SORT_ASC];
            $orderBy = ['ruz_id'=>SORT_ASC];
        } else {
            $where = ['ruz_status'=>Update::STATUS_QUEUED];
            //$orderBy = ['modified_at'=>SORT_ASC, 'ruz_id'=>SORT_ASC];
            $orderBy = ['ruz_id'=>SORT_ASC];
        }
        
        $queued = Update::find()
            ->where($where)
            ->orderBy($orderBy)
            ->limit($limit)
            ->all();

        if(count($queued)==0){
            return null;
        }
                
        $queued = ArrayHelper::map($queued, 'ruz_id', function($object, $defaultValue){ return $object; });

        $responses = RuzOrganization::findAllByIdList(ArrayHelper::getColumn($queued, 'ruz_id'));

        $success = 0;
        foreach($responses as $response){
            $organization_update = $queued[$response->id];
            $organization = new RuzOrganizationInternal();
            $organization->attributes = $response->attributes;
            $organization->ruz_checkforupdates_id = $organization_update->ruz_checkforupdates_id;
            
            if(!$organization->validate()){
                \Yii::error("Invalid data for organization ID: {$organization->id}. Skipping. Organization: ".json_encode($organization->attributes)."\n\nError:" . json_encode($organization->errors),__METHOD__);
                $organization_update->ruz_status = Update::STATUS_ERROR;
                $organization_update->save();
                continue;
            }
            
            if(!empty($organization->datumPoslednejUpravy) && $organization->datumPoslednejUpravy < $organization_update->checkforupdates->ruz_prev_update){
                \Yii::warning("Organization has not chanaged since last RUZ update according to 'datumPoslednejUpravy'. Skipping. Organization: ".json_encode($organization->attributes),__METHOD__);
                $organization_update->ruz_status = Update::STATUS_ERROR;
                $organization_update->save();
                continue;
            }
            
            try{
                if($organization->save()){
                    $organization_update->ruz_status = Update::STATUS_SUCCESS;
                    $organization_update->save();
                    $success++;
                } else {
                    \Yii::error("Error during RUZ update. Failed to save organization ID: {$organization->id}. Skipping. Organization: ".json_encode($organization->attributes)."\n\nError:" . json_encode($organization->errors),__METHOD__);
                    $organization_update->ruz_status = Update::STATUS_ERROR;
                    $organization_update->save();
                }
            } catch(\Throwable $e){
                \Yii::error("Error during RUZ update. Failed to save organization ID: {$organization->id}. Organization: ".json_encode($organization->attributes)."\n\nException: ". json_encode($e), __METHOD__);
                $organization_update->ruz_status = Update::STATUS_ERROR;
                $organization_update->save();
                throw $e;
            }           
        }
        
        return $success;
    }
    
    public function getCheckforupdates()
    {
        return $this->hasOne(Checkforupdates::class, ['id'=>'ruz_checkforupdates_id']);
    }
}
