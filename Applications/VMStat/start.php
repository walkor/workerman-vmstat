<?php 
use \Workerman\Worker;
use \Workerman\WebServer;
use \Workerman\Connection\TcpConnection;

// #### 一个web界面的vmstat工具 ####

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';

$worker = new Worker('Websocket://0.0.0.0:7777');
$worker->name = 'VMStatWorker';
// 进程启动时，开启一个vmstat进程，并广播vmstat进程的输出给所有浏览器客户端
$worker->onWorkerStart = function($worker)
{
    $process_handle = popen('vmstat 1', 'r');
    if($process_handle)
    {
        $process_connection = new TcpConnection($process_handle);
        $process_connection->onMessage = function($process_connection, $data)use($worker)
        {
            foreach($worker->connections as $connection)
            {
                $connection->send($data);
            }
        };
    }
};
// 浏览器发来消息时什么也不做
$worker->onMessage = function($connection, $data)
{
    // 浏览器发来消息什么也不做
};

// WebServer，用来给浏览器吐html js css
$web = new WebServer("http://0.0.0.0:55555");
// WebServer数量
$web->count = 2;
// 设置站点根目录
$web->addRoot('www.your_domain.com', __DIR__.'/Web');


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

