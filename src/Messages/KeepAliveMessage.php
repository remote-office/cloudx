<?php

  namespace CloudX\Messages;

  /**
   * Class KeepAliveMessage
   *
   * @author David Betgen <code@platform-x.dev>
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