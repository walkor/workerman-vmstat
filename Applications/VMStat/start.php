<?php 
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */
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
    // 把进程句柄存储起来，在进程关闭的时候关闭句柄
    $worker->process_handle = popen('vmstat 1', 'r');
    if($worker->process_handle)
    {
        $process_connection = new TcpConnection($worker->process_handle);
        $process_connection->onMessage = function($process_connection, $data)use($worker)
        {
            foreach($worker->connections as $connection)
            {
                $connection->send($data);
            }
        };
    }
    else
    {
       echo "vmstat 1 fail\n";
    }
};

// 进程关闭时
$worker->onWorkerStop = function($worker)
{
    @shell_exec('killall vmstat');
    @pclose($worker->process_handle);
};

$worker->onConnect = function($connection)
{
    $connection->send("procs -----------memory---------- ---swap-- -----io---- -system-- ----cpu----\n");
    $connection->send("r  b   swpd   free   buff  cache   si   so    bi    bo   in   cs us sy id wa\n");
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

