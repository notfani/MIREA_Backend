<?php
	function drawShape($shape, $color, $width, $height): string
	{
		$colors = ['red', 'green', 'blue', 'yellow', 'black'];
		$colorName = $colors[$color] ?? 'black';
		
		return match ($shape) {
			0 => "<svg width='$width' height='$height'>
                        <circle cx='" . ($width / 2) . "' cy='" . ($height / 2) . "' r='" . ($width / 2) . "' fill='$colorName' />
                    </svg>",
			1 => "<svg width='$width' height='$height'>
                        <rect width='$width' height='$height' fill='$colorName' />
                    </svg>",
			2 => "<svg width='$width' height='$height'>
                        <polygon points='" . ($width / 2) . ",0 " . ($width) . "," . $height . " 0," . $height . "' fill='$colorName' />
                    </svg>",
			3 => "<svg width='$width' height='$height'>
                        <rect width='$width' height='" . ($height / 2) . "' fill='$colorName' />
                    </svg>",
			default => "<svg width='$width' height='$height'>
                        <text x='10' y='20' fill='black'>Неизвестная форма</text>
                    </svg>",
		};
	}


