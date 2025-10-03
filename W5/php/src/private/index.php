<?php
	// Приватная страница (доступна только после входа)
	require_once __DIR__ . '/../DB.php';
	require_once __DIR__ . '/../RedisClient.php';
	require_once __DIR__ . '/../Content.php';
	
	$uid = (int)($_COOKIE['uid'] ?? 0);
	if ($uid <= 0) {
		http_response_code(401);
		echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Требуется вход</title></head><body><h1>Требуется вход</h1><p>Пожалуйста, выполните вход через /api/login</p></body></html>';
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
	
	// Готовим список PDF пользователя
	$files = [];
	try {
		$db = DB::get();
		$st = $db->prepare('SELECT id, filename, uploaded_at FROM pdfs WHERE user_id = ? ORDER BY id DESC');
		$st->execute([$uid]);
		$files = $st->fetchAll();
	} catch (Throwable $e) {
		// Если БД недоступна — покажем страницу без списка, но не упадём
	}
	
	$greeting = $payload['greeting'] ?? ($lang === 'ru' ? 'Привет' : 'Hello');
	$title = $lang === 'ru' ? 'Личный кабинет' : 'Dashboard';
	$uploadLabel = $lang === 'ru' ? 'Загрузить PDF' : 'Upload PDF';
	$yourFiles = $lang === 'ru' ? 'Ваши файлы' : 'Your files';
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
            margin: 6px 0
        }</style>
</head>
<body>
<header>
    <main>
        <h1><?= htmlspecialchars($greeting, ENT_QUOTES) ?>!</h1>
        <img src="<?= htmlspecialchars($banner, ENT_QUOTES) ?>" alt="banner">
    </main>
</header>
<main>
    <section class="card" style="margin-top:16px;">
        <h2><?= htmlspecialchars($uploadLabel, ENT_QUOTES) ?></h2>
        <form action="/api/upload" method="post" enctype="multipart/form-data">
            <input type="file" name="pdf" accept="application/pdf" required>
            <button type="submit" class="btn">OK</button>
        </form>
    </section>

    <section class="card" style="margin-top:16px;">
        <h2><?= htmlspecialchars($yourFiles, ENT_QUOTES) ?></h2>
		<?php if (!$files): ?>
            <p class="text-muted">—</p>
		<?php else: ?>
            <ul class="files">
				<?php foreach ($files as $f): ?>
                    <li>
                        <a href="/api/pdf/<?= (int)$f['id'] ?>" target="_blank">
							<?= htmlspecialchars($f['filename'], ENT_QUOTES) ?>
                        </a>
                        <span class="text-muted">(<?= htmlspecialchars((string)$f['uploaded_at'], ENT_QUOTES) ?>)</span>
                    </li>
				<?php endforeach; ?>
            </ul>
		<?php endif; ?>
    </section>
</main>
</body>
</html>
