<?php

  namespace CloudX;

  use LibX\Util\Stack;

  /**
   * Class TaskStack
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class TaskStack extends Stack
  {
    /**
     * Push a Task on the stack
     * 
     * @param Task $task
     * @return void
     */
    public function push(Task $task)
    {
      return array_push($this->array, $task);
    }

    /**
     * Pop a Task from the stack
     * 
     * @param void
     * @return Task
     */
    public function pop()
    {
      return array_pop($this->array);
    }

    /**
     * Find a Task in this stack with specified id
     * 
     * @param string $id
     * @return Task
     */
    public function find($id)
    {
      $task = null;

      $this->rewind();
      while($this->valid() && is_null($task))
      {
        if($this->current()->getId() === $id)
          $task = $this->current();

        $this->next();
      }

      return $task;
    }

    /**
     * Filter tasks by status
     * 
     * @param array $status
     * @return TaskStack
     */
    public function status(array $status)
    {
      $tasks = new self();

      $this->rewind();
      foreach($this as $task)
      {
        if(in_array($task->getStatus(), $status))
          $tasks->push($task);
      }

      return $tasks;
    }
  }