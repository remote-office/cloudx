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
  
  use Socket;

  /**
   * Class Runner
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class Runner
  {
    const STARTING  = 1;
    const STARTED   = 2;
    const STOPPING  = 4;
    const STOPPED   = 5;

    const RUNNING   = 3;
    const IDLE      = 0;

    protected $id;
    protected $name;
    protected $runnable;

    // Status flags
    protected $status;

    // Internal socket
    protected $socket;

    // Internal values
    protected $ppid;
    protected $pid;
    
    // Shared memory
    protected $memory;

    protected $runnables = [];

    protected $terminate = false;

    protected $signals = [];

    /**
     * Construct a Runner
     *
     * @param string $name
     * @return Runner
     */
    public function __construct($name = 'Runner')
    {
      $this->setId(uniqid());
      $this->setName($name);

      // Set initial status
      $this->setStatus(Runner::STARTING);

      // Create shared memory (64KB)
      $this->memory = null; //new Memory(1024 * 64);
      
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

        // Set PID of this runner
        $this->setPid($pid);
      }
      else
      {
        declare(ticks = 1);

        // Set communication socket
        $this->setSocket($sockets[0]);

        // Set parent process id
        $this->setParentPid(posix_getppid());

        // Set PID of this runner
        $this->setPid(posix_getpid());

        pcntl_async_signals(TRUE);

        // ReInstall signalhandler on important POSIX signals
        pcntl_signal(SIGUSR2, array($this, 'signal'));
        pcntl_signal(SIGTERM, array($this, 'signal'));
        pcntl_signal(SIGINT,  array($this, 'signal'));
        pcntl_signal(SIGHUP,  array($this, 'signal'));

        register_tick_function(array($this, 'tick'));

        $this->setStatus(Runner::STARTED);
      }
    }

    public function tick()
    {
      pcntl_signal_dispatch();
    }


    public function handle($signal)
    {
      //echo __METHOD__  . ' ' . $signal . "\n";

      // Handle signal
      switch($signal)
      {
        // Signal when a parent has send a message
        case SIGUSR2:
          {
            // Gather sockets
            $sockets = array($this->getSocket());

            // Select
            @socket_select($sockets, $null, $null, 5);

            // Do we have something to read
            if(count($sockets) > 0)
            {
              $data = $this->read();

              if(!empty($data))
              {
                // Unserialize Message
                $class = unserialize($data);

                if(in_array(Message::class, class_parents($class)))
                {
                  // Get pid of message
                  $pid = $class->getPid();

                  if($class instanceof RunnableMessage)
                  {
                    // Set it
                    $this->setRunnable($class->getRunnable());
                  }

                  if($class instanceof StatusMessage)
                  {
                    // Get status (Runner::STOPPED or Runner::IDLE)
                    $status = $class->getStatus();

                    if($status === Runner::STOPPING)
                      $this->terminate = true;
                  }

                  if($class instanceof CommandMessage)
                  {
                    // Get command 
                    $command = $class->getCommand();

                  }
                }
              }
            }
          }
          break;

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

    /**
     * Handle posix signals
     *
     * @param int $signal
     * @return void
     */
    public function signal($signal)
    {
      // Add signal to stack
      $this->signals[] = $signal;

    }

    public function __destruct()
    {

    }

    public function getId()
    {
      return $this->id;
    }

    public function setId($id)
    {
      $this->id = $id;
    }

    /**
     * Get name of this Runner
     *
     * @param void
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set PID of this Runner
     *
     * @param int $pid
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Check if this Runner as a name
     *
     * @param void
     * @return string
     */
    public function hasName()
    {
      return !is_null($this->name);
    }

    /**
     * Get socket of this Runner
     *
     * @param void
     * @return Socket
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Set Socket of this Runner
     *
     * @param Socket $socket
     * @return void
     */
    public function setSocket($socket)
    {
        $this->socket = $socket;
    }

    /**
     * Get PID of this Runner
     *
     * @param void
     * @return integer
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Set PID of this Runner
     *
     * @param int $pid
     * @return void
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    public function hasPid()
    {
      return !is_null($this->pid);
    }

    /**
     * Get PPID of this Runner
     *
     * @param void
     * @return integer
     */
    public function getParentPid()
    {
        return $this->ppid;
    }

    /**
     * Set PPID of this Runner
     *
     * @param int $ppid
     * @return void
     */
    public function setParentPid($ppid)
    {
        $this->ppid = $ppid;
    }

    public function hasParentPid()
    {
      return !is_null($this->ppid);
    }

    protected function getRunnable()
    {
      return $this->runnable;
    }

    protected function setRunnable(Runnable $runnable = null)
    {
      $this->runnable = $runnable;
    }

    protected function hasRunnable()
    {
      return !is_null($this->runnable);
    }

    public function getStatus()
    {
      return $this->status;
    }

    public function setStatus($status)
    {
    	// When in child inform parent of status change
    	if($this->getParentPid() != 0)
    	{
    		// Create a StatusMessage
        $statusMessage = new StatusMessage($this->getPid(), $status);

        // Serialize
        $data = serialize($statusMessage);

        // Write
        $this->write($data);

        // Notify parent
        posix_kill($this->getParentPid(), SIGUSR1);
    	}

      $this->status = $status;
    }

    /**
     * Check status for idle
     *
     * @param void
     * @return boolean
     */
    public function isIdle()
    {
      return ($this->getStatus() == Runner::IDLE);
    }

    /**
     * Check status for running
     *
     * @param void
     * @return boolean
     */
    public function isRunning()
    {
      return ($this->getStatus() == Runner::RUNNING);
    }

    /**
     * Check status for stopped
     *
     * @param void
     * @return boolean
     */
    public function isStopped()
    {
      return ($this->getStatus() == Runner::STOPPED);
    }

    /**
     * Stop the runner
     *
     * @param void
     * @return void
     */
    public function stop()
    {
      // Create a StatusMessage
      $statusMessage = new StatusMessage($this->getPid(), Runner::STOPPING);

      // Serialize
      $data = serialize($statusMessage);

      // Write
      $this->write($data);
 
      // Notify child
      posix_kill($this->getPid(), SIGUSR2);

      // Close socket
      //socket_close($this->getSocket());
    }

    /**
     * Execute a Runnable
     *
     * @param Runnable
     * @return void
     */
    public function execute(Runnable $runnable)
    {
      // Update status
      $this->setStatus(Runner::RUNNING);

      // Create a RunnableMessage
      $runnableMessage = new RunnableMessage($this->getPid(), $runnable);

      // Send runnable class to other process
      $data = serialize($runnableMessage);

      // Write to other process
      $this->write($data);

      // Notify child
      posix_kill($this->getPid(), SIGUSR2);
    }

    /**
     * Execute a Runnable
     *
     * @param Runnable
     * @return void
     */
    public function callback(callable $callable, $parameters = [])
    {
      // Create a CallbackMessage
      $callbackMessage = new CallbackMessage($this->getPid(), $callable, $parameters);

      // Send runnable class to other process
      $data = serialize($callbackMessage);

      // Write to other process
      $this->write($data);

      // Notify parent
      posix_kill($this->getParentPid(), SIGUSR1);
    }

    /**
     * Send a keep alive message to parent
     *
     * @param void
     * @return void
     */
    public function keepalive()
    {
    	// When in child
    	if($this->getParentPid() != 0)
    	{
	    	// Create a KeepAliveMessage
	      $keepAliveMessage = new KeepAliveMessage($this->getPid());

	      // Serialize
	      $data = serialize($keepAliveMessage);

	      // Write
	      $this->write($data);

	      // Notify parent
	      posix_kill($this->getParentPid(), SIGUSR1);
    	}
    }

    /**
     * Send a log message to parent
     *
     * @param string $log
     * @return void
     */
    public function log($log)
    {
    	// When in child
    	if($this->getParentPid() != 0)
    	{
	    	// Create a LogMessage
	      $logMessage = new LogMessage($this->getPid(), $log);

	      // Serialize
	      $data = serialize($logMessage);

	      // Write
	      $this->write($data);

	      // Notify parent
	      posix_kill($this->getParentPid(), SIGUSR1);
    	}
    }

    /**
     * Read from socket
     *
     * @param void
     * @return string
     */
    public function read()
    {
      // Init data
      $data = null;

      try
      {
        // Read length of data from socket
        if(strlen($length = @socket_read($this->getSocket(), 4)) !== 4)
          throw new \Exception(__METHOD__ . '; socket_read() failed: ' . socket_strerror(socket_last_error()));

        // Unpack length
        $length = unpack('N', $length);
        $length = $length[1];

        // Read data
        if(strlen($data = @socket_read($this->getSocket(), $length)) !== $length)
          throw new \Exception(__METHOD__ . '; socket_read() failed: ' . socket_strerror(socket_last_error()));

        // Uncompress data
        $data = gzinflate($data);
      }
      catch(\Exception $exception)
      {
        //echo $exception->getMessage() . "\n";

        // Should only happend when connection fails
        //exit;
      }

      return $data;
    }

    /**
     * Write to socket
     *
     * @param string $data
     * @return void
     */
    public function write($data)
    {
      // Compress data
      $data = gzdeflate($data);

      // Pack length of data into 32 bit binary string
      $length = pack('N', strlen($data));

      try
      {
        // Write to socket (length of data)
        if(@socket_write($this->getSocket(), $length) !== strlen($length))
          throw new \Exception(__METHOD__ . '; socket_write() failed: ' . socket_strerror(socket_last_error()));

        // Write to socket (data)
        if(@socket_write($this->getSocket(), $data) !== strlen($data))
          throw new \Exception(__METHOD__ . '; socket_write() failed: ' . socket_strerror(socket_last_error()));
      }
      catch(\Exception $exception)
      {
        //echo $exception->getMessage() . "\n";

        // Should only happend when connection fails
        //exit;
      }
    }

    /**
     * Idle mode (await incomining messages)
     *
     * @param void
     * @return void
     */
    public function idle()
    {
      // Enter idle loop
      $this->setStatus(Runner::IDLE);

      // Start idle timer
      $timer = time();

      while(!$this->terminate)
      {
        // Check signal queue
        if(count($this->signals) > 0)
        {
          $signals = $this->signals;
          $this->signals = [];

          // Handle signals in signal "queue"
          foreach($signals as $key => $signal)
            $this->handle($signal);
        }

        if($this->hasRunnable())
        {
          $runnable = $this->getRunnable();
          $runnable->run();

          // Reset idle timer
          $timer = time();

          // Clear runnable
          $this->setRunnable();

          // Update status to idle
          $this->setStatus(Runner::IDLE);
        }
        else
        {
          time_nanosleep(0, 1000);

          //pcntl_signal_dispatch();
        }
      }
      
      exit;
    }

    public function output($output)
    {
      echo $output . "\n";
    }

    /**
     * Wait
     *
     * @param void
     * @return void
     */
    protected function wait($time)
    {
      sleep($time);
    }
  }