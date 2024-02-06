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
    public function push(Task $task)
    {
      return array_push($this->array, $task);
    }

    public function pop()
    {
      return array_pop($this->array);
    }
  }