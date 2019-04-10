<?php 

require_once('./MysqliDb.php');

$config = [
    'host' => '111.206.0.209',
    'user' => 'root',
    'passwd' => '54root%$',
    'database' => 'bangtvnew'

];
$db = new MysqliDb ($config['host'], $config['user'], $config['passwd'], $config['database']);

# yii所在路径
$yii_path = "E:\\Company\\health_api";
$yii_cmd = $yii_path."\\yii";
$path = $yii_path."\\console\\migrations";

$create_time = "m190410";


// 查询线上数据库表数量
$tables = $db->rawQuery("select `TABLE_COLLATION`,`ENGINE`,`TABLE_COMMENT`,`TABLE_NAME` from INFORMATION_SCHEMA.TABLES where TABLE_SCHEMA='{$config['database']}'");
if(!empty($tables)){
    foreach($tables as $item => $t){
        $command = "php ".$yii_cmd ." migrate/create create_".$t['TABLE_NAME']."_table";
        $fp = popen($command,'w');
        @fputs($fp,'yes');
        @pclose($fp);
        unset($command,$fp);

        $struct =  $db->rawQuery("select * from INFORMATION_SCHEMA.Columns where table_name= '{$t['TABLE_NAME']}' and table_schema='{$config['database']}'");
        $data['struct'] = $struct;
        $data['table'] = $t;
        createTable($path,$data,$create_time,$t['TABLE_NAME']);
        sleep(3);
    }
}

function createTable($path,$data,$create_time,$table_name)
{

    $files = scandir($path);
    foreach ($files as $item) {
        if (strpos($item, $create_time) !== false) {
            if (strpos($item, $table_name) !== false) {
                createPHPFile($item, $data,$path);
                break;
            }
        }
    }
}



function createPHPFile($item,$data,$path = ''){
    $php_template=<<<PHP
<?php

use yii\db\Migration;

class %s extends Migration
{
    public function up()
    {
        \$tableOptions = null;
        if (\$this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            \$tableOptions = '%s';
        }
        
        %s

        
    }

    public function down()
    {
        \$this->dropTable('{{%s}}');
        
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}


PHP;

    $get_file_name = function($spe,$item){
        $names = explode($spe,$item);
        return $names[0];

    };
    $classname = $get_file_name(".",$item);


    $char_code = $get_file_name('_',$data['table']['TABLE_COLLATION']);
    $char_code_sec = $data['table']['TABLE_COLLATION'];
    $table_engine = $data['table']['ENGINE'];
    $table_desc = $data['table']['TABLE_COMMENT'];

    $table_options = "CHARACTER SET {$char_code} COLLATE {$char_code_sec} ENGINE={$table_engine} Comment =\"{$table_desc}\" ";

    $table_name = "%".$data['table']['TABLE_NAME'];


    $type_arr = [
        'int' => 'integer',
        'smallint' => 'smallInteger',
        'char'     => 'char',
        'varchar'  => 'string',
        'text'     => 'text',
        'mediumtext' => 'text',
        'longtext'   => 'text',
        'bigint'   => 'bigInteger',
        'tinyint'  => 'smallInteger',
    ];
    $inset = [];

    foreach($data['struct'] as $item => $vv){
        $str = "'{$vv['COLUMN_NAME']}' => ";
        if(isset($vv['COLUMN_KEY']) && strpos(strtolower($vv['COLUMN_KEY']),'pri') !== false){
            $str.= "\$this->primaryKey()";
        }else{
            if(!empty($vv['DATA_TYPE'])){
                $method = '';
                if(isset($type_arr[$vv['DATA_TYPE']])){
                    $method = $type_arr[$vv['DATA_TYPE']];
                }else{
                }

                $regx = "#\((\d+)\)#";
                $arrMatches = [];
                $length = "";
                preg_match_all($regx, $vv['COLUMN_TYPE'], $arrMatches);
                $unsign = explode(' ',$vv['COLUMN_TYPE']);
                $unsign = end($unsign);
                $ext="";
                if(!empty($unsign)){
                    $ext = "->unsigned()";
                }

                if(end($arrMatches)){
                    $length = end($arrMatches)[0];

                }


                $str .="\$this->$method"."({$length})".$ext;
            }


        }
        if($vv['IS_NULLABLE'] == 'NO'){
            $str .="->notNull()";
        }

        if(!empty($vv['COLUMN_DEFAULT'])){
            $str.="->defaultValue({$vv['COLUMN_DEFAULT']})";
        }



        if(!empty($vv['COLUMN_COMMENT'])){
            $str.= "->comment('{$vv["COLUMN_COMMENT"]}')";
        }

        $inset[] = $str;

    }
    $create_str = "\$this->createTable('{{{$table_name}}}',[\r\t".implode(','."\r\t\t\t",$inset)."\r\t\t\t]);";

    $content = sprintf($php_template,$classname,$table_options,$create_str,$table_name);

    file_put_contents($path.'/'.$classname.'.php',$content);

}

