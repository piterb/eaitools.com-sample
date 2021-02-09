<?php

namespace data\controllers;

use Yii;
use yii\console\ExitCode;
use data\models\Proxysql;
use common\components\Command;

class DbController extends \yii\console\Controller
{
    /**
     * Backup a specified database
     * @param string $dbid DB id of the database to be backedup
     * @param string $targetdbid Optional. DB id of the database where the backup should be imported. If specified also integrity check of the backup will be made. Otherwise backup will be stored in files only.
     * @return integer Returns 0 if backup was successfull
     */
    public function actionBackupDb($dbid, $targetdbid = null)
    {
        $start = microtime(true);
        $db = \Yii::$app->get($dbid);
        $backup = $db->backupdb((!is_null($targetdbid)) ? \Yii::$app->get($targetdbid) : null);
        $finish = microtime(true);
        $duration = $finish - $start;
        echo sprintf("Backup created in %.3fs.\nBackup: {$backup}\n", $duration);
        return ExitCode::OK;
    }
    
    /**
     * Restore a database from backup.
     *
     * @param string $dbid DB id of the database to be restored
     * @param string $dbfile Backup file to be restored. Backup file must be sql.gz format.
     * @return integer Returns 0 if restore was successfull
     */
    public function actionRestoreDb($dbid, $dbfile)
    {
        $start = microtime(true);
        $db = \Yii::$app->get($dbid);
        $db->restoredb($dbfile);
        $finish = microtime(true);
        $duration = $finish - $start;
        echo sprintf("Restored in %.3fs.\n", $duration);
        return ExitCode::OK;
    }
    
    /**
     * Verify checksum of two tables
     *
     * @param string $db1 DB component id of the first database
     * @param string $db2 DB component id of the second database
     * @param array $tables Optional. Comma separated list of db table names to be compared, e.g. table1,table2,table3. If not specified, all tables from db1 will be compared.
     * @return integer Returns 0 if verification was successfull and tables are identical.
     */
    public function actionVerifyChecksumTables($db1, $db2, array $tables = [])
    {
        if(!is_array($tables)){
            echo "Invalid argument for tables list.\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        if(count($tables) < 1){
            $tables = \Yii::$app->get($db1)->schema->tableNames;
        }
        
        $success = true;
        
        foreach($tables as $table){
            $checksum1 = \Yii::$app->get($db1)->checksum($table);
            $checksum2 = \Yii::$app->get($db2)->checksum($table);
            
            if($checksum1 === $checksum2){
                echo "Tables {$table} are identical. Checksum: {$checksum1}\n";
            } else {
                echo "Tables {$table} are different. Checksums:\n{$checksum1}\n{$checksum2}\n";
                $success = false;
            }
        }
        
        if($success === true){
            echo "All tables are identical.\n";
            return ExitCode::OK;
        } else {
            echo "Error. Tables are different.\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Create a backup of a specified DB table. Backup file will be CSV separated by , optionally enclosed by " line break \n without headers.
     * @param string $table DB table name
     * @param string $dbid Optional. Id of the database from which the table will be backed up. Default DB component will be used if not specified.
     * @param string $backupdbid Id of the DB component config where the backup will be imported. If specified backup will be additionally imported to this database and integrity check of the original and backup data will be made via checksum. Must be different from the default DB component.
     * @return integer Returns 0 if backup was successfull
     */
    public function actionBackup($table, $dbid = null, $backupdbid = null)
    {   
        $start = microtime(true);
        
        if(!is_null($dbid))
            $db = Yii::$app->get($dbid);
        else 
            $db = Yii::$app->db;
        
        if(!is_null($backupdbid)){
            $backup = $db->backup($table, \Yii::$app->get($backupdbid));
        } else {
            $backup = $db->backup($table);
        }
        $finish = microtime(true);
        $duration = $finish - $start;
        echo sprintf("Backup created in %.3fs.\nSchema: {$backup['schema']}\nData: {$backup['data']}\n", $duration);
        return ExitCode::OK;
    }
    
    /**
     * Restore a table from backup. Restored table will be truncated first.
     * 
     * @param string $table Table to be restored
     * @param string $file_data Backup file to be restored. Backup file must be CSV separated by , optionally enclosed by " line break \n without headers.
     * @param string $file_schema Optionally, schema definition where the data should be imported.
     * @param string $dbid Optional. Id of the database to which the table will be imported. Default DB component will be used if not specified.
     * @return integer Returns 0 if restore was successfull
     */
    public function actionRestore($table, $file_data, $file_schema = null, $dbid = null)
    {
        $start = microtime(true);
        
        if(!is_null($dbid))
            $db = Yii::$app->get($dbid);
        else
            $db = Yii::$app->db;
            
        $numrows = $db->restore($table, ['schema'=>$file_schema, 'data'=>$file_data]);
        $finish = microtime(true);
        $duration = $finish - $start;
        echo sprintf("Restored rows {$numrows} in %.3fs.\n", $duration);
        return ExitCode::OK;
    }
    
    /**
     * Verify checksum of two tables
     * 
     * @param string $db1 DB component id of the first database
     * @param string $db2 DB component id of the second database
     * @param string $table1 First table name
     * @param string $table2 Second table name
     * @return integer Returns 0 if verification was successfull and tables are identical.
     */
    public function actionVerifyChecksum($db1, $db2, $table1, $table2)
    {
        $checksum1 = \Yii::$app->get($db1)->checksum($table1);
        $checksum2 = \Yii::$app->get($db2)->checksum($table2);
        
        if($checksum1 === $checksum2){
            echo "Tables are identical. Checksum: {$checksum1}\n";
            return ExitCode::OK;
        } else {
            echo "Tables are different. Checksums:\n{$checksum1}\n{$checksum2}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
    
    /**
     * Gzip and upload whole DB backup directory (specified in backuppath property)
     * 
     * @param string $dbid Id of the database to be backedup
     * @param string $label Optional. If specified label suffix will be added to the filename
     * @param string $s3id Optional. Id of the s3 connection config where the backup should be uploaded
     * 
     * @return number Returns 0 if backup to S3 was successfull.
     */
    public function actionBackupToS3($dbid, $label = null, $s3id = "s3-eaitools-ams3")
    {
        $db = \Yii::$app->get($dbid);
        $suffix = (!is_null($label)) ? "_{$label}" : "";
        $backupfilebase = "backup_".$db->createBackupId()."{$suffix}.tar.gz";
        $backupfile = "/tmp/{$backupfilebase}";
        $s3key = "backup/{$backupfilebase}";
        Command::run("tar czvf {$backupfile} -C {$db->backuppath} . 2>&1");
        
        echo "Uploading backup {$backupfile} to S3 {$s3key}.\n";
        
        Command::run(\Yii::$app->basePath."/../yii {$s3id}/s3/upload {$backupfile} {$s3key} 2>&1");
        
        echo "Backup uploaded to S3: {$s3key}\n";
        
        Command::run("rm -rf {$backupfile} 2>&1");
        Command::run("rm -rf {$db->backuppath}/* 2>&1");
        
        return ExitCode::OK;
    }
    
    /**
     * Make a side by side diff of two tables with the same schema.
     * 
     * @param string $dbid Id of the database where the tables are located
     * @param string $tableName1 Table name with or without schema. If no schema name included, current DB connection schema will be used.
     * @param string $tableName2 Table name with or without schema. If no schema name included, current DB connection schema will be used.
     * @param string $key Order by key or primary key of both tables
     * @param string $format Optional. Defaults to json. Output format of the diff. Possible values are: json, csv
     * 
     * @return number Returns 0 if diff successfully made and exported to output.
     */
    public function actionDiff($dbid, $tableName1, $tableName2, $key, $format = null)
    {
        $db = \Yii::$app->get($dbid);

        if(strpos($tableName1, ".") !== false ){
            list($schema1, $table1) = explode(".", $tableName1);
        } else{
            $schema1 = $db->dbname;
            $table1 = $tableName1;
        }
        if(strpos($tableName2, ".") !== false ){
            list($schema2, $table2) = explode(".", $tableName2);
        } else{
            $schema2 = $db->dbname;
            $table2 = $tableName2;
        }
        
        $diff = $db->diff($schema1, $table1, $schema2, $table2, $key);
        
        if(count($diff) > 0){
            if($format === 'json' || is_null($format)){
                print json_encode($diff, JSON_PRETTY_PRINT);
            } elseif($format === 'csv'){
                $fp = fopen('php://output', 'w');
                fputcsv($fp, array_keys($diff[0]), ",", '"');
                foreach($diff as $fields){
                    fputcsv($fp, $fields, ",", '"');
                }
                fclose($fp);
            }
        } else {
            echo "Tables are identical.\n";
        }
        
        return ExitCode::OK;
    }
}