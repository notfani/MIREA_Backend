from flask import Blueprint, request
from crud import ThemeRepo
bp = Blueprint("theme", __name__, url_prefix="/api/theme")
@bp.post("/set")
def set_theme():
    data = request.get_json()
    ThemeRepo.set(data["theme"], data.get("user", "default"))
    return {"status": "ok"}
@bp.get("/get")
def get_theme():
    t = ThemeRepo.get(request.args.get("user", "default"))
    return {"theme": t.name if t else "light"}