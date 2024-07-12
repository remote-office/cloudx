<?php

  namespace CloudX;

  use CloudX\Interfaces\Runnable;

  use CloudX\Messages\Message;
  use CloudX\Messages\RunnableMessage;
  use CloudX\Messages\StatusMessage;
  use CloudX\Messages\LogMessage;
  use CloudX\Messages\KeepAliveMessage;
  use CloudX\Messages\CallbackMessage;
  use CloudX\Messages\CommandMessage;
  
  use CloudX\AbstractWorker;

  use Exception;
  use Socket;

  /**
   * Class DistributedWorker
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class DistributedWorker extends AbstractWorker
  {
    /**
     * Construct a DistributedWorker
     *
     * @param string $name
     * @param string $host
     * @param interger $port
     * @param Socket $socket
     * @return Worker
     */
    public function __construct($name, $host = null, $port = null, Socket $socket = null)
    {
      $this->setId(uniqid());
      $this->setName($name);

      // Set initial status
      $this->setStatus(self::STARTING);

      if(is_null($socket))
      {
        $this->setParentPid(1);
        $this->setPid(0);

        // Create socket
        $socket = @socket_create(AF_INET, SOCK_STREAM, 0);

        if($socket === false)
          throw new Exception('Unable to create a socket');
       
        // Connect to distributed cloud
        if(@socket_connect($socket, $host, $port) === false)
          throw new Exception('Unable to connect a socket: ' . socket_strerror(socket_last_error()));
      }
      else
      {
        $this->setParentPid(0);
        $this->setPid(1);
      }

      // Assign socket to this worker
      $this->setSocket($socket);
    }
  }