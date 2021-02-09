<?php

namespace data\models;

use yii\base\Model;
use yii\base\NotSupportedException;
use common\components\Command;

class DataSource extends Model
{
    public $db;
    
    public static function tableName()
    {
        throw new NotSupportedException("Base table name must be defined.");    
    }
    
    public static function primaryKey()
    {
        throw new NotSupportedException("Primary key must be defined.");
    }
    
    /**
     * Table name for the runtime table
     *
     * @return string
     */
    public static function runtimeTableName()
    {
        return static::tableName();
    }
    
    /**
     * Table name for the data update
     *
     * @return string
     */
    public static function updateTableName()
    {
        return "update_".static::tableName();
    }
    
    /**
     * Table name of the runtime backup after commit
     *
     * @return string
     */
    public static function backupTableName()
    {
        return "backup_".static::tableName();
    }
    
    public function clone()
    {
        $this->db->drop(static::updateTableName());
        $this->db->copy(static::runtimeTableName(), static::updateTableName());
    }
    
    /**
     * Creates backup table from the runtime and commit the update to the runtime.
     */
    public function commit()
    {
        $this->db->drop(static::backupTableName());
        $this->db->rename(static::updateTableName(), static::runtimeTableName(), static::backupTableName());
    }
    
    /**
     * Rolls back the backup to the runtime and creates back the update table.
     */
    public function rollback()
    {
        $this->db->drop(static::updateTableName());
        $this->db->rename(static::backupTableName(), static::runtimeTableName(), static::updateTableName());
    }
    
    public function diff()
    {
        $diff = $this->db->diff($this->db->dbname, static::runtimeTableName(), $this->db->dbname, static::updateTableName(), static::primaryKey());
        
        $diff_file = $this->db->backuppath . '/diff_'.static::tableName().'_'.$this->db->createBackupId().'.csv';

        $fp = fopen($diff_file, 'w');

        if(count($diff) > 0){
            fputcsv($fp, array_keys($diff[0]), ",", '"');
            foreach($diff as $fields){
                fputcsv($fp, $fields, ",", '"');
            }
        } else {
            fputcsv($fp, ['Tables are identical'], ",", '"');
        }
            
        fclose($fp);
        
        return $diff_file;
    }
}