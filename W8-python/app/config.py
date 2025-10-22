import os
from pathlib import Path


class Config:
    _instance = None

    def __new__(cls):
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            default_db = os.getenv("DATABASE_URL") or f"sqlite:///{os.path.join(os.getcwd(), 'app.db')}"
            cls._instance.SQLALCHEMY_DATABASE_URI = default_db

            upload_folder = os.getenv("UPLOAD_FOLDER") or os.path.join(os.getcwd(), "app", "static", "uploads")
            cls._instance.UPLOAD_FOLDER = upload_folder
            Path(cls._instance.UPLOAD_FOLDER).mkdir(parents=True, exist_ok=True)
        return cls._instance