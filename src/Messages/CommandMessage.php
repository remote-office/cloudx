<?php

  namespace CloudX\Messages;

  /**
   * Class CommandMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class CommandMessage extends Message
  {
    protected $command;

    /**
     * Constuct a CommandMessage
     *
     * @param integer $pid
     * @return CommandMessage
     */
    public function __construct($pid, $command)
    {
      parent::__construct($pid);

      $this->setCommand($command);
    }

    public function getCommand()
    {
      return $this->command;
    }

    public function setCommand($command)
    {
      $this->command = $command;
    }
  }