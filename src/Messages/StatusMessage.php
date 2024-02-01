<?php

  namespace CloudX\Messages;

  /**
   * Class StatusMessage
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class StatusMessage extends Message
  {
    protected $status;

    /**
     * Constuct a StatusMessage
     *
     * @param integer $pid
     * @return StatusMessage
     */
    public function __construct($pid, $status)
    {
      parent::__construct($pid);

      $this->setStatus($status);
    }

    public function getStatus()
    {
      return $this->status;
    }

    public function setStatus($status)
    {
      $this->status = $status;
    }
  }