import html, base64, sys
from playwright.sync_api import sync_playwright
logo = base64.b64encode(open('../../public/images/cluk-logo.png','rb').read()).decode()
e = html.escape
def page(D):
    out = []
    n = 0
    for num, ctitle, arts in D['chapters']:
        out.append(f'<h2><span>{e(D["chapter"])} {num}</span>{e(ctitle)}</h2>')
        for atitle, paras in arts:
            if atitle:
                n += 1
                out.append(f'<h3>{e(D["article"])} {n} · {e(atitle)}</h3>')
            items = []
            for p in paras:
                if isinstance(p, list):
                    items.append('<li>' + e(p[0]) + '<ul>' + ''.join(f'<li>{e(x)}</li>' for x in p[1]) + '</ul></li>')
                else:
                    items.append(f'<li>{e(p)}</li>')
            out.append('<ol>' + ''.join(items) + '</ol>')
    return f'''<!doctype html><html><head><meta charset="utf-8"><style>
@page {{ size: A4; margin: 18mm 18mm 20mm; }}
body {{ font-family: Arial, Helvetica, sans-serif; color: #222624; font-size: 9.6pt; line-height: 1.45; margin: 0; }}
.head {{ display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2.5px solid #52ae32; padding-bottom: 10px; margin-bottom: 18px; }}
.head img {{ height: 34px; }}
.head .tag {{ font-size: 7pt; letter-spacing: 2px; color: #676e69; font-weight: bold; margin-top: 4px; }}
.head .co {{ text-align: right; font-size: 8pt; color: #676e69; line-height: 1.5; }}
h1 {{ font-size: 21pt; margin: 0 0 2px; letter-spacing: -0.3px; }}
.ver {{ color: #676e69; font-size: 9pt; margin-bottom: 14px; }}
.cols {{ column-count: 2; column-gap: 9mm; }}
h2 {{ font-size: 11.5pt; color: #327b1d; margin: 14px 0 6px; break-after: avoid; column-span: none; }}
h2 span {{ display: block; font-size: 7pt; letter-spacing: 1.6px; text-transform: uppercase; color: #676e69; }}
h3 {{ font-size: 9.6pt; margin: 10px 0 3px; break-after: avoid; }}
ol {{ margin: 0; padding-left: 16px; }}
ol > li {{ margin-bottom: 3px; }}
ul {{ margin: 2px 0 0; padding-left: 14px; list-style: '– '; }}
.closing {{ margin-top: 16px; padding: 9px 12px; background: #edf6e8; border-left: 3px solid #52ae32; font-weight: bold; break-inside: avoid; }}
</style></head><body>
<div class="head"><div><img src="data:image/png;base64,{logo}"><div class="tag">CONNECTING THE DOTS</div></div><div class="co">{e(D["company"]).replace(" · ", "<br>", 1)}</div></div>
<h1>{e(D["title"])}</h1><div class="ver">{e(D["version"])}</div>
<div class="cols">{"".join(out)}<div class="closing">{e(D["closing"])}</div></div>
</body></html>'''

names = {'en': 'CLUK_BV_Terms_and_conditions_EN', 'nl': 'CLUK_BV_Algemene_voorwaarden_NL', 'fr': 'CLUK_BV_Conditions_generales_FR', 'es': 'CLUK_BV_Condiciones_generales_ES'}
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium')
    for loc, name in names.items():
        ns = {}; exec(open(f'content_{loc}.py').read(), ns); D = ns['DOC']
        open(f'{name}.html', 'w').write(page(D))
        pg = b.new_page(); pg.goto('file://' + __import__('os').path.abspath(f'{name}.html'))
        pg.pdf(path=f'{name}.pdf', format='A4', print_background=True, display_header_footer=True,
               header_template='<span></span>',
               footer_template=f'<div style="width:100%;font-family:Arial;font-size:7pt;color:#676e69;padding:0 18mm;display:flex;justify-content:space-between"><span>CLUK BV · {e(D["title"])} · {e(D["version"])}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>',
               margin={'top': '16mm', 'bottom': '18mm', 'left': '16mm', 'right': '16mm'})
        pg.close(); print(name)
    b.close()
