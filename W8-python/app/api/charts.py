import os

def plot_all():
    try:
        import pandas as pd
        import matplotlib
        matplotlib.use('Agg')
        import matplotlib.pyplot as plt
        from PIL import Image
    except Exception as e:
        print(f"Skipping chart generation - dependencies not available: {e}")
        return

    from models import Fixture
    import tempfile

    try:
        fixtures = db.session.query(Fixture).all()
        if not fixtures:
            print("No data found in Fixture table, skipping chart generation")
            return
        data = pd.DataFrame([{
            'id': f.id,
            'f1': f.f1,
            'f2': f.f2,
            'f3': f.f3,
            'f4': f.f4,
            'f5': f.f5
        } for f in fixtures])
        if os.path.exists('/app/static'):
            out_dir = '/app/static/charts'
        elif os.path.exists('static'):
            out_dir = 'static/charts'
        else:
            out_dir = os.path.join('app', 'static', 'charts')
        os.makedirs(out_dir, exist_ok=True)
        print(f"Generating charts in: {out_dir}")
        watermark_path = 'watermark.png'
        watermark = None
        if os.path.exists(watermark_path):
            try:
                watermark = Image.open(watermark_path).convert('RGBA')
            except Exception:
                watermark = None
        for idx, (kind, name) in enumerate([
            ("scatter", "scatter.png"),
            ("bar",     "bar.png"),
            ("hist",    "hist.png")
        ], 1):
            plt.figure(figsize=(5,4))
            if kind == "scatter":
                plt.scatter(data.f1, data.f2)
                plt.xlabel('F1')
                plt.ylabel('F2')
                plt.title('Scatter Plot')

            elif kind == "bar":
                data.f4.value_counts().head(10).plot.bar()
                plt.xlabel('F4 Values')
                plt.ylabel('Count')
                plt.title('Bar Chart')

            else:
                plt.hist(data.f1, bins=10)
                plt.xlabel('F1')
                plt.ylabel('Frequency')
                plt.title('Histogram')

            plt.tight_layout()
            output_path = os.path.join(out_dir, name)
            if watermark is None:
                plt.savefig(output_path, dpi=100, bbox_inches='tight')
                plt.close()
                print(f"Generated chart: {output_path}")
                continue

            tmp = os.path.join(tempfile.gettempdir(), name)
            plt.savefig(tmp, dpi=100, bbox_inches='tight')
            plt.close()

            try:
                with Image.open(tmp) as base:
                    base = base.convert('RGBA')
                    wm = watermark.resize((int(base.width*.3), int(base.height*.3)))
                    transparent = Image.new('RGBA', base.size, (0,0,0,0))
                    transparent.paste(base, (0,0))
                    transparent.paste(wm,
                                    (base.width-wm.width-10,
                                        base.height-wm.height-10),
                                    wm)
                    transparent.save(output_path)
                    print(f"Generated chart with watermark: {output_path}")
            except Exception as e:
                print(f"Watermark failed, saving without: {e}")
                try:
                    from shutil import copyfile
                    copyfile(tmp, output_path)
                except Exception:
                    pass

        print(f"Chart generation complete! 3 charts created.")
    except Exception as e:
        print(f"Error generating charts: {e}")
        import traceback
        traceback.print_exc()
