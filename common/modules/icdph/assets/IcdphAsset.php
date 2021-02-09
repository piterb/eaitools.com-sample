<?php

namespace common\modules\icdph\assets;

use yii\web\AssetBundle;

class IcdphAsset extends AssetBundle
{
    public $sourcePath = '@common/modules/icdph/assets/icdph';
    public $css = [
        'css/icdph-latest.min.css'
    ];
    public $js = [
        'js/icdph-latest.min.js',
    ];
    
    public function init() 
    {
        parent::init();
        
        if(!array_key_exists('icdph.icdphasset.access-token', \Yii::$app->params)){
            throw new \yii\base\InvalidConfigException("Parameter 'icdph.icdphasset.access-token' must be specified in order to use IcdphAsset bundle.");
        }
        
        foreach($this->js as $i => $js){
            if(strpos($js, 'js/icdph') >=0 ){
                $this->js[$i] = "{$js}?access-token=".\Yii::$app->params['icdph.icdphasset.access-token'];
            }
        }
    }
}
