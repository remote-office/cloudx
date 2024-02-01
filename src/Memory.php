<?php

  namespace CloudX;
  
  use Exception;
  
  /**
   * Class Memory
   * 
   * @author David Betgen <d.betgen@remote-office.nl>
   * @version 1.0
   */
  class Memory
  {
    protected $key;
    protected $resource;
    
    /**
     * Construct a Memory with a given size
     * 
     * @param integer $size
     * @return Memory
     */
    public function __construct($size)
    {
      // Setup shared memory
      $tmp = tempnam(sys_get_temp_dir(), 'CloudX');
      
      // Get inode and use it as a key
      $this->key = fileinode($tmp);
      
      // Create shared memory segment
      $this->resource = shm_attach($this->key, $size);
    }
    
    /**
     * Read from shared memory
     * 
     * @param integer $key
     * @return mixed
     * @throws Exception
     */
    public function read($key)
    {
      // Lock
      $this->lock();
      
      if(!shm_has_var($this->resource, $key))
        throw new Exception('Key ' . $key . ' not found in shared memory');
      
      // Get value from shared memory
      $value = shm_get_var($this->resource, $key);
        
      // Unlock
      $this->unlock();
      
      return $value;
    }
    
    /**
     * Write to shared memory
     * 
     * @param integer $key
     * @param mixed $value
     * @return void
     */
    public function write($key, $value)
    {
      // Lock
      $this->lock();
      
      // Write value to shared memory with given key
      if(!shm_put_var($this->resource, $key, $value))
        throw new \Exception('Could not write to shareed memory with key ' . $key);
      
      // Unlock
      $this->unlock();
    }
    
    /**
     * Lock access to shared memory
     * 
     * @param void
     * @return void
     * @throws Exception
     */
    private function lock()
    {
      // Get a System V semaphore identifier
      $identifier = sem_get($this->key);
      
      if($identifier === false)
        throw new Exception('Could not get semaphore identifier');
      
      // Acquire lock (this will block)
      if(!sem_acquire($identifier))
        throw new Exception('Could not acquire lock on semaphore');
      
    }
    
    /**
     * Unlock access to shared memory
     *
     * @param void
     * @return void
     * @throws Exception
     */
    private function unlock()
    {
      // Get a System V semaphore identifier
      $identifier = sem_get($this->key);
      
      if($identifier === false)
        throw new Exception('Could not get semaphore identifier');
      
      // Release lock
      if(!sem_release($identifier))
        throw new Exception('Could not release lock on semaphore');
    }
  }