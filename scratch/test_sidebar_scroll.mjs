import { spawn } from 'child_process';

const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new',
    '--remote-debugging-port=9226',
    '--window-size=1280,680',
    '--disable-extensions',
    'about:blank'
]);

await new Promise(r => setTimeout(r, 1500));

try {
    const list = await (await fetch('http://127.0.0.1:9226/json')).json();
    const target = list.find(t => t.type === 'page') || list[0];
    const ws = new WebSocket(target.webSocketDebuggerUrl);
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
    await send('Page.enable');

    // Navigate to login
    await send('Page.navigate', { url: 'http://localhost/SmartFacultyPortal/auth/login.php' });
    await new Promise(r => setTimeout(r, 1000));

    // Fill login form
    await send('Runtime.evaluate', {
        expression: `(() => {
            document.getElementById('username').value = 'faculty01';
            document.getElementById('password').value = 'faculty@01';
            document.querySelector('form').submit();
        })()`
    });
    await new Promise(r => setTimeout(r, 1500));

    for (const page of ['dashboard.php', 'timetable.php', 'notifications.php']) {
        await send('Page.navigate', { url: `http://localhost/SmartFacultyPortal/faculty/${page}` });
        await new Promise(r => setTimeout(r, 1200));

        const r = await send('Runtime.evaluate', {
            expression: `(() => {
                const s = document.querySelector('.fd-sidebar, .fn-sidebar');
                if (!s) return { url: window.location.href, error: 'No sidebar found' };
                const cs = window.getComputedStyle(s);
                return {
                    page: '${page}',
                    windowHeight: window.innerHeight,
                    sidebarOverflowY: cs.overflowY,
                    sidebarScrollHeight: s.scrollHeight,
                    sidebarClientHeight: s.clientHeight,
                    hasSidebarScrollbar: s.scrollHeight > s.clientHeight && cs.overflowY !== 'hidden',
                    windowScrollHeight: document.documentElement.scrollHeight,
                    canPageScrollVertically: document.documentElement.scrollHeight > window.innerHeight
                };
            })()`,
            returnByValue: true
        });
        console.log(page, r.result.value);
    }

    ws.close();
} catch (e) {
    console.error(e);
} finally {
    chrome.kill();
}
