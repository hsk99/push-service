<?php

namespace app\api\controller;

use app\common\service\Gateway;
use support\Redis;

class Channel
{
    /**
     * 订阅发布
     *
     * @author HSK
     * @date 2022-08-12 15:46:44
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function publish(\support\Request $request)
    {
        try {
            if (empty(request()->input('channel')) || empty(request()->input('event'))) {
                return api([], 400, 'Parameter error');
            }

            // 应用信息
            $projectInfo = request()->projectInfo;

            // 订阅发布
            $subscription_count = Gateway::publish(request()->all(), $projectInfo);

            return api([
                'subscription_count' => $subscription_count
            ]);
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return api([], 400, 'error');
        }
    }

    /**
     * 在线订阅channel列表
     *
     * @author HSK
     * @date 2022-08-22 16:50:59
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function list(\support\Request $request)
    {
        try {
            $type = request()->input('type');
            if (!empty($type) && !in_array($type, ['public', 'private', 'presence'])) {
                return api([], 400, 'Parameter error');
            }

            // 应用信息
            $projectInfo = request()->projectInfo;

            $channels = [];
            foreach (Redis::hGetAll('PushService:online:project-' . $projectInfo['id'] . ':subscription_count') as $channel => $subscription_count) {
                if ($subscription_count > 0) {
                    $channel_type = get_channel_type($channel);
                    if (!empty($type) && $channel_type !== $type) {
                        continue;
                    }

                    $channels[] = [
                        'type'               => $channel_type,
                        'channel'            => $channel,
                        'subscription_count' => $subscription_count,
                    ];
                }
            };

            return api($channels);
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return api([], 400, 'error');
        }
    }

    /**
     * 在线订阅channel详情
     *
     * @author HSK
     * @date 2022-08-22 17:03:04
     *
     * @param \support\Request $request
     *
     * @return \support\Response
     */
    public function info(\support\Request $request)
    {
        try {
            if (empty($channel = request()->input('channel'))) {
                return api([], 400, 'Parameter error');
            }

            // 应用信息
            $projectInfo = request()->projectInfo;

            $subscription_count = (int)Redis::hGet('PushService:online:project-' . $projectInfo['id'] . ':subscription_count', $channel);
            if (0 === $subscription_count) {
                return api([], 400, 'channel does not exist');
            }

            $channel_type = get_channel_type($channel);

            // 获取用户信息
            $users = [];
            if ('presence' === $channel_type) {
                $count = 100;
                foreach (Gateway::getClientSessionsByGroup('project-' . $projectInfo['id'] . '.' . $channel) as $item) {
                    if (isset($item['presence'][$channel])) {
                        $user_id   = key($item['presence'][$channel]);
                        $user_info = current($item['presence'][$channel]);

                        $users[] = [
                            'user_id'   => $user_id,
                            'user_info' => $user_info,
                        ];

                        if ($count-- <= 0) {
                            break;
                        }
                    }
                }
            }

            return api([
                'type'               => $channel_type,
                'channel'            => $channel,
                'subscription_count' => $subscription_count,
                'users'              => $users,
            ]);
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return api([], 400, 'error');
        }
    }
}
