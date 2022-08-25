
##### 请求方式
- POST 

##### Header

|Header|类型|说明|
|:----    |:----- |-----   |
|x-pusher-signature |string |签名  |

> 签名：` hash_hmac('sha256', 请求数据包, 应用密钥, false) `

##### 请求数据

```json
{
  "time_ms": 1661132991.891466,
  "events": {
    "channel_added": [
      {
        "type": "public",
        "channel": "public"
      },
      {
        "type": "private",
        "channel": "private-message"
      }
    ],
    "channel_removed": [
      {
        "type": "private",
        "channel": "private-push"
      },
      {
        "type": "presence",
        "channel": "presence-message"
      }
    ],
    "user_added": {
      "presence-message": [
        {
          "channel": "presence-message",
          "user_id": 49,
          "user_info": "{\"name\":\"张三49\"}"
        }
      ]
    },
    "user_removed": {
      "presence-message": [
        {
          "channel": "presence-message",
          "user_id": 37,
          "user_info": "{\"name\":\"张三37\"}"
        }
      ]
    }
  }
}
```

##### 数据说明

> 整个数据包为 ` JSON ` 数据

|参数名|类型|说明|
|:----    |:----- |-----   |
|time_ms |string |毫秒时间戳   |
|events |array | 事件    |

![](https://www.plantuml.com/plantuml/svg/SoWkIImgoIhEp-Egvb9GK2h9p4sDporMib8mD3CpD3GsihGqrBEmD3GnCzC1oQUMfUQLWAH1ge7yv8p4lBpKdFZ4b9JK5A1mD5XO0IeDLb9IMP0Ab55wigFh-QxzBzOjUZcZzVd6tK_dTIlf85H13G8fX2XvjcF1oyR9Ib0LjM0wLWVLrgBKtFmomlPsKylUqkBK8hXNOLOf5HIb5gVc9QVgvgOM5oUcfo8v1zb1g9P1GkFvb1NFEhO_wsnukd4UYlKwoDh0rcQ2x5I2QF1qmQOWBoqVeUJ9_eNF6jShmLNLGbcnOBeMsKE8KAJKIw207c2FSO5mBPT3QbuAE5K30000)