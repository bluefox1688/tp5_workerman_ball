<?php

namespace app\push\controller;

use think\worker\Server;

class Worker extends Server
{
    protected $socket = 'websocket://0.0.0.0:2346';
    protected $processes = 1;

    public function onMessage($connection, $data)
    {
        $data = json_decode($data, true);
        $type = $data['type'];
        if (!$type) {
            return $connection->send(json_encode(['status' => 400, 'msg' => '参数错误']));
        }
        switch ($type) {
            case 'login':
                $username = $data['username'];
                $userlist = cache('userlist');
                if ($userlist) {

                    foreach ($userlist as $key => $value) {
                        if ($value['username'] == $username) {
                            return $connection->send(json_encode(['status' => 400, 'msg' => '该昵称已经被抢占了，换一个吧！']));
                        }
                    }
                }
                $userinfo = [
                    'username' => $username,
                    'x' => $data['x'],
                    'y' => $data['y'],
                ];
                $userlist[] = $userinfo;
                $connection->username = $username;
                cache('userlist', $userlist);
                foreach ($this->worker->connections as $conn) {
                    $conn->send(json_encode(['status' => 200, 'msg' => $userinfo]));
                }
                $connection->send(json_encode(['status' => 500, 'msg' => $userlist]));
                break;
            case 'move':
                $userlist = cache('userlist');
                foreach ($userlist as $key => $value) {
                    if ($value['username'] == $data['username']) {
                        $userlist[$key]['x'] = $data['x'];
                        $userlist[$key]['y'] = $data['y'];
                    }
                }
                cache('userlist', $userlist);
                $userinfo = [
                    'username' => $data['username'],
                    'x' => $data['x'],
                    'y' => $data['y']
                ];

                foreach ($this->worker->connections as $conn) {
                    $conn->send(json_encode(['status' => 300, 'msg' => $userinfo]));
                }
                break;
            case 'message':
                $msg = [
                    'username' => $data['username'],
                    'info' => $data['info'],
                ];
                foreach ($this->worker->connections as $conn) {
                    $conn->send(json_encode(['status' => 600, 'msg' => $msg]));
                }
                break;

        }
    }

    public function onConnect($connection)
    {

    }

    public function onClose($connection)
    {
        $userlist = cache('userlist');
        foreach ($userlist as $key => $value) {
            if ($value['username'] == $connection->username) {
                unset($userlist[$key]);
                break;
            }
        }
        cache('userlist', $userlist);
        foreach ($this->worker->connections as $conn) {
            $conn->send(json_encode(['status' => 500, 'msg' => $userlist]));
        }
    }


    /**
     * 当客户端的连接上发生错误时触发
     * @Author: 296720094@qq.com chenning
     * @param $connection
     * @param $code
     * @param $msg
     */
    public function onError($connection, $code, $msg)
    {
        echo "error $code $msg\n";
    }

    /**
     * 每个进程启动
     * @Author: 296720094@qq.com chenning
     * @param $worker
     */
    public function onWorkerStart($worker)
    {

    }

    public function onWorkerStop()
    {

    }
}
