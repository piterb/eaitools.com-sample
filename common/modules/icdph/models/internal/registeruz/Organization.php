<?php

namespace common\modules\icdph\models\internal\registeruz;

use yii\db\ActiveRecord;

/**
 * Uctovna Jednotka model
 *
 * @property integer $id
 * @property string $ico
 * @property string $dic
 * @property string $sid
 * @property string $nazovUJ
 * @property string $mesto
 * @property string $ulica
 * @property string $psc
 * @property date $datumZalozenia
 * @property date $datumZrusenia
 * @property string $pravnaForma
 * @property string $skNace
 * @property string $velkostOrganizacie
 * @property string $kraj
 * @property string $okres
 * @property string $sidlo
 * @property boolean $konsolidovana
 * @property string $zdrojDat
 * @property date $datumPoslednejUpravy
 * @property integer $ruz_checkforupdates_id
 */
class Organization extends ActiveRecord
{
    
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%icdph_ruz_uctovna_jednotka}}';
    }
    
    /**
     * {@inheritdoc}
     */
    public static function getDb()
    {
        return \Yii::$app->get('db_dwh');
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['datumZalozenia','datumZrusenia','datumPoslednejUpravy'], 'default', 'value' => null],
            
            [['id','ruz_checkforupdates_id'], 'integer'],
            [['ico','dic','sid','nazovUJ','mesto','ulica','psc','pravnaForma','skNace','velkostOrganizacie','druhVlastnictva','kraj','okres','sidlo','zdrojDat'], 'string'],
            [['datumZalozenia','datumZrusenia','datumPoslednejUpravy'],'date', 'format'=>'yyyy-MM-dd'],
            [['konsolidovana'],'boolean']
        ];
    }
    
    public static function findLastVersion($id)
    {
        return self::find()
            ->where(['id'=>$id])
            ->orderBy(['ruz_checkforupdates_id'=>SORT_DESC])
            ->one();
    }
    
    public static function searchByName($name, $limit = 10)
    {
        $lastDuplicateQuery = self::find()
            ->select(['id AS uj_last_id', 'MAX(ruz_checkforupdates_id) AS uj_last_ruz_checkforupdates_id'])
            ->where(['like','nazovUJ',"{$name}%", false])
            ->groupBy(['id']);
        
        return self::find()
            ->leftJoin(['uj_last'=>$lastDuplicateQuery], 'uj_last.uj_last_id = id')
            ->where('uj_last.uj_last_ruz_checkforupdates_id = ruz_checkforupdates_id')
            ->all();
    }
    
    public static function searchByICO($ico, $limit = 10)
    {
        $lastDuplicateQuery = self::find()
        ->select(['id AS uj_last_id', 'MAX(ruz_checkforupdates_id) AS uj_last_ruz_checkforupdates_id'])
        ->where(['ico' => $ico])
        ->groupBy(['id']);
        
        return self::find()
        ->leftJoin(['uj_last'=>$lastDuplicateQuery], 'uj_last.uj_last_id = id')
        ->where('uj_last.uj_last_ruz_checkforupdates_id = ruz_checkforupdates_id')
        ->all();
    }
    
    public static function findById($id)
    {
        return self::find()
            ->where(['id'=>$id])
            ->orderBy(['ruz_checkforupdates_id'=>SORT_DESC])
            ->one();
    }
}
