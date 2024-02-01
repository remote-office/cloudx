<?php

	namespace CloudX\Messages;

  /**
   * Class Message
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  abstract class Message
  {
    protected $pid;

    /**
     * Constuct a Message
     *
     * @param integer $pid
     * @return Message
     */
    protected function __construct($pid)
    {
      $this->setPid($pid);
    }

    public function getPid()
    {
      return $this->pid;
    }

    public function setPid($pid)
    {
      $this->pid = $pid;
    }
  }