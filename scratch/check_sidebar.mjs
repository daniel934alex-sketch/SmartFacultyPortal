import { spawn } from 'child_process';
import fs from 'fs';

const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9223',
    '--window-size=1280,720',
    '--disable-gpu'
]);

await new Promise(r => setTimeout(r, 1500));

try {
    const list = await (await fetch('http://127.0.0.1:9223/json')).json();
    const ws = new WebSocket(list[0].webSocketDebuggerUrl);
    await new Promise(r => ws.onopen = r);

    let id = 1;
    const send = (m, p = {}) => new Promise(res => {
        const i = id++;
        const h = e => {
            const d = JSON.parse(e.data);
            if (d.id === i) {
                ws.removeEventListener('message', h);
                res(d.result);
            }
        };
        ws.addEventListener('message', h);
        ws.send(JSON.stringify({ id: i, method: m, params: p }));
    });

    await send('Network.enable');
    await send('Network.setCookie', {
        name: 'PHPSESSID',
        value: 'n45th3i16kevujq2pqc3e2sh4q',
        domain: 'localhost',
        path: '/'
    });

    for (const page of ['dashboard.php', 'timetable.php', 'notifications.php']) {
        await send('Page.navigate', { url: `http://localhost/SmartFacultyPortal/faculty/${page}` });
        await new Promise(r => setTimeout(r, 1500));

        const r = await send('Runtime.evaluate', {
            expression: `(() => {
                const s = document.querySelector('.fd-sidebar, .fn-sidebar');
                if (!s) return { error: 'No sidebar found' };
                const cs = window.getComputedStyle(s);
                return {
                    page: '${page}',
                    windowHeight: window.innerHeight,
                    scrollHeight: s.scrollHeight,
                    clientHeight: s.clientHeight,
                    offsetHeight: s.offsetHeight,
                    overflowY: cs.overflowY,
                    hasScrollbar: s.scrollHeight > s.clientHeight
                };
            })()`,
            returnByValue: true
        });
        console.log(page, r.result.value);

        // Capture screenshot
        const shot = await send('Page.captureScreenshot', { format: 'png' });
        fs.writeFileSync(`C:/laragon/www/SmartFacultyPortal/scratch/${page}.png`, Buffer.from(shot.data, 'base64'));
    }

    ws.close();
} catch (e) {
    console.error(e);
} finally {
    chrome.kill();
}
