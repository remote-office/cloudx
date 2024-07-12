<?php

  namespace CloudX;

  use CloudX\AbstractWorker;
  use LibX\Util\Stack;
  
  /**
   * Class WorkerStack
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class WorkerStack extends Stack
  {
    /**
     * Push a Worker onto the stack
     *
     * @param AbstractWorker $worker
     * @return void
     */
    public function push(AbstractWorker $worker)
    {
      array_push($this->array, $worker);
    }

    /**
     * Pop a Worker from the stack
     *
     * @param void
     * @return Worker
     */
    public function pop()
    {
      return array_pop($this->array);
    }
  }

?>