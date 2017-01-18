yii2-queue
==========
a yii2 extension to make simple to use queue.

yii2-queue 基于shmilyzxt/yii2-queue修改，依赖yii-redis开发

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist gittmy/yii2-queue "dev-master"
```

or add

```
"gittmy/yii2-queue": "dev-master"
```

to the require section of your `composer.json` file.


Usage
-----

1:在配置文件中配置好需要使用的队列，完整的配置代码如下：

redis队列
```php
'queue' => [
            'class' => 'gittmy\queue\drives\RedisQueue',
            'hostname' => '127.0.0.1',
            'port' => 6379,
            'database' => 2,
//            'password' => '',
        ],
```

2：在components数组配置项中配置好队列后，就可以开始使用队列了，首先是任务入队列，提供两个方法：
\Yii::$app->queue->pushOn($hander,$data,$queue='default')
即时任务入队列：这样的任务入队列后，如果队列监听在运行，那么任务会立刻进入ready状态，可以被监听进程执行。
该方法有3个参数，第一个为任务处理handler，第二个为任务数据，第三个为队列名称，默认为 default。
\Yii::$app->queue->laterOn($delay,$handler,$data,$queue='default')
延时任务入队列：这样的任务入队列后不会立刻被队列监听进程之行，需要等待 $delay秒后任务才就绪。

目前支持的handler有：
 1，新建自己的队列处理handler，继承、gittmy\queue\JobHandler,并实现任务处理方法handle()和失败处理方法failed()。
 2, 一个php闭包，形如 function($job,$data){}

```php
\Yii::$app->queue->pushOn(new SendMial(),['email'=>'4006690@qq.com','title'=>'test','content'=>'email test'],'email');
\Yii::$app->queue->pushOn(function($job,$data){var_dump($data)},['email'=>'4006690@qq.com','title'=>'test','content'=>'email test'],'email');

\Yii::$app->queue->laterOn(120,new SendMial(),['email'=>'4006690@qq.com','title'=>'test','content'=>'email test'],'email');
\Yii::$app->queue->pushOn(120,function($job,$data){var_dump($data)},['email'=>'4006690@qq.com','title'=>'test','content'=>'email test'],'email');
```

3:新建自己的队列处理handler，继承gittmy\queue\JobHandler,并实现任务处理方法handle和失败处理方法failed，一个发邮件的jobhandler类似：

```php
class SendMail extends JobHandler
{

    public function handle($job,$data)
    {
        if($job->getAttempts() > 3){
            $this->failed($job);
        }

        $payload = $job->getPayload();

        //$payload即任务的数据，你拿到任务数据后就可以执行发邮件了
        //TODO 发邮件
    }

    public function failed($job,$data)
    {
        die("发了3次都失败了，算了");
    }
}
```

4：启动后台队列监听进程，对任务进行处理，您可以使用yii console app来启动,你也可以使用更高级的如swoole来高效的运行队列监听，
目前提供了一个Worker类，在控制台程序使用Worker::listen(Queue $queue,$queueName='default',$attempt=10,$memory=512,$sleep=3,$delay=0)可以
启动队列监听进程，其中  $attempt是任务尝试次数，$memory是允许使用最大内存，$sleep表示每次尝试从队列中获取任务的间隔时间，$delay代表把任务重新加入队列
时是否延时（0代表不延时），一个标准yii console app 启动队列监听进程代码如下;

```php
class WorkerController extends \yii\console\Controller
{
    public function actionListen($queueName='default',$attempt=10,$memeory=128,$sleep=3 ,$delay=0){
        Worker::listen(\Yii::$app->queue,$queueName,$attempt,$memeory,$sleep,$delay);
    }
}
//需要在控制台也配置上队列组件配置方法跟第一步的配置相同
yii worker/listen default 10 128 3 0
```

当后台监听任务启动起来后，一但有任务加入队列，队列就会调用入队列时设置的handler对队列任务进行处理了。每次会pop出一个任务进行处理，处理完成后删除任务，直到队列为空。

5：关于任务失败处理：
默认情况下，一个任务在执行时出现异常或者一个任务失败时并不是认为它真正失败了，此时会检测它的尝试次数是否已经超出设置的attempt，如果没超出会重新入队列尝试，如果超出了，
则该任务才是真正失败，这是会先调用任务处理handler类的failed()方法处理失败操作,如果没有failed()方法（比如handler为闭包或者您自定义的继承自gittmy\queue\JobHandler
的类没有写failed()方法），则会尝试使用扩展自身的失败日志处理机制（配置项里的failed配置），会尝试把失败任务的详细信息写入到数据库表中（目前只支持数据库方式）。
建议您采用继承gittmy\queue\JobHandler的方式生成任务处理handler并写自己的failed方法处理失败任务。


6：任务事件支持:
目前任务支持2个事件（beforeExecute,beforeDelete）, beforeExecute是在任务被pop出来之后，执行之前执行。beforeDelete是任务在被删除之前执行
您可以使用这两个事件做自定易操作，只需要像上面配置文件里配置 jobEvent那样绑定事件处理handler即可 1。
