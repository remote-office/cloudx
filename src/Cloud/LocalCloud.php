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
use CloudX\Messages\DataMessage;

use CloudX\TaskStack;
use CloudX\Worker;

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



    public function getManager()
    {
        return $this->manager;
    }

    public function available()
    {
        $manager = $this->getManager();

        $available = parent::available();

        // When no worker is available and cloud worker limit is not reached add a worker
        if(!$available)
        {
            if($manager->getWorkers()->sizeOf() < $this->capacity)
            {
                // Create a new Worker
                $worker = new Worker();

                if($worker->getParentPid() === 0)
                    $manager->add($worker);
                else
                    $worker->loop();
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

                // Send a terminate command to the workers (instead of a posix kill)
                foreach($manager->getWorkers() as $worker)
                    posix_kill($worker->getPid(), $signal);

                // Handle sigterm and sigint
                exit(0);
            }
            break;

            case SIGHUP:
            {
                echo 'Running workers...' . "\n";

                // Handle sighup
                $manager = Manager::getInstance();
                foreach($manager->getWorkers() as $worker)
                {
                    if($worker->hasName())
                        echo '--' . $worker->getName() . ' - ' . $worker->getPid() . "\n";
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