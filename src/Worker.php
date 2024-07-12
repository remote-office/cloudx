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
  
  /**
   * Class Worker
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class Worker extends AbstractWorker
  {
    /**
     * Construct a Worker
     *
     * @param string $name
     * @return Worker
     */
    public function __construct($name = 'Worker')
    {
      $this->setId(uniqid());
      $this->setName($name);

      // Set initial status
      $this->setStatus(self::STARTING);

      // Init sockets array
      $sockets = array();

      // Create socket pair for interprocess communication
      if(socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets) === false)
        throw new \Exception(__METHOD__ . '; '. socket_strerror(socket_last_error()));

      /**
       * Set communication socket before fork to ensure we can receive messages
       * from childs sending data before fork returns to parent
       */

      // Set communication socket
      $this->setSocket($sockets[1]);

      // Fork the currect process
      $pid = pcntl_fork();

      if($pid == -1)
        throw new \Exception(__METHOD__ . '; Could not fork');

      /**
       * Forking
       *
       * When PID is not equal to zero => Parent process
       * When PID is equal to zero => Child process
       */

      if($pid != 0)
      {
        // Set parent process id
        $this->setParentPid(0);

        // Set PID of this worker
        $this->setPid($pid);
      }
      else
      {
        declare(ticks = 1);

        // Set communication socket
        $this->setSocket($sockets[0]);

        // Set parent process id
        $this->setParentPid(posix_getppid());

        // Set PID of this worker
        $this->setPid(posix_getpid());

        // ReInstall signalhandler on important POSIX signals
        //pcntl_signal(SIGUSR2, array($this, 'signal'));
        //pcntl_signal(SIGTERM, array($this, 'signal'));
        //pcntl_signal(SIGINT,  array($this, 'signal'));
        //pcntl_signal(SIGHUP,  array($this, 'signal'));

        $this->setStatus(self::STARTED);
      }
    }

    /**
     * Handle posix signals
     *
     * @param int $signal
     * @return void
     */
    public function signal($signal)
    {
        // Handle signal
        switch($signal)
        {
            case SIGTERM:
            case SIGINT:
            {
                // Handle sigterm and sigint
                exit();
            }
            break;

            case SIGHUP:
            {
                echo 'Hi there... stop poking me!' . "\n";
            }
            break;
        }
    }

    public function __destruct()
    {

    }
  }