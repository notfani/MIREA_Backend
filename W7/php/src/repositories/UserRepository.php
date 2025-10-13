<?php
	
	/**
	 * Репозиторий для работы с пользователями
	 */
	class UserRepository
	{
		private PDO $db;
		
		public function __construct()
		{
			$this->db = DB::get();
		}
		
		/**
		 * Найти пользователя по логину
		 */
		public function findByLogin(string $login): ?array
		{
			try {
				$stmt = $this->db->prepare("SELECT * FROM users WHERE login = ?");
				$stmt->execute([$login]);
				$user = $stmt->fetch();
				return $user ?: null;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to find user by login', [
					'login' => $login,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Найти пользователя по ID
		 */
		public function findById(int $id): ?array
		{
			try {
				$stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
				$stmt->execute([$id]);
				$user = $stmt->fetch();
				return $user ?: null;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to find user by id', [
					'id' => $id,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Создать нового пользователя
		 */
		public function create(string $login, string $passwordHash): bool
		{
			try {
				$stmt = $this->db->prepare(
					"INSERT INTO users(login, pwd_hash, theme, lang) VALUES (?, ?, 'light', 'ru')"
				);
				$result = $stmt->execute([$login, $passwordHash]);
				
				Logger::getInstance()->info('User created', ['login' => $login]);
				
				return $result;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to create user', [
					'login' => $login,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Обновить тему пользователя
		 */
		public function updateTheme(int $userId, string $theme): bool
		{
			try {
				$stmt = $this->db->prepare("UPDATE users SET theme = ? WHERE id = ?");
				$result = $stmt->execute([$theme, $userId]);
				
				Logger::getInstance()->info('User theme updated', [
					'userId' => $userId,
					'theme' => $theme
				]);
				
				return $result;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to update user theme', [
					'userId' => $userId,
					'theme' => $theme,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Обновить язык пользователя
		 */
		public function updateLanguage(int $userId, string $language): bool
		{
			try {
				$stmt = $this->db->prepare("UPDATE users SET lang = ? WHERE id = ?");
				$result = $stmt->execute([$language, $userId]);
				
				Logger::getInstance()->info('User language updated', [
					'userId' => $userId,
					'language' => $language
				]);
				
				return $result;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to update user language', [
					'userId' => $userId,
					'language' => $language,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
	}
