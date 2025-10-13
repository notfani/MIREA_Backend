<?php
	
	/**
	 * Репозиторий для работы с PDF файлами
	 */
	class PdfRepository
	{
		private PDO $db;
		
		public function __construct()
		{
			$this->db = DB::get();
		}
		
		/**
		 * Найти файл по ID
		 */
		public function findById(int $id): ?array
		{
			try {
				$stmt = $this->db->prepare("SELECT * FROM pdfs WHERE id = ?");
				$stmt->execute([$id]);
				$file = $stmt->fetch();
				return $file ?: null;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to find PDF by id', [
					'id' => $id,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Найти файл по ID и пользователю
		 */
		public function findByIdAndUser(int $id, int $userId): ?array
		{
			try {
				$stmt = $this->db->prepare("SELECT * FROM pdfs WHERE id = ? AND user_id = ?");
				$stmt->execute([$id, $userId]);
				$file = $stmt->fetch();
				return $file ?: null;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to find PDF by id and user', [
					'id' => $id,
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Получить все файлы пользователя
		 */
		public function findByUserId(int $userId): array
		{
			try {
				$stmt = $this->db->prepare(
					"SELECT id, filename, original_name, uploaded_at
                 FROM pdfs
                 WHERE user_id = ?
                 ORDER BY uploaded_at DESC"
				);
				$stmt->execute([$userId]);
				return $stmt->fetchAll();
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to find PDFs by user', [
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Создать запись о файле
		 */
		public function create(int $userId, string $filename, string $originalName): int
		{
			try {
				$stmt = $this->db->prepare(
					"INSERT INTO pdfs(user_id, filename, original_name) VALUES (?, ?, ?) RETURNING id"
				);
				$stmt->execute([$userId, $filename, $originalName]);
				$result = $stmt->fetch();
				
				$id = $result['id'] ?? 0;
				
				Logger::getInstance()->info('PDF record created', [
					'id' => $id,
					'userId' => $userId,
					'originalName' => $originalName
				]);
				
				return $id;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to create PDF record', [
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
		
		/**
		 * Удалить файл
		 */
		public function delete(int $id, int $userId): bool
		{
			try {
				$stmt = $this->db->prepare("DELETE FROM pdfs WHERE id = ? AND user_id = ?");
				$result = $stmt->execute([$id, $userId]);
				
				Logger::getInstance()->info('PDF record deleted', [
					'id' => $id,
					'userId' => $userId
				]);
				
				return $result;
			} catch (PDOException $e) {
				Logger::getInstance()->error('Failed to delete PDF record', [
					'id' => $id,
					'userId' => $userId,
					'error' => $e->getMessage()
				]);
				throw new RuntimeException('Database error');
			}
		}
	}
