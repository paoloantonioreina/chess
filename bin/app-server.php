<?php

date_default_timezone_set("Europe/Rome");
require dirname(__DIR__) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Zend\Db\Adapter\Adapter;
use Ryanhs\Chess\Chess as Chess;
use WebSocket\Server;

class MyWebSocketServer implements MessageComponentInterface {

    protected $clients;
    private $game = [];

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
        //     $conn->close();
        //   $this->onMessage($conn, "New host is online");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $event = json_decode($msg, true);
        $from->send(json_encode(array('success' => true)));

        $command = isset($event['c']) ? $event['c'] : null;
        $user = isset($event['user']) ? $event['user'] : null;
            
        switch ($command) {
            case 'newgame':
                $gameid = $from->resourceId;
                $from->send(json_encode(array('success' => true, 'responce' => 'newgame', 'gameid' => $gameid)));
                break;

            case 'analyze':
                $resourceId = $event['gameid'];
                $text = $event['text'];
                $client = $this->getClientFromResurceId($resourceId);
                $client->send(json_encode(array('success' => true, 'responce' => 'analyze', 'text' => $text)));
                break;

            case 'responce':
                $resourceId = $event['gameid'];
                $f = $event['from'];
                $to = $event['to'];
                $name = $event['name'];
                $client = $this->getClientFromResurceId($resourceId);
                $client->send(json_encode(array('success' => true, 'responce' => 'move', 'from' => $f, 'to' => $to, 'name'=>$name)));
                print_r($event);
                $from->close();
                echo " responce from engine : $f$to for $resourceId\n";
                break;

            case 'move':
                $fen = isset($event['fen']) ? $event['fen'] : null;
                $gameid = isset($event['gameid']) ? $event['gameid'] : null;
                $time   = isset($event['time']) ? $event['time'] : null;
                $engine = isset($event['engine']) ? $event['engine'] : null;

                $command = sprintf(
                        '%s %s %s %s %s &> /dev/null &', //
                        escapeshellcmd('/usr/bin/php'), escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'engines'.DIRECTORY_SEPARATOR.$engine.'.php'), escapeshellarg($fen) , escapeshellarg($time), escapeshellarg($gameid)
                );

                exec($command);
                print_r($event);
                
                echo str_replace('\\','',__DIR__ . DIRECTORY_SEPARATOR . 'engines'.DIRECTORY_SEPARATOR.$engine.'.php');
                $from->send(json_encode(array('success' => true, 'responce' => 'ok')));
                break;
                
            case 'savegame':
                    $pgn = isset($event['pgn']) ? $event['pgn'] : null;
                    file_put_contents(__DIR__.'/gameData.pgn', $pgn, FILE_APPEND);
                     $from->send(json_encode(array('success' => true, 'responce' => 'file saved')));
                break;  
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo $e->getMessage();
    }

    /**
     * 
     * @param type $from
     * @return type
     */
    private function getClient($from) {
        foreach ($this->clients as $client) {
            if ($client->resourceId == $from->resourceId) {
                return $client;
            };
        }
    }

    private function getClientFromResurceId($resourceId) {
        foreach ($this->clients as $client) {
            if ($client->resourceId == $resourceId) {
                return $client;
            };
        }
    }

}

chdir(dirname(__DIR__));
require 'init_autoloader.php';


$server = IoServer::factory(
                new HttpServer(
                new WsServer(
                new MyWebSocketServer()
                )
                ), 8989 // porta
);


$server->run();
