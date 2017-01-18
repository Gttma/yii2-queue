<?php

namespace gittmy\queue;
use gittmy\queue\drives\RedisQueue;

/**
 * 队列监听进程类,用户后台启动监听队列
 * User: tmy
 * Date: 2017/1/18
 * Time: 10:08
 */
class Worker
{
    /**
     * 启用一个队列后台监听任务
     * @param RedisQueue $queue
     * @param string $queueName 监听队列的名称(在pushon的时候把任务推送到哪个队列，则需要监听相应的队列才能获取任务)
     * @param int $attempt 队列任务失败尝试次数，0为不限制
     * @param int $memory 允许使用的最大内存
     * @param int $sleep 每次检测的时间间隔
     */
    public static function listen(Queue $queue, $queueName = 'default', $attempt = 10, $memory = 512, $sleep = 3, $delay = 0){
        while (true){
//            try{
                $job = $queue->pop($queueName);
//            }catch (\Exception $e){
//                continue;
//            }
            if($job instanceof Job){
//                echo $queue->getJobCount($queueName)."\r\n";
//                echo $job->getAttempts()."\r\n";
                if($attempt > 0 && $job->getAttempts() > $attempt){
                    $job->failed();
                }else{
//                    try{
                        $job->execute();
//                    }catch (\Exception $e){
                        if (! $job->isDeleted()) {
                            $job->release($delay);
//                        }
                    }
                }
            }else{
                self::sleep($sleep);
            }


            if (self::memoryExceeded($memory)) {
                self::stop();
            }
        }
    }

    /**
     * 判断内存使用是否超出
     * @param  int   $memoryLimit
     * @return bool
     */
    public static function memoryExceeded($memoryLimit)
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * 停止队列监听
     */
    public static function stop(){
        die;
    }

    /**
     * 休眠
     */
    public static function sleep($seconds){
        sleep($seconds);
        echo "sleep\r\n";
    }
}