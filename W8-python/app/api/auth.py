from flask import Blueprint, request, jsonify, session
from models import User
from crud import UserRepo

bp = Blueprint('auth', __name__)

@bp.route('/api/register', methods=['POST'])
def register():
    data = request.get_json() or {}
    email = (data.get('email') or '').strip()
    password = data.get('password') or ''
    if not email or not password:
        return jsonify({'error': 'Email and password required'}), 400
    if len(password) < 6:
        return jsonify({'error': 'Password must be at least 6 characters'}), 400
    user = UserRepo.create(email, password)
    if not user:
        return jsonify({'error': 'User already exists'}), 409
    session['user_id'] = user.id
    return jsonify({'ok': True, 'user': {'id': user.id, 'email': user.email}}), 201

@bp.route('/api/login', methods=['POST'])
def login():
    data = request.get_json() or {}
    email = (data.get('email') or '').strip()
    password = data.get('password') or ''
    if not email or not password:
        return jsonify({'error': 'Email and password required'}), 400
    user = UserRepo.authenticate(email, password)
    if not user:
        return jsonify({'error': 'Invalid credentials'}), 401
    session['user_id'] = user.id
    return jsonify({'ok': True, 'user': {'id': user.id, 'email': user.email}})

@bp.route('/api/logout', methods=['POST'])
def logout():
    session.pop('user_id', None)
    return jsonify({'ok': True})

@bp.route('/api/me', methods=['GET'])
def me():
    uid = session.get('user_id')
    if not uid:
        return jsonify({'user': None})
    user = User.query.get(uid)
    if not user:
        return jsonify({'user': None})
    return jsonify({'user': {'id': user.id, 'email': user.email}})
