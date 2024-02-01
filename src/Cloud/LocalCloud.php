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

/**
 * Class LocalCloud
 *
 * @author David Betgen <d.betgen@remote-office.nl>
 * @version 1.0
 */
class LocalCloud extends Cloud
{
    protected $type;
    protected $capacity;

    protected $signals = [];

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

        pcntl_async_signals(TRUE);

        // Install signalhandler on important POSIX signals
        pcntl_signal(SIGUSR1, array($this, 'signal'));
        pcntl_signal(SIGCHLD, array($this, 'signal'));
        pcntl_signal(SIGTERM, array($this, 'signal'));
        pcntl_signal(SIGINT,  array($this, 'signal'));
        pcntl_signal(SIGHUP,  array($this, 'signal'));
    }

    public function getManager()
    {
        return Manager::getInstance();
    }

    /**
     * Stop this Cloud
     *
     * @param void
     * @return void
     */
    public function stop()
    {
        $manager = Manager::getInstance();

        foreach($manager->getRunners() as $runner)
        {
            // Stop an runner
            $runner->stop();
        }

        // Remove all runners from manager
        $manager->getRunners()->clear();

        $this->wait();
    }

    /**
     * Run a task
     * 
     * @param Task $task
     * @return void
     */
    public function run(Task $task)
    {
        $runner = $this->runner();
        $runner->execute($task->getRunnable());
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
        $manager = Manager::getInstance();

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
        $manager = Manager::getInstance();

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
            {
                $manager->add($manager->create());
                $available = true;
            }
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

    /**
     * Handle posix signals
     *
     * @param int $signal
     * @return void
     */
    public function handle($signal)
    {
        //echo __METHOD__  . ' ' . $signal . "\n";

        // Handle signal
        switch($signal)
        {
            // Signal when a child has send a message
            case SIGUSR1:
            {
                $manager = Manager::getInstance();

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

                if(count($sockets) > 0)
                {
                    // Select
                    @socket_select($sockets, $null, $null, 0);

                    foreach(array_keys($sockets) as $hash)
                    {
                        $runner = $runners[$hash];
                        $data = $runner->read();

                        if(!empty($data))
                        {
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
                    }
                }
            }
            break;

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

                foreach($manager->getRunners() as $runner)
                    posix_kill($runner->getPid(), $signal);

                // Handle sigterm and sigint
                exit();
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