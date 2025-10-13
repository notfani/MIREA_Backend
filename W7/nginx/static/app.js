fetch('/api/content').then(r => r.json()).then(d => {
    document.body.className = d.theme;
    document.querySelector('#greet').innerText = d.greeting;
});