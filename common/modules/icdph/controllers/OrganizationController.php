<?php

namespace common\modules\icdph\controllers;

use common\components\ApiController;
use common\modules\icdph\models\Organization;

/**
 * Organization controller
 */
class OrganizationController extends ApiController
{
    /**
     * Search for organizations by specified name (Alias for search action)
     *
     * @param type $term The name of the organization
     * @return array List of the organizations.
     */
    public function actionIndex($term)
    {
        $this->actionSearch($term);
    }
    
    /**
     * Search for organizations by specified name.
     *
     * @param type $term The name of the organization
     * @return array List of the organizations.
     */
    public function actionSearch($term)
    {
        $resultset = [];
        
        if($organizations = Organization::searchByName($term)){
            foreach($organizations as $organization){
                $resultset[] = $organization->attributes;
            }
        }
        
        
        if($callback = \Yii::$app->request->get('callback')){
            header("Content-Type: application/javascript");
            echo $callback . "(" . json_encode($resultset) . ")";
            exit;
        } else {
            return $resultset;
        }
    }
    
    /**
     * Search for organizations by ICO.
     *
     * @param type $ico The ICO of the organization
     * @return array List of the organizations.
     */
    public function actionSearchIco($ico)
    {
        $resultset = [];
        
        if($organizations = Organization::searchByICO($ico)){
            foreach($organizations as $organization){
                $resultset[] = $organization->attributes;
            }
        }
        
        
        if($callback = \Yii::$app->request->get('callback')){
            header("Content-Type: application/javascript");
            echo $callback . "(" . json_encode($resultset) . ")";
            exit;
        } else {
            return $resultset;
        }
    }
    
    /**
     * Get the organization details by RPOID
     *
     * @param type $id rpoid retrieved from the index operation
     * @return array Organization details
     */
    public function actionView($id)
    {
        $result = [];
        if($organization = Organization::findByRPOID($id)){
            $result = $organization;
        }
        
        if($callback = \Yii::$app->request->get('callback')){
            header("Content-Type: application/javascript");
            echo $callback . "(" . json_encode($result) . ")";
            exit;
        } else {
            return $result;
        }
    }
}