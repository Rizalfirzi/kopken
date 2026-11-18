#!/usr/bin/env python3
"""
Comprehensive Outlet Harvester for Kedai Kyuti (Kopi Kenangan)
Harvests ALL outlets nationwide from upstream APIs, specifically ensuring
Kota Karawang and all regencies/cities across Indonesia are fully populated.
Upserts into MongoDB Atlas ('kyuti' database) and writes data/outlets_master.json.
"""

import os
import sys
import time
import json
import urllib.request
import urllib.parse
import http.cookiejar
from pymongo import MongoClient

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"

SOURCES = [
    "https://tixandcoffee.developerngantuk.my.id",
    "https://twinsjasdor.my.id",
    "https://ridstore.developerngantuk.my.id",
    "https://tyaastore.my.id"
]

CITIES_AND_KEYWORDS = [
    # Karawang & surrounding areas (Priority 1)
    "karawang", "kwg", "cikampek", "telukjambe", "klari", "galuh mas", "kosambi", "wadas",
    # Jabodetabek
    "jakarta", "jkt", "jaksel", "jakpus", "jaktim", "jakbar", "jakut",
    "bogor", "depok", "tangerang", "bekasi", "cikarang", "bintaro", "serpong", "bsd", 
    "karawaci", "cibubur", "alam sutera", "gading serpong",
    # Jawa Barat
    "bandung", "bdg", "cimahi", "cirebon", "sukabumi", "tasikmalaya", "garut", "purwakarta", 
    "subang", "indramayu", "majalengka", "kuningan", "cianjur", "sumedang",
    # Jawa Tengah & DIY
    "semarang", "solo", "surakarta", "yogyakarta", "jogja", "magelang", "salatiga", 
    "purwokerto", "tegal", "pekalongan", "kudus", "pati", "klaten", "cilacap", "kebumen",
    # Jawa Timur
    "surabaya", "sby", "malang", "sidoarjo", "gresik", "pasuruan", "mojokerto", 
    "jember", "banyuwangi", "kediri", "madiun", "blitar", "probolinggo", "batu", "tuban", "bojonegoro",
    # Bali & Nusa Tenggara
    "bali", "denpasar", "kuta", "seminyak", "ubud", "badung", "gianyar", "mataram", "lombok", "kupang",
    # Sumatera
    "medan", "palembang", "pekanbaru", "batam", "padang", "lampung", "jambi", "bengkulu", "aceh", 
    "bandar lampung", "binjai", "dumai", "pangkalpinang", "tanjungpinang",
    # Kalimantan
    "balikpapan", "samarinda", "banjarmasin", "pontianak", "palangkaraya", "tarakan", "banjarbaru",
    # Sulawesi & Indonesia Timur
    "makassar", "manado", "palu", "kendari", "gorontalo", "ambon", "jayapura", "sorong",
    # Common location categories & letters
    "rest area", "km 57", "km 62", "spbu", "shell", "pertamina", "bp", "mall", "plaza", "square",
    "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", 
    "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z"
]

def harvest():
    print("[*] Starting Nationwide Outlets Harvesting...")
    start_time = time.time()
    
    all_outlets = {}

    for base in SOURCES:
        print(f"\n---> Connecting to source: {base}")
        cj = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
        
        try:
            req_home = urllib.request.Request(f"{base}/", headers={"User-Agent": USER_AGENT})
            opener.open(req_home, timeout=10)
        except Exception as e:
            print(f"[!] Handshake failed for {base}: {e}")
            continue

        headers = {
            "User-Agent": USER_AGENT,
            "Accept": "application/json",
            "Referer": f"{base}/",
            "X-Requested-With": "XMLHttpRequest"
        }

        # Query each keyword
        for kw in CITIES_AND_KEYWORDS:
            url = f"{base}/api/kopken/outlets?q={urllib.parse.quote(kw)}"
            try:
                req = urllib.request.Request(url, headers=headers)
                with opener.open(req, timeout=8) as resp:
                    data = json.loads(resp.read().decode("utf-8"))
                    outlets = data if isinstance(data, list) else data.get("outlets", [])
                    new_count = 0
                    for o in outlets:
                        code = o.get("code")
                        if not code:
                            continue
                        if code not in all_outlets:
                            all_outlets[code] = o
                            new_count += 1
                        else:
                            # Merge details if richer
                            existing = all_outlets[code]
                            for field in ["address", "city", "phone", "hours", "img", "category"]:
                                if not existing.get(field) and o.get(field):
                                    existing[field] = o[field]
                    if new_count > 0:
                        print(f"  [+] '{kw}' -> +{new_count} new outlets (Total: {len(all_outlets)})")
            except Exception as e:
                pass
            time.sleep(0.05)

    outlets_list = list(all_outlets.values())
    print(f"\n[★] Total unique outlets harvested: {len(outlets_list)}")

    # Check Karawang count
    karawang = [o for o in outlets_list if "karawang" in str(o).lower() or "kwg" in str(o).lower() or "cikampek" in str(o).lower()]
    print(f"[★] Karawang & surrounding outlets: {len(karawang)}")
    for k in karawang:
        print(f"    - {k.get('code')} | {k.get('name')} | {k.get('address')[:60]}")

    # 1. Save to local data/outlets_master.json
    data_dir = os.path.join(os.path.dirname(__file__), "..", "data")
    os.makedirs(data_dir, exist_ok=True)
    outlets_file = os.path.join(data_dir, "outlets_master.json")
    with open(outlets_file, "w", encoding="utf-8") as f:
        json.dump(outlets_list, f, indent=2, ensure_ascii=False)
    print(f"[✓] Saved {len(outlets_list)} outlets to {outlets_file}")

    # 2. Upsert to MongoDB Atlas
    uri = os.getenv("MONGODB_URI", "mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/kyuti?retryWrites=true&w=majority")
    try:
        print("[*] Upserting to MongoDB Atlas ('kyuti' database)...")
        client = MongoClient(uri, serverSelectionTimeoutMS=10000)
        db = client["kyuti"]
        col = db["outlets"]

        # Bulk upsert
        from pymongo import UpdateOne
        ops = []
        for o in outlets_list:
            ops.append(UpdateOne(
                {"code": o["code"]},
                {"$set": o},
                upsert=True
            ))
        
        if ops:
            res = col.bulk_write(ops, ordered=False)
            print(f"[✓] MongoDB Atlas updated: {len(ops)} total ({res.upserted_count} new, {res.modified_count} updated).")
            print(f"[✓] Total documents in kyuti.outlets: {col.count_documents({})}")
    except Exception as e:
        print(f"[!] MongoDB upsert error: {e}")

    duration = round(time.time() - start_time, 2)
    print(f"\n[★] Completed in {duration}s!")

if __name__ == "__main__":
    harvest()
