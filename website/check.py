#!/usr/bin/env python3
"""Validate local pages, links and bilingual navigation."""

from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parent
PAGES = sorted(ROOT.glob('*.html')) + sorted((ROOT / 'es').glob('*.html'))


class Links(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
        self.ids = set()
        self.language = None

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'html':
            self.language = values.get('lang')
        if 'id' in values:
            self.ids.add(values['id'])
        for name in ('href', 'src', 'data-model'):
            if values.get(name):
                self.links.append(values[name])


def check():
    errors = []
    if len(PAGES) != 20:
        errors.append(f'Expected 20 pages, found {len(PAGES)}')
    if {p.name for p in ROOT.glob('*.html')} != {p.name for p in (ROOT / 'es').glob('*.html')}:
        errors.append('English and Spanish page names differ')
    for page in PAGES:
        parser = Links()
        parser.feed(page.read_text(encoding='utf-8'))
        expected = 'es' if page.parent.name == 'es' else 'en'
        if parser.language != expected:
            errors.append(f'{page}: expected lang={expected}')
        for link in parser.links:
            parts = urlsplit(link)
            if parts.scheme or link.startswith('//') or link.startswith('mailto:'):
                continue
            destination = page.parent / unquote(parts.path) if parts.path else page
            if not destination.exists():
                errors.append(f'{page.relative_to(ROOT)}: missing {link}')
            if parts.fragment and destination == page and parts.fragment not in parser.ids:
                errors.append(f'{page.relative_to(ROOT)}: missing anchor {parts.fragment}')
    return errors


if __name__ == '__main__':
    issues = check()
    if issues:
        print('\n'.join(issues))
        raise SystemExit(1)
    print(f'Validated {len(PAGES)} pages and their local links/assets.')
