<?php

namespace gittmy\queue;


use yii\base\Event;

/**
 * job 事件类
 * User: tmy
 * Date: 2017-1-18
 * Time: 15:32
 */

class JobEvent extends Event
{
    /**
     * @var Job
     */
    public $job;

    /**
     * @var string
     */
    public $payload;
}