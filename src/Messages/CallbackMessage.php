<?php

  namespace CloudX\Messages;

  use Closure;

  /**
   * Class CallbackMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class CallbackMessage extends Message
  {
    protected $callable;
    protected $parameters;

    /**
     * Constuct a CallbackMessage
     *
     * @param integer $pid
     * @param Closure $closure
     * @param array $parameters
     * @return CallbackMessage
     */
    public function __construct($pid, callable $callable, $parameters = [])
    {
      parent::__construct($pid);

      $this->setCallable($callable);
      $this->setParameters($parameters);
    }

    public function getCallable()
    {
      return $this->callable;
    }

    public function setCallable(callable $callable)
    {
      $this->callable = $callable;
    }

    public function getParameters()
    {
      return $this->parameters;
    }

    public function setParameters($parameters)
    {
      $this->parameters = $parameters;
    }
  }