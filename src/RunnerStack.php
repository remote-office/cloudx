<?php

  namespace CloudX;

  use LibX\Util\Stack;
  
  /**
   * Class RunnerStack
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class RunnerStack extends Stack
  {
    /**
     * Push a Runner onto the stack
     *
     * @param Runner $runner
     * @return void
     */
    public function push(Runner $runner)
    {
      array_push($this->array, $runner);
    }

    /**
     * Pop a Runner from the stack
     *
     * @param void
     * @return Runner
     */
    public function pop()
    {
      return array_pop($this->array);
    }
  }

?>