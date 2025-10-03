<?php
	function shellSort(array &$array): void
	{
		$n = count($array);
		for ($gap = floor($n / 2); $gap > 0; $gap = floor($gap / 2)) {
			for ($i = $gap; $i < $n; $i++) {
				$temp = $array[$i];
				$j = $i;
				while ($j >= $gap && $array[$j - $gap] > $temp) {
					$array[$j] = $array[$j - $gap];
					$j -= $gap;
				}
				$array[$j] = $temp;
			}
		}
	}
	
	if (isset($_GET['array'])) {
		$input = $_GET['array'];
		$array = explode(',', $input);
		shellSort($array);
		echo "<h1>Отсортированный массив:</h1>";
		echo "<pre>" . implode(', ', $array) . "</pre>";
	} else {
		echo "<h1>Пожалуйста, передайте массив в параметре 'array'.</h1>";
	}

