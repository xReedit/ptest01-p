<?php 
require_once 'SocketIO.php';

class SocketService
{
    private $client;

    public function connect() {
        $this->$client = new SocketIO('http://localhost:5819')
        $this->$client->setQueryParams([            
            'idorg' => '16',
            'idsede' => '13',
            'isFromApp' => 0,
            'isSendPrint' => 1
        ]); 
    }

    public function emit(evento, payload) {
        $this->$client->emit(evento, payload);
    }    
}
