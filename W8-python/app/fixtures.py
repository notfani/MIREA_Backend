import random, string
from models import Fixture

def generate():
    fixtures = []
    for _ in range(50):
        fixtures.append(Fixture(
            f1=random.uniform(0,100),
            f2=random.uniform(0,100),
            f3=random.uniform(0,100),
            f4=''.join(random.choices(string.ascii_lowercase, k=5)),
            f5=random.choice([True,False])
        ))
    return fixtures