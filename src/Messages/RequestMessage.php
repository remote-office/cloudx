<?php

  namespace CloudX\Messages;

  /**
   * Class RequestMessage
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class RequestMessage extends Message
  {
    protected $request;

    /**
     * Constuct a RequestMessage
     *
     * @param integer $pid
     * @return RequestMessage
     */
    public function __construct($pid, $request)
    {
      parent::__construct($pid);

      $this->setRequest($request);
    }

    public function getRequest()
    {
      return $this->request;
    }

    public function setRequest($request)
    {
      $this->request = $request;
    }
  }