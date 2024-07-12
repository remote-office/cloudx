<?php

  namespace CloudX\Messages;

  /**
   * Class ResponseMessage
   * 
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class ResponseMessage extends Message
  {
    protected $response;

    /**
     * Constuct a ResponseMessage
     *
     * @param integer $pid
     * @return ResponseMessage
     */
    public function __construct($pid, $response)
    {
      parent::__construct($pid);

      $this->setResponse($response);
    }

    public function getResponse()
    {
      return $this->response;
    }

    public function setResponse($response)
    {
      $this->response = $response;
    }
  }