#!/usr/bin/env python3
"""
Kedai Kyuti - Kopi Kenangan Multi-Source to MongoDB Atlas Realtime Sync
Author: Antigravity Team
Description:
Fetches live Kopi Kenangan outlets, menus, categories, and item customizations
stealthily from multiple upstream sources (twinsjasdor & tixandcoffee)
and upserts them into MongoDB Atlas ('kyuti' database) in real-time.
Also exports master JSON files to data/ for instant local and Vercel loading.
"""

import os
import sys
import time
import json
import argparse
import urllib.request
import urllib.parse
import http.cookiejar
from datetime import datetime, timezone
from pymongo import MongoClient, UpdateOne

MONGODB_URI = os.getenv(
    "MONGODB_URI",
    "mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/?retryWrites=true&w=majority&appName=Cluster-Utama"
)
DB_NAME = os.getenv("MONGODB_DATABASE", "kyuti")

SOURCES = [
    "https://tixandcoffee.developerngantuk.my.id",
    "https://ridstore.developerngantuk.my.id",
    "https://tyaastore.my.id",
    "https://twinsjasdor.my.id"
]

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"


class KopkenMongoSync:
    def __init__(self, uri=MONGODB_URI, db_name=DB_NAME):
        print(f"[*] Connecting to MongoDB Atlas ({db_name})...")
        self.client = MongoClient(uri, serverSelectionTimeoutMS=10000)
        self.db = self.client[db_name]
        self.outlets_col = self.db["outlets"]
        self.menus_col = self.db["menus"]
        self.details_col = self.db["item_details"]
        self.sync_logs_col = self.db["sync_logs"]
        
        # Ensure indexes
        self.outlets_col.create_index("code", unique=True)
        self.menus_col.create_index("key", unique=True)
        self.details_col.create_index([("outlet", 1), ("code", 1)], unique=True)
        print("[+] MongoDB Atlas connected and indexes verified.")

    def fetch_from_source(self, base_url):
        """Fetch outlets and menu from a single upstream source using stealth customer handshake"""
        cj = http.cookiejar.CookieJar()
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
        
        # Step 1: Handshake as customer visiting homepage
        try:
            req_home = urllib.request.Request(f"{base_url}/", headers={"User-Agent": USER_AGENT})
            opener.open(req_home, timeout=10)
        except Exception as e:
            print(f"[!] Handshake failed for {base_url}: {e}")
            return [], []

        headers = {
            "User-Agent": USER_AGENT,
            "Accept": "application/json",
            "Referer": f"{base_url}/",
            "X-Requested-With": "XMLHttpRequest"
        }

        # Step 2: Fetch outlets
        outlets = []
        try:
            req_out = urllib.request.Request(f"{base_url}/api/kopken/outlets", headers=headers)
            with opener.open(req_out, timeout=12) as resp:
                data = json.loads(resp.read().decode("utf-8"))
                outlets = data.get("outlets", [])
                print(f"[+] Retrieved {len(outlets)} outlets from {base_url}")
        except Exception as e:
            print(f"[!] Error fetching outlets from {base_url}: {e}")

        # Step 3: Fetch menu
        menu_items = []
        menu_full = None
        try:
            sample_outlet = outlets[0]["code"] if outlets else "KK.SBY.RKABNGAN"
            req_menu = urllib.request.Request(f"{base_url}/api/kopken/menu?outlet={sample_outlet}", headers=headers)
            with opener.open(req_menu, timeout=12) as resp:
                menu_full = json.loads(resp.read().decode("utf-8"))
                menu_items = menu_full.get("items", [])
                print(f"[+] Retrieved {len(menu_items)} menu items from {base_url}")
        except Exception as e:
            print(f"[!] Error fetching menu from {base_url}: {e}")

        return outlets, menu_full

    def sync_all(self):
        start_time = time.time()
        print(f"\n[*] Starting multi-source sync to MongoDB Atlas ('{DB_NAME}')...")

        all_outlets = {}
        all_menus = {}
        sample_menu_full = None

        for src in SOURCES:
            outlets, menu_full = self.fetch_from_source(src)
            for o in outlets:
                code = o.get("code")
                if code and code not in all_outlets:
                    all_outlets[code] = o

            if menu_full and menu_full.get("items"):
                if sample_menu_full is None:
                    sample_menu_full = menu_full
                for it in menu_full["items"]:
                    key = it.get("key") or it.get("id")
                    if key and key not in all_menus:
                        all_menus[key] = it

        outlets_list = list(all_outlets.values())
        menu_list = list(all_menus.values())

        if not outlets_list and not menu_list:
            print("[!] Both upstream sources failed or returned empty data.")
            return

        now = datetime.now(timezone.utc)

        # Upsert Outlets into MongoDB
        if outlets_list:
            operations = []
            for o in outlets_list:
                doc = {**o, "updated_at": now}
                operations.append(UpdateOne({"code": o["code"]}, {"$set": doc}, upsert=True))
            res = self.outlets_col.bulk_write(operations)
            print(f"[✓] Outlets in MongoDB: {len(operations)} total ({res.upserted_count} new, {res.modified_count} updated).")

        # Upsert Menus into MongoDB
        if menu_list:
            operations = []
            for m in menu_list:
                key = m.get("key") or m.get("id")
                doc = {**m, "updated_at": now}
                operations.append(UpdateOne({"key": str(key)}, {"$set": doc}, upsert=True))
            res = self.menus_col.bulk_write(operations)
            print(f"[✓] Menus in MongoDB: {len(operations)} total ({res.upserted_count} new, {res.modified_count} updated).")

        # Update Master JSON files in data/
        data_dir = os.path.join(os.path.dirname(__file__), "..", "data")
        os.makedirs(data_dir, exist_ok=True)

        if outlets_list:
            with open(os.path.join(data_dir, "outlets_master.json"), "w", encoding="utf-8") as f:
                json.dump(outlets_list, f, indent=2, ensure_ascii=False)

        if sample_menu_full:
            sample_menu_full["items"] = menu_list
            # Strip heavy redundant upstream bloat (bundles alone was 355 KB, saving 85% bandwidth)
            sample_menu_full.pop("bundles", None)
            sample_menu_full.pop("satuan", None)
            sample_menu_full.pop("seliter", None)
            sample_menu_full.pop("raw_dump", None)
            with open(os.path.join(data_dir, "menu_master.json"), "w", encoding="utf-8") as f:
                json.dump(sample_menu_full, f, indent=2, ensure_ascii=False)

        duration = round(time.time() - start_time, 2)
        print(f"[+] Master JSON snapshots updated in {data_dir}")

        # Log to MongoDB
        self.sync_logs_col.insert_one({
            "timestamp": now,
            "outlets_count": len(outlets_list),
            "menu_count": len(menu_list),
            "sources": SOURCES,
            "duration_seconds": duration,
            "status": "success"
        })
        print(f"[★] Multi-source sync completed successfully in {duration}s!\n")


def main():
    parser = argparse.ArgumentParser(description="Multi-Source Sync Kopi Kenangan to MongoDB Atlas")
    parser.add_argument("--daemon", action="store_true", help="Run continuously in background")
    parser.add_argument("--interval", type=int, default=300, help="Sync interval in seconds (default: 300 / 5 mins)")
    args = parser.parse_args()

    sync = KopkenMongoSync()

    if args.daemon:
        print(f"[*] Starting daemon mode. Syncing every {args.interval} seconds (Press Ctrl+C to stop)...")
        while True:
            try:
                sync.sync_all()
            except Exception as e:
                print(f"[!] Error during sync: {e}")
            time.sleep(args.interval)
    else:
        sync.sync_all()


if __name__ == "__main__":
    main()
