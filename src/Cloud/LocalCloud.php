<?php

namespace CloudX\Cloud;

use CloudX\Cloud;

use CloudX\Manager;
use CloudX\Task;

use CloudX\Messages\Message;
use CloudX\Messages\StatusMessage;
use CloudX\Messages\LogMessage;
use CloudX\Messages\KeepAliveMessage;
use CloudX\Messages\CallbackMessage;
use CloudX\TaskStack;

/**
 * Class LocalCloud
 *
 * @author David Betgen <code@platform-x.dev>
 * @version 1.0
 */
class LocalCloud extends Cloud
{
    protected $type;
    protected $capacity;

    protected $signals = [];

    protected $manager;

    /**
     * Construct a Cloud
     *
     * @param integer $size
     * @return Cloud
     */
    public function __construct($capacity = null)
    {
        // Set capacity
        $this->capacity = $capacity;

        $this->manager = new Manager();

        // Install signalhandler on important POSIX signals
        pcntl_signal(SIGCHLD, array($this, 'signal'));
        pcntl_signal(SIGTERM, array($this, 'signal'));
        pcntl_signal(SIGINT,  array($this, 'signal'));
        pcntl_signal(SIGHUP,  array($this, 'signal'));
    }

    /**
     * Process messages from runners (child threads)
     * 
     * @param void
     * @return void
     */
    public function process()
    {
        $manager = $this->getManager();

        $runners = array();
        $sockets = array();

        foreach($manager->getRunners() as $runner)
        {
            if(is_object($runner->getSocket()))
            {
                // Add instance to lookup table
                $runners[spl_object_hash($runner)] = $runner;
                // Add socket to lookup table
                $sockets[spl_object_hash($runner)] = $runner->getSocket();
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
                $runner = $runners[$hash];
                $data = $runner->read();

                if(!empty($data))
                {
                    //echo __METHOD__ . ' - ' . $data . "\n";

                    // Unserialize Message
                    $class = unserialize($data);

                    if(in_array(Message::class, class_parents($class)))
                    {
                        // Get pid of message
                        $pid = $class->getPid();

                        if($class instanceof StatusMessage)
                        {
                            // Get status (Runner::STOPPED or Runner::IDLE)
                            $status = $class->getStatus();

                            // Update status
                            $runner->setStatus($status);
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
                        elseif($class instanceof LogMessage)
                        {
                            $log = $class->getLog();

                            echo '[' . date('Y/m/d H:i:s', time()) . '] -- Runner with pid ' . $runner->getPid() . ' (' . $log . ')' . "\n";
                        }
                    }
                }
                else
                {
                    //echo 'Data is empty' . "\n";
                }
            }
        }
    }

    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Stop this Cloud
     *
     * @param void
     * @return void
     */
    protected function stop()
    {
        $manager = $this->getManager();

        foreach($manager->getRunners() as $runner)
        {
            // Stop an runner
            $runner->stop();
        }

        $this->wait();

        // Remove all runners from manager
        $manager->getRunners()->clear();
    }

    /**
     * Run tasks
     * 
     * @param TaskStack $tasks
     * @return void
     */
    public function run(TaskStack $tasks)
    {
        while($tasks->sizeOf() > 0)
        {
            // Process any messages
            $this->process();

            if($tasks->sizeOf() > 0)
            {
                if($this->available())
                    $this->runner()->execute($tasks->pop()->getRunnable());
                else
                    $this->nanosleep(0, 100);
            }
        }
        
        // Stop running threads
        $this->stop();
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

    /**
     * Get a runner
     * 
     * @param void
     * @return Runner|null
     */
    protected function runner()  
    {
        // Get manager
        $manager = $this->getManager();

        // Get runners
        $runners = $manager->getRunners();
        $runners = clone($runners);
        // Reset pointer
        $runners->rewind();

        // Init runner
        $runner = null;

        while($runners->valid() && is_null($runner))
        {
            // Current element
            if($runners->current()->isIdle())
                $runner = $runners->current();

            // Next element
            $runners->next();
        }

        return $runner;
    }

    public function available()
    {
        // Get manager
        $manager = $this->getManager();

        // Get runners
        $runners = $manager->getRunners();
        $runners = clone($runners);
        // Reset pointer
        $runners->rewind();

        // Init available
        $available = false;

        while($runners->valid() && !$available)
        {
            // Current element
            if($runners->current()->isIdle())
                $available = true;

            // Next element
            $runners->next();
        }

        // When no runner is available and cloud runner limit is not reached add a runner
        if(!$available)
        {
            if($manager->getRunners()->sizeOf() < $this->capacity)
                $manager->add($manager->create());
        }

        return $available;
    }

    /**
     * Wait until all childs have exited
     *
     * @param voud
     * @return void
     */
    protected function wait()
    {
        while($pid = pcntl_waitpid(-1, $status) != -1)
        {
            // Child has exited, no action needed
        }
    }

    public function handle($signal)
    {
        switch($signal)
        {
            // Handle signal child
            case SIGCHLD:
            {
                while(($pid = pcntl_waitpid(0, $status, WNOHANG)) > 0)
                {
                    if(pcntl_wifexited($status))
                    {
                        //echo 'Child ' .  $pid . ' exited normally' . "\n";
                    }
                    else
                    {
                        // Translate status to exit code
                        $code = pcntl_wexitstatus($status);

                        //echo 'Child ' .  $pid . ' exited with code ' .  $code . "\n";
                    }
                }
            }
            break;

            case SIGTERM:
            case SIGINT:
            {
                $manager = Manager::getInstance();

                // Send a terminate command to the runners (instead of a posix kill)
                foreach($manager->getRunners() as $runner)
                    posix_kill($runner->getPid(), $signal);

                // Handle sigterm and sigint
                exit(0);
            }
            break;

            case SIGHUP:
            {
                echo 'Running runners...' . "\n";

                // Handle sighup
                $manager = Manager::getInstance();
                foreach($manager->getRunners() as $runner)
                {
                    if($runner->hasName())
                        echo '--' . $runner->getName() . ' - ' . $runner->getPid() . "\n";
                }
            }
            break;
        }
    }

    /**
     * Handle posix signals
     *
     * @param int $signal
     * @return void
     */
    public function signal($signal)
    {
        $this->handle($signal);
    }
}