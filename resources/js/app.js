
import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: window.location.hostname + ':6001' // Adjust based on your setup
});

window.Echo.channel('driver-channel')
    .listen('DriverOnline', (e) => {
        console.log('Received message:', e.message);
    });
