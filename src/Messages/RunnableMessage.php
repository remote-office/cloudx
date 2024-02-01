<?php

  namespace CloudX\Messages;

  use CloudX\Interfaces\Runnable;

  /**
   * Class RunnableMessage
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class RunnableMessage extends Message
  {
    protected $runnable;

    /**
     * Constuct a RunnableMessage
     *
     * @param integer $pid
     * @param Runnable $runnable
     * @return RunnableMessage
     */
    public function __construct($pid, Runnable $runnable)
    {
      parent::__construct($pid);

      $this->setRunnable($runnable);
    }

    public function getRunnable()
    {
      return $this->runnable;
    }

    public function setRunnable(Runnable $runnable)
    {
      $this->runnable = $runnable;
    }
  }