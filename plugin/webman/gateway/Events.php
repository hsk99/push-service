<?php

namespace plugin\webman\gateway;

use GatewayWorker\Lib\Gateway;
use support\Redis;
use support\Log;
use app\common\model\Project as ProjectModel;

class Events
{
    /**
     * @author HSK
     * @date 2022-08-12 11:36:38
     *
     * @param \GatewayWorker\BusinessWorker $businessWorker
     *
     * @return void
     */
    public static function onWorkerStart(\GatewayWorker\BusinessWorker $businessWorker)
    {
        if (0 === $businessWorker->id) {
            \Workerman\Timer::add(3, function () {
                static::updateOnlineData();
            }, '', false);

            \Workerman\Timer::add(30, function () {
                static::updateOnlineData();
            });
        }
    }

    /**
     * @author HSK
     * @date 2022-08-12 11:36:46
     *
     * @param \GatewayWorker\BusinessWorker $businessWorker
     *
     * @return void
     */
    public static function onWorkerStop(\GatewayWorker\BusinessWorker $businessWorker)
    {
        try {
            // 清除数据
            array_map(function ($key) {
                $key = str_replace(config('redis.default.prefix'), '', $key);
                Redis::del($key);
            }, Redis::keys('PushService:online:*'));
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * @author HSK
     * @date 2022-08-10 15:45:41
     *
     * @param string $client_id
     *
     * @return void
     */
    public static function onConnect(string $client_id)
    {
        static::info('Client connection');

        Gateway::updateSession($client_id, ['SERVER' => $_SERVER]);
    }

    /**
     * @author HSK
     * @date 2022-08-10 16:14:23
     *
     * @param string $client_id
     *
     * @return void
     */
    public static function onClose(string $client_id)
    {
        static::info('Client disconnects');

        static::updateOnlineData($client_id);

        // 取消订阅
        try {
            foreach (($_SESSION['channels'] ?? []) as $channel => $uid) {
                if ($uid === '') {
                    static::unsubscribePublicChannel($client_id, $channel);
                } else {
                    static::unsubscribePresenceChannel($client_id, $channel);
                }
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * @author HSK
     * @date 2022-08-10 16:19:04
     *
     * @param string $client_id
     * @param array $data
     *
     * @return void
     */
    public static function onWebSocketConnect(string $client_id, array $data)
    {
        if (!preg_match('/\/app\/([^\/^\?^ ]+)/', $data['server']['REQUEST_URI'], $match)) {
            Gateway::closeClient($client_id);
            static::info('Client illegal connection');
            return;
        }
        static::connectAuth($client_id, $match[1]);
    }

    /**
     * @author HSK
     * @date 2022-08-10 16:40:08
     *
     * @param string $client_id
     * @param mixed $message
     *
     * @return void
     */
    public static function onMessage(string $client_id, $message)
    {
        static::debug('Client send', $message);

        $data = json_decode($message, true);
        if (!$data || !is_array($data)) {
            return;
        }

        $session = Gateway::getSession($client_id);

        if (
            !isset($session['access_key'])
            && (!isset($data['event']) || 'pusher:auth' !== $data['event'])
        ) {
            Gateway::closeClient($client_id);
            static::info('Connection not authenticated');
            return;
        }

        $event = $data['event'] ?? null;
        switch ($event) {
            case 'pusher:auth':
                // {"event":"pusher:auth","auth":"ecc1dcdecd380a38cadc74cd9d0fb9bf"}

                if (!isset($data['auth'])) {
                    Gateway::closeClient($client_id);
                    static::info('Client illegal connection');
                    return;
                }
                static::connectAuth($client_id, $data['auth']);
                return;
            case 'pusher:ping':
                static::sendToClient($client_id, '{"event":"pusher:pong","data":"{}"}');
                return;
            case 'pusher:subscribe':
                // {"event":"pusher:subscribe","data":{"channel":"my-channel"}}

                $channel = $data['data']['channel'];
                // private- 和 presence- 开头的channel需要验证
                switch (get_channel_type($channel)) {
                    case 'presence':
                        // {"event":"pusher:subscribe","data":{"auth":"b054014693241bcd9c26:10e3b628cb78e8bc4d1f44d47c9294551b446ae6ec10ef113d3d7e84e99763e6","channel_data":"{\"user_id\":100,\"user_info\":{\"name\":\"123\"}}","channel":"presence-channel"}}
                        $client_auth = $data['data']['auth'];
                        $auth = $session['access_key'] . ':' . hash_hmac('sha256', 'hsk99:' . $client_id . ':' . $channel . ':' . $data['data']['channel_data'], $session['secret_key'], false);
                        // {"event":"pusher:error","data":{"code":null,"message":"Received invalid JSON"}}
                        if ($client_auth !== $auth) {
                            static::sendToClient($client_id, static::error(null, 'Received invalid JSON ' . $auth));
                            return;
                        }

                        $user_data = json_decode($data['data']['channel_data'], true);
                        if (!$user_data || !isset($user_data['user_id']) || !isset($user_data['user_info'])) {
                            static::sendToClient($client_id, static::error(null, 'Bad channel_data'));
                            return;
                        }

                        static::subscribePresenceChannel($client_id, $channel, $user_data['user_id'], $user_data['user_info']);
                        return;
                    case 'private':
                        // {"event":"pusher:subscribe","data":{"auth":"b054014693241bcd9c26:10e3b628cb78e8bc4d1f44d47c9294551b446ae6ec10ef113d3d7e84e99763e6","channel":"private-channel"}}
                        $client_auth = $data['data']['auth'];
                        $auth = $session['access_key'] . ':' . hash_hmac('sha256', 'hsk99:' . $client_id . ':' . $channel . ':' . ($data['data']['channel_data'] ?? null), $session['secret_key'], false);
                        // {"event":"pusher:error","data":{"code":null,"message":"Received invalid JSON"}}
                        if ($client_auth !== $auth) {
                            static::sendToClient($client_id, static::error(null, 'Received invalid JSON ' . $auth));
                            return;
                        }

                        static::subscribePrivateChannel($client_id, $channel);
                        break;
                    default:
                        static::subscribePublicChannel($client_id, $channel);
                        break;
                }
                // {"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"my-channel"}
                static::sendToClient($client_id, json_encode(
                    [
                        'event'   => 'pusher_internal:subscription_succeeded',
                        'data'    => '{}',
                        'channel' => $channel
                    ],
                    JSON_UNESCAPED_UNICODE
                ));
                return;
            case 'pusher:unsubscribe':
                // {"event":"pusher:unsubscribe","data":{"channel":"my-channel"}}

                $channel = $data['data']['channel'];
                $channel_type = get_channel_type($channel);
                switch ($channel_type) {
                    case 'public':
                        static::unsubscribePublicChannel($client_id, $channel);
                        break;
                    case 'private':
                        static::unsubscribePrivateChannel($client_id, $channel);
                        break;
                    case 'presence':
                        static::unsubscribePresenceChannel($client_id, $channel);
                        break;
                }
                return;
            default:
                if (!isset($data['event'])) {
                    static::sendToClient($client_id, static::error(null, 'illegal data'));
                    return;
                }
                if (strpos($event, 'pusher:') === 0) {
                    static::sendToClient($client_id, static::error(null, 'Unknown event'));
                    return;
                }
                if (!isset($data['channel'])) {
                    static::sendToClient($client_id, static::error(null, 'Data exception'));
                    return;
                }
                $channel = $data['channel'];
                // 客户端触发事件必须是private 或者 presence的channel
                $channel_type = get_channel_type($channel);
                if ($channel_type !== 'private' && $channel_type !== 'presence') {
                    // {"event":"pusher:error","data":{"code":null,"message":"Client event rejected - only supported on private and presence channels"}}
                    static::sendToClient($client_id, static::error(null, 'Client event rejected - only supported on private and presence channels'));
                    return;
                }
                // 当前链接没有订阅这个channel
                if (!isset($session['channels'][$channel])) {
                    static::sendToClient($client_id, static::error(null, 'Client event rejected - you didn\'t subscribe this channel'));
                    return;
                }
                // 事件必须以client-为前缀
                if (strpos($event, 'client-') !== 0) {
                    static::sendToClient($client_id, static::error(null, 'Client event rejected - client events must be prefixed by \'client-\''));
                    return;
                }

                // 全局发布事件
                static::publishToClients($session['project'], $channel, $event, json_encode($data['data'], JSON_UNESCAPED_UNICODE), $client_id);
        }
    }

    /**
     * 连接认证
     *
     * @author HSK
     * @date 2022-08-23 11:53:09
     *
     * @param string $client_id
     * @param string $auth
     *
     * @return void
     */
    protected static function connectAuth(string $client_id, string $auth)
    {
        // 校验连接
        if (!$projectInfo = ProjectModel::getInfoAccessKey($auth)) {
            Gateway::closeClient($client_id);
            static::info('Connection authentication parameter error');
            return;
        }
        if (2 === $projectInfo['status']) {
            Gateway::closeClient($client_id);
            static::info('app disabled');
            return;
        }
        static::info('Connection authentication succeeded');

        // 记录连接认证信息
        Gateway::updateSession($client_id, [
            'project'    => 'project-' . $projectInfo['id'],
            'access_key' => $projectInfo['access_key'],
            'secret_key' => $projectInfo['secret_key'],
            'channels'   => [],
            'presence'   => []
        ]);

        // 加入所属应用全局分组
        Gateway::joinGroup($client_id, 'project-' . $projectInfo['id']);

        /*
         * 向客户端发送链接成功的消息
         * {"event":"pusher:connection_established","data":"{\"socket_id\":\"7f000001226a00000018\",\"activity_timeout\":120}"}
         */
        static::sendToClient($client_id, json_encode([
            'event' => 'pusher:connection_established',
            'data'  => json_encode([
                'socket_id'        => $client_id,
                'activity_timeout' => 35
            ])
        ], JSON_UNESCAPED_UNICODE));

        static::updateOnlineData($client_id);
    }

    /**
     * 组装错误信息
     *
     * @author HSK
     * @date 2022-08-10 17:16:37
     *
     * @param mixed $code
     * @param string $message
     *
     * @return string
     */
    protected static function error($code, string $message): string
    {
        return json_encode(['event' => 'pusher:error', 'data' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 客户端订阅 Public channels
     *
     * @author HSK
     * @date 2022-08-11 17:12:36
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function subscribePublicChannel(string $client_id, string $channel)
    {
        $session = Gateway::getSession($client_id);

        // 加入应用订阅分组
        Gateway::joinGroup($client_id, $session['project'] . '.' . $channel);

        // 更新Client订阅记录
        Gateway::updateSession($client_id, ['channels' => $session['channels'] + [$channel => '']]);

        static::updateOnlineData($client_id, $channel);
        static::updateStatistics($client_id, $channel);
    }

    /**
     * 客户端订阅 Private channels
     *
     * @author HSK
     * @date 2022-08-11 17:13:19
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function subscribePrivateChannel(string $client_id, string $channel)
    {
        static::subscribePublicChannel($client_id, $channel);
    }

    /**
     * 客户端订阅 Presence channels
     *
     * @author HSK
     * @date 2022-08-17 17:25:31
     *
     * @param string $client_id
     * @param string $channel
     * @param string $uid
     * @param mixed $user_info
     *
     * @return void
     */
    protected static function subscribePresenceChannel(string $client_id, string $channel, string $uid, $user_info)
    {
        $session = Gateway::getSession($client_id);

        // 加入应用订阅分组
        Gateway::joinGroup($client_id, $session['project'] . '.' . $channel);

        // 加入应用订阅成员分组
        Gateway::joinGroup($client_id, 'presence.' . $session['project'] . '.' . $channel . '@' . $uid);

        // 更新Client订阅记录
        Gateway::updateSession($client_id, ['channels' => $session['channels'] + [$channel => $uid]]);

        // 向应用订阅推送新成员上线信息
        if (1 === Gateway::getClientIdCountByGroup('presence.' . $session['project'] . '.' . $channel . '@' . $uid)) {
            Gateway::updateSession($client_id, ['presence' => $session['presence'] + [$channel => [$uid => $user_info]]]);

            // {"event":"pusher_internal:member_added","data":"{\"user_id\":1488465780,\"user_info\":{\"name\":\"123\",\"sex\":\"1\"}}","channel":"presence-channel"}
            static::publishToClients(
                $session['project'],
                $channel,
                'pusher_internal:member_added',
                json_encode([
                    'user_id'   => $uid,
                    'user_info' => $user_info
                ], JSON_UNESCAPED_UNICODE),
                $client_id
            );
        }

        // 向当前订阅推送成员列表
        $hash = [];
        $count = 100;
        foreach (Gateway::getClientSessionsByGroup($session['project'] . '.' . $channel) as $item) {
            if (isset($item['presence'][$channel])) {
                $hash[] = $item['presence'][$channel];
                if ($count-- <= 0) {
                    break;
                }
            }
        }
        // {"event":"pusher_internal:subscription_succeeded","data":"{\"presence\":{\"count\":2,\"ids\":[\"1488465780\",\"14884657802\"],\"hash\":{\"1488465780\":{\"name\":\"123\",\"sex\":\"1\"},\"14884657802\":{\"name\":\"123\",\"sex\":\"1\"}}}}","channel":"presence-channel"}
        static::sendToClient($client_id, json_encode([
            'event'   => 'pusher_internal:subscription_succeeded',
            'channel' => $channel,
            'data'    => json_encode([
                'presence' => [
                    'count' => count($hash),
                    'ids'   => array_keys($hash),
                    'hash'  => $hash
                ]
            ])
        ], JSON_UNESCAPED_UNICODE));

        static::updateOnlineData($client_id, $channel);
        static::updateStatistics($client_id, $channel);
    }

    /**
     * 客户端取消订阅 Public channels
     *
     * @author HSK
     * @date 2022-08-11 17:13:33
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function unsubscribePublicChannel(string $client_id, string $channel)
    {
        $session = Gateway::getSession($client_id) ?? $_SESSION;

        // 退出应用订阅分组
        Gateway::leaveGroup($client_id, $session['project'] . '.' . $channel);

        // 更新Client订阅记录
        unset($session['channels'][$channel]);
        Gateway::updateSession($client_id, ['channels' => $session['channels']]);

        static::updateOnlineData($client_id, $channel);
    }

    /**
     * 客户端取消订阅 Private channels
     *
     * @author HSK
     * @date 2022-08-11 17:14:01
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function unsubscribePrivateChannel(string $client_id, string $channel)
    {
        static::unsubscribePublicChannel($client_id, $channel);
    }

    /**
     * 客户端取消订阅 Presence channels
     *
     * @author HSK
     * @date 2022-08-17 17:25:52
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function unsubscribePresenceChannel(string $client_id, string $channel)
    {
        $session = Gateway::getSession($client_id) ?? $_SESSION;
        $uid     = $session['channels'][$channel];

        // 退出应用订阅分组
        Gateway::leaveGroup($client_id, $session['project'] . '.' . $channel);

        // 退出应用订阅成员分组
        Gateway::leaveGroup($client_id, 'presence.' . $session['project'] . '.' . $channel . '@' . $uid);

        // 向应用订阅推送成员退出信息
        if (0 === Gateway::getClientIdCountByGroup('presence.' . $session['project'] . '.' . $channel . '@' . $uid)) {
            // {"event":"pusher_internal:member_removed","data":"{\"user_id\":\"14884657801\"}","channel":"presence-channel"}
            static::publishToClients(
                $session['project'],
                $channel,
                'pusher_internal:member_removed',
                json_encode([
                    'user_id' => $uid
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        // 更新Client订阅记录
        unset($session['channels'][$channel]);
        unset($session['presence'][$channel]);
        Gateway::updateSession($client_id, ['channels' => $session['channels'], 'presence' => $session['presence']]);

        static::updateOnlineData($client_id, $channel);
    }

    /**
     * 发布事件
     *
     * @author HSK
     * @date 2022-08-11 17:15:31
     *
     * @param string $project
     * @param string $channel
     * @param string $event
     * @param string $data
     * @param string|null $socket_id
     *
     * @return void
     */
    protected static function publishToClients(string $project, string $channel, string $event, string $data, string $socket_id = null)
    {
        if (0 === Gateway::getClientIdCountByGroup($project . '.' . $channel)) {
            return;
        }

        $group   = $project . '.' . $channel;
        $message = json_encode([
            'event'   => $event,
            'channel' => $channel,
            'data'    => $data
        ], JSON_UNESCAPED_UNICODE);

        Gateway::sendToGroup($group, $message, $socket_id);

        static::debug('Server push', $message);

        try {
            // 在线订阅数
            $subscription_count = (int)Redis::hGet('PushService:online:' . $project . ':subscription_count', $channel);
            --$subscription_count;
            // 记录推送详细信息
            Redis::lPush('RecordCache', json_encode([
                'day'          => date('Ymd', time()),
                'project'      => str_replace('project-', '', $project),
                'channel'      => $channel,
                'event'        => $event,
                'data'         => $data,
                'subscription' => $subscription_count < 0 ? 0 : $subscription_count,
                'create_time'  => get_date(),
            ], 320));
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * 向client发送数据
     *
     * @author HSK
     * @date 2022-08-10 17:22:42
     *
     * @param string $client_id
     * @param string $message
     * @param boolean $raw
     *
     * @return boolean
     */
    protected static function sendToClient(string $client_id, string $message, bool $raw = false): bool
    {
        static::debug('Server push', $message, $client_id);

        return Gateway::sendToClient($client_id, $message, $raw);
    }

    /**
     * 更新在线数据
     *
     * @author HSK
     * @date 2022-08-12 10:59:03
     *
     * @param string $client_id
     * @param string|null $channel
     *
     * @return void
     */
    protected static function updateOnlineData(string $client_id = null, string $channel = null)
    {
        try {
            $data = [];
            if (!isset($client_id) && !isset($channel)) {
                foreach (Redis::hGetAll('PushService:statistic:project') as $project) {
                    $data[$project] = Redis::hGetAll('PushService:statistic:' . $project . ':channel');
                }
            } else {
                $session = Gateway::getSession($client_id) ?? $_SESSION;

                if (isset($session['project'])) {
                    $data[$session['project']] = !is_null($channel) ? [$channel => ''] : $session['channels'];
                }
            }

            foreach ($data as $project => $channels) {
                // 更新应用在线数量
                Redis::hSet('PushService:online:client_count', $project, Gateway::getClientIdCountByGroup($project));

                // 更新应用订阅channel以及在线数量
                foreach ($channels as $channel => $value) {
                    Redis::hSet('PushService:online:' . $project . ':subscription_count', $channel, Gateway::getClientIdCountByGroup($project . '.' . $channel));
                }
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * 更新统计数据
     *
     * @author HSK
     * @date 2022-08-12 14:27:21
     *
     * @param string $client_id
     * @param string $channel
     *
     * @return void
     */
    protected static function updateStatistics(string $client_id, string $channel)
    {
        try {
            $session = Gateway::getSession($client_id);
            $day     = date('Ymd', time());

            // 应用
            Redis::hSetNx('PushService:statistic:project', $session['project'], $session['project']);
            // 应用订阅channel
            Redis::hSetNx('PushService:statistic:' . $session['project'] . ':channel', $channel, $channel);
            // 应用订阅channel次数
            Redis::hIncrBy('PushService:statistic:' . $session['project'] . ':subscription_count:' . $channel, $day, 1);
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * log info
     *
     * @author HSK
     * @date 2022-08-10 16:35:36
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    protected static function info(string $message, array $context = [])
    {
        $logContext = [
            'client_id'   => $_SERVER['GATEWAY_CLIENT_ID'],
            'client_ip'   => $_SERVER['REMOTE_ADDR'],
            'client_port' => $_SERVER['REMOTE_PORT'],
        ] + $context;

        Log::channel('push')->info($message, $logContext);
    }

    /**
     * log debug
     *
     * @author HSK
     * @date 2022-08-10 16:45:33
     *
     * @param string $message
     * @param string $context
     * @param string|null $client_id
     *
     * @return void
     */
    protected static function debug(string $message, string $context, string $client_id = null)
    {
        if (
            '{"event":"pusher:ping","data":{}}' === $context ||
            '{"event":"pusher:pong","data":"{}"}' === $context
        ) {
            return;
        }

        $session = Gateway::getSession($_SERVER['GATEWAY_CLIENT_ID']) ?? $_SESSION;

        $logContext = [
            'client'  => [
                'client_id'   => $_SERVER['GATEWAY_CLIENT_ID'],
                'client_ip'   => $_SERVER['REMOTE_ADDR'],
                'client_port' => $_SERVER['REMOTE_PORT'],
            ],
            'project' => [
                'project'    => $session['project'] ?? '',
                'access_key' => $session['access_key'] ?? '',
                'secret_key' => $session['secret_key'] ?? '',
            ],
            'message' => $context,
        ];
        if (empty($client_id)) {
            unset($logContext['client']);
        }
        if (empty($session['access_key'])) {
            unset($logContext['project']);
        }

        Log::channel('push')->debug($message, $logContext);
    }
}
