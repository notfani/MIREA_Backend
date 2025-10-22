(function(){
  function qs(id){ return document.getElementById(id); }
  const loginModalEl = qs('loginModal');
  const registerModalEl = qs('registerModal');
  const loginModal = new bootstrap.Modal(loginModalEl);
  const registerModal = new bootstrap.Modal(registerModalEl);

  async function api(path, opts={}){
    opts.headers = opts.headers || {};
    if (opts.json) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.json);
    }
    opts.credentials = 'same-origin';
    const res = await fetch(path, opts);
    let data;
    try { data = await res.json(); } catch(e){ data = null; }
    return {status: res.status, ok: res.ok, data};
  }

  async function refreshMe(){
    const r = await api('/api/me');
    const user = r.data && r.data.user;
    const userEmail = qs('userEmail');
    const authArea = qs('authArea');
    const uploadSection = qs('uploadSection');

    if (user){
      userEmail.textContent = user.email;
      authArea.innerHTML = `<button id="btnLogout" class="btn btn-sm btn-danger">Logout</button>`;
      qs('btnLogout').addEventListener('click', logout);
      qs('btnLogin').style.display = 'none';
      qs('btnRegister').style.display = 'none';

      if (uploadSection) {
        uploadSection.innerHTML = `
          <h4>1. Загрузить PDF</h4>
          <form id="uploadForm" enctype="multipart/form-data">
              <div id="dropZone" class="d-flex align-items-center justify-content-center" style="border: 2px dashed #6c757d; min-height: 140px;">
                  <span class="text-muted">Перетащите файл или <input type="file" name="file" accept="application/pdf" class="form-control-sm d-inline w-auto"> <button class="btn btn-sm btn-success">Upload</button></span>
              </div>
          </form>
        `;

        const uploadForm = qs('uploadForm');
        if (uploadForm) {
          uploadForm.addEventListener('submit', uploadPDF);
        }
      }

      await loadPDFList();
    } else {
      userEmail.textContent = '';
      authArea.innerHTML = '';
      qs('btnLogin').style.display = 'inline-block';
      qs('btnRegister').style.display = 'inline-block';

      if (uploadSection) {
        uploadSection.innerHTML = '<h4>1. Загрузить PDF</h4><div class="alert alert-warning">Войдите в аккаунт для загрузки PDF-файлов</div>';
      }

      const list = qs('pdfList');
      if (list) {
        list.innerHTML = '<li class="list-group-item text-muted">Войдите в аккаунт для просмотра файлов</li>';
      }
    }
  }

  async function register(){
    qs('regError').textContent = '';
    const email = qs('regEmail').value.trim();
    const password = qs('regPassword').value;
    const r = await api('/api/register', {method:'POST', json:{email, password}});
    if (!r.ok){ qs('regError').textContent = (r.data && r.data.error) || 'Ошибка'; return; }
    registerModal.hide();
    await refreshMe();
    await loadPDFList();
    await loadCharts();
  }

  async function login(){
    qs('loginError').textContent = '';
    const email = qs('loginEmail').value.trim();
    const password = qs('loginPassword').value;
    const r = await api('/api/login', {method:'POST', json:{email, password}});
    if (!r.ok){ qs('loginError').textContent = (r.data && r.data.error) || 'Ошибка'; return; }
    loginModal.hide();
    await refreshMe();
    await loadPDFList();
    await loadCharts();
  }

  async function logout(){
    await api('/api/logout', {method:'POST'});
    await refreshMe();
    await loadCharts();
  }

  async function uploadPDF(e){
    e.preventDefault();
    const formData = new FormData(e.target);
    const file = formData.get('file');
    if (!file || !file.name) {
      alert('Выберите файл');
      return;
    }
    try {
      const res = await fetch('/api/pdf/upload', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (res.ok) {
        e.target.reset();
        await loadPDFList();
        alert('Файл успешно загружен!');
      } else {
        alert(data.error || 'Ошибка загрузки');
      }
    } catch(err) {
      console.error(err);
      alert('Ошибка загрузки файла');
    }
  }

  async function loadPDFList(){
    try {
      const res = await fetch('/api/pdf/list', {credentials: 'same-origin'});
      const pdfs = await res.json();
      const list = qs('pdfList');
      list.innerHTML = '';
      if (!pdfs || pdfs.length === 0) {
        list.innerHTML = '<li class="list-group-item text-muted">Нет загруженных файлов</li>';
        return;
      }
      pdfs.forEach(pdf => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
          <span>${pdf.name}</span>
          <a href="/api/pdf/download/${pdf.id}" class="btn btn-sm btn-primary" download>Скачать</a>
        `;
        list.appendChild(li);
      });
    } catch(err) {
      console.error('Error loading PDFs:', err);
    }
  }

  let currentTheme = 'light';
  async function toggleTheme(){
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-bs-theme', currentTheme);
    qs('btnThemeToggle').textContent = currentTheme === 'light' ? 'Light' : 'Dark';
    try {
      await fetch('/api/theme/set', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({theme: currentTheme}),
        credentials: 'same-origin'
      });
    } catch(err) {
      console.error('Error saving theme:', err);
    }
  }

  async function loadTheme(){
    try {
      const res = await fetch('/api/theme/get', {credentials: 'same-origin'});
      const data = await res.json();
      if (data && data.theme) {
        currentTheme = data.theme;
        document.documentElement.setAttribute('data-bs-theme', currentTheme);
        qs('btnThemeToggle').textContent = currentTheme === 'light' ? 'Light' : 'Dark';
      }
    } catch(err) {
      console.error('Error loading theme:', err);
    }
  }

  async function loadCharts(){
    try {
      const res = await fetch('/api/charts/list', {credentials: 'same-origin'});
      const data = await res.json();
      const chartsDiv = qs('charts');
      chartsDiv.innerHTML = '';
      if (data.message) {
        chartsDiv.innerHTML = `
          <div class="col-12">
            <div class="alert alert-warning">
              ${data.message}
            </div>
          </div>
        `;
        return;
      }

      if (!data.charts || data.charts.length === 0) {
        chartsDiv.innerHTML = `
          <div class="col-12">
            <div class="alert alert-info">
              Графики ещё не сгенерированы. 
              <button id="btnGenerateCharts" class="btn btn-sm btn-primary ms-2">Сгенерировать графики</button>
            </div>
          </div>
        `;
        const btnGenerate = qs('btnGenerateCharts');
        if (btnGenerate) {
          btnGenerate.addEventListener('click', async () => {
            btnGenerate.disabled = true;
            btnGenerate.textContent = 'Генерация...';
            try {
              const genRes = await fetch('/api/charts/generate', {
                method: 'POST',
                credentials: 'same-origin'
              });
              if (genRes.ok) {
                await loadCharts();
                alert('Графики успешно сгенерированы!');
              } else {
                const err = await genRes.json();
                alert(err.error || 'Ошибка генерации графиков');
                btnGenerate.disabled = false;
                btnGenerate.textContent = 'Сгенерировать графики';
              }
            } catch(err) {
              console.error('Chart generation error:', err);
              alert('Ошибка генерации графиков');
              btnGenerate.disabled = false;
              btnGenerate.textContent = 'Сгенерировать графики';
            }
          });
        }
        return;
      }

      data.charts.forEach(name => {
        const col = document.createElement('div');
        col.className = 'col-md-4';
        col.innerHTML = `
          <div class="card">
            <img src="/static/charts/${name}" class="card-img-top chart-img" alt="${name}" 
                onerror="this.parentElement.innerHTML='<div class=\\'alert alert-warning m-2\\'>Не удалось загрузить график</div>'">
            <div class="card-body">
              <small class="text-muted">${name.replace('.png', '')}</small>
            </div>
          </div>
        `;
        chartsDiv.appendChild(col);
      });
    } catch(err) {
      console.error('Error loading charts:', err);
      const chartsDiv = qs('charts');
      chartsDiv.innerHTML = '<div class="col-12"><div class="alert alert-warning">Не удалось загрузить графики</div></div>';
    }
  }

  qs('btnRegister').addEventListener('click', ()=> registerModal.show());
  qs('btnLogin').addEventListener('click', ()=> loginModal.show());
  qs('regSubmit').addEventListener('click', register);
  qs('loginSubmit').addEventListener('click', login);
  qs('btnThemeToggle').addEventListener('click', toggleTheme);

  const uploadForm = qs('uploadForm');
  if (uploadForm) {
    uploadForm.addEventListener('submit', uploadPDF);
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    refreshMe().catch(console.error);
    loadTheme().catch(console.error);
    loadPDFList().catch(console.error);
    loadCharts().catch(console.error);
  });
})();
