<?php

namespace CloudX;

use CloudX\Messages\Message;
use CloudX\Messages\StatusMessage;
use CloudX\Messages\LogMessage;
use CloudX\Messages\KeepAliveMessage;
use CloudX\Messages\CallbackMessage;
use CloudX\Messages\DataMessage;

/**
 * Class Cloud
 *
 * @author David Betgen <code@platform-x.dev>
 * @version 1.0
 */
abstract class Cloud
{
    protected $type;
    
    protected $autoscale;
    
    public abstract function getManager();
    
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Run tasks
     * 
     * @param TaskStack $tasks
     * @return void
     */
    public function run(TaskStack $tasks)
    {
        while($tasks->status([Task::STATUS_OPEN, Task::STATUS_RUNNING])->sizeOf() > 0)
        {
            // Process any messages
            $this->process($tasks);

            if($tasks->status([Task::STATUS_OPEN])->sizeOf() > 0)
            {
                if($this->available())
                {
                    $task = $tasks->status([Task::STATUS_OPEN])->pop();
                    $task->setStatus(Task::STATUS_RUNNING);

                    $this->worker()->execute($task);
                }
                else
                {
                    $this->nanosleep(0, 100);
                }
            }
        }

        // Stop running threads
        //$this->stop();
    }

    /**
     * Stop this Cloud
     *
     * @param void
     * @return void
     */
    public function stop()
    {
        $manager = $this->getManager();

        foreach($manager->getWorkers() as $worker)
        {
            // Stop an worker
            $worker->stop();
        }

        $this->wait();

        // Remove all workers from manager
        $manager->getWorkers()->clear();
    }

    protected abstract function wait();

    /**
     * Get a worker
     * 
     * @param void
     * @return Worker|null
     */
    protected function worker()  
    {
        // Get manager
        $manager = $this->getManager();

        // Get workers
        $workers = $manager->getWorkers();
        $workers = clone($workers);
        // Reset pointer
        $workers->rewind();

        // Init worker
        $worker = null;

        while($workers->valid() && is_null($worker))
        {
            // Current element
            if($workers->current()->isIdle())
                $worker = $workers->current();

            // Next element
            $workers->next();
        }

        return $worker;
    }

    public function available()
    {
        // Get manager
        $manager = $this->getManager();

        // Get workers
        $workers = $manager->getWorkers();
        $workers = clone($workers);
        // Reset pointer
        $workers->rewind();

        // Init available
        $available = false;

        while($workers->valid() && !$available)
        {
            // Current element
            if($workers->current()->isIdle())
                $available = true;

            // Next element
            $workers->next();
        }

        return $available;
    }

    /**
     * Process messages from workers (child threads)
     * 
     * @param TaskStack $tasks
     * @return void
     */
    public function process(TaskStack $tasks)
    {
        $manager = $this->getManager();

        $workers = array();
        $sockets = array();

        foreach($manager->getWorkers() as $worker)
        {
            if(is_object($worker->getSocket()))
            {
                // Add instance to lookup table
                $workers[spl_object_hash($worker)] = $worker;
                // Add socket to lookup table
                $sockets[spl_object_hash($worker)] = $worker->getSocket();
            }
        }

        // Zend engine limitation fix
        $null = null;

        //echo 'Socket select on ' . count($sockets) . ' sockets' . "\n";

        if(count($sockets) > 0)
        {
            // Select
            @socket_select($sockets, $null, $null, 0);

            //echo 'Data is ready on ' . count($sockets) . ' sockets' . "\n";

            foreach(array_keys($sockets) as $hash)
            {
                $worker = $workers[$hash];
                $data = $worker->read();

                if(!empty($data))
                {
                    echo __METHOD__ . ' - ' . $data . "\n";

                    // Unserialize Message
                    $class = unserialize($data);

                    if(in_array(Message::class, class_parents($class)))
                    {
                        // Get pid of message
                        $pid = $class->getPid();

                        if($class instanceof StatusMessage)
                        {
                            // Get status (STOPPED or IDLE)
                            $status = $class->getStatus();

                            // Update status
                            $worker->setStatus($status);
                        }
                        elseif($class instanceof CallbackMessage)
                        {
                            // Get callable
                            $callable = $class->getCallable();
                            $parameters = $class->getParameters();

                            // Call it
                            call_user_func_array($callable, $parameters);
                        }
                        elseif($class instanceof KeepAliveMessage)
                        {

                        }
                        elseif($class instanceof DataMessage)
                        {
                            // Find "local" task
                            $task = $tasks->find($class->getTask()->getId());
                            $task->setData($class->getTask()->getData());
                            $task->setStatus(Task::STATUS_CLOSED);
                        }
                        elseif($class instanceof LogMessage)
                        {
                            $log = $class->getLog();
                           
                            echo '[' . date('Y/m/d H:i:s', time()) . '] -- Worker with pid ' . $worker->getPid() . ' (' . $log . ')' . "\n";
                        }
                    }
                }
                else
                {
                    echo 'Data is empty' . "\n";
                }
            }
        }

        return true;
    }

    /**
     * Sleep
     *
     * @param integer $seconds
     * @param integer $milliseconds
     * @param integer $microseconds
     * @param integer $nanoseconds
     * @return void
     */
    protected function nanosleep($seconds = 0, $milliseconds = 0, $microseconds = 0, $nanoseconds = 0)
    {
        // Convert milliseconds to nanoseconds
        if($milliseconds > 0)
            $nanoseconds = $nanoseconds + ($milliseconds * 1000000);

        // Convert microseconds to nanoseconds
        if($microseconds > 0)
            $nanoseconds = $nanoseconds + ($microseconds * 1000);

        // Init nanosleep
        $nanosleep = array();
        $nanosleep['seconds'] = $seconds;
        $nanosleep['nanoseconds'] = $nanoseconds;

        // Loop to avoid wakeup when other threads get interrupted
        while(is_array($nanosleep))
            $nanosleep = time_nanosleep($nanosleep['seconds'], $nanosleep['nanoseconds']);
    }
}