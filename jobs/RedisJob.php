<?php

namespace gittmy\queue\jobs;

use gittmy\queue\Job;
use gittmy\queue\helpers\ArrayHelper;

/**
 * redis 列队任务处理类
 * User: tmy
 * Date: 2017-1-18
 * Time: 17:14
 */
class RedisJob extends Job
{

    public function getAttempts()
    {
        return ArrayHelper::get(unserialize($this->job), 'attempts');
    }

    public function getPayload()
    {
        return $this->job;
    }

    /**
     * Get the job identifier.
     *
     * @return string
     */
    public function getJobId()
    {
        return ArrayHelper::get(unserialize($this->job), 'id');
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete()
    {
        parent::delete();
        $this->queueInstance->deleteReserved($this->queue, $this->job);
    }

    /**
     * 释放工作回到队列中
     *
     * @param  int $delay
     * @return void
     */
    public function release($delay = 0)
    {
        parent::release($delay);
        $this->delete();
        $this->queueInstance->release($this->queue, $this->job, $delay, $this->getAttempts() + 1);
    }
}