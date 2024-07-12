<?php

  namespace CloudX\Messages;

use CloudX\Task;

  /**
   * Class DataMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class DataMessage extends Message
  {
    protected $task;

    /**
     * Constuct a DataMessage
     *
     * @param integer $pid
     * @return DataMessage
     */
    public function __construct($pid, Task $task)
    {
      parent::__construct($pid);

      $this->setTask($task);
    }

    public function getTask()
    {
      return $this->task;
    }

    public function setTask($task)
    {
      $this->task = $task;
    }
  }