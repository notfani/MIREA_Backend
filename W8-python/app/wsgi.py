from run import create_app

# Expose 'app' WSGI application for servers like gunicorn when building from ./app
app = create_app()

