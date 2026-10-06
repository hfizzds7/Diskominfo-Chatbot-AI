import urllib.request
import re

html = urllib.request.urlopen('https://dinkes.bandung.go.id/daftar-biaya-lab-dinkes/').read().decode('utf-8')
urls = re.findall(r'http[s]?://[^\s\"\'<>]+', html)
for url in urls:
    if 'ninja' in url or 'admin-ajax' in url or 'csv' in url:
        print(url)

