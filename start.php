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
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Connection\TcpConnection;

// #### 一个web界面的vmstat工具 ####

// 自动加载类
require_once __DIR__ . '/vendor/autoload.php';

$worker = new Worker('Websocket://0.0.0.0:7777');
$worker->name = 'VMStatWorker';
// 进程启动时，开启一个vmstat进程，并广播vmstat进程的输出给所有浏览器客户端
$worker->onWorkerStart = function($worker)
{
    // 把进程句柄存储起来，在进程关闭的时候关闭句柄
    $worker->process_handle = popen('vmstat 1 -n', 'r');
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
$web = new Worker("http://0.0.0.0:55555");
// WebServer数量
$web->count = 2;

$web->name = 'web';

define('WEBROOT', __DIR__ . '/Web');

$web->onMessage = function (TcpConnection $connection, Request $request) {
    $path = $request->path();
    if ($path === '/') {
        $connection->send(exec_php_file(WEBROOT.'/index.php'));
        return;
    }
    $file = realpath(WEBROOT. $path);
    if (false === $file) {
        $connection->send(new Response(404, array(), '<h3>404 Not Found</h3>'));
        return;
    }
    // Security check! Very important!!!
    if (strpos($file, WEBROOT) !== 0) {
        $connection->send(new Response(400));
        return;
    }
    if (\pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $connection->send(exec_php_file($file));
        return;
    }

    $if_modified_since = $request->header('if-modified-since');
    if (!empty($if_modified_since)) {
        // Check 304.
        $info = \stat($file);
        $modified_time = $info ? \date('D, d M Y H:i:s', $info['mtime']) . ' ' . \date_default_timezone_get() : '';
        if ($modified_time === $if_modified_since) {
            $connection->send(new Response(304));
            return;
        }
    }
    $connection->send((new Response())->withFile($file));
};

function exec_php_file($file) {
    \ob_start();
    // Try to include php file.
    try {
        include $file;
    } catch (\Exception $e) {
        echo $e;
    }
    return \ob_get_clean();
}


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}

