from flask import Flask
import os
from config import Config
from models import db, Fixture
from crud import FixtureRepo
from fixtures import generate


def create_app():
    app = Flask(__name__, static_folder="static", template_folder="templates")
    cfg = Config()
    app.config.from_object(cfg)
    app.config["SQLALCHEMY_DATABASE_URI"] = cfg.SQLALCHEMY_DATABASE_URI
    app.secret_key = os.getenv("SECRET_KEY", "dev-secret-key")
    db.init_app(app)

    with app.app_context():
        db.create_all()

        # Run migrations for existing tables
        try:
            from migrate_db import run_migrations
            run_migrations()
        except Exception as e:
            print(f"Migration warning: {e}")

        # During tests or in light-weight runs we may want to skip heavy initialization
        if not os.getenv('SKIP_INIT'):
            try:
                # Generate fixtures if they don't exist
                if not Fixture.query.first():
                    FixtureRepo.bulk_create(generate())
                    print("Fixtures generated successfully")

                # Always try to generate charts (will skip if dependencies missing)
                from api.charts import plot_all
                plot_all()
            except Exception as e:
                # If optional deps (pandas/matplotlib) are missing, ignore during init
                print(f"Initialization warning: {e}")

    # Register blueprints
    from api.pdf import bp as pdf_bp
    from api.theme import bp as theme_bp
    from api.auth import bp as auth_bp
    from api.charts_api import bp as charts_bp

    app.register_blueprint(pdf_bp)
    app.register_blueprint(theme_bp)
    app.register_blueprint(auth_bp)
    app.register_blueprint(charts_bp)

    @app.route('/')
    def index():
        # serve the single-page app index
        return app.send_static_file('index.html')

    return app


app = create_app()
if __name__ == "__main__":
    app.run()