<?php

namespace gittmy\queue\drives;

use gittmy\queue\helpers\ArrayHelper;
use gittmy\queue\Queue;
use yii\redis\Connection;

/**
 * redis 列队驱动器
 * User: tmy
 * Date: 2017/1/17
 * Time: 17:12
 */
class RedisQueue extends Queue
{
    public $connector;

    public $hostname;
    public $port;
    public $database;
    public $password;

    public function init()
    {
        parent::init();

        $redis = \Yii::createObject([
            'class' => Connection::className(),
            'hostname' => $this->hostname,
            'port' => $this->port,
            'database' => $this->database,
            'password' => $this->password,
        ]);
        if (!$redis instanceof Connection) {
            throw new \Exception('确保yii-redis扩展正确安装');
        }

        $this->connector = $redis;

    }

    /**
     * 入队列
     * @param $job
     * @param string $data
     * @param null $queue
     * @return int
     */
    protected function push($job, $data = '', $queue = null)
    {
        return $this->connector->rpush($this->getQueue($queue), $this->createPayload($job, $data, $queue));
    }

    /**
     * 延时任务入队列
     * @param $dealy
     * @param $job
     * @param string $data
     * @param null $queue
     * @return int
     */
    protected function later($dealy, $job, $data = '', $queue = null)
    {
        return $this->connector->zadd($this->getQueue($queue) . ':delayed', time() + $dealy, $this->createPayload($job, $data, $queue));
    }
    /**
     * 出队列
     * @param null $queue
     * @return object
     * @throws \yii\base\InvalidConfigException
     */
    public function pop($queue = null)
    {
        $original = $queue ?: $this->queue;
        $queue = $this->getQueue($queue);
        if (!is_null($this->expire)) {
            $this->migrateAllExpiredJobs($queue);
        }
        $job = $this->connector->lpop($queue);
        if (!is_null($job)) {
            $this->connector->zadd($queue . ':reserved', time() + $this->expire, $job);
            $config = array_merge($this->jobEvent, [
                'class' => 'gittmy\queue\jobs\RedisJob',
                'queue' => $original,
                'job' => $job,
                'queueInstance' => $this,
            ]);
            return \Yii::createObject($config);
        }
        return false;
    }
    /**
     * 获取队列当前任务数 = 执行队列任务数 + 等待队列任务数
     * @param null $queue
     * @return mixed
     */
    public function getJobCount($queue = null)
    {
        $queue = $this->getQueue($queue);
        return $this->connector->llen($queue) + $this->connector->zcard($queue . ":delayed");
    }
    /**
     * 将任务重新加入队列中
     * 此时，任务的尝试次数要加1
     * @param  string $queue
     * @param  string $payload
     * @param  int $delay
     * @param  int $attempts
     * @return void
     */
    public function release($queue, $payload, $delay, $attempts = 0)
    {
        $payload = $this->setMeta($payload, 'attempts', $attempts);
        $this->connector->zadd($this->getQueue($queue) . ':delayed', time() + $delay, $payload);
    }
    /**
     * 给队列数据添加id和attempts字段
     * @param  string $job
     * @param  mixed $data
     * @param  string $queue
     * @return string
     */
    protected function createPayload($job, $data = '', $queue = null)
    {
        $payload = parent::createPayload($job, $data);
        $payload = $this->setMeta($payload, 'id', $this->getRandomId());
        return $this->setMeta($payload, 'attempts', 1);
    }
    /**
     * 创建一个随机串作为id
     * @param int $length
     * @return string
     */
    protected function getRandomId()
    {
        $string = md5(time() . rand(1000, 9999));
        return $string;
    }
    /**
     * 获取队列名称（即redis里面的key）
     * @param  string|null $queue
     * @return string
     */
    protected function getQueue($queue)
    {
        return 'queues:' . ($queue ?: $this->queue);
    }

    /**
     * 当延时任务到大执行时间时，将延时任务从延时任务集合中移动到主执行队列中
     * @param  string $from
     * @param  string $to
     * @return void
     */
    public function migrateExpiredJobs($from, $to)
    {
        $jobs = $this->getExpiredJobs(
            $this->connector, $from, $time = time()
        );
        if (count($jobs) > 0) {
            //开始redis事物
            $this->connector->watch($from);
            $this->connector->multi();

            $this->removeExpiredJobs( $this->connector, $from, $time);
            $this->pushExpiredJobsOntoNewQueue( $this->connector, $to, $jobs);

            $this->connector->exec();
        }
    }

    /**
     * 从已处理集合中删除一个任务
     * @param  string $queue
     * @param  string $job
     * @return void
     */
    public function deleteReserved($queue, $job)
    {
        $this->connector->zrem($this->getQueue($queue) . ':reserved', $job);
    }
    /**
     * 从指定队列删除过期任务
     * @param  $transaction
     * @param  string $from
     * @param  int $time
     * @return void
     */
    protected function removeExpiredJobs($transaction, $from, $time)
    {
        $transaction->zremrangebyscore($from, '-inf', $time);
    }
    /**
     * 将任务从一个队列移动到另一个队列
     * @param  Connection $transaction
     * @param  string $to
     * @param  array $jobs
     * @return void
     */
    protected function pushExpiredJobsOntoNewQueue($transaction, $to, $jobs)
    {
        call_user_func_array([$transaction, 'rpush'], array_merge([$to], $jobs));
    }
    /**
     * 合并等待执行和已经处理的任务
     * @param  string $queue
     * @return void
     */
    protected function migrateAllExpiredJobs($queue)
    {
        $this->migrateExpiredJobs($queue . ':delayed', $queue);
        $this->migrateExpiredJobs($queue . ':reserved', $queue);
    }
    /**
     * 在输入数据中添加新的字段
     * @param  string $payload
     * @param  string $key
     * @param  string $value
     * @return string
     */
    protected function setMeta($payload, $key, $value)
    {
        $payload = unserialize($payload);
        $newPayload = serialize(ArrayHelper::set($payload, $key, $value));
        return $newPayload;
    }

    /**
     * 从指定队列中获取所有超时的任务
     * @param  \Predis\Transaction\MultiExec $transaction
     * @param  string $from
     * @param  int $time
     * @return array
     */
    protected function getExpiredJobs($transaction, $from, $time)
    {
        return $transaction->zrangebyscore($from, '-inf', $time);
    }

    /**
     * 清空指定队列
     * @param null $queue
     * @return integer
     * @throws \Exception execution failed
     */
    public function flush($queue = null)
    {
        $queue = $this->getQueue($queue);
        return $this->connector->del([$queue, $queue . ":delayed", $queue . ":reserved"]);
    }
}