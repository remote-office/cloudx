<?php

	namespace CloudX;

  use CloudX\Interfaces\Runnable;

  /**
   * Class Task
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class Task
  {
    const STATUS_OPEN = 0;
    const STATUS_RUNNING = 1;
    const STATUS_CLOSED = 2;
    
    protected $id;
    protected $runnable;
    protected $status;

    protected $data;

    public function __construct($id, Runnable $runnable)
    {
      $this->id = $id;
      $this->runnable = $runnable;

      $this->status = self::STATUS_OPEN;
    }

    public function getId()
    {
      return $this->id;
    }

    public function getRunnable() 
    {
      return $this->runnable;
    }

    public function getStatus()
    {
      return $this->status;
    }

    public function setStatus($status)
    {
      $this->status = $status;
    }

    public function hasData()
    {
      return !is_null($this->data);
    }

    public function getData()
    {
      return $this->data;
    }

    public function setData($data)
    {
      $this->data = $data;
    }

  }