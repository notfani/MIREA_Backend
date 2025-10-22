"""
Migration helper to add missing columns to existing tables
This will be run automatically on app startup
"""
from models import db
from sqlalchemy import text

def run_migrations():
    """Apply database schema migrations"""
    try:
        # Check if user_id column exists in pdf table
        result = db.session.execute(text(
            "SELECT column_name FROM information_schema.columns "
            "WHERE table_name='pdf' AND column_name='user_id'"
        ))

        if not result.fetchone():
            print("Running migration: Adding user_id and created_at to pdf table...")

            # Add user_id column
            db.session.execute(text(
                "ALTER TABLE pdf ADD COLUMN user_id INTEGER"
            ))

            # Add created_at column
            db.session.execute(text(
                "ALTER TABLE pdf ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
            ))

            # Add foreign key constraint
            db.session.execute(text(
                'ALTER TABLE pdf ADD CONSTRAINT fk_pdf_user '
                'FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE SET NULL'
            ))

            # Create index
            db.session.execute(text(
                "CREATE INDEX idx_pdf_user_id ON pdf(user_id)"
            ))

            db.session.commit()
            print("Migration completed successfully!")
        else:
            print("Migration already applied, skipping...")

    except Exception as e:
        print(f"Migration error (may be safe to ignore if already applied): {e}")
        db.session.rollback()

