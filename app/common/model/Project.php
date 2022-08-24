<?php

namespace app\common\model;

use support\hsk99\Model;
use think\model\concern\SoftDelete;

class Project extends Model
{
    use SoftDelete;

    protected $table = 'project';
    protected $deleteTime = false;

    /**
     * 获取推送所需的信息
     *
     * @author HSK
     * @date 2022-08-19 09:51:06
     *
     * @param integer $id
     *
     * @return array
     */
    public static function getPushInfo(int $id): array
    {
        try {
            return static::field('id, access_key, secret_key, web_hook')->find($id)->toArray();
        } catch (\Throwable $th) {
            \Hsk99\WebmanException\RunException::report($th);
            return [];
        }
    }
}
