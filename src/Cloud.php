<?php

namespace CloudX;

use CloudX\Messages\Message;
use CloudX\Messages\StatusMessage;
use CloudX\Messages\LogMessage;
use CloudX\Messages\KeepAliveMessage;
use CloudX\Messages\CallbackMessage;

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
}