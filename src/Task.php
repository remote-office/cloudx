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
    protected $id;
    protected $runnable;

    public function __construct($id, Runnable $runnable)
    {
      $this->id = $id;
      $this->runnable = $runnable;
    }

    public function getId()
    {
      return $this->id;
    }

    public function getRunnable()
    {
      return $this->runnable;
    }
  }