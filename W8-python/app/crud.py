from models import db, PDF, Theme, Fixture, User
class PDFRepo:
    @staticmethod
    def create(name, path, user_id=None):
        p = PDF(name=name, path=path, user_id=user_id)
        db.session.add(p); db.session.commit(); return p

    @staticmethod
    def list(user_id=None):
        if user_id:
            return PDF.query.filter_by(user_id=user_id).order_by(PDF.created_at.desc()).all()
        return PDF.query.all()

    @staticmethod
    def get_by_id(pdf_id, user_id=None):
        if user_id:
            return PDF.query.filter_by(id=pdf_id, user_id=user_id).first()
        return PDF.query.get(pdf_id)

class ThemeRepo:
    @staticmethod
    def set(name, user="default"):
        t = Theme.query.filter_by(user=user).first()
        if t: t.name = name
        else: t = Theme(name=name, user=user)
        db.session.add(t); db.session.commit()
        return t
    @staticmethod
    def get(user="default"):
        return Theme.query.filter_by(user=user).first()
class FixtureRepo:
    @staticmethod
    def bulk_create(objects):
        db.session.bulk_save_objects(objects)
        db.session.commit()
    @staticmethod
    def all():
        return Fixture.query.all()
class UserRepo:
    @staticmethod
    def create(email, password):
        if User.query.filter_by(email=email).first():
            return None
        u = User(email=email)
        u.set_password(password)
        db.session.add(u)
        db.session.commit()
        return u

    @staticmethod
    def get_by_email(email):
        return User.query.filter_by(email=email).first()

    @staticmethod
    def authenticate(email, password):
        u = UserRepo.get_by_email(email)
        if not u: return None
        return u if u.check_password(password) else None
