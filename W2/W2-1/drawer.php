<?php
	include 'functions.php';
	
	if (isset($_GET['num'])) {
		$num = (int)$_GET['num'];
		$shape = ($num & 0b11); // 0-3 биты
		$color = ($num >> 2) & 0b111; // 4-6 биты
		$width = ($num >> 7) & 0b111111111; // 7-15 биты
		$height = ($num >> 16) & 0b111111111; // 16-24 биты
		
		// Установим минимальные размеры для фигур
		$width = max($width, 50); // Минимальная ширина 50 пикселей
		$height = max($height, 50); // Минимальная высота 50 пикселей
		
		$svg = drawShape($shape, $color, $width, $height);
		echo $svg;
	} else {
		echo "Не указан код фигуры.";
	}

