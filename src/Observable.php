<?php

namespace Noair;

interface Observable
{
    /**
     * @return  array    The results of any pending events
     */
    public function subscribe(array $eventHandlers);

    /**
     * @return  mixed   Result of the event
     */
    public function publish(Event $event, $priority);

    /**
     * @return  self  This object
     */
    public function unsubscribe($eventName, $callback);
}
