<?php

namespace gittmy\queue\helpers;

use gittmy\queue\JobHandler;

/**
 * 闭包函数辅助类
 * User: tmy
 * Date: 2017-1-17
 * Time: 10:56
 */
class QueueClosure extends JobHandler
{
    /**
     * @var \Closure
     */
    public $closure;

    /**
     * 回调列队处理类
     * @param   $job
     * @param  array $data
     * @return void
     * @throws \Exception
     */
    public function handle($job, $data)
    {
        if ($this->closure instanceof \Closure) {
            $closure = $this->closure;
            $closure($job, $data);
        } else {
            throw new \Exception("closure is wrong!");
        }
    }
}