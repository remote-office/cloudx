<?php

  namespace CloudX;

  /**
   * Class TaskManager
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class TaskManager
  {
    protected $tasks = array();

    /**
     * Add a task to the task manager
     *
     * @param Task $task
     * @return void
     */
    public function addTask(Task $task)
    {
      $this->tasks[$task->getId()] = $task;
    }

    /**
     * Remove a task from the task manager
     *
     * @param Task $task
     * @return void
     */
    public function removeTask(Task $task)
    {
      if(isset($this->tasks[$task->getId()]))
        unset($this->tasks[$task->getId()]);
    }
  }