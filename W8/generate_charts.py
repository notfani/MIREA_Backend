import os
import json
from datetime import datetime, timedelta
import random

from faker import Faker
import matplotlib.pyplot as plt
import matplotlib
matplotlib.use('Agg')
from PIL import Image, ImageDraw, ImageFont
import numpy as np

fake = Faker('ru_RU')
Faker.seed(42)
random.seed(42)

FIXTURES_COUNT = 50
OUTPUT_DIR = 'static/charts'
WATERMARK_TEXT = 'MIREA Backend\nW8 Statistics'

def generate_fixtures():
    fixtures = []

    for i in range(FIXTURES_COUNT):
        fixture = {
            'id': i + 1,
            'name': fake.name(),
            'email': fake.email(),
            'age': random.randint(18, 65),
            'salary': random.randint(30000, 200000),
            'department': random.choice(['IT', 'HR', 'Sales', 'Marketing', 'Finance']),
            'city': fake.city(),
            'registration_date': (datetime.now() - timedelta(days=random.randint(1, 1000))).strftime('%Y-%m-%d'),
            'performance_score': round(random.uniform(60, 100), 2),
            'projects_completed': random.randint(0, 50)
        }
        fixtures.append(fixture)

    return fixtures

def add_watermark(image_path, text):
    img = Image.open(image_path).convert('RGBA')

    watermark = Image.new('RGBA', img.size, (255, 255, 255, 0))
    draw = ImageDraw.Draw(watermark)

    try:
        font = ImageFont.truetype('arial.ttf', 40)
    except:
        font = ImageFont.load_default()

    bbox = draw.textbbox((0, 0), text, font=font)
    text_width = bbox[2] - bbox[0]
    text_height = bbox[3] - bbox[1]

    width, height = img.size
    x = (width - text_width) // 2
    y = (height - text_height) // 2

    draw.text((x, y), text, fill=(128, 128, 128, 60), font=font)

    watermarked = Image.alpha_composite(img, watermark)
    watermarked = watermarked.convert('RGB')
    watermarked.save(image_path, 'PNG')

def create_chart_1(fixtures):
    departments = {}
    for f in fixtures:
        dept = f['department']
        if dept not in departments:
            departments[dept] = []
        departments[dept].append(f['salary'])

    fig, ax = plt.subplots(figsize=(10, 6))
    ax.boxplot(departments.values(), labels=departments.keys())
    ax.set_title('Распределение зарплат по отделам', fontsize=16, fontweight='bold')
    ax.set_xlabel('Отдел', fontsize=12)
    ax.set_ylabel('Зарплата (₽)', fontsize=12)
    ax.grid(True, alpha=0.3)
    plt.xticks(rotation=45)
    plt.tight_layout()

    output_path = os.path.join(OUTPUT_DIR, 'chart_salary_by_department.png')
    plt.savefig(output_path, dpi=150, bbox_inches='tight')
    plt.close()

    add_watermark(output_path, WATERMARK_TEXT)
    return 'chart_salary_by_department.png'

def create_chart_2(fixtures):
    ages = [f['age'] for f in fixtures]
    scores = [f['performance_score'] for f in fixtures]

    fig, ax = plt.subplots(figsize=(10, 6))
    scatter = ax.scatter(ages, scores, c=scores, cmap='viridis', alpha=0.6, s=100, edgecolors='black')
    ax.set_title('Зависимость производительности от возраста', fontsize=16, fontweight='bold')
    ax.set_xlabel('Возраст', fontsize=12)
    ax.set_ylabel('Оценка производительности', fontsize=12)
    ax.grid(True, alpha=0.3)

    z = np.polyfit(ages, scores, 1)
    p = np.poly1d(z)
    ax.plot(ages, p(ages), "r--", alpha=0.8, linewidth=2, label=f'Тренд: y={z[0]:.2f}x+{z[1]:.2f}')
    ax.legend()

    cbar = plt.colorbar(scatter, ax=ax)
    cbar.set_label('Производительность', rotation=270, labelpad=20)

    plt.tight_layout()
    output_path = os.path.join(OUTPUT_DIR, 'chart_performance_vs_age.png')
    plt.savefig(output_path, dpi=150, bbox_inches='tight')
    plt.close()

    add_watermark(output_path, WATERMARK_TEXT)
    return 'chart_performance_vs_age.png'

def create_chart_3(fixtures):
    departments = {}
    for f in fixtures:
        dept = f['department']
        if dept not in departments:
            departments[dept] = {'total': 0, 'count': 0}
        departments[dept]['total'] += f['projects_completed']
        departments[dept]['count'] += 1

    avg_projects = {dept: data['total'] / data['count'] for dept, data in departments.items()}

    fig, ax = plt.subplots(figsize=(10, 6))
    colors = plt.cm.Set3(range(len(avg_projects)))
    bars = ax.bar(avg_projects.keys(), avg_projects.values(), color=colors, edgecolor='black', linewidth=1.5)

    ax.set_title('Среднее количество завершенных проектов по отделам', fontsize=16, fontweight='bold')
    ax.set_xlabel('Отдел', fontsize=12)
    ax.set_ylabel('Среднее количество проектов', fontsize=12)
    ax.grid(True, alpha=0.3, axis='y')

    for bar in bars:
        height = bar.get_height()
        ax.text(bar.get_x() + bar.get_width()/2., height,
                f'{height:.1f}',
                ha='center', va='bottom', fontweight='bold')

    plt.xticks(rotation=45)
    plt.tight_layout()

    output_path = os.path.join(OUTPUT_DIR, 'chart_projects_by_department.png')
    plt.savefig(output_path, dpi=150, bbox_inches='tight')
    plt.close()

    add_watermark(output_path, WATERMARK_TEXT)
    return 'chart_projects_by_department.png'

def create_chart_4(fixtures):
    registrations_by_month = {}
    for f in fixtures:
        date = datetime.strptime(f['registration_date'], '%Y-%m-%d')
        month_key = date.strftime('%Y-%m')
        registrations_by_month[month_key] = registrations_by_month.get(month_key, 0) + 1

    sorted_months = sorted(registrations_by_month.items())
    months = [item[0] for item in sorted_months]
    counts = [item[1] for item in sorted_months]

    fig, ax = plt.subplots(figsize=(12, 6))
    ax.plot(months, counts, marker='o', linewidth=2, markersize=8, color='#2563eb')
    ax.fill_between(range(len(counts)), counts, alpha=0.3, color='#2563eb')

    ax.set_title('Регистрации сотрудников по месяцам', fontsize=16, fontweight='bold')
    ax.set_xlabel('Месяц', fontsize=12)
    ax.set_ylabel('Количество регистраций', fontsize=12)
    ax.grid(True, alpha=0.3)
    plt.xticks(rotation=45)
    plt.tight_layout()

    output_path = os.path.join(OUTPUT_DIR, 'chart_registrations_timeline.png')
    plt.savefig(output_path, dpi=150, bbox_inches='tight')
    plt.close()

    add_watermark(output_path, WATERMARK_TEXT)
    return 'chart_registrations_timeline.png'

def main():
    print("Начинаем генерацию фикстур и графиков...")

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    print(f"Генерируем {FIXTURES_COUNT} фикстур...")
    fixtures = generate_fixtures()

    fixtures_path = os.path.join(OUTPUT_DIR, 'fixtures.json')
    with open(fixtures_path, 'w', encoding='utf-8') as f:
        json.dump(fixtures, f, ensure_ascii=False, indent=2)
    print(f"Фикстуры сохранены в {fixtures_path}")

    print("Создаем графики...")
    charts = []

    print("  - График 1: Распределение зарплат по отделам (Box Plot)")
    charts.append(create_chart_1(fixtures))

    print("  - График 2: Зависимость производительности от возраста (Scatter Plot)")
    charts.append(create_chart_2(fixtures))

    print("  - График 3: Среднее количество проектов по отделам (Bar Chart)")
    charts.append(create_chart_3(fixtures))

    print("  - График 4: Временная шкала регистраций (Line Chart)")
    charts.append(create_chart_4(fixtures))

    charts_list_path = os.path.join(OUTPUT_DIR, 'charts_list.json')
    with open(charts_list_path, 'w', encoding='utf-8') as f:
        json.dump({'charts': charts, 'generated_at': datetime.now().isoformat()}, f, indent=2)

    print(f"Создано {len(charts)} графиков с водяными знаками!")
    print(f"Графики сохранены в {OUTPUT_DIR}")
    print("\nСписок созданных файлов:")
    for chart in charts:
        print(f"   - {chart}")

    return charts

if __name__ == '__main__':
    main()

