import { spawn } from 'child_process';

// 1. Launch Chrome
const chromePath = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const chrome = spawn(chromePath, [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--window-size=1280,800',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check'
]);

await new Promise(r => setTimeout(r, 1500));

try {
    const listRes = await fetch('http://127.0.0.1:9222/json');
    const tabs = await listRes.json();
    const wsUrl = tabs[0]?.webSocketDebuggerUrl;
    console.log('Connecting to:', wsUrl);

    const ws = new WebSocket(wsUrl);
    await new Promise(r => ws.onopen = r);

    let id = 1;
    function send(method, params = {}) {
        return new Promise((resolve) => {
            const reqId = id++;
            const handler = (event) => {
                const data = JSON.parse(event.data);
                if (data.id === reqId) {
                    ws.removeEventListener('message', handler);
                    resolve(data.result);
                }
            };
            ws.addEventListener('message', handler);
            ws.send(JSON.stringify({ id: reqId, method, params }));
        });
    }

    await send('Network.enable');
    await send('Page.enable');

    // Set cookie
    await send('Network.setCookie', {
        name: 'PHPSESSID',
        value: 'n45th3i16kevujq2pqc3e2sh4q',
        domain: 'localhost',
        path: '/'
    });

    // Navigate to faculty/timetable.php
    await send('Page.navigate', { url: 'http://localhost/SmartFacultyPortal/faculty/timetable.php' });
    await new Promise(r => setTimeout(r, 2000));

    // Evaluate scrollbars and overflowing elements
    const evalResult = await send('Runtime.evaluate', {
        expression: `(() => {
            const results = [];
            const all = document.querySelectorAll('*');
            for (const el of all) {
                const style = window.getComputedStyle(el);
                const hasVScroll = (style.overflowY === 'auto' || style.overflowY === 'scroll') && el.scrollHeight > el.clientHeight;
                const hasHScroll = (style.overflowX === 'auto' || style.overflowX === 'scroll') && el.scrollWidth > el.clientWidth;
                if (hasVScroll || hasHScroll) {
                    results.push({
                        tag: el.tagName,
                        id: el.id,
                        className: el.className,
                        scrollHeight: el.scrollHeight,
                        clientHeight: el.clientHeight,
                        scrollWidth: el.scrollWidth,
                        clientWidth: el.clientWidth,
                        overflowY: style.overflowY,
                        overflowX: style.overflowX,
                        rect: el.getBoundingClientRect()
                    });
                }
            }
            return {
                windowInnerHeight: window.innerHeight,
                windowInnerWidth: window.innerWidth,
                htmlScrollHeight: document.documentElement.scrollHeight,
                htmlClientHeight: document.documentElement.clientHeight,
                bodyScrollHeight: document.body.scrollHeight,
                bodyClientHeight: document.body.clientHeight,
                scrollableElements: results
            };
        })()`,
        returnByValue: true
    });

    console.log('Inspection result:', JSON.stringify(evalResult.result.value, null, 2));

    // Also take screenshot
    const shot = await send('Page.captureScreenshot', { format: 'png' });
    if (shot && shot.data) {
        const fs = await import('fs');
        fs.writeFileSync('scratch/timetable_view.png', Buffer.from(shot.data, 'base64'));
        console.log('Saved screenshot to scratch/timetable_view.png');
    }

    ws.close();
} catch (e) {
    console.error('Error:', e);
} finally {
    chrome.kill();
}
