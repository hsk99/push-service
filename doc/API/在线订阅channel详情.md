    
##### 简要描述

- 在线订阅channel详情

##### 请求URL
- ` {{host}}/api/channel/info `
  
##### 请求方式
- POST 

##### Header

|header|必选|类型|说明|
|:----    |:---|:----- |-----   |
|X-hsk99-Key |是  |string |应用访问密钥   |
|X-hsk99-Signature |是  |string | 签名    |

##### 参数

|参数名|必选|类型|说明|
|:----    |:---|:----- |-----   |
|channel     |是  |string | 订阅渠道    |

##### 返回示例 

```json
{
  "code": 200,
  "msg": "success",
  "data": {
    "type": "presence",
    "channel": "presence-message",
    "subscription_count": 1,
    "users": [
      {
        "user_id": 42,
        "user_info": "{\"name\":\"张三42\"}"
      }
    ]
  }
}
```

##### 返回参数说明 

|参数名|类型|说明|
|:-----  |:-----|-----                           |
|type |string |频道类型 |
|channel |string |订阅频道 |
|subscription_count |number |在线订阅数 |
|users |array |在线用户信息 |
|users.user_id |string |用户ID |
|users.user_info |string |用户信息 |


##### 备注 

- 签名：` hash_hmac('sha256', json_encode($body, 320), 应用密钥, false) `

-  频道类型为 ` presence ` 时才会存在 ` users ` ，其他订阅为空数组
