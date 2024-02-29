<?php

class db
{

    private $host = "mysql";
    private $db_name = "db";
    private $username = "user";
    private $password = "password";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";port=3306",$this->username, $this->password);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage() . PHP_EOL;
        }

        return $this->conn;
    }

}

class index {
    public $function;
    public $name;
    public $size;
    static $count = 0;

    public function __construct(callable  $function, string $name, int $size = 16)
    {
        $this->function = $function;

        $this->name = $name?:('index_'.self::$count++);
        $this->size = $size;
    }
}

class dictionary {
    private $conn;
    private $indexes = [];

    public function __construct(){
        $db = new db();
        $this->conn =  $db->getConnection();
    }
    public function init($filename, $overwrite = false)
    {
        $filesize = filesize($filename);
        $sha1_file = sha1_file($filename);
        $sql = "CREATE TABLE IF NOT EXISTS `dictionary_last_updload` (
            id int,
            filename varchar(32),
            filesize int,
            sha1_file varchar(40),
            PRIMARY KEY (id)
    )
             ";
        $this->conn->query($sql);

        $this->indexes[] = new index(function($word){ return $word;}, 'forward');
        $this->indexes[] = new index(function($word){ return strrev($word);}, 'reverse');
        $this->indexes[] = new index(function($word){ $l = strlen($word); return substr($word,$l/2);}, 'middle');;

        if (!$overwrite){
            $sql = "SELECT * FROM dictionary_last_updload";
            $results = $this->conn->query($sql);
            $result = $results->fetch();
            if ("$filename" == $result['filename'] && $filesize == $result['filesize'] && $sha1_file == $result['sha1_file']){
                return;
            }
        }

//        drop existing tables
        $sql = "DROP TABLE IF EXISTS `dictionary`;";
        $this->conn->query($sql);
//         create tables with the corresponding indexes.
        $sql = "CREATE TABLE `dictionary` (
            id int(11) NOT NULL AUTO_INCREMENT,
            word varchar(255) NOT NULL,
            PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        $this->conn->query($sql);
        foreach ($this->indexes as $index)
{
    $index_name = $index->name;
    $sql = "
alter table dictionary
    add $index_name varchar({$index->size}) null;
create index {$index_name}_idx
    on dictionary ($index_name);";

    $this->conn->query($sql);
}


//  load each word in the file into the database
        if ($file = fopen($filename, "r")) {
            while(!feof($file)) {
                $word = fgets($file);
                $cols = [];
                $values = [];
                $cols[] = 'word';
                $values[] = preg_replace('/[^\da-z]/i', '', $word);

                foreach ($this->indexes as $index) {
                    $cols[] = $index->name;
//                    echo "\$index->name {$index->name}" . PHP_EOL;
                    $values[] = substr(preg_replace('/[^\da-z0-9]/i', '', call_user_func($index->function,$word)),0,$index->size);
                }
                $sql = "insert into dictionary ( " . implode(',', $cols) . ")
                    values ('" . implode("','", $values) . "');";
                try {
                    $this->conn->query($sql);
                } catch (Exception $e) {
//                    echo "exception " . $e->getMessage() . PHP_EOL;
                }

            }
            fclose($file);
        }

        $filesize = filesize($filename);
        $sha1_file = sha1_file($filename);
        $sql = "insert into dictionary_last_updload (id, filename, filesize, sha1_file)
            values (1,'$filename','$filesize','$sha1_file')
            ON DUPLICATE KEY UPDATE filename = '$filename', filesize = '$filesize', sha1_file = '$sha1_file'";
        $this->conn->query($sql);

    }

    public function find_misspelled_words($filename){
        $file_contents = file_get_contents($filename);
        $words = preg_split("/[\s,]+/", $file_contents);
        $words = array_unique($words);

        foreach ($words as $k => $word){
            $word = strtolower(preg_replace('/[^\da-z]/i', '', $word));
            $words[$k] = $word;
        }

        //mysql insert words into temp table in chunks of 200
        $sql = "create TEMPORARY table temp_words  (`word` varchar(255),
            PRIMARY KEY (`word`));";
        $this->conn->query($sql);
        $chunk_size = 200;
        $chunks = array_chunk($words, $chunk_size);
        foreach ($chunks as $chunk){
            $sql = "insert into temp_words (word) values ";
            foreach ($chunk as $word){
                $sql .= "('$word'), ";
            }
            $sql = substr($sql, 0, -2);
            $sql .= " ON DUPLICATE KEY UPDATE word = VALUES(word);";
            $this->conn->query($sql);
        }

        $sql = "select count(*) c, group_concat(temp_words.word) as misspelled_words from temp_words
        left join dictionary
        on dictionary.word = temp_words.word
        where dictionary.word is null;";

        $results = $this->conn->query($sql);
        $result = $results->fetch();
        $misspelled_words = explode(',', $result['misspelled_words']);

        if (count($misspelled_words) != $result['c']){
            throw new Exception("too many misspelled words");
        }
        return $misspelled_words;
    }

    public function find_candidates($misspelled_words){
        $candidates_details = [];
        $range = 5;
        foreach ($misspelled_words as $misspelled_word){
            $misspelled_word = preg_replace('/[^\da-z0-9]/i', '', $misspelled_word);
            $candidates_details[$misspelled_word] = [];
            foreach ($this->indexes as $index){
                $index_value = substr(preg_replace('/[^\da-z0-9]/i', '', call_user_func($index->function, $misspelled_word)), 0, $index->size);
                $index_name = $index->name;
                $candidates_details[$misspelled_word][$index_name] = [];
                $sql = "select count(*) c, group_concat(close_word) as close_words,
                group_concat(close_index) as close_indexes from
                (select word as close_word, $index_name as close_index
                from dictionary
                where $index_name >= '$index_value'
                order by $index_name limit $range) as t1;";
                $results = $this->conn->query($sql);
                $result = $results->fetch();
                $candidates_details[$misspelled_word][$index_name] =
                    array_merge($candidates_details[$misspelled_word][$index_name]
                        , $result['close_words'] ? explode(',', $result['close_words']) : []);
                $sql = "select count(*) c, group_concat(close_word) as close_words,
                group_concat(close_index) as close_indexes from
                (select word as close_word, $index_name as close_index
                from dictionary
                where $index_name < '$index_value'
                order by $index_name desc limit $range) as t1;";
                $results = $this->conn->query($sql);
                $result = $results->fetch();
                $candidates_details[$misspelled_word][$index_name] =
                    array_merge($candidates_details[$misspelled_word][$index_name]
                        , $result['close_words'] ? explode(',', $result['close_words']) : []);

            }
        }

        $word_candidate_dist = [];
        foreach ($candidates_details as $misspelled_word => $candidates){
            $word_candidate_dist[$misspelled_word] = [];
            foreach ($candidates as $index => $candidates_list){
                foreach ($candidates_list as $candidate){
                    $word_candidate_dist[$misspelled_word][] =
                      ['candidate' => $candidate, "dist" => levenshtein($misspelled_word, $candidate)];
                }
            }

            usort($word_candidate_dist[$misspelled_word], function($a, $b){
                $result = $a['dist'] - $b['dist'];
                if ($result == 0){
                    return strcmp($a['candidate'], $b['candidate']);
                }
                return $a['dist'] - $b['dist'];
            });

            $last_word = '';
            foreach ($word_candidate_dist[$misspelled_word] as $k => $v){
                if ($last_word == $v['candidate']){
                    unset($word_candidate_dist[$misspelled_word][$k]);
                }
                $last_word = $v['candidate'];
            }
        }


        return ['candidates' => $word_candidate_dist , 'details' => $candidates_details];
    }
}

class file_scanner {
    static function find_word_location_context($filename, $misspelled_words){

        $word_location_context  = [];
        foreach ($misspelled_words as $misspelled_word){
            $word_location_context = [];
        }

        $line_number = 1;
        $handle = fopen($filename, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                foreach ($misspelled_words as $misspelled_word){
                    $search_line = preg_replace('/[^\da-z0-9 ]/i', '', $line);
                    $pos = stripos($search_line, $misspelled_word);
                    if ($pos !== false){
                        $word_location_context[$misspelled_word][] =
                            ['line_number' => $line_number, 'pos' => $pos, 'context' => substr($line, $pos - 10, 20 + strlen($misspelled_word))];
                    }

                }
            }
            fclose($handle);
        }
        return $word_location_context;
    }
}
