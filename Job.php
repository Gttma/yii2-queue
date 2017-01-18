<?php

namespace gittmy\queue;

use SuperClosure\Serializer;

use yii\base\Component;

/**
 * 队列任务抽象基类，一个job类的实例代表一个队列里的任务
 * User: tmy
 * Date: 2017-1-18
 * Time: 13:21
 */
abstract class Job extends Component
{
    //事件
    const EVENT_BEFORE_EXECUTE = 'beforeExecute';
    
    const EVENT_BEFORE_DELETE = 'beforeDelete';

    /**
     * 任务所属队列的名称
     * @var string
     */
    protected $queue;

    /**
     * Queue实例
     * @var Queue
     */
    public $queueInstance;

    /**
     * job处理handler实例
     * @var
     */
    public $handler;

    /**
     * 任务数据
     * @var
     */
    public $job;


    /**
     * 任务是否删除标识
     * @var bool
     */
    protected $deleted = false;

    /**
     * 任务是否releas标识
     * @var bool
     */
    protected $released = false;

    /**
     * 获取任务已经尝试执行的次数
     * @return int
     */
    abstract public function getAttempts();

    /**
     * 获取任务的数据
     * @return string
     */
    abstract public function getPayload();

    /**
     * 检测任务是否被重新加入队列
     * @return bool
     */
    public function isReleased()
    {
        return $this->released;
    }

    /**
     * 检测任务是否被删除或者被release
     * @return bool
     */
    public function isDeletedOrReleased()
    {
        return $this->isDeleted() || $this->isReleased();
    }

    /**
     * 删除任务，子类需要实现具体的删除
     * @return void
     */
    public function delete()
    {
        $this->trigger(self::EVENT_BEFORE_DELETE, new JobEvent(["job" => $this, 'payload' => $this->getPayload()]));
        $this->deleted = true;
    }

    /**
     * 判断任务是否被删除
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deleted;
    }

    /**
     * 将任务重新加入队列
     * @param  int $delay
     * @return void
     */
    public function release($delay = 0)
    {
        $this->released = true;
    }

    /**
     * 执行任务
     * @return void
     */
    public function execute()
    {
        $this->trigger(self::EVENT_BEFORE_EXECUTE, new JobEvent(["job" => $this, 'payload' => $this->getPayload()]));
        $this->resolveAndFire();
    }

    /**
     * 真正任务执行方法（调用hander的handle方法）
     * @param  array $payload
     * @return void
     */
    protected function resolveAndFire()
    {
        $payload = $this->getPayload();
        $payload = unserialize($payload);
        $type = $payload['type'];
        $class = $payload['job'];

        if ($type == 'closure' && ($closure = (new Serializer())->unserialize($class[1])) instanceof \Closure) {
            $this->handler = $this->getHander($class[0]);
            $this->handler->closure = $closure;
            $this->handler->handle($this, $payload['data']);
        } else if ($type == 'classMethod') {
            $payload['job'][0]->$payload['job'][1]($this, $payload['data']);
        } else if ($type == 'staticMethod') {
            $payload['job'][0]::$payload['job'][1]($this, $payload['data']);
        } else {
            $this->handler = $this->getHander($class);
            $this->handler->handle($this, $payload['data']);
        }

        //执行完任务后删除
        if (!$this->isDeletedOrReleased()) {
            $this->delete();
        }
    }


    /**
     * 任务执行失败后的处理方法（调用handler的failed方法）
     * @return void
     */
    public function failed()
    {
        $payload = $this->getPayload();
        $payload = unserialize($payload);
        $type = $payload['type'];
        $class = $payload['job'];

        if ($type == 'closure' && ($closure = (new Serializer())->unserialize($class[1])) instanceof \Closure) {
            $this->handler = $this->getHander($class[0]);
        } else if ($type == 'classMethod') {
            $this->handler = $payload['job'][0];
        } else if ($type == 'staticMethod') {
            $this->handler = $this->getHander($payload['job'][0]);
        } else {
            $this->handler = $this->getHander($class);
        }

        //如果有自定义的failed方法，则调用
        if (method_exists($this->handler, 'failed')) {
            $this->handler->failed($this, $payload['data']);
        } //如果没有自定义的方法，则检测是否将错误写入数据库
        else {
            if ($this->queueInstance->failed['logFail'] === true) {
                $failedProvider = \Yii::createObject($this->queueInstance->failed['provider']);
                $failedProvider->log($this->queueInstance->className(), $this->getQueue(), $this->getPayload());
            }
        }
    }

    /**
     * 解析并还原payload数据
     * @deprecated
     * @param $paylod
     * @return array|mixed
     */
    protected function resolvePaylod($payload)
    {
        if (is_string($payload)) {
            return unserialize($payload);
        }

        if (is_array($payload)) {
            $ret = [];
            foreach ($payload as $k => $v) {
                $this->resolvePaylod($v);
            }
            return $ret;
        }

        return $payload;
    }

    /**
     * 获取任务处理handler实例
     */
    protected function getHander($class)
    {
        if (is_object($class) && $class instanceof JobHandler) {
            return $this->handler = $class;
        } else {
            return $this->handler = \Yii::$container->get($class);
        }
    }


    /**
     * 获取队列名称
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * 设置队列名称
     * @param $queue
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
    }

    /*
     * 属性设置
     */
    public function setJob($job)
    {
        $this->job = $job;
    }

    /**
     * 属性
     * @return mixed
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * 属性
     * @return mixed
     */
    public function getqueueInstance()
    {
        return $this->queueInstance;
    }

    /**
     * 属性
     * @param $queueInstance
     */
    public function setqueueInstance($queueInstance)
    {
        $this->queueInstance = $queueInstance;
    }
}