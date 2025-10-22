import os, uuid
from flask import Blueprint, request, send_from_directory, jsonify, session
from config import Config
from crud import PDFRepo
bp = Blueprint("pdf", __name__, url_prefix="/api/pdf")
conf = Config()

@bp.post("/upload")
def upload():
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Необходимо войти в аккаунт"}), 401
    if 'file' not in request.files:
        return jsonify({"error": "Файл не выбран"}), 400
    file = request.files["file"]
    if not file.filename:
        return jsonify({"error": "Файл не выбран"}), 400
    if file.content_type != "application/pdf":
        return jsonify({"error": "Разрешены только PDF файлы"}), 400
    name = f"{uuid.uuid4().hex}.pdf"
    path = os.path.join(conf.UPLOAD_FOLDER, name)
    file.save(path)
    PDFRepo.create(file.filename, path, user_id=user_id)
    return jsonify({"status": "ok", "name": file.filename}), 201

@bp.get("/list")
def list_pdf():
    user_id = session.get('user_id')
    if not user_id:
        return jsonify([])

    pdfs = PDFRepo.list(user_id=user_id)
    return jsonify([{"id": p.id, "name": p.name, "created_at": p.created_at.isoformat() if p.created_at else None} for p in pdfs])

@bp.get("/download/<int:pdf_id>")
def download(pdf_id):
    user_id = session.get('user_id')
    if not user_id:
        return jsonify({"error": "Необходимо войти в аккаунт"}), 401

    p = PDFRepo.get_by_id(pdf_id, user_id=user_id)
    if not p:
        return jsonify({"error": "Файл не найден"}), 404

    return send_from_directory(directory=conf.UPLOAD_FOLDER,
                            path=os.path.basename(p.path),
                            as_attachment=True,
                            download_name=p.name)