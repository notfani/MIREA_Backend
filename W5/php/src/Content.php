<?php
	
	class Content
	{
		static function personal()
		{
			$uid = $_COOKIE['uid'] ?? 0;
			$theme = $_COOKIE['theme'] ?? 'light';
			$lang = $_COOKIE['lang'] ?? 'en';
			
			$redis = RedisClient::get();
			$key = "content:$uid:$theme:$lang";
			$cached = $redis->get($key);
			if ($cached) return $cached;
			
			$greeting = $lang === 'ru' ? 'Привет' : 'Hello';
			$banner = '/static/light.svg';
			if ($theme === 'dark') $banner = '/static/dark.svg';
			elseif ($theme === 'colorblind') $banner = '/static/cb.svg';
			
			$out = json_encode([
				'greeting' => $greeting,
				'theme' => $theme,
				'banner' => $banner
			]);
			$redis->setex($key, 60, $out); // 1 мин кеш
			return $out;
		}
	}
