
```javascript
// connect
var id = Math.ceil(Math.random() * 100);
var pusher = new Pusher('ecc1dcdecd380a38cadc74cd9d0fb9bf', {
    forceTLS: false,
    wsHost: '127.0.0.1',
    wsPort: 8790,
    // wssPort: 8790,
    // wsPath: '/wss',
    channelAuthorization: {
        endpoint: 'http://127.0.0.1:8789/api/connect/auth',
        params: {
            access_key: 'ecc1dcdecd380a38cadc74cd9d0fb9bf',
            user_id: id,
            user_info: JSON.stringify({ 'name': '张三' + id })
        }
    }
})




// error
pusher.connection.bind("error", function (err) {
    console.log(JSON.stringify(err));


    // disconnect
    if (-1 === err.data.code) {
        pusher.connection.disconnect();
    }
});




// Public channels
var public = pusher.subscribe('public');


// Subscription succeeded
public.bind("pusher:subscription_succeeded", () => {
    console.log('public subscription_succeeded');
});
// custom event
public.bind('message', function (data) {
    console.log('public-message：' + JSON.stringify(data));
});




// Private channels
var private = pusher.subscribe('private-message');


// Subscription succeeded
public.bind("pusher:subscription_succeeded", () => {
    console.log('private subscription_succeeded');
});
// custom event
private.bind('client-message', function (data) {
    console.log('private-client-message：' + JSON.stringify(data));
});


// Private channels (Client push)
var client = pusher.subscribe('private-push');
// custom event
client.bind('message', function (data) {
    console.log('client-push-message：' + JSON.stringify(data));
    private.trigger('client-message', JSON.stringify(data));
});




// Presence channels
var presence = pusher.subscribe("presence-message");


// Subscription succeeded
presence.bind("pusher:subscription_succeeded", (data) => {
    console.log('presence subscription_succeeded：' + JSON.stringify(data));
});


// add member
presence.bind("pusher:member_added", (data) => {
    console.log('member_added：' + JSON.stringify(data));
});


// remove member
presence.bind("pusher:member_removed", (data) => {
    console.log('member_removed：' + JSON.stringify(data));
});


// custom event
presence.bind("message", (data) => {
    console.log('member_removed：' + JSON.stringify(data));
});
```