<?php
    /**
     * Приватная страница (личный кабинет)
     * Доступна только для авторизованных пользователей
     */
    require_once __DIR__ . '/../_bootstrap.php';

    // Проверка авторизации
    $uid = (int)($_COOKIE['uid'] ?? 0);
    if ($uid <= 0) {
        http_response_code(401);
        echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Требуется вход</title></head><body><h1>Требуется вход</h1><p>Пожалуйста, выполните вход через главную страницу.</p><p><a href="/">На страницу входа</a></p></body></html>';
        exit;
    }

    // Получаем данные пользователя
    $userRepository = new UserRepository();
    $user = null;
    $login = 'user';

    try {
        $user = $userRepository->findById($uid);
        if ($user) {
            $login = $user['login'];
        }
    } catch (Throwable $e) {
        Logger::getInstance()->error('Failed to load user data', [
                'userId' => $uid,
                'error' => $e->getMessage()
        ]);
    }

    // Получаем настройки из cookies (или из БД, если cookie нет)
    $theme = $_COOKIE['theme'] ?? ($user['theme'] ?? 'light');
    $lang = $_COOKIE['lang'] ?? ($user['lang'] ?? 'ru');

    // Подготовка CSS файла
    $cssMap = [
            'light' => '/css/light.css',
            'dark' => '/css/dark.css',
            'colorblind' => '/css/colorblind.css',
    ];
    $cssHref = $cssMap[$theme] ?? $cssMap['light'];

    // Получаем список PDF файлов
    $pdfRepository = new PdfRepository();
    $files = [];

    try {
        $files = $pdfRepository->findByUserId($uid);
    } catch (Throwable $e) {
        Logger::getInstance()->error('Failed to load PDF files', [
                'userId' => $uid,
                'error' => $e->getMessage()
        ]);
    }

    // Получаем контент через Redis (с кешированием)
    $request = new Request();
    $contentService = new Content();
    $contentJson = $contentService->personal($request);
    $contentData = json_decode($contentJson, true);
    $contentPayload = $contentData['data'] ?? [];

    $greeting = $contentPayload['greeting'] ?? ($lang === 'ru' ? 'Привет' : 'Hello');
    $banner = $contentPayload['banner'] ?? '/static/light.svg';

    // Локализация
    $translations = [
            'ru' => [
                    'title' => 'Личный кабинет',
                    'upload' => 'Загрузить PDF',
                    'your_files' => 'Ваши файлы',
                    'logout' => 'Выйти',
                    'no_files' => 'Файлы отсутствуют',
                    'delete' => 'Удалить',
                    'uploaded' => 'Загружен',
                    'theme' => 'Тема',
                    'theme_changed' => 'Тема обновлена',
            ],
            'en' => [
                    'title' => 'Dashboard',
                    'upload' => 'Upload PDF',
                    'your_files' => 'Your files',
                    'logout' => 'Logout',
                    'no_files' => 'No files',
                    'delete' => 'Delete',
                    'uploaded' => 'Uploaded',
                    'theme' => 'Theme',
                    'theme_changed' => 'Theme updated',
            ],
    ];

    $t = $translations[$lang] ?? $translations['ru'];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">
    <style>
        main {
            max-width: 960px;
            margin: 24px auto;
            padding: 0 16px;
        }

        header img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        ul.files {
            list-style: none;
            padding: 0;
        }

        ul.files li {
            margin: 6px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
            border-radius: 6px;
            background: rgba(0, 0, 0, 0.02);
        }

        ul.files li:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .file-date {
            font-size: 12px;
            opacity: 0.7;
        }

        .delete-btn {
            background: #dc2626;
            color: white;
            border: 0;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background-color 0.2s;
        }

        .delete-btn:hover {
            background: #b91c1c;
        }

        .delete-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .topbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
        }

        .userbox {
            font-size: 14px;
            opacity: .85;
        }

        .themebox {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .themebox select {
            padding: 6px 8px;
            border-radius: 6px;
        }

        #themeMsg {
            font-size: 12px;
            opacity: .8;
        }

        button.logout {
            background: #dc2626;
            color: #fff;
            border: 0;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font: 500 14px system-ui, Arial;
        }

        button.logout:hover {
            background: #b91c1c;
        }

        button.logout:active {
            background: #991b1b;
        }

        #uploadMessage {
            display: none;
            margin-top: 12px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 12px;
            margin-top: 16px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn {
            background: #2563eb;
            color: white;
            border: 0;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 8px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<header>
    <main>
        <div class="topbar">
            <div class="userbox">👤 <?= htmlspecialchars($login, ENT_QUOTES) ?> (ID: <?= (int)$uid ?>)</div>
            <div class="themebox">
                <label for="themeSelect"><?= htmlspecialchars($t['theme'], ENT_QUOTES) ?>:</label>
                <select id="themeSelect">
                    <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>>Light</option>
                    <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>>Dark</option>
                    <option value="colorblind" <?= $theme === 'colorblind' ? 'selected' : '' ?>>Colorblind</option>
                </select>
                <span id="themeMsg"></span>
            </div>
            <button class="logout" id="btnLogout"
                    type="button"><?= htmlspecialchars($t['logout'], ENT_QUOTES) ?></button>
        </div>
        <h1><?= htmlspecialchars($greeting, ENT_QUOTES) ?>!</h1>
        <img id="bannerImg" src="<?= htmlspecialchars($banner, ENT_QUOTES) ?>" alt="banner">
    </main>
</header>
<main>
    <section class="card">
        <h2><?= htmlspecialchars($t['upload'], ENT_QUOTES) ?></h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="pdf" id="pdfFile" accept="application/pdf" required>
            <button type="submit" class="btn" id="uploadBtn">OK</button>
        </form>
        <div id="uploadMessage"></div>
    </section>

    <section class="card">
        <h2><?= htmlspecialchars($t['your_files'], ENT_QUOTES) ?></h2>
        <?php if (empty($files)): ?>
            <p class="text-muted"><?= htmlspecialchars($t['no_files'], ENT_QUOTES) ?></p>
        <?php else: ?>
            <ul class="files">
                <?php foreach ($files as $f): ?>
                    <li>
                        <div class="file-info">
                            <div class="file-name">
                                <a href="/api/pdf/<?= (int)$f['id'] ?>" target="_blank">
                                    <?= htmlspecialchars($f['original_name'] ?? $f['filename'], ENT_QUOTES) ?>
                                </a>
                            </div>
                            <div class="file-date">
                                <?= htmlspecialchars($t['uploaded'], ENT_QUOTES) ?>:
                                <?= htmlspecialchars((string)$f['uploaded_at'], ENT_QUOTES) ?>
                            </div>
                        </div>
                        <button class="delete-btn" data-id="<?= (int)$f['id'] ?>">
                            <?= htmlspecialchars($t['delete'], ENT_QUOTES) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<script>
    // Выход из системы
    const btnLogout = document.getElementById('btnLogout');
    btnLogout?.addEventListener('click', async () => {
        try {
            await fetch('/api/logout', {method: 'POST', credentials: 'same-origin'});
            ['uid', 'theme', 'lang'].forEach(n => document.cookie = n + '=; Max-Age=0; path=/');
        } catch (e) {
            console.error('Logout error:', e);
        }
        location.href = '/';
    });

    // Функция отображения сообщений
    function showUploadMessage(text, isSuccess = false) {
        const uploadMessage = document.getElementById('uploadMessage');
        uploadMessage.textContent = text;
        uploadMessage.style.display = 'block';
        uploadMessage.style.backgroundColor = isSuccess ? '#dcfce7' : '#fef2f2';
        uploadMessage.style.color = isSuccess ? '#166534' : '#dc2626';
        uploadMessage.style.border = `1px solid ${isSuccess ? '#bbf7d0' : '#fecaca'}`;
    }

    // Смена темы
    const themeMapCss = {
        light: '/css/light.css',
        dark: '/css/dark.css',
        colorblind: '/css/colorblind.css'
    };
    const themeMapBanner = {
        light: '/static/light.svg',
        dark: '/static/dark.svg',
        colorblind: '/static/cb.svg'
    };
    const themeSelect = document.getElementById('themeSelect');
    const themeMsg = document.getElementById('themeMsg');
    const bannerImg = document.getElementById('bannerImg');

    function setThemeLocally(theme) {
        const link = document.querySelector('link[rel="stylesheet"]');
        if (link && themeMapCss[theme]) {
            link.href = themeMapCss[theme];
        }
        if (bannerImg && themeMapBanner[theme]) {
            bannerImg.src = themeMapBanner[theme];
        }
    }

    themeSelect?.addEventListener('change', async (e) => {
        const value = e.target.value;
        themeSelect.disabled = true;
        themeMsg.textContent = '...';
        const fd = new FormData();
        fd.append('theme', value);
        try {
            const resp = await fetch('/api/theme', {method: 'POST', body: fd, credentials: 'same-origin'});
            const data = await resp.json().catch(() => ({}));
            if (resp.ok && data.ok) {
                setThemeLocally(value);
                themeMsg.textContent = '<?= htmlspecialchars($t['theme_changed'], ENT_QUOTES) ?>';
                themeMsg.style.color = '#166534';
                setTimeout(() => themeMsg.textContent = '', 2000);
            } else {
                const msg = data.error || 'Error';
                themeMsg.textContent = msg;
                themeMsg.style.color = '#dc2626';
            }
        } catch (err) {
            themeMsg.textContent = 'Network error';
            themeMsg.style.color = '#dc2626';
        } finally {
            themeSelect.disabled = false;
        }
    });

    // Загрузка файла
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    const pdfFile = document.getElementById('pdfFile');

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const file = pdfFile.files[0];
        if (!file) {
            showUploadMessage('Выберите файл для загрузки');
            return;
        }

        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Загрузка...';
        document.getElementById('uploadMessage').style.display = 'none';

        const formData = new FormData(uploadForm);

        try {
            const response = await fetch('/api/upload', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            const result = await response.json().catch(() => ({}));

            if (response.ok && result.ok) {
                showUploadMessage('✅ Файл успешно загружен!', true);
                pdfFile.value = '';
                setTimeout(() => location.reload(), 1500);
            } else {
                const errorMsg = result.error || result.message || 'Ошибка при загрузке файла';
                showUploadMessage('❌ ' + errorMsg);
            }
        } catch (error) {
            showUploadMessage('❌ Ошибка соединения с сервером');
        } finally {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'OK';
        }
    });

    // Удаление файла
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if (!id) return;

            if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;

            const originalText = e.target.textContent;
            e.target.disabled = true;
            e.target.textContent = '...';

            try {
                const response = await fetch('/api/delete-pdf/' + id, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                const result = await response.json().catch(() => ({}));

                if (response.ok && result.ok) {
                    location.reload();
                } else {
                    const errorMsg = result.error || result.message || 'Ошибка при удалении файла';
                    alert('❌ ' + errorMsg);
                    e.target.disabled = false;
                    e.target.textContent = originalText;
                }
            } catch (error) {
                alert('❌ Ошибка соединения с сервером');
                e.target.disabled = false;
                e.target.textContent = originalText;
            }
        });
    });
</script>
</body>
</html>
