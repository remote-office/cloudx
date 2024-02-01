<?php

  namespace CloudX\Messages;

  /**
   * Class KeepAliveMessage
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class KeepAliveMessage extends Message
  {
  	/**
     * Constuct a KeepAliveMessage
     *
     * @param integer $pid
     * @return KeepAliveMessage
     */
    public function __construct($pid)
    {
      parent::__construct($pid);
    }
  }