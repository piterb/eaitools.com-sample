<?php

namespace common\modules\icdph\models\external\registeruz;

use Yii;
use yii\base\Model;

/**
 * This is the model Organization for Register UZ source
 *
 */
class Organization extends Model
{
    public $id;
    public $ico;
    public $dic;
    public $sid;
    public $nazovUJ;
    public $mesto;
    public $ulica;
    public $psc;
    public $datumZalozenia;
    public $datumZrusenia;
    public $pravnaForma;
    public $skNace;
    public $velkostOrganizacie;
    public $druhVlastnictva;
    public $kraj;
    public $okres;
    public $sidlo;
    public $konsolidovana;
    public $idUctovnychZavierok;
    public $idVyrocnychSprav;
    public $zdrojDat;
    public $datumPoslednejUpravy;
 
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'],'integer'],
            [['ico','dic','sid','nazovUJ','mesto','ulica','psc','pravnaForma','skNace','velkostOrganizacie','druhVlastnictva','kraj','okres','sidlo','zdrojDat'],'string'],
            [['idUctovnychZavierok','idVyrocnychSprav'], 'safe'],
            [['datumZalozenia','datumZrusenia','datumPoslednejUpravy'],'date', 'format'=>'yyyy-MM-dd'],
            [['konsolidovana'], 'boolean'],
        ];
    }
    
    /**
     * Find organization ID (from source)
     * 
     * @param string $ico
     * @return string ID of the organization (in source)
     */
    public static function findIdByICO($ico)
    {
        $response = Yii::$app->get('httpclient.registeruz')->callget("cruz-public/api/uctovne-jednotky?zmenene-od=2000-01-01&max-zaznamov=100&ico={$ico}");
        
        $result = json_decode($response->content,true);
        
        if(isset($result['id']) && isset($result['id'][0])){
            return $result['id'][0];
        } else {
            return null;
        }
    }

    /**
     * Check for updates for from a specified time
     *
     * @param date $last_update
     * @param integer $last_id
     * @param string $ico
     * @param integer $maxrows
     * 
     * @return Returns JSON list of RUZ IDs that needs to be updated and number of remaining IDs that are not in the list. 
     */
    public static function findIdByDate($last_update, $last_id = 0, $ico = null, $maxrows = 10000)
    {        
        $ico = (!is_null($ico)) ? "&ico={$ico}" : "";
        
        $response = Yii::$app->get('httpclient.registeruz')->callget("cruz-public/api/uctovne-jednotky?zmenene-od={$last_update}&pokracovat-za-id={$last_id}&max-zaznamov={$maxrows}{$ico}");
        
        return json_decode($response->content,true);
    }

    /**
     * Find organization data by ICO
     * 
     * @param type $ico The id number of the organization
     * @return Organization
     */
    public static function findByICO($ico)
    {
        if(!$id = static::findIdByICO($ico)){
            return null;
        }
        
    	$response = Yii::$app->get('httpclient.registeruz')->callget("cruz-public/api/uctovna-jednotka?id={$id}");
    
        $result = json_decode($response->content,true);
        
    	if(isset($result['ico'])){
            $organization = new static();
            $organization->attributes = $result;
            return $organization;
    	} else {
            return null;
    	}
    }
    
    /**
     * Find all units specified by RUZ unit id list.
     * 
     * @param array $idList List of RUZ ids
     * @return \common\modules\icdph\models\external\registeruz\Organization[]
     */
    public static function findAllByIdList($idList)
    {
        $requests = [];
        foreach($idList as $id)
        {
            $requests[] = \Yii::$app->get('httpclient.registeruz')->prepareget("cruz-public/api/uctovna-jednotka?id={$id}");
        }
        
        try {
            $responses = \Yii::$app->get('httpclient.registeruz')->batchSend($requests);
        } catch (\Exception $e){
            Yii::error("An error occured during calling HTTP GET for RUZ. " . json_encode($e));
            // Sleep and retry once
            sleep(60);
            $responses = \Yii::$app->get('httpclient.registeruz')->batchSend($requests);
        }
        
        $organizations = [];
        foreach($responses as $response){
            if($response->isOk){
                $result = json_decode($response->content,true);
                
                if(isset($result['id'])){
                    $organization = new static();
                    $organization->attributes = $result;
                    $organizations[] = $organization;
                } else {
                    \Yii::warning("Unexpected RUZ response. It does not contain id. Response: ".$response->content,__METHOD__);
                }
            }
        }
        
        return $organizations;
    }
}
