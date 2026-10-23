"""KBO/BCE open data for the CLUK app.

probe:  log in to the KBO open data portal and list the files offered
        (names only, nothing about the account).
update: download the newest Full extract, keep only the enterprise numbers
        in numbers.json and write kbo.json for `php artisan cluk:kbo-import`.

Only the standard library; credentials come from KBO_USER / KBO_PASSWORD.
"""
import csv, html.parser, http.cookiejar, io, json, os, re, sys, urllib.parse, urllib.request, zipfile

BASE = 'https://kbopub.economie.fgov.be'
LOGIN = BASE + '/kbo-open-data/login?lang=en'
UA = 'CLUK-app KBO update (github actions)'


class Forms(html.parser.HTMLParser):
    def __init__(self):
        super().__init__()
        self.forms, self.links, self._f = [], [], None

    def handle_starttag(self, tag, a):
        a = dict(a)
        if tag == 'form':
            self._f = {'action': a.get('action', ''), 'method': (a.get('method') or 'get').lower(), 'inputs': []}
            self.forms.append(self._f)
        elif tag == 'input' and self._f is not None:
            self._f['inputs'].append(a)
        elif tag == 'a' and a.get('href'):
            self.links.append(a['href'])

    def handle_endtag(self, tag):
        if tag == 'form':
            self._f = None


def session():
    jar = http.cookiejar.CookieJar()
    op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    op.addheaders = [('User-Agent', UA)]
    return op


def get(op, url, data=None):
    r = op.open(url, data=urllib.parse.urlencode(data).encode() if data is not None else None, timeout=120)
    return r.geturl(), r.read().decode('utf-8', 'replace')


def login(op):
    url, page = get(op, LOGIN)
    p = Forms(); p.feed(page)
    form = next((f for f in p.forms if any(i.get('type') == 'password' for i in f['inputs'])), None)
    if not form:
        sys.exit('No login form found on ' + url)
    data = {}
    for i in form['inputs']:
        name, typ = i.get('name'), (i.get('type') or 'text').lower()
        if not name:
            continue
        if typ == 'password':
            data[name] = os.environ['KBO_PASSWORD']
        elif typ in ('text', 'email') and not i.get('value'):
            data[name] = os.environ['KBO_USER']
        elif typ in ('hidden', 'submit') or i.get('value'):
            data[name] = i.get('value', '')
    print('login form fields:', sorted(data.keys()))
    after, page = get(op, urllib.parse.urljoin(url, form['action']), data)
    p = Forms(); p.feed(page)
    if any(any(i.get('type') == 'password' for i in f['inputs']) for f in p.forms):
        sys.exit('Login refused: check KBO_USER and KBO_PASSWORD (and that the licence was accepted on the portal).')
    print('logged in, landed on', urllib.parse.urlparse(after).path)
    return after, page


def find_zips(op, start, page):
    """Walk the portal pages (max 25) and collect links to .zip files."""
    seen, todo, zips = set(), [(start, page)], {}
    while todo and len(seen) < 25:
        url, body = todo.pop(0)
        if url in seen:
            continue
        seen.add(url)
        p = Forms(); p.feed(body)
        for h in p.links:
            full = urllib.parse.urljoin(url, h)
            path = urllib.parse.urlparse(full).path
            if not full.startswith(BASE + '/kbo-open-data') or 'logout' in path:
                continue
            if path.lower().endswith('.zip') or 'files/' in path and re.search(r'KboOpenData_\d+', path):
                zips[path.rsplit('/', 1)[-1]] = full
            elif full not in seen and len(seen) + len(todo) < 25:
                try:
                    todo.append(get(op, full))
                except Exception as e:  # noqa
                    print('skip', path, e)
    print('pages looked at:', sorted(urllib.parse.urlparse(u).path + ('?' + urllib.parse.urlparse(u).query if urllib.parse.urlparse(u).query else '') for u in seen))
    return zips


def latest_full(zips):
    full = sorted((n for n in zips if n.endswith('_Full.zip')), key=lambda n: int(re.search(r'KboOpenData_(\d+)', n).group(1)))
    if not full:
        sys.exit('No Full extract found.')
    return full[-1]


def rows(z, name):
    f = next((n for n in z.namelist() if n.lower() == name), None)
    if not f:
        return
    with z.open(f) as b:
        yield from csv.DictReader(io.TextIOWrapper(b, 'utf-8-sig'))


def filter_extract(path, numbers):
    want = set(numbers)
    out = {}
    with zipfile.ZipFile(path) as z:
        meta = {}
        for r in rows(z, 'meta.csv'):
            if 'Variable' in r:
                meta[r['Variable']] = r.get('Value', '')
            else:
                meta.update(r)
        for r in rows(z, 'enterprise.csv'):
            n = r['EnterpriseNumber']
            if n in want:
                out[n] = {'status': r.get('Status'), 'situation': r.get('JuridicalSituation'), 'form': r.get('JuridicalForm'), 'start': r.get('StartDate')}
        names = {}
        for r in rows(z, 'denomination.csv'):
            n = r['EntityNumber']
            if n in out and r.get('TypeOfDenomination') == '001':
                # Prefer Dutch (2), then French (1), then any.
                rank = {'2': 0, '1': 1}.get(r.get('Language'), 2)
                if n not in names or rank < names[n][0]:
                    names[n] = (rank, r['Denomination'])
        for n, (_, v) in names.items():
            out[n]['name'] = v
        for r in rows(z, 'address.csv'):
            n = r['EntityNumber']
            if n in out and r.get('TypeOfAddress') == 'REGO' and 'address' not in out[n]:
                street = (r.get('StreetNL') or r.get('StreetFR') or '').strip()
                num = ' '.join(x for x in [r.get('HouseNumber', '').strip(), ('bus ' + r['Box'].strip()) if r.get('Box', '').strip() else ''] if x)
                town = (r.get('MunicipalityNL') or r.get('MunicipalityFR') or '').strip()
                out[n]['address'] = ', '.join(x for x in [' '.join(x for x in [street, num] if x), ' '.join(x for x in [r.get('Zipcode', '').strip(), town] if x)] if x)
        codes = {}
        for r in rows(z, 'code.csv'):
            cat = r.get('Category')
            if cat in ('JuridicalSituation', 'JuridicalForm'):
                codes.setdefault(cat, {}).setdefault(r['Code'], {})[r.get('Language', '').lower()] = r.get('Description')
    snap = meta.get('SnapshotDate', '')
    if re.match(r'^\d{2}-\d{2}-\d{4}$', snap):
        d, m, y = snap.split('-'); snap = f'{y}-{m}-{d}'
    return {'snapshot': snap, 'extract': meta.get('ExtractNumber'), 'numbers': sorted(want), 'enterprises': out, 'codes': codes}


def main():
    mode = sys.argv[1]
    op = session()
    start, page = login(op)
    zips = find_zips(op, start, page)
    print('zip files offered:', sorted(zips))
    if mode == 'probe':
        return
    name = latest_full(zips)
    print('downloading', name)
    with op.open(zips[name], timeout=1800) as r, open('kbo.zip', 'wb') as f:
        while chunk := r.read(1 << 20):
            f.write(chunk)
    print('size MB', round(os.path.getsize('kbo.zip') / 1e6))
    numbers = json.load(open('numbers.json'))
    data = filter_extract('kbo.zip', numbers)
    json.dump(data, open('kbo.json', 'w'), ensure_ascii=False)
    print(f"snapshot {data['snapshot']}: {len(data['enterprises'])} of {len(numbers)} numbers found")
    sit = data['codes'].get('JuridicalSituation', {})
    print('juridical situations:', '; '.join(f"{k}={v.get('nl') or v.get('fr')}" for k, v in sorted(sit.items())))


if __name__ == '__main__':
    main()
