<?php

namespace app\common\model;

use support\hsk99\Model;
use think\model\concern\SoftDelete;

class Record extends Model
{
    use SoftDelete;

    protected $table = 'record';
    protected $deleteTime = false;
}
