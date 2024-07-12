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
  use CloudX\Messages\DataMessage;

  use Socket;

  abstract class AbstractWorker
  {
    const STARTING  = 1;
    const STARTED   = 2;
    const STOPPING  = 4;
    const STOPPED   = 5;

    const RUNNING   = 3;
    const IDLE      = 0;

    protected $id;
    protected $name;
    protected $task;
    
    // Status flags
    protected $status;

    // Internal socket  
    protected $socket;

    protected $ppid;
    protected $pid;

    protected $terminate = false;
 
    public function getId()
    {
      return $this->id;
    }

    public function setId($id)
    {
      $this->id = $id;
    }

    /**
     * Get name of this Worker
     *
     * @param void
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set PID of this Worker
     *
     * @param int $pid
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Check if this Worker as a name
     *
     * @param void
     * @return string
     */
    public function hasName()
    {
      return !is_null($this->name);
    }

    /**
     * Get PID of this Worker
     *
     * @param void
     * @return integer
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Set PID of this Worker
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
     * Get PPID of this Worker
     *
     * @param void
     * @return integer
     */
    public function getParentPid()
    {
        return $this->ppid;
    }

    /**
     * Set PPID of this Worker
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

    /**
     * Get socket of this Worker
     *
     * @param void
     * @return Socket
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Set Socket of this Worker
     *
     * @param Socket $socket
     * @return void
     */
    public function setSocket(Socket $socket)
    {
        $this->socket = $socket;
    }

    protected function getTask()
    {
      return $this->task;
    }

    protected function setTask(Task $task = null)
    {
      $this->task = $task;
    }

    protected function hasTask()
    {
      return !is_null($this->task);
    }

    /**
     * Check status for idle
     *
     * @param void
     * @return boolean
     */
    public function isIdle()
    {
      return ($this->getStatus() == self::IDLE);
    }

    /**
     * Check status for running
     *
     * @param void
     * @return boolean
     */
    public function isRunning()
    {
      return ($this->getStatus() == self::RUNNING);
    }

    /**
     * Check status for stopped
     *
     * @param void
     * @return boolean
     */
    public function isStopped()
    {
      return ($this->getStatus() == self::STOPPED);
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
        //posix_kill($this->getParentPid(), SIGUSR1);
      }

      $this->status = $status;
    }

    /**
     * Stop the worker
     *
     * @param void
     * @return void
     */
    public function stop()
    {
      // Create a StatusMessage
      $statusMessage = new StatusMessage($this->getPid(), self::STOPPING);

      // Serialize
      $data = serialize($statusMessage);

      // Write
      $this->write($data);
    }

    /**
     * Execute a Task
     *
     * @param Task
     * @return void
     */
    public function execute(Task $task)
    {
      // Update status
      $this->setStatus(self::RUNNING);

      // Create a RunnableMessage
      $runnableMessage = new RunnableMessage($this->getPid(), $task);

      // Send runnable class to other process
      $data = serialize($runnableMessage);

      // Write to other process
      $this->write($data);
    }

    /**
     * Process received data
     * 
     * @param string $data
     * @return void
     */
    private function process($data)
    {
      if(!empty($data))
      {
        // Unserialize Message
        $class = unserialize($data);

        //var_dump($class);

        if(in_array(Message::class, class_parents($class)))
        {
          // Get pid of message
          $pid = $class->getPid();

          
          if($class instanceof RunnableMessage)
          {
            // Set it
            $this->setTask($class->getTask());
          }

          if($class instanceof StatusMessage)
          {
            // Get status (self::STOPPED or self::IDLE)
            $status = $class->getStatus();

            if($status === self::STOPPING)
            {
              $this->terminate = true;
            }
          }

          if($class instanceof CommandMessage)
          {
            // Get command 
            $command = $class->getCommand();
          }
        }
      }
      else
      {
        //echo 'No data to read... ' . "\n";

        exit;
      }
    }

    protected function listen()
    {
      // Zend engine limitation fix
      $null = null;

      // Gather sockets
      $sockets = array($this->getSocket());

      // Select
      @socket_select($sockets, $null, $null, 1);

      // Do we have something to read
      if(count($sockets) > 0)
        $this->process($this->read());
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

        //echo $data . "\n";
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

      //echo  'Write: ' . $data . "\n";

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
     * Sleep
     *
     * @param integer $seconds
     * @param integer $milliseconds
     * @param integer $microseconds
     * @param integer $nanoseconds
     * @return void
     */
    protected function nanosleep($seconds = 0, $milliseconds = 0, $microseconds = 0, $nanoseconds = 0)
    {
        // Convert milliseconds to nanoseconds
        if($milliseconds > 0)
            $nanoseconds = $nanoseconds + ($milliseconds * 1000000);

        // Convert microseconds to nanoseconds
        if($microseconds > 0)
            $nanoseconds = $nanoseconds + ($microseconds * 1000);

        // Init nanosleep
        $nanosleep = array();
        $nanosleep['seconds'] = $seconds;
        $nanosleep['nanoseconds'] = $nanoseconds;

        // Loop to avoid wakeup when other threads get interrupted
        while(is_array($nanosleep))
            $nanosleep = time_nanosleep($nanosleep['seconds'], $nanosleep['nanoseconds']);
    }

    public function output($output)
    {
      echo $output . "\n";
    }

    /**
     * Loop mode (await incomining messages)
     *
     * @param void
     * @return void
     */
    public function loop()
    {
      // Enter idle loop
      $this->setStatus(self::IDLE);

      // Start idle timer
      $timer = time();

      // Start keepalve time
      $keepalive = time();

      while(!$this->terminate)
      {
        // Syscall select (on socket)
        $this->listen();

        if($this->hasTask())
        {
          $task = $this->getTask();

          $runnable = $task->getRunnable();

          $data = $runnable->run();

          if(!is_null($data))
          {
            $task->setData($data);

            // Create a DataMessage
            $dataMessage = new DataMessage($this->getPid(), $task);

            // Send data class to other process
            $data = serialize($dataMessage);

            // Write to other process
            $this->write($data);  
          }

          // Reset idle timer
          $timer = time();

          // Clear task
          $this->setTask();

          // Update status to idle
          $this->setStatus(self::IDLE);
        }
        else
        {
          $now = time();

          // Check keepalive timer
          if($now - $keepalive > 10)
          {
            // Create a KeepAliveMessage
            $keepAliveMessage = new KeepAliveMessage($this->getPid());

            // Send data class to other process
            $data = serialize($keepAliveMessage);

            // Write to other process
            $this->write($data);

            // Update last keepalive
            $keepalive = $now;
          }
        }
      }
      
      exit(0);
    }
  }