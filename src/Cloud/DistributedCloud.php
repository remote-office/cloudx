<?php

namespace CloudX\Cloud;

use CloudX\Cloud;
use CloudX\DistributedWorker;
use CloudX\Manager;
use CloudX\Task;

use CloudX\Messages\Message;
use CloudX\Messages\StatusMessage;
use CloudX\Messages\LogMessage;
use CloudX\Messages\KeepAliveMessage;
use CloudX\Messages\CallbackMessage;
use CloudX\Messages\DataMessage;

use CloudX\TaskStack;
use Exception;
use Socket;

/**
 * Class DistributedCloud
 *
 * @author David Betgen <code@platform-x.dev>
 * @version 1.0
 */
class DistributedCloud extends Cloud
{
    protected $socket; 

    protected $manager;

    public function __construct($host, $port)
    {
        $this->manager = new Manager();

        // Create a socket
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);

        // Bind socket to host and port
        if(@socket_bind($this->socket, $host, $port) === false)
            throw new Exception('Unable to bind socket to ' . $host . ':' . $port);

        // Listen on socket
        if(@socket_listen($this->socket) === false)
            throw new Exception('Unable to listen for a connection');

        // Socket is non blocking
        if(@socket_set_nonblock($this->socket) === false)
            throw new Exception('Unable set socket to non blocking mode');
    }

    public function process(TaskStack $tasks)
    {
        $manager = $this->getManager();

        // Check for any workers
        if(($socket = socket_accept($this->socket)) instanceof Socket)
        {
            $manager->add(new DistributedWorker(uniqid(), null, null, $socket));
        }

        parent::process($tasks);
    }

    /**
     * Wait until all childs have exited
     *
     * @param voud
     * @return void
     */
    protected function wait()
    {
        echo 'Waiting....' . "\n";
    }

    public function getManager()
    {
        return $this->manager;
    }
}