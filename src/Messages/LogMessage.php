<?php

  namespace CloudX\Messages;

  /**
   * Class LogMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class LogMessage extends Message
  {
   	protected $log;

    /**
     * Constuct a LogMessage
     *
     * @param integer $pid
     * @return LogMessage
     */
    public function __construct($pid, $log)
    {
      parent::__construct($pid);

      $this->setLog($log);
    }

    public function getLog()
    {
      return $this->log;
    }

    public function setLog($log)
    {
      $this->log = $log;
    }
  }