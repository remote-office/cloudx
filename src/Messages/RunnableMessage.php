<?php

  namespace CloudX\Messages;

  use CloudX\Task;

  /**
   * Class RunnableMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class RunnableMessage extends Message
  {
    protected $task;

    /**
     * Constuct a RunnableMessage
     *
     * @param integer $pid
     * @param Task $task
     * @return RunnableMessage
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

    public function setTask(Task $task)
    {
      $this->task = $task;
    }
  }