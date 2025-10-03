<?php
// Получаем информацию о сервере
	$ls = shell_exec('ls -la');
	$ps = shell_exec('ps aux');
	$whoami = shell_exec('whoami');
	$id = shell_exec('id');

?>

    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Информация о сервере</title>
        <style>
            body {
                font-family: Arial, sans-serif;
            }

            pre {
                background: #f4f4f4;
                padding: 10px;
            }
        </style>
    </head>
    <body>
    <h1>Информация о сервере</h1>
    <h2>Содержимое директории:</h2>
    <pre><?php echo $ls; ?></pre>

    <h2>Запущенные процессы:</h2>
    <pre><?php echo $ps; ?></pre>

    <h2>Текущий пользователь:</h2>
    <pre><?php echo $whoami; ?></pre>

    <h2>Информация о пользователе:</h2>
    <pre><?php echo $id; ?></pre>
    </body>
    </html>
<?php
