from flask import Blueprint, jsonify, session
import os

bp = Blueprint("charts", __name__, url_prefix="/api/charts")

@bp.get("/list")
def list_charts():
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"charts": [], "total": 0, "message": "Войдите для просмотра графиков"})

    chart_names = ['scatter.png', 'bar.png', 'hist.png']
    available = []

    if os.path.exists('/app/static/charts'):
        charts_dir = '/app/static/charts'
    elif os.path.exists('static/charts'):
        charts_dir = 'static/charts'
    else:
        charts_dir = os.path.join('app', 'static', 'charts')

    for name in chart_names:
        path = os.path.join(charts_dir, name)
        if os.path.exists(path):
            available.append(name)

    return jsonify({"charts": available, "total": len(available)})

@bp.post("/generate")
def generate_charts():
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Необходимо войти в аккаунт"}), 401

    try:
        from api.charts import plot_all
        plot_all()
        return jsonify({"status": "ok", "message": "Charts generated successfully"})
    except Exception as e:
        return jsonify({"error": str(e)}), 500
