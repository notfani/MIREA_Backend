<?php
    // Приватная страница (доступна только после входа)
    require_once __DIR__ . '/../DB.php';
    require_once __DIR__ . '/../RedisClient.php';
    require_once __DIR__ . '/../Content.php';

    $uid = (int)($_COOKIE['uid'] ?? 0);
    if ($uid <= 0) {
        http_response_code(401);
        echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Требуется вход</title></head><body><h1>Требуется вход</h1><p>Пожалуйста, выполните вход через главную страницу.</p><p><a href="/">На страницу входа</a></p></body></html>';
        exit;
    }

    // Получаем персональный контент (кешируется в Redis)
    $payload = json_decode(Content::personal(), true) ?: [];
    $theme = $payload['theme'] ?? 'light';
    $banner = $payload['banner'] ?? '/static/light.svg';
    $lang = $_COOKIE['lang'] ?? 'en';

    $cssMap = [
            'light' => '/css/light.css',
            'dark' => '/css/dark.css',
            'colorblind' => '/css/colorblind.css',
    ];
    $cssHref = $cssMap[$theme] ?? $cssMap['light'];

    // Достаём логин пользователя
    $login = 'user';
    try {
        $db = DB::get();
        $stU = $db->prepare('SELECT login FROM users WHERE id=?');
        $stU->execute([$uid]);
        $row = $stU->fetch();
        if ($row && isset($row['login'])) $login = $row['login'];
    } catch (Throwable $e) {
    }

    // Готовим список PDF пользователя
    $files = [];
    try {
        $db = DB::get();
        $st = $db->prepare('SELECT id, filename, original_name, uploaded_at FROM pdfs WHERE user_id = ? ORDER BY id DESC');
        $st->execute([$uid]);
        $files = $st->fetchAll();
    } catch (Throwable $e) {
        // Если БД недоступна — покажем страницу без списка
    }

    $greeting = $payload['greeting'] ?? ($lang === 'ru' ? 'Привет' : 'Hello');
    $title = $lang === 'ru' ? 'Личный кабинет' : 'Dashboard';
    $uploadLabel = $lang === 'ru' ? 'Загрузить PDF' : 'Upload PDF';
    $yourFiles = $lang === 'ru' ? 'Ваши файлы' : 'Your files';
    $logoutText = $lang === 'ru' ? 'Выйти' : 'Logout';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">
    <style>main {
            max-width: 960px;
            margin: 24px auto;
            padding: 0 16px;
        }

        header img {
            max-width: 100%;
            height: auto;
            border-radius: 8px
        }

        ul.files {
            list-style: none;
            padding: 0
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
            padding: 4px 8px;
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
            opacity: .85
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
            background: #b91c1c
        }

        button.logout:active {
            background: #991b1b
        }

        #uploadMessage {
            display: none;
            margin-top: 12px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<header>
    <main>
        <div class="topbar">
            <div class="userbox">👤 <?= htmlspecialchars($login, ENT_QUOTES) ?> (id <?= (int)$uid ?>)</div>
            <button class="logout" id="btnLogout"
                    type="button"><?= htmlspecialchars($logoutText, ENT_QUOTES) ?></button>
        </div>
        <h1><?= htmlspecialchars($greeting, ENT_QUOTES) ?>!</h1>
        <img src="<?= htmlspecialchars($banner, ENT_QUOTES) ?>" alt="banner">
    </main>
</header>
<main>
    <section class="card" style="margin-top:16px;">
        <h2><?= htmlspecialchars($uploadLabel, ENT_QUOTES) ?></h2>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="pdf" id="pdfFile" accept="application/pdf" required>
            <button type="submit" class="btn" id="uploadBtn">OK</button>
        </form>
        <div id="uploadMessage"></div>
    </section>

    <section class="card" style="margin-top:16px;">
        <h2><?= htmlspecialchars($yourFiles, ENT_QUOTES) ?></h2>
        <?php if (!$files): ?>
            <p class="text-muted">—</p>
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
                            <div class="file-date"><?= htmlspecialchars((string)$f['uploaded_at'], ENT_QUOTES) ?></div>
                        </div>
                        <button class="delete-btn" data-id="<?= (int)$f['id'] ?>">Удалить</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<script>
    const btn = document.getElementById('btnLogout');
    btn?.addEventListener('click', async () => {
        try {
            const r = await fetch('/api/logout', {method: 'POST', credentials: 'same-origin'});
            // игнорируем ответ, чистим клиентские куки (на случай несоответствий) и редиректим
            ['uid', 'theme', 'lang'].forEach(n => document.cookie = n + '=; Max-Age=0; path=/');
        } catch (e) {
        }
        location.href = '/';
    });

    // Обработка отправки формы загрузки
    const uploadForm = document.getElementById('uploadForm');
    const uploadMessage = document.getElementById('uploadMessage');
    const uploadBtn = document.getElementById('uploadBtn');
    const pdfFile = document.getElementById('pdfFile');

    function showUploadMessage(text, isSuccess = false) {
        uploadMessage.textContent = text;
        uploadMessage.style.display = 'block';
        uploadMessage.style.backgroundColor = isSuccess ? '#dcfce7' : '#fef2f2';
        uploadMessage.style.color = isSuccess ? '#166534' : '#dc2626';
        uploadMessage.style.border = `1px solid ${isSuccess ? '#bbf7d0' : '#fecaca'}`;
    }

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const file = pdfFile.files[0];
        if (!file) {
            showUploadMessage('Выберите файл для загрузки');
            return;
        }

        // Показываем индикатор загрузки
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Загрузка...';
        uploadMessage.style.display = 'none';

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
                pdfFile.value = ''; // Очищаем поле выбора файла
                // Обновляем список файлов через 1.5 секунды
                setTimeout(() => location.reload(), 1500);
            } else {
                const errorMsg = result.message || 'Ошибка при загрузке файла';
                showUploadMessage('❌ ' + errorMsg);
            }
        } catch (error) {
            showUploadMessage('❌ Ошибка соединения с сервером');
        } finally {
            // Восстанавливаем кнопку
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'OK';
        }
    });

    // Обработка удаления файлов
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            if (!id) return;

            if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;

            try {
                const response = await fetch('/api/delete-pdf/' + id, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                const result = await response.json().catch(() => ({}));

                if (response.ok && result.ok) {
                    // Успешно удалено, перезагружаем список файлов
                    location.reload();
                } else {
                    const errorMsg = result.message || 'Ошибка при удалении файла';
                    alert('❌ ' + errorMsg);
                }
            } catch (error) {
                alert('❌ Ошибка соединения с сервером');
            }
        });
    });
</script>
</body>
</html>
