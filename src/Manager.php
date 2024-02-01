<?php

	namespace CloudX;

	use LibX\Util\Hashtable;

  /**
   * Class Manager
   *
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class Manager
  {
    protected $runners;

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
      $this->setRunners(new Hashtable());
    }

    /**
     * Get runners of this Manager
     *
     * @param void
     * @return LibXHashtable
     */
    public function getRunners()
    {
      return $this->runners;
    }

    /**
     * Set runners of this Manager
     *
     * @param Hashtable $runners
     * @return void
     */
    public function setRunners(Hashtable $runners)
    {
      $this->runners = $runners;
    }

    /**
     * Add an Runner to this Manager
     *
     * @param Runner $runner
     * @return void
     */
    public function add(Runner $runner)
    {
      $this->getRunners()->set(spl_object_hash($runner), $runner);
    }

    /**
     * Remove a Runner from this Manager
     *
     * @param Runner $runner
     * @return void
     */
    public function remove(Runner $runner)
    {
      $this->getRunners()->delete(spl_object_hash($runner));
    }

    public function create()
    {
      // Create a new Runner
      $runner = new Runner();

      if($runner->getParentPid() == 0)
      {
        /**
         * Parent process
         */

        // Return runner
        return $runner;
      }
      else
      {
        /**
         * Child process
         */

        // Idle mode (await messages)
        $runner->idle();
      }
    }

    public function destroy()
    {

    }
  }