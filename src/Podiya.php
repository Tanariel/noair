<?php

namespace DavidRockin\Podiya;

/**
 * Podiya main class
 *
 * @author  David Tkachuk
 * @package Podiya
 * @version 2.0
 */
class Podiya
{
    const PRIORITY_URGENT	= 0;
    const PRIORITY_HIGHEST	= 1;
    const PRIORITY_HIGH		= 2;
    const PRIORITY_NORMAL	= 3;
    const PRIORITY_LOW		= 4;
    const PRIORITY_LOWEST	= 5;
    
    /**
     * An array that contains registered events and their handlers by priority
     *
     * @access  private
     * @since   0.1
     */
    private $events = [];
    
    /**
     * An array holding any published events to which no handler has yet subscribed
     * 
     * @access  private
     * @since   2.0
     */
    private $pending = [];
    
    /**
     * Whether we should put published events for which there are no subscribers
     * onto the $pending list.
     * 
     * @access  private
     * @since   2.0
     */
    private $holdUnheardEvents = false;

    /**
     * Determine if events may be held if there are no subscribers for them
     *
     * @access  public
     * @return  bool    Return true if events may be held, otherwise false
     * @since   2.0
     */
    public function willHoldUnheardEvents() {
        return $this->holdUnheardEvents;
    }
    
    /**
     * Specifies if the event should be held if there are no subscribers for it
     *
     * @access  public
     * @param   bool    $hold   Hold the event in the pending list or not
     * @return  bool    Returns the new value we've set it to
     * @since   2.0
     */
    public function holdUnheardEvents($hold = true) {
        if (!$hold) {
            $this->pending = [];
        }
        return ($this->holdUnheardEvents = (bool) $hold);
    }
    
    /**
     * Registers an event handler to an event
     * 
     * @access  public
     * @param   string      $eventName  The published event's name
     * @param   callable    $callback   A callback that will handle the event
     * @param   int         $priority   Priority of the event (0-5)
     * @param   bool        $force      Whether to ignore event cancellation
     * @return  mixed   False if $eventName isn't published, array of first two params otherwise
     * @since   2.0
     */
    public function subscribe($eventName, callable $callback, 
                              $priority = self::PRIORITY_NORMAL, $force = false)
    {
        $interval = false;
        if (strpos($eventName, 'timer:') === 0) {
            $interval = (int) substr($eventName, 6);
            $eventName = 'timer';
        }
        
        if (!$this->hasSubscribers($eventName)) {
            $this->events[$eventName] = [
                'subscribers'          => 0,
                self::PRIORITY_URGENT  => [],
                self::PRIORITY_HIGHEST => [],
                self::PRIORITY_HIGH    => [],
                self::PRIORITY_NORMAL  => [],
                self::PRIORITY_LOW     => [],
                self::PRIORITY_LOWEST  => [],
            ];
        }
        
        $newsub = [
            'callback' => $callback,
            'force'    => (bool) $force,
        ];
        if ($interval) {
            $newsub['interval'] = $interval; // milliseconds
            $newsub['lastcalltime'] = self::currentTimeMillis();
        }
        $this->events[$eventName][$priority][] = $newsub;
        $this->events[$eventName]['subscribers']++;
        
        // there will never be pending timer events, so go ahead and return
        if ($interval) {
            return [$eventName . ':' . $interval, $callback, $result];
        }
        
        // now re-publish any pending events for this subscriber
        $result = null;
        $pcount = count($this->pending); // will be 0 if functionality is disabled
        for ($i = 0; $i < $pcount; $i++) {
            if ($this->pending[$i]->getName() == $eventName) {
                $result[] = $this->publish(array_splice($this->pending, $i, 1), $priority);
            }
        }
        
        return [$eventName, $callback, $result];
    }
    
    /**
     * Subscribes multiple handlers at once
     * 
     * @access  public
     * @param   array   $arr    The list of handlers
     * @return  void
     * @since   2.0
     */
    public function subscribe_array(array $arr)
    {
        foreach ($arr as $info) {
            $this->subscribe($info[0], $info[1],
                            (isset($info[2]) ? $info[2] : self::PRIORITY_NORMAL),
                            (isset($info[3]) ? $info[3] : false));    
        }
    }
    
    /**
     * Detach a handler from its event
     * 
     * @access  public
     * @param   string      $eventName  The event we want to unsubscribe from
     * @param   callable    $callback   The callback we want to remove from the event
     * @return  \DavidRockin\Podiya\Podiya  This object
     * @since   2.0
     */
    public function unsubscribe($eventName, callable $callback)
    {
        if (strpos($eventName, 'timer:') === 0) {
            $callback = ['interval' => (int) substr($eventName, 6), 'callback' => $callback];
            $eventName = 'timer';
        }
        
        if (($priority = $this->isSubscribed($eventName, $callback)) !== false) {
            foreach ($this->events[$eventName][$priority] as $subscribers) {
                $key = self::array_search_deep($callback, $this->events[$eventName][$priority]);
                if ($key !== false) {
                    unset($this->events[$eventName][$priority][$key]);
                    $this->events[$eventName]['subscribers']--;
                }
            }
            
            if ($this->events[$eventName]['subscribers'] == 0) {
                unset($this->events[$eventName]);
            }
        }
        
        return $this;
    }
    
    /**
     * Unsubscribes multiple handlers at once
     * 
     * @access  public
     * @param   array   $arr    The list of handlers
     * @return  void
     * @since   2.0
     */
    public function unsubscribe_array(array $arr)
    {
        foreach ($arr as $info) {
            $this->unsubscribe($info[0], $info[1]);
        }
    }
    
    /**
     * Remove all subscribers from an event
     * 
     * @access  public
     * @param   string  $eventName  The desired event's name
     * @return  void
     * @since   2.0
     */
    public function unsubscribeAll($eventName)
    {
        unset($this->events[$eventName]);
    }
    
    /**
     * Get the array of subscribers by priority for a given event name
     * 
     * @access  public
     * @param   string  $eventName  The desired event's name
     * @return  mixed   Array of subscribers by priority if found, false otherwise
     * @since   2.0
     */
    public function getSubscribers($eventName)
    {
        return ($this->hasSubscribers($eventName)) ? $this->events[$eventName] : false;
    }
    
    /**
     * Determine if the event name has any subscribers
     * 
     * @access  public
     * @param   string  $eventName  The desired event's name
     * @return  bool    Whether or not the event was published
     * @since   2.0
     */
    public function hasSubscribers($eventName)
    {
        return isset($this->events[$eventName]);
    }
    
    /**
     * Determine if the described event has been subscribed to or not by the callback
     * 
     * @access  public
     * @param   string      $eventName  The desired event's name
     * @param   callable    $callback   The specific callback we're looking for
     * @return  mixed   Priority it's subscribed to if found, false otherwise; use ===
     * @since   2.0
     */
    public function isSubscribed($eventName, callable $callback)
    {
        if ($this->hasSubscribers($eventName)) {
            return self::array_search_deep($callback, $this->events[$eventName]);
        }
        return false;
    }
    
    /**
     * Let any relevant subscribers know an event needs to be handled
     *
     * Note: The event object can be used to share information to other similar
     * event handlers.
     *
     * @access  public
     * @param   DavidRockin\Podiya\Event    $event  An event object
     * @param   mixed   $priority   Notify only subscribers of a certain priority level
     * @return  mixed   Result of the event
     * @since   2.0
     */
    public function publish(Event $event, $priority = false)
    {
        if ($this->holdUnheardEvents
            && !($event->getName() == 'timer' || $this->hasSubscribers($event->getName()))
        ) {
            array_unshift($this->pending, $event);
            return;
        }
        
        $result = null;
        
        if ($priority === false) {
            // Loop through all the priority levels
            foreach ($this->events[$event->getName()] as $plevel => &$subscribers) {
                if ($plevel != 'subscribers') {
                    // Loop through the subscribers of this priority level
                    foreach ($subscribers as &$subscriber) {
                        if (!$event->isCancelled() || $subscriber['force']) {
                            // check for timer & handle it
                            if (isset($subscriber['interval'])) {
                                if (self::currentTimeMillis() - $subscriber['lastcalltime']
                                    > $subscriber['interval']
                                ) {
                                    $subscriber['lastcalltime'] = self::currentTimeMillis();
                                } else {
                                    continue;
                                }
                            }
                            $event->addPreviousResult($result);
                            $result = call_user_func($subscriber['callback'], $event);
                        }
                    }
                }
            }
        } else {
            // Loop through the subscribers of the given priority
            foreach ($this->events[$event->getName()][$priority] as &$subscriber) {
                if (!$event->isCancelled() || $subscriber['force']) {
                    // check for timer & handle it
                    if (isset($subscriber['interval'])) {
                        if (self::currentTimeMillis() - $subscriber['lastcalltime']
                            > $subscriber['interval']
                        ) {
                            $subscriber['lastcalltime'] = self::currentTimeMillis();
                        } else {
                            continue;
                        }
                    }
                    $event->addPreviousResult($result);
                    $result = call_user_func($subscriber['callback'], $event);
                }
            }
        }
        return $result;
    }
    
    /**
     * Searches a multi-dimensional array for a value in any dimension.
     * Named similar to the built-in PHP array_search() function.
     *
     * @access  public
     * @param   mixed   $needle     The value to be searched for
     * @param   array   $haystack   The array
     * @return  mixed   The top-level key containing the needle if found, false otherwise
     * @since   2.0
     */
    public static function array_search_deep($needle, array $haystack)
    {
        if (is_array($needle)
            && !is_callable($needle)
            && count(array_diff_assoc($needle, $haystack)) == 0
        ) {
            return true;
        }
        
        foreach ($haystack as $key => $value) {
            if ($needle === $value
                || (is_array($value)
                    && self::array_search_deep($needle, $value) !== false
                )
            ) {
                return $key;
            }
        }
        return false;
    }
    
    /**
     * Returns the current timestamp in milliseconds.
     * Named for the similar function in Java.
     * 
     * @access  public
     * @return  int Current timestamp in milliseconds
     * @since   2.0
     */
    public static function currentTimeMillis()
    {
        return (int) (microtime(true) * 1000);
    }
}
