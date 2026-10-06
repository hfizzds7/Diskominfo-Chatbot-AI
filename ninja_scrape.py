import urllib.request
import urllib.parse
import json

url = 'https://dinkes.bandung.go.id/wp-admin/admin-ajax.php'
data = urllib.parse.urlencode({'action': 'ninja_tables_public_action', 'table_id': '1542', 'target_action': 'get_all_data', 'chunk_number': 0}).encode('utf-8')
req = urllib.request.Request(url, data=data)
try:
    response = urllib.request.urlopen(req)
    result = response.read().decode('utf-8')
    data = json.loads(result)
    for row in data:
        print(row)
except Exception as e:
    print("Error:", e)

