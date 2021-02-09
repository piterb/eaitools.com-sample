<?php

namespace common\components\db;

use common\components\Command;
use yii\base\InvalidConfigException;
use yii\db\Exception;
use yii\base\InvalidValueException;

/**
 * {@inheritdoc}
 */
class Connection extends \yii\db\Connection
{    
    const DIFF_STATUS_LEFTONLY = 'leftonly';
    const DIFF_STATUS_RIGHTONLY = 'rightonly';
    const DIFF_STATUS_EQUAL = 'equal';
    const DIFF_STATUS_MODIFIED = 'modified';
    
    const DSN_REGEX = "/^((?P<driver>\w+):\/\/)?((?P<user>\w+)?(:(?P<password>\w+))?@)?((?P<adapter>\w+):)?((host=(?P<host>[\w-\.]+))|(unix_socket=(?P<socket_file>[\w\/\.]+)))((:(?P<port>\d+))|(([;]{0,1}port=(?P<port1>\d+))))?((;dbname=|\/)(?P<database>[\w\-]+))?$/im";
    
    /**
     * Absolute path to the folder were backup will be made. By default it is @app/runtime/backup
     * @var string
     */
    public $backuppath = null;
    
    /**
     * Template for the SQL command for dump
     * @var string
     */
    public $sqldumptpl = 'mysqldump -h "{host}" -P {port} -u "{username}" -p\'{password}\' {dbname}';
    
    /**
     * Template for the SQL command for dumping the schema
     * @var string
     */
    public $sqldumpschematpl = 'mysqldump -d -h "{host}" -P {port} -u "{username}" -p\'{password}\' {dbname}';
    
    /**
     * Template for the SQL command for importing database
     * @var string
     */
    public $sqlimporttpl = 'mysql -h "{host}" -P {port} -u "{username}" -p\'{password}\' {dbname}';
    
    /**
     * Template for the SQL command for importing schema
     * @var string
     */
    public $sqlimportschematpl = 'mysql -h "{host}" -P {port} -u "{username}" -p\'{password}\' {dbname}';
    
    /**
     * Populated SQL dump command
     * @return string
     */
    public function getSqldump()
    {
        $sqlcmd = $this->sqldumptpl;
        $sqlcmd = str_replace("{host}", $this->host, $sqlcmd);
        $sqlcmd = str_replace("{port}", $this->port, $sqlcmd);
        $sqlcmd = str_replace("{username}", $this->username, $sqlcmd);
        $sqlcmd = str_replace("{password}", $this->password, $sqlcmd);
        $sqlcmd = str_replace("{dbname}", $this->dbname, $sqlcmd);
        
        return $sqlcmd;
    }
    
    /**
     * Populated SQL command for dumping the schema
     * @return string
     */
    public function getSqldumpschema()
    {
        $sqlcmd = $this->sqldumpschematpl;
        $sqlcmd = str_replace("{host}", $this->host, $sqlcmd);
        $sqlcmd = str_replace("{port}", $this->port, $sqlcmd);
        $sqlcmd = str_replace("{username}", $this->username, $sqlcmd);
        $sqlcmd = str_replace("{password}", $this->password, $sqlcmd);
        $sqlcmd = str_replace("{dbname}", $this->dbname, $sqlcmd);
        
        return $sqlcmd;
    }
    
    /**
     * Populated command for importing data
     * @return string
     */
    public function getSqlimport()
    {
        $sqlcmd = $this->sqlimporttpl;
        $sqlcmd = str_replace("{host}", $this->host, $sqlcmd);
        $sqlcmd = str_replace("{port}", $this->port, $sqlcmd);
        $sqlcmd = str_replace("{username}", $this->username, $sqlcmd);
        $sqlcmd = str_replace("{password}", $this->password, $sqlcmd);
        $sqlcmd = str_replace("{dbname}", $this->dbname, $sqlcmd);
        
        return $sqlcmd;
    }
    
    /**
     * Populated command for importing schema
     * @return string
     */
    public function getSqlimportschema()
    {
        $sqlcmd = $this->sqlimportschematpl;
        $sqlcmd = str_replace("{host}", $this->host, $sqlcmd);
        $sqlcmd = str_replace("{port}", $this->port, $sqlcmd);
        $sqlcmd = str_replace("{username}", $this->username, $sqlcmd);
        $sqlcmd = str_replace("{password}", $this->password, $sqlcmd);
        $sqlcmd = str_replace("{dbname}", $this->dbname, $sqlcmd);
        
        return $sqlcmd;
    }
    
    /**
     * Current DB connection host
     * @return string
     */
    public function getHost()
    {
        $default = "localhost";
        $matches = [];
        if(!preg_match(self::DSN_REGEX, $this->dsn, $matches)) return $default;
        return (!empty($matches['host'])) ? $matches['host'] : $default;
    }
    
    /**
     * Current DB connection port
     * @return string|mixed
     */
    public function getPort()
    {
        $default = "3306";
        $matches = [];
        if(!preg_match(self::DSN_REGEX, $this->dsn, $matches)) return $default;

        if(!empty($matches['port'])){
            return $matches['port'];
        } elseif (!empty($matches['port1'])){
            return $matches['port1'];
        } else {
            return $default;
        }
    }
    
    /**
     * Current DB connection database name
     * @return string
     */
    public function getDbname()
    {
        $default = null;
        $matches = [];
        if(!preg_match(self::DSN_REGEX, $this->dsn, $matches)) return $default;
        return (!empty($matches['database'])) ? $matches['database'] : $default;
        
        //return $this->createCommand("SELECT DATABASE()")->queryScalar();
    }
    
    /**
     * Base path of the application
     * @return string
     */
    public function getBasepath()
    {
       return \Yii::$app->basePath;
    }
    
    public function createBackupPath()
    {
        $backuppath = (is_null($this->backuppath)) ? "{$this->basepath}/runtime/backup" : $this->backuppath;
        
        if(!file_exists($backuppath)) mkdir($backuppath, 0777, true);
        
        return $backuppath;
    }
    
    public function createBackupId()
    {
        $microtime = microtime(true);
        $usec = (strpos($microtime, ".") === false) ? "0000" : explode(".", $microtime)[1];
        
        return date("Ymd_His").".{$usec}";
    }
    
    public function backupdb($verifyDb = null)
    {
        $backuppath = $this->createBackupPath();
        $backupid = $this->createBackupId();
        $backupdbfile = "{$backuppath}/{$this->dbname}_{$backupid}.sql.gz";
        
        Command::run("{$this->sqldump} | gzip > {$backupdbfile}");
        
        if(!is_null($verifyDb)){
            if($verifyDb instanceof Connection){
                \Yii::info("Creating database for backup verification.", __METHOD__);
                $verifyDb->restoredb($backupdbfile); 
                \Yii::info("Database created.", __METHOD__);
                
                $tables = $this->schema->tableNames;
                foreach($tables as $table){
                    \Yii::info("Verifying backup integrity for table {$table}.", __METHOD__);
                    $checksum1 = $this->checksum($table);
                    $checksum2 = $verifyDb->checksum($table);
                    if($checksum1 !== $checksum2){
                        throw new IntegrityCheckException("Integrity check failed for table {$table}. Original table and backup are not identical. Checksums: \n{$checksum1}\n{$checksum2}\n");
                    }
                    \Yii::info("Integrity verification successfull for table {$table}.", __METHOD__);
                }
            } else {
                throw new InvalidConfigException("\$verifyDb parameter must be an instance of ".get_class($this)." object.");
            }
        }
        
        return $backupdbfile;
    }
    
    public function restoredb($dbfile)
    {
        Command::run("gunzip < {$dbfile} | {$this->sqlimport}");
    }
    
    /**
     * Make a backup of a specified table
     * @param string $table Table to backup
     * @param Connection $db Connection object. If specified backup will be imported into this DB and checksum will verify if original and new tables are equal. This db object must be different from default DB connection object. Otherwise original data will be overwritten with this backup.
     * @return array Absolute path to the backup schema and data files, e.g. ['schema'=>'/path/to/schema.sql','data'=>'path/to/data.csv']
     */
    public function backup($table, $db = null)
    {
        $backuppath = $this->createBackupPath();        
        $backupid = $this->createBackupId();
        $file_schema = "{$backuppath}/{$table}_{$backupid}.sql";
        $file_data = "{$backuppath}/{$table}_{$backupid}.csv";
        $files = [
            'schema' => null,
            'data' => null
        ];
        Command::run("{$this->sqldumpschema} {$table} > {$file_schema}");
        $files['schema'] = $file_schema;
        
        $this->open();
        // Use unbuffered query not to load all records into memory
        $this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $reader = $this->createCommand("SELECT * FROM {$table}")->query();
        $this->close();
        
        $fp = fopen($file_data, 'w');
        while($row = $reader->read()){
            // Add NULL values
            foreach($row as $i=>$col) { $row[$i] = (is_null($col)) ? "NULL" : $col;}
            fputcsv($fp, $row, ',', '"');
        }
        fclose($fp);
        
        $files['data'] = $file_data;
        
        if(!is_null($db)){
            if($db instanceof Connection){
                \Yii::info("Verifying backup integrity for table {$table}.", __METHOD__);
                $db->restore($table, $files);
                $checksum1 = $this->checksum($table);
                $checksum2 = $db->checksum($table);
                if($checksum1 !== $checksum2){
                    throw new IntegrityCheckException("Integrity check failed during backup of table {$table}. Original table and backup are not identical. Checksums: \n{$checksum1}\n{$checksum2}\n");
                }
                \Yii::info("Integrity verification successfull for table {$table}.", __METHOD__);
            } else {
                throw new InvalidConfigException("\$db parameter must be an instance of ".get_class($this)." object.");
            }
        }
        
        return $files;
    }
    
    /**
     * Restore a table from a file
     * 
     * @param string $table Table name to be restored
     * @param array $files Absolute path to the backup schema and data files, e.g. ['schema'=>'/path/to/schema.sql','data'=>'path/to/data.csv']. Schema file is optional. Data is mandatory.
     * @return number Number of restored rows
     */
    public function restore($table, $files)
    {
        if(array_key_exists('schema', $files) && !is_null($files['schema'])){
            if(file_exists($files['schema']))
                $file_schema = $files['schema'];
            else
                throw new Exception("Failed to restore. Schema file {$files['schema']} does not exists.");
        }
        else {
            $file_schema = null;
        }
        
        if(array_key_exists('data', $files)){
            if(file_exists($files['data'])){
                $file_data = $files['data'];
            } else {
                throw new Exception("Failed to restore. Data file {$files['data']} does not exists.");
            }
        }
        
        if(!is_null($file_schema))
            Command::run("{$this->sqlimportschema} < {$file_schema}");
        
        $this->createCommand("TRUNCATE TABLE {$table}")->execute();
        $numrows = $this->createCommand("
            LOAD DATA LOCAL INFILE '{$file_data}'
            INTO TABLE `{$table}`
            CHARACTER SET {$this->charset} 
            FIELDS TERMINATED BY ','
            OPTIONALLY ENCLOSED BY '\"'
            LINES TERMINATED BY '\n'
        ")->execute();
        
        return $numrows;
    }
    
    public function checksum($table)
    {
        $checksum = $this->createCommand("CHECKSUM TABLE {$table}")->queryOne()['Checksum'];
        
        if(is_null($checksum))
            throw new IntegrityCheckException("Table {$table} does not exists or there was an error while generating checksum.");
        else
            return $checksum;
    }
    
    /**
     * Make a side by side diff of two tables with the same schema.
     * 
     * @param string $schema1 Left schema name.
     * @param string $table1 Left table name.
     * @param string $schema2 Right schema name.
     * @param string $table2 Right table name.
     * @param string $key Primary key or key that should be used for matching same records for both tables
     * 
     * @throws InvalidValueException
     * @return array Col by col side by side diff on each row.
     */
    public function diff($schema1, $table1, $schema2, $table2, $key)
    {
        $tableFullName1 = "{$schema1}.{$table1}";
        $tableFullName2 = "{$schema2}.{$table2}";
        
        $rows = \Yii::$app->get('db_dwh')->createCommand("CALL diff('{$schema1}', '{$table1}', '{$schema2}', '{$table2}', '{$key}')")->queryAll();
        
        $diff = [];
        for($i = 0; $i < count($rows); $i++){
            $diffcol = [
                'column'=>null,
                $key=>null,
                $tableFullName1=>null,
                $tableFullName2=>null,
                'status'=>null
            ];
            if(array_key_exists($i+1, $rows) && $rows[$i][$key] === $rows[$i+1][$key]){
                foreach($rows[$i] as $colname => $colvalue){
                    if($colname === 'tableName') continue;
                    $left = $rows[$i][$colname];
                    $right = $rows[$i+1][$colname];
                    
                    $diffcol['column'] = $colname;
                    $diffcol[$key] = $rows[$i][$key];
                    $diffcol[$tableFullName1] = $left;
                    $diffcol[$tableFullName2] = $right;
                    
                    if($left !== $right){
                        $diffcol['status'] = self::DIFF_STATUS_MODIFIED;
                    } else {
                        $diffcol['status'] = self::DIFF_STATUS_EQUAL;
                    }
                    $diff[] = $diffcol;
                }
                $i = $i+1;
            } else {
                foreach($rows[$i] as $colname => $colvalue){
                    if($colname === 'tableName') continue;
                    if($rows[$i]['tableName'] === $tableFullName1){
                        $diffcol['column'] = $colname;
                        $diffcol[$key] = $rows[$i][$key];
                        $diffcol[$tableFullName1] = $colvalue;
                        $diffcol[$tableFullName2] = null;
                        $diffcol['status'] = self::DIFF_STATUS_LEFTONLY;
                    } elseif($rows[$i]['tableName'] === $tableFullName2) {
                        $diffcol['column'] = $colname;
                        $diffcol[$key] = $rows[$i][$key];
                        $diffcol[$tableFullName1] = null;
                        $diffcol[$tableFullName2] = $colvalue;
                        $diffcol['status'] = self::DIFF_STATUS_RIGHTONLY;
                    } else {
                        throw new InvalidValueException("Invalid table name {$rows[$i]['tableName']}.");
                    }
                    $diff[] = $diffcol;
                }
            }
        }
        
        return $diff;
    }
    
    public function copy($sourceTable, $destinationTable)
    {
        $backup = $this->backup($sourceTable);
        
        $backup_schema_old = $backup['schema'];
        $backup['schema'] = $backup_schema_old . ".copy";
        
        // Replace source table name with destination table name in the DDL file
        file_put_contents(
            $backup['schema'], 
            str_replace(
                "DROP TABLE IF EXISTS `{$sourceTable}`", 
                "DROP TABLE IF EXISTS `{$destinationTable}`", 
                file_get_contents($backup_schema_old)
            )
        );
        file_put_contents(
            $backup['schema'],
            str_replace(
                "CREATE TABLE `{$sourceTable}`",
                "CREATE TABLE `{$destinationTable}`",
                file_get_contents($backup['schema'])
            )
        );
        
        $this->restore($destinationTable, $backup);

        $checksum1 = $this->checksum($sourceTable);
        $checksum2 = $this->checksum($destinationTable);
        
        if($checksum1 !== $checksum2){
            throw new IntegrityCheckException("Integrity check failed during copy of table {$sourceTable} to {$destinationTable}. Source table and destination tables are not identical. Checksums: \n{$checksum1}\n{$checksum2}\n");
        }
        \Yii::info("Integrity verification successfull for table {$sourceTable} and {$destinationTable}.", __METHOD__);
    }
    
    /**
     * Renames old table to the new table.
     * 
     * @param string $oldTable Old table name
     * @param string $newTable New table name
     * @param string $tmpTable Optional. Needs to be used when newTable already exists. New table will be moved to this tmpTable.
     * @return number
     */
    public function rename($oldTable, $newTable, $tmpTable = null)
    {
        if(is_null($tmpTable))
            return $this->createCommand("RENAME TABLE {$oldTable} TO {$newTable}")->execute();
        else
            return $this->createCommand("RENAME TABLE {$newTable} TO {$tmpTable},{$oldTable} TO {$newTable}")->execute();
    }
    
    public function drop($table)
    {
        return $this->createCommand("DROP TABLE IF EXISTS {$table}")->execute();
    }
}
