<?php

	namespace CloudX;

	use LibX\Util\Hashtable;

  /**
   * Class Manager
   *
   * @author David Betgen <code@platform-x.dev>
   * @version 1.0
   */
  class Manager
  {
    protected $workers;

    protected static $instance;

    /**
     * Get an instance of this Manager
     *
     * @param void
     * @return Manager
     */
    public static function getInstance()
    {
      if (!isset(self::$instance))
        self::$instance = new self();

      return self::$instance;
    }

    /**
     * Construct a Manager
     *
     * @param void
     * @return Manager
     */
    public function __construct()
    {
      $this->setWorkers(new Hashtable());
    }

    /**
     * Get workers of this Manager
     *
     * @param void
     * @return LibXHashtable
     */
    public function getWorkers()
    {
      return $this->workers;
    }

    /**
     * Set workers of this Manager
     *
     * @param Hashtable $workers
     * @return void
     */
    public function setWorkers(Hashtable $workers)
    {
      $this->workers = $workers;
    }

    /**
     * Add an Worker to this Manager
     *
     * @param AbstractWorker $worker
     * @return void
     */
    public function add(AbstractWorker $worker)
    {
      $this->getWorkers()->set(spl_object_hash($worker), $worker);
    }

    /**
     * Remove a Worker from this Manager
     *
     * @param AbstractWorker $worker
     * @return void
     */
    public function remove(AbstractWorker $worker)
    {
      $this->getWorkers()->delete(spl_object_hash($worker));
    }
  }