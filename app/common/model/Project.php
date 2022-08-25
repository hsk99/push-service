<?php

namespace app\common\model;

use support\hsk99\Model;
use think\model\concern\SoftDelete;
use support\Redis;

class Project extends Model
{
    use SoftDelete;

    protected $table = 'project';
    protected $deleteTime = false;

    /**
     * 新增后操作
     *
     * @author HSK
     * @date 2022-08-25 09:46:25
     *
     * @param RdmsTask $model
     *
     * @return void
     */
    public static function onAfterInsert($model)
    {
        try {
            $id = ($model->toArray()['id'] ?? $model->getWhere()['id']) ?? null;
            $info = static::find($id);
            if (empty($info)) {
                return;
            }
            $info = $info->toArray();
            Redis::hSet('ProjectCache:id', $id, json_encode($info, 320));
            Redis::hSet('ProjectCache:access_key', $info['access_key'], json_encode($info, 320));
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * 更新后操作
     *
     * @author HSK
     * @date 2022-08-25 09:46:25
     *
     * @param RdmsTask $model
     *
     * @return void
     */
    public static function onAfterUpdate($model)
    {
        try {
            $id = ($model->toArray()['id'] ?? $model->getWhere()['id']) ?? null;
            $info = static::find($id);
            if (empty($info)) {
                return;
            }
            $info = $info->toArray();
            Redis::hSet('ProjectCache:id', $id, json_encode($info, 320));
            Redis::hSet('ProjectCache:access_key', $info['access_key'], json_encode($info, 320));
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
        }
    }

    /**
     * 获取应用信息
     *
     * @author HSK
     * @date 2022-08-19 09:51:06
     *
     * @param integer $id
     *
     * @return array
     */
    public static function getInfoId(int $id): array
    {
        try {
            if (Redis::hExists('ProjectCache:id', $id)) {
                $info = Redis::hGet('ProjectCache:id', $id);
                $info = json_decode($info, true);
            } else {
                $info = static::field('id, name, desc, access_key, secret_key, web_hook, status')->find($id);
                if (empty($info)) {
                    return [];
                }
                $info = $info->toArray();

                Redis::hSet('ProjectCache:id', $id, json_encode($info, 320));
                Redis::hSet('ProjectCache:access_key', $info['access_key'], json_encode($info, 320));
            }
            return $info;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }

    /**
     * 获取应用信息
     *
     * @author HSK
     * @date 2022-08-25 10:18:25
     *
     * @param string $access_key
     *
     * @return array
     */
    public static function getInfoAccessKey(string $access_key): array
    {
        try {
            if (Redis::hExists('ProjectCache:access_key', $access_key)) {
                $info = Redis::hGet('ProjectCache:access_key', $access_key);
                $info = json_decode($info, true);
            } else {
                $info = static::field('id, name, desc, access_key, secret_key, web_hook, status')->where('access_key', $access_key)->find();
                if (empty($info)) {
                    return [];
                }
                $info = $info->toArray();

                Redis::hSet('ProjectCache:id', $info['id'], json_encode($info, 320));
                Redis::hSet('ProjectCache:access_key', $access_key, json_encode($info, 320));
            }
            return $info;
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }
}
