<?php

namespace gittmy\queue;

/**
 * job 事件 handler
 * User: tmy
 * Date: 2017-1-18
 * Time: 15:39
 */
class JobEventHandler
{
    public static function beforeExecute(JobEvent $event)
    {
        echo "beforeExecute\r\n";
    }

    public static function beforeDelete(JobEvent $event)
    {
        echo "beforeDelete\r\n";
    }
}