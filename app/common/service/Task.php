<?php

namespace app\common\service;

use support\Log;
use support\Redis;
use think\facade\Db;
use app\common\model\Project as ProjectModel;
use app\common\service\Gateway;

class Task
{
    /**
     * @var array
     */
    protected static $_webHookData = [];

    /**
     * @var \Workerman\Http\Client
     */
    protected static $_httpClient = null;

    /**
     * 更新推送统计
     *
     * @author HSK
     * @date 2022-08-15 17:17:44
     * 
     * @param string $day
     *
     * @return void
     */
    public static function updatePushData($day = null)
    {
        try {
            $day = $day ?? date('Ymd', time());

            $statistic = Db::name('record')
                ->where('day', $day)
                ->group('project, channel')
                ->field('project, channel, COUNT(id) AS push')
                ->select();
            foreach ($statistic as $item) {
                try {
                    if ($id = Db::name('statistics')->where(['day' => $day, 'project' => $item['project'], 'channel' => $item['channel']])->value('id')) {
                        Db::name('statistics')->where('id', $id)->update([
                            'push'        => $item['push'],
                            'update_time' => get_date(),
                        ]);
                    } else {
                        Db::name('statistics')->insert([
                            'day'         => $day,
                            'project'     => $item['project'],
                            'channel'     => $item['channel'],
                            'push'        => $item['push'],
                            'create_time' => get_date(),
                        ]);
                    }
                } catch (\Throwable $th) {
                    \Hsk99\WebmanException\RunException::report($th);
                }
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * 更新订阅统计
     *
     * @author HSK
     * @date 2022-08-15 17:17:30
     * 
     * @param string $day
     *
     * @return void
     */
    public static function updateSubscriptionData($day = null)
    {
        try {
            $day = $day ?? date('Ymd', time());

            foreach (Redis::hGetAll('PushService:statistic:project') as $project) {
                $project_id = str_replace('project-', '', $project);
                foreach (Redis::hGetAll('PushService:statistic:' . $project . ':channel') as $channel) {
                    try {
                        $subscription = (int)Redis::hGet('PushService:statistic:' . $project . ':subscription_count:' . $channel, $day);
                        if (0 === $subscription) {
                            continue;
                        }

                        if ($id = Db::name('statistics')->where(['day' => $day, 'project' => $project_id, 'channel' => $channel])->value('id')) {
                            Db::name('statistics')->where('id', $id)->update([
                                'subscription' => $subscription,
                                'update_time'  => get_date(),
                            ]);
                        } else {
                            Db::name('statistics')->insert([
                                'day'          => $day,
                                'project'      => $project_id,
                                'channel'      => $channel,
                                'subscription' => $subscription,
                                'create_time'  => get_date(),
                            ]);
                        }
                    } catch (\Throwable $th) {
                        \Hsk99\WebmanException\RunException::report($th);
                    }
                }
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * WebHook
     *
     * @author HSK
     * @date 2022-08-19 11:37:00
     *
     * @return void
     */
    public static function webHook()
    {
        try {
            foreach (Redis::hGetAll('PushService:online:client_count') as $project => $client_count) {
                $project_id = str_replace('project-', '', $project);

                $old_data = static::$_webHookData[$project_id] ?? [];
                $new_data = $push_data = [];

                // 组装应用订阅数据
                foreach (Redis::hGetAll('PushService:online:' . $project . ':subscription_count') as $channel => $subscription_count) {
                    $channel_type = get_channel_type($channel);

                    // 订阅channel存在在线Client
                    if ($subscription_count > 0) {
                        $new_data['channels'][$channel] = [
                            'type'    => $channel_type,
                            'channel' => $channel,
                        ];

                        // 订阅上线
                        if (!isset($old_data['channels'][$channel])) {
                            $push_data['channel_added'][] = $new_data['channels'][$channel];
                        }

                        // 获取用户信息
                        if ('presence' === $channel_type) {
                            $count = 100;
                            foreach (Gateway::getClientSessionsByGroup($project . '.' . $channel) as $item) {
                                if (isset($item['presence'][$channel])) {
                                    $user_id   = key($item['presence'][$channel]);
                                    $user_info = current($item['presence'][$channel]);

                                    $new_data['users'][$channel][] = [
                                        'channel'   => $channel,
                                        'user_id'   => $user_id,
                                        'user_info' => $user_info,
                                    ];

                                    if ($count-- <= 0) {
                                        break;
                                    }
                                }
                            }

                            // 用户的上线、下线
                            if (!isset($old_data['users'][$channel]) && isset($new_data['users'][$channel])) {
                                $push_data['user_added'][$channel] = $new_data['users'][$channel];
                            } else {
                                $user_added = array_diff_key($new_data['users'][$channel] ?? [], $old_data['users'][$channel] ?? []);
                                if (!empty($user_added)) {
                                    $push_data['user_added'][$channel] = $user_added;
                                }

                                $user_removed = array_diff_key($old_data['users'][$channel] ?? [], $new_data['users'][$channel] ?? []);
                                if (!empty($user_removed)) {
                                    $push_data['user_removed'][$channel] = $user_removed;
                                }
                            }
                        }
                    }
                    // 订阅channel不存在在线Client
                    else {
                        if (isset($old_data['channels'][$channel])) {
                            if ('presence' === $channel_type) {
                                // 用户下线
                                if (!empty($old_data['users'][$channel])) {
                                    $push_data['user_removed'][$channel] = $old_data['users'][$channel];
                                }
                            }

                            // 订阅下线
                            $push_data['channel_removed'][] = [
                                'type'    => $channel_type,
                                'channel' => $channel,
                            ];
                        }
                    }
                };

                // 执行推送
                if (!empty($push_data)) {
                    $project_info = ProjectModel::getPushInfo($project_id);
                    if (empty($project_info['web_hook'])) {
                        continue;
                    }

                    static::wehHookSend($push_data, $project_info);
                }

                // 应用订阅Client全部下线，清除数据
                if (0 === (int)$client_count) {
                    unset(static::$_webHookData[$project_id]);
                    return;
                }
                // 更新缓存数据
                else {
                    static::$_webHookData[$project_id] = $new_data;
                }
            }
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * WebHook 发送数据
     *
     * @author HSK
     * @date 2022-08-19 15:44:50
     *
     * @param array $push_data
     * @param array $project_info
     *
     * @return void
     */
    protected static function wehHookSend(array $push_data, array $project_info)
    {
        try {
            if (!isset(static::$_httpClient)) {
                $options = [
                    'max_conn_per_addr' => 128, // 每个地址最多维持多少并发连接
                    'keepalive_timeout' => 15,  // 连接多长时间不通讯就关闭
                    'connect_timeout'   => 30,  // 连接超时时间
                    'timeout'           => 30,  // 等待响应的超时时间
                ];
                static::$_httpClient = new \Workerman\Http\Client($options);
            }

            $data = [
                'time_ms' => microtime(true),
                'events'  => $push_data
            ];
            $data = json_encode($data, 320);

            static::$_httpClient->request(
                $project_info['web_hook'],
                [
                    'method' => 'POST',
                    'version' => '1.1',
                    'headers' => [
                        'Content-Type'       => 'application/json',
                        'X-Pusher-Signature' => hash_hmac('sha256', $data, $project_info['secret_key'], false),
                    ],
                    'data' => $data,
                    'success' => function ($response) use ($data, $project_info) {
                        Log::channel('push')->debug('WebHook success', [
                            'project'        => $project_info,
                            'request_param'  => $data,
                            'response_param' => $response->getBody(),
                        ]);
                    },
                    'error' => function ($exception) use ($data, $project_info) {
                        Log::channel('push')->debug('WebHook error', [
                            'project'       => $project_info,
                            'request_param' => $data,
                            'exception'     => (string)$exception,
                        ]);
                    }
                ]
            );
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            Log::channel('push')->debug('WebHook error', [
                'project'       => $project_info,
                'request_param' => $data,
                'exception'     => (string)$th,
            ]);
        }
    }
}
