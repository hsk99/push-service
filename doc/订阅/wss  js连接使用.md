
## 使用类库

- [push.js](https://github.com/webman-php/push/blob/main/src/push.js "push.js")

- [push-uniapp.js](https://github.com/webman-php/push/blob/main/src/push-uniapp.js "push-uniapp.js")

## 示例

```javascript
var connection = new Push({
    url: "wss://127.0.0.1:8790",
    app_key: "ecc1dcdecd380a38cadc74cd9d0fb9bf",
    auth: "http://127.0.0.1:8789/api/connect/auth?access_key=ecc1dcdecd380a38cadc74cd9d0fb9bf"
});



// Public channels
var public = connection.subscribe('public');


// Subscription succeeded
public.on("pusher:subscription_succeeded", () => {
    console.log('public subscription_succeeded');
});
// custom event
public.on('message', function (data) {
    console.log('public-message：' + JSON.stringify(data));
});




// Private channels
var private = connection.subscribe('private-message');


// Subscription succeeded
public.on("pusher:subscription_succeeded", () => {
    console.log('private subscription_succeeded');
});
// custom event
private.on('client-message', function (data) {
    console.log('private-client-message：' + JSON.stringify(data));
});


// Private channels (Client push)
var client = connection.subscribe('private-push');
// custom event
client.on('message', function (data) {
    console.log('client-push-message：' + JSON.stringify(data));
    private.trigger('client-message', JSON.stringify(data));
});
```
