<?php

date_default_timezone_set("Europe/Rome");

chdir(dirname(__DIR__));
require 'init_autoloader.php';
require 'libs/Names.php';

use WebSocket\Client;
use Zend\Db\Adapter\Adapter;

$adapter = new Adapter(array(
    'database' => 'catalogo',
    'driver' => 'PDO_Mysql',
    'hostname' => 'localhost',
    'username' => 'root',
    'password' => 'root',
    'dsn' => 'mysql:dbname=catalogo;host=127.0.0.1;charset=utf8'
        ));

$NameFinder = new Names();
while (1) {
    $f = 0;


    $feeds = $adapter->query("SELECT url FROM feeds", array());
    $data = $feeds->toArray();
    $t = 0;
    
    foreach ($data as $record) {
        try {
            echo "try to connecting to : {$record['url']}";
            $feed = Zend\Feed\Reader\Reader::import($record['url']);
            echo PHP_EOL;
            $t+= $feed->count();
            foreach ($feed as $entry) {
                $description = strip_tags($entry->getDescription());
                $dta = $entry->getDateCreated()->format('Y-m-d h:m:s');
                $id = md5($description . $dta);

                // quering if exist;
                $r = $adapter->query("SELECT id FROM news WHERE id=?", array($id));
                if (!$r->toArray()) {
                    $findNames = $NameFinder->searchNames($description)  ; 
                    foreach ($findNames as $name) { 
                        $id_person = md5($name);
                             $adapter->query("INSERT INTO persons VALUES (? , ?, 1,  0 ) ON DUPLICATE KEY UPDATE seen = seen +1 ", array($id_person, $name)); 
                             $adapter->query("INSERT INTO person_news VALUES (? , ? ) ", array($id_person, $id)); 
                    } 
                    $adapter->query("INSERT INTO news VALUES (? , ?,  ? ) ", array($id, $description, $dta));
                   
                }
              
            }
        } catch (\Exception $exc) {
            echo PHP_EOL;
            echo $exc->getMessage();
        }
    }
    echo PHP_EOL;
    echo "$f new feeds founds";

    echo PHP_EOL;
    echo "Waiting ";
    for ($i = 0; $i <= 10; $i++) {
        sleep(6);
        echo '.';
    }
    echo PHP_EOL;
}
/*

$feed = Zend\Feed\Reader\Reader::import('http://www.world-boxing-news.com/rss/all.xml');
//http://roundbyroundboxing.com/feed/
//$feed = Zend\Feed\Reader\Reader::import('http://www.world-boxing-news.com/rss/news-.xml');
echo 'The feed contains ' . $feed->count() . ' entries.' . "\n\n";
$o = 0;
foreach ($feed as $entry) { 
    //$adapter->query("");
    $client->send(json_encode(array('title'=>$entry->getTitle(), 'description'=>$entry->getDescription())));   
    $o++;  
}
 //echo $client->receive(); // Will output 'Hello WebSocket.org!'
$client->close();


echo "\n";
 * 
 */


