function startPing(sessionDuration)
{
    let last = Date.now();
    window.setInterval(() => {
        if (Date.now() - last > sessionDuration * 1000) {
            window.location.reload();
        } else if (Date.now() - last < 1500) {
            fetch('/api/ping');
        }
    }, 1000);
    window.onkeypress = () => {last = Date.now();};
    window.onclick = () => {last = Date.now();};
    window.onmousemove = () => {last = Date.now();};
}