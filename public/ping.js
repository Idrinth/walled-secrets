function startPing(sessionDuration)
{
    let last = Date.now();
    window.setInterval(() => {
        if (Date.now() - last > sessionDuration * 1000) {
            window.location.reload();
        } else if (Date.now() - last < 5500) {
            fetch('/ping');
        }
    }, 5000);
    window.onkeypress = () => {last = Date.now();};
    window.onclick = () => {last = Date.now();};
    window.onmousemove = () => {last = Date.now();};
}