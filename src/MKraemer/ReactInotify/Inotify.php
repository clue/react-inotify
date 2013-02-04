<?php

namespace MKraemer\ReactInotify;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

/**
 * Inotify allows to listen to inotify events emit evenement events
 */
class Inotify extends EventEmitter
{
    /**
     * @var resource
     */
    protected $inotifyHandler = false;

    /**
     * @var array
     */
    protected $watchDescriptors = array();
    
    /**
     * @var React\EventLoop\LoopInterface
     */
    protected $loop;

    /**
     * Constructor. Actual initialization takes place once first watched
     * paths is registered during add()
     *
     * @param React\EventLoop\LoopInterface $loop Event Loop
     * @see self::add()
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * Checks all new inotify events available
     * and emits them via evenement
     */
    public function __invoke()
    {
        if (false !== ($events = \inotify_read($this->inotifyHandler))) {
            foreach ($events as $event) {
                $path = $this->watchDescriptors[$event['wd']]['path'];
                $this->emit($event['mask'], array($path . $event['name']));
            }
        }
    }

    /**
     * Adds a path to the list of watched paths
     *
     * @param string  $path      Path to the watched file or directory
     * @param integer $mask      Bitmask of inotify constants
     */
    public function add($path, $mask)
    {
        if ($this->inotifyHandler === false) {
            // initialize inotify handler
            $this->inotifyHandler = \inotify_init();
            stream_set_blocking($this->inotifyHandler, 0);
            
            // wait for any file events by reading from inotify handler asynchronously
            $this->loop->addReadStream($this->inotifyHandler, $this);
        }
        $descriptor = \inotify_add_watch($this->inotifyHandler, $path, $mask);
        $this->watchDescriptors[$descriptor] = array('path' => $path);
    }

    /**
     * Clear all pending watched paths and close the inotifyHandler
     */
    public function close()
    {
        if ($this->inotifyHandler !== false) {
            $this->loop->removeReadStream($this->inotifyHandler);
            
            fclose($this->inotifyHandler);
            $this->inotifyHandler = false;
            
            $this->watchDescriptors = array();
        }
    }
}
