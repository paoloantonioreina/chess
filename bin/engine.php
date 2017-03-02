<?php

use WebSocket\Client;
use Zend\Db\Adapter\Adapter;

date_default_timezone_set("Europe/Rome");
chdir(dirname(__DIR__));
require 'init_autoloader.php';


$fen = isset($argv[1]) ? $argv[1] : false;
$time = isset($argv[2]) ? $argv[2] : 1;
$gameid = isset($argv[3]) ? $argv[3] : 0;

$hashValue = 512; // 512mb hash table;
$multipv = 2;
//$engine_path = '/usr/local/bin/stockfish';
$engine_path = '/Users/antonioreina/chess/engines/critter-16a';
$thinking_time = (int) $time * 1000;
if ($fen == "") {
    exit;
}


$cwd = './';

$descriptorspec = array(
    0 => array("pipe", "r"),
    1 => array("pipe", "w"),
);
$other_options = array('bypass_shell' => 'true');
$process = proc_open($engine_path, $descriptorspec, $pipes, $cwd, null, $other_options);

if (is_resource($process)) {
    $client = new Client("ws://127.0.0.1:8989/");
    $info = proc_get_status($process);

    fwrite($pipes[0], "uci\n");
    fwrite($pipes[0], "ucinewgame\n");
    if ($threads) {
        fwrite($pipes[0], "setoption name threads value $threads\n");
    }
    
    if ($multipv) {
        fwrite($pipes[0], "setoption name multipv value $multipv\n");
    }
    fwrite($pipes[0], "setoption name Hash value $hashValue\n");
    fwrite($pipes[0], "isready\n");
    fwrite($pipes[0], "position fen $fen\n");
    fwrite($pipes[0], "go movetime $thinking_time\n");

    $found = false;
    $str = "";
    $score = 0;
    $name = '';
    while (!$found) {
        $s = fgets($pipes[1], 512);
        $think = explode(' ', $s);
        if ($name == '') {
            $name = $s;
        }
        echo $s;
        echo PHP_EOL;
        if (strpos(' ' . $s, 'seldepth')) {
            $client->send(json_encode(array('gameid' => $gameid, 'c' => 'analyze', 'text' => $s)));
        }

        $score = (isset($think[9])) ? $think[9] : $score;

        if (strpos(' ' . $s, 'bestmove')) {
            echo PHP_EOL;
            echo PHP_EOL;
            //echo ( $score / 10 );  
            $bestMovie = $s;
            //   echo $bestMovie;
            $move = substr($s, 9, 2) . '-' . substr($s, 11, 2);
            $from = substr($s, 9, 2);
            $to = substr($s, 11, 2);

            echo $move;

            $client->send(json_encode(array('gameid' => $gameid, 'from' => $from, 'to' => $to, 'c' => 'responce', 'name' => $name, 'fen' => $fen)));


            $client->close();
            $found = true;
        }
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
}
 