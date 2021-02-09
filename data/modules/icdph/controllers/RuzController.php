<?php

namespace data\modules\icdph\controllers;

use Yii;
use yii\console\ExitCode;
use common\components\Command;
use data\modules\icdph\models\ruz\Checkforupdates;
use data\modules\icdph\models\ruz\Update;

class RuzController extends \yii\console\Controller
{
    public function actionCheckforupdates()
    {
        $op = Yii::$app->request->params[0];
        
        if(Yii::$app->mutex->acquire($op)){
           
            echo "Started checking for updates.\n";
            \Yii::info("Started checking for updates.",__METHOD__);
            
            try {

                $checkforpdates = Checkforupdates::findLast();
                
                // No updateds performed yet
                if(is_null($checkforpdates)){
                    $checkforupdates_new = new Checkforupdates();
                    $checkforupdates_new->ruz_last_update = "2000-01-01";
                    $checkforupdates_new->ruz_last_id = 0;
                    $checkforupdates_new->ruz_id_remaining_count = 99999;
                    if(!$checkforupdates_new->save()){
                        \Yii::error(json_encode($checkforupdates_new->errors), __METHOD__);
                        throw new \Exception(json_encode($checkforupdates_new->errors));
                    }
                    $checkforupdates_new->check(Yii::$app->params['icdph.update.checkforupdates.sleep']);
                }
                // Last update did not finished
                elseif($checkforpdates->ruz_id_remaining_count > 0) {
                    
                    \Yii::info("Resuming RUZ update from last run.", __METHOD__);
                    $checkforpdates->check(Yii::$app->params['icdph.update.checkforupdates.sleep']);
                    
                }
                // No update run today
                elseif(date('Y-m-d', strtotime('-'.\Yii::$app->params['icdph.update.checkforupdates.freqency'].' days')) > $checkforpdates->ruz_last_update) {
                    
                    $checkforupdates_new = new Checkforupdates();
                    $checkforupdates_new->ruz_last_update = $checkforpdates->ruz_last_update;
                    $checkforupdates_new->ruz_last_id = 0;
                    $checkforupdates_new->ruz_id_remaining_count = 99999;
                    if(!$checkforupdates_new->save()){
                        \Yii::error(json_encode($checkforupdates_new->errors), __METHOD__);
                        throw new \Exception(json_encode($checkforupdates_new->errors));
                    }
                    
                    $checkforupdates_new->check(Yii::$app->params['icdph.update.checkforupdates.sleep']);
                } else {
                    Yii::$app->mutex->release($op);
                    echo "No new updates.\n";
                    \Yii::info("No new updates.",__METHOD__);
                    return ExitCode::OK;
                }                
                
            } catch(\Exception $e) {
                Yii::$app->mutex->release($op);
                // Reenable DB logging if error was raised when logging disabled
                \Yii::$app->db->enableLogging = true;
                
                echo "Error occured during update." . json_encode($e) . "\n";
                \Yii::error("Error occured during update." . json_encode($e),__METHOD__);
                
                Yii::$app->mailer->compose()
                    ->setFrom(\Yii::$app->params['adminEmail'])
                    ->setTo(\Yii::$app->params['adminEmail'])
                    ->setSubject('API Update Alert: RUZ Checkforupdates')
                    ->setTextBody("An error occured during execution of update check from RUZ. Message: ".$e->getMessage()." ".json_encode($e))
                    ->send();
                
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            Yii::$app->mutex->release($op);
            echo "Check for update finished.\n";
            \Yii::info("Check for update finished.",__METHOD__);
            return ExitCode::OK;
            
        } else {
            echo "Update currently running. Cannot execute more than once.\n";
            \Yii::info("Update currently running. Cannot execute more than once.",__METHOD__);
            return ExitCode::TEMPFAIL;
        }
    }
    
    /**
     * Perform update of uctovne-jednotky from the update queue.
     * 
     * @param integer $limit Maximum number of units to be updated = max number of parallel http requests. Default 10.
     * @param boolean $retry Wheteher to retry failed updates first. Default false.
     * 
     * @return number
     */
    public function actionUpdate($limit = 10, $retry = 0)
    {
        $op = Yii::$app->request->params[0];
        
        if(Yii::$app->mutex->acquire($op)){
            
            echo "Started update.\n";
            \Yii::info("Started update.",__METHOD__);
            
            try {
                
                $start = microtime(true);
                $numupdated = Update::run($limit, $retry);
                $finish = microtime(true);
                $duration = $finish - $start;
                
                if(is_null($numupdated)){
                    echo "No updates planned.\n";
                    \Yii::info("No updates planned.",__METHOD__);
                    \Yii::$app->mutex->release($op);
                    return ExitCode::OK;
                } elseif($numupdated == 0){
                    throw new \Exception("Failed to perform update. Zero records have been updated.");
                } else {
                    echo "Updated {$numupdated} records in {$duration}s\n";
                    \Yii::info("Updated {$numupdated} records in {$duration}s\n",__METHOD__);
                    \Yii::$app->mutex->release($op);
                    return ExitCode::OK;
                }
                
            } catch(\Exception $e) {
                Yii::$app->mutex->release($op);
                
                echo "Error occured during update. " . json_encode($e) . "\n";
                \Yii::error("Error occured during update. " . json_encode($e),__METHOD__);
                
                Yii::$app->mailer->compose()
                ->setFrom(\Yii::$app->params['adminEmail'])
                ->setTo(\Yii::$app->params['adminEmail'])
                ->setSubject('API Update Alert: RUZ Update')
                ->setTextBody("An error occured during execution of update from RUZ. Message: ".$e->getMessage()." ".json_encode($e))
                ->send();
                
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
        } else {
            echo "Update currently running. Cannot execute more than once.\n";
            \Yii::info("Update currently running. Cannot execute more than once.",__METHOD__);
            return ExitCode::TEMPFAIL;
        }
    }
    
    /**
     * Perform full update of uctovne-jednotky from the update queue.
     *
     * @param integer $limit Maximum number of units to be updated = max number of parallel http requests. Default 10.
     * @param boolean $retry Wheteher to retry failed updates first. Default false.
     * @param integer $sleep Sleep between each loop cycle in seconds. Defaults to 2s.
     * @param integer $max_exec_time Maximum execution time in seconds the script will be running regardless the status of updates waiting in the queue. Defaults to 0 - unlimited.
     *
     * @return number
     */
    public function actionUpdateall($limit = 10, $retry = 0, $sleep = 2, $max_exec_time = 0)
    {
        $op = Yii::$app->request->params[0];
        
        if(Yii::$app->mutex->acquire($op)){
            
            echo date("Y-m-d H:i:s") . "\n";
            echo "Started update all\n";

            \Yii::info("Started update all.",__METHOD__);
            
            //try {
                
                $start = microtime(true);
                $totalupdated = 0;
                $stoptime = time() + $max_exec_time;
                
                // TODO: Commands not woring in websupport
                /*\Yii::$app->get('db_dwh')->drop("backup_".Update::tableName());
                \Yii::$app->get('db_dwh')->copy(Update::tableName(), "backup_".Update::tableName());*/
                
                $updatedurations = [];
                $loops = 0;
                while(($max_exec_time == 0 || time() < $stoptime) && (Update::countQueued() > 0 || ($retry && Update::countError() > 0))){
                    $updatestart = microtime(true);
                    $loops++;
                    $totalupdated = $totalupdated + Update::run($limit, $retry);
                    $updatefinish = microtime(true);
                    $updatedurations[] = $updateduration = $updatefinish - $updatestart;
                    echo "Updated {$totalupdated} records in {$updateduration}s from process start. Sleeping {$sleep}s\n";
                    sleep($sleep);
                }
                
                $finish = microtime(true);
                $duration = round($finish - $start);
                $avgduration = round(array_sum($updatedurations) / $loops);
                
                echo date("Y-m-d H:i:s") . "\n";
                echo "Updated:{$totalupdated} Queued:".Update::countQueued()." Errors:".Update::countError()." Duration:{$duration}s Avg.duration:{$avgduration}s #Loops:{$loops}\n";
                \Yii::info("Updated:{$totalupdated} Queued:".Update::countQueued()." Errors:".Update::countError()." Duration:{$duration}s Avg.duration:{$avgduration}s #Loops:{$loops}",__METHOD__);
                \Yii::$app->mutex->release($op);
                return ExitCode::OK;
                
            //} catch(\Exception $e) {
                Yii::$app->mutex->release($op);
                
                echo date("Y-m-d H:i:s") . "\n";
                echo "Error occured during update all. " . json_encode($e) . "\n";
                \Yii::error("Error occured during update all. " . json_encode($e),__METHOD__);
                
                $finish = microtime(true);
                $duration = $finish - $start;
                $stats = "Updated:{$totalupdated} Queued:".Update::countQueued()." Errors:".Update::countError()." Duration:{$duration}s";
                echo $stats."\n";
                \Yii::info($stats,__METHOD__);
                
                Yii::$app->mailer->compose()
                ->setFrom(\Yii::$app->params['adminEmail'])
                ->setTo(\Yii::$app->params['adminEmail'])
                ->setSubject('API Update Alert: RUZ Update')
                ->setTextBody("An error occured during execution of update all from RUZ. Message: ".$e->getMessage()." ".json_encode($e)." \n\n".$stats)
                ->send();
                
                return ExitCode::UNSPECIFIED_ERROR;
            //}
            
        } else {
            echo "Update all currently running. Cannot execute more than once.\n";
            \Yii::info("Update all currently running. Cannot execute more than once.",__METHOD__);
            return ExitCode::TEMPFAIL;
        }
    }
    
    public function actionTest()
    {
        try {
            echo "Running tests.\n";
            
            $command = Command::run(\Yii::$app->basePath."/../vendor/bin/codecept run api_icdph -c ".\Yii::$app->basePath."/../api/codeception.yml -n --html 2>&1", false);
            if($command->result != 0){
                throw new \Codeception\Exception\Fail("Codeception tests failed.\n\n".$command->outputString);
            }
            echo $command->outputString . "\n";
            
        } catch (\Codeception\Exception\Fail $e) {
            
            echo $e->getMessage() . "\n";
            \Yii::error($e->getMessage(),__METHOD__);
            
            Command::run("zip -r -j /tmp/codeception_report.zip ".\Yii::$app->basePath."/../api/tests/_output");
            
            Yii::$app->mailer->compose()
            ->setFrom(\Yii::$app->params['adminEmail'])
            ->setTo(\Yii::$app->params['adminEmail'])
            ->setSubject('API Health check failed')
            ->setTextBody($e->getMessage())
            ->attach("/tmp/codeception_report.zip")
            ->send();
            
            return ExitCode::UNSPECIFIED_ERROR;
            
        } catch (\Exception $e) {
            
            Yii::$app->mailer->compose()
            ->setFrom(\Yii::$app->params['adminEmail'])
            ->setTo(\Yii::$app->params['adminEmail'])
            ->setSubject('API Health check failed')
            ->setTextBody($e->getMessage())
            ->send();
            
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        return ExitCode::OK;
    }

    /** 
     * Same as updateall function, just without retry parameter. See updateall function description for more details.
     * 
     * @param integer $limit
     * @param integer $sleep
     * @param integer $max_exec_time
     *
     * @return number
    */
    public function actionUpdateall2($limit = 10, $sleep = 2, $max_exec_time = 0)
    {
        return $this->actionUpdateall($limit, 0, $sleep, $max_exec_time);
    }
}