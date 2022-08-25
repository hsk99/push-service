
    
##### 简要描述

- 在线订阅channel列表

##### 请求URL
- ` {{host}}/api/channel/list `
  
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
|type     |否  |string | 订阅类型    |

##### 返回示例 

``` json
{
  "code": 200,
  "msg": "success",
  "data": [
    {
      "type": "public",
      "channel": "public",
      "subscription_count": "1"
    },
    {
      "type": "private",
      "channel": "private-message",
      "subscription_count": "1"
    },
    {
      "type": "private",
      "channel": "private-push",
      "subscription_count": "1"
    },
    {
      "type": "presence",
      "channel": "presence-message",
      "subscription_count": "1"
    }
  ]
}
```

##### 返回参数说明 

|参数名|类型|说明|
|:-----  |:-----|-----                           |
|data |array |在线订阅频道列表 |
|type |string |频道类型 |
|channel |string |订阅频道 |
|subscription_count |string |在线订阅数 |


##### 备注 

- 签名：` hash_hmac('sha256', json_encode($body, 320), 应用密钥, false) `

-  ` type ` 值为：` public ` 、 ` private `、 ` presence`
