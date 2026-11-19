#!/usr/bin/env python3
"""
Kedai Kyuti - Kopi Kenangan Nationwide Outlet Stock Harvester
Harvests real-time stock and availability for outlets across Indonesia using multi-source pool.
Upserts into MongoDB Atlas ('outlet_stocks' collection) and updates data/outlet_stocks_master.json.
"""

import os
import sys
import time
import json
import itertools
import urllib.request
from datetime import datetime, timezone
from concurrent.futures import ThreadPoolExecutor, as_completed
from pymongo import MongoClient, UpdateOne

MONGODB_URI = os.getenv(
    "MONGODB_URI",
    "mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/?retryWrites=true&w=majority&appName=Cluster-Utama"
)
DB_NAME = os.getenv("MONGODB_DATABASE", "kyuti")

USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
SOURCES = [
    "https://ridstore.developerngantuk.my.id",
    "https://tyaastore.my.id",
    "https://twinsjasdor.my.id",
    "https://tixandcoffee.developerngantuk.my.id"
]


def get_cookie(base_url):
    try:
        req = urllib.request.Request(f"{base_url}/", headers={"User-Agent": USER_AGENT})
        with urllib.request.urlopen(req, timeout=8) as res:
            cookies = res.headers.get_all("Set-Cookie", [])
            cookie_parts = []
            for c in cookies:
                cookie_parts.append(c.split(";")[0])
            cookie_str = "; ".join(cookie_parts)
            if cookie_str:
                return cookie_str
    except Exception as e:
        print(f"[!] Cookie handshake error for {base_url}: {e}", flush=True)
    return None


def init_source_pool():
    pool = []
    for s in SOURCES:
        c = get_cookie(s)
        if c:
            pool.append({
                "base": s,
                "cookie": c,
                "headers": {
                    "User-Agent": USER_AGENT,
                    "Accept": "application/json",
                    "Referer": f"{s}/",
                    "X-Requested-With": "XMLHttpRequest",
                    "Cookie": c
                }
            })
            print(f"[+] Active source pool added: {s}", flush=True)
    return pool


def harvest_all():
    print("[*] Starting Multi-Source Nationwide Stock Harvesting...", flush=True)
    start_time = time.time()

    pool = init_source_pool()
    if not pool:
        print("[X] Could not connect to any upstream source.", flush=True)
        sys.exit(1)

    data_dir = os.path.join(os.path.dirname(__file__), "..", "data")
    outlets_file = os.path.join(data_dir, "outlets_master.json")
    menu_file = os.path.join(data_dir, "menu_master.json")
    stocks_file = os.path.join(data_dir, "outlet_stocks_master.json")

    with open(outlets_file, "r", encoding="utf-8") as f:
        outlets = json.load(f)

    all_master_keys = []
    if os.path.exists(menu_file):
        with open(menu_file, "r", encoding="utf-8") as f:
            menu_data = json.load(f)
            all_master_keys = [i.get("key") or i.get("id") for i in menu_data.get("items", []) if (i.get("key") or i.get("id"))]

    # Load existing stock map if present
    existing_stock_map = {}
    if os.path.exists(stocks_file):
        try:
            with open(stocks_file, "r", encoding="utf-8") as f:
                existing_stock_map = json.load(f)
        except Exception:
            existing_stock_map = {}

    print(f"[*] Found {len(outlets)} outlets in master database.", flush=True)
    print(f"[*] Master menu has {len(all_master_keys)} catalog item keys.", flush=True)
    print(f"[*] Existing stock profiles loaded: {len(existing_stock_map)}", flush=True)

    # Prioritize key outlets at the front of the queue
    priority_codes = {
        "LA.CODEFIN": 1,
        "KK.BDG.RKPJAJRN": 1,
        "KK.KWG.GLHMAS": 1,
        "BP.KARAWANG": 1,
        "KK.SBY.RKABNGAN": 1,
    }
    sorted_outlets = sorted(
        outlets,
        key=lambda o: 0 if (o.get("code") in priority_codes or o.get("code") in existing_stock_map)
        else (1 if any(k in o.get("name", "").lower() for k in ["karawang", "bandung", "jakarta", "surabaya"]) else 2)
    )

    stock_map = dict(existing_stock_map)
    total_outlets = len(sorted_outlets)
    processed = 0
    errors = 0

    pool_cycle = itertools.cycle(pool)

    def fetch_outlet(outlet):
        code = outlet.get("code")
        if not code:
            return None

        # Try up to 2 sources in pool if one fails or rate limits
        for _ in range(min(len(pool), 2)):
            src_obj = next(pool_cycle)
            url = f"{src_obj['base']}/api/kopken/menu?outlet={urllib.parse.quote(code)}"
            try:
                req = urllib.request.Request(url, headers=src_obj["headers"])
                with urllib.request.urlopen(req, timeout=2.5) as res:
                    data = json.loads(res.read().decode())
                    items = data.get("items", [])
                    is_closed = bool(data.get("isClosed"))

                    is_premium = bool(data.get("premium") or data.get("isPremium"))
                    live_keys = set()
                    sold_out_keys = set()
                    price_map = {}
                    for item in items:
                        key = item.get("key") or item.get("id")
                        if not key:
                            continue
                        live_keys.add(key)
                        if item.get("isSoldOut") or item.get("notCarried"):
                            sold_out_keys.add(key)
                        np = item.get("normalPrice") or item.get("outletPrice") or item.get("price")
                        if np:
                            price_map[key] = np
                        if "kenangan mantan" in (item.get("name") or "").lower() and np and np >= 22000:
                            is_premium = True

                    # Items not in live outlet menu are not carried (sold out)
                    for mk in all_master_keys:
                        if mk not in live_keys:
                            sold_out_keys.add(mk)

                    return {
                        "code": code,
                        "soldOut": sorted(list(sold_out_keys)),
                        "isClosed": is_closed,
                        "soldOutCount": len(sold_out_keys),
                        "isPremium": is_premium,
                        "prices": price_map if is_premium else {}
                    }
            except Exception:
                continue
        return None

    print(f"[*] Harvesting stock across {len(pool)} upstream sources with 10 workers...", flush=True)
    with ThreadPoolExecutor(max_workers=10) as executor:
        future_to_code = {executor.submit(fetch_outlet, o): o["code"] for o in sorted_outlets}
        for future in as_completed(future_to_code):
            processed += 1
            res = future.result()
            if res:
                stock_map[res["code"]] = {
                    "soldOut": res["soldOut"],
                    "isClosed": res["isClosed"],
                    "soldOutCount": res["soldOutCount"],
                    "isPremium": res.get("isPremium", False),
                    "prices": res.get("prices", {})
                }
            else:
                errors += 1

            if processed % 50 == 0 or processed == total_outlets:
                elapsed = time.time() - start_time
                print(f"[{processed}/{total_outlets}] outlets processed ({len(stock_map)} total stored, {errors} errors, {elapsed:.1f}s)", flush=True)

    # Save to data/outlet_stocks_master.json
    with open(stocks_file, "w", encoding="utf-8") as f:
        json.dump(stock_map, f, indent=2, ensure_ascii=False)
    print(f"[✓] Successfully wrote {len(stock_map)} outlet stock profiles to {stocks_file}", flush=True)

    # Upsert to MongoDB Atlas
    try:
        print("[*] Upserting outlet stocks to MongoDB Atlas...", flush=True)
        client = MongoClient(MONGODB_URI, serverSelectionTimeoutMS=10000)
        db = client[DB_NAME]
        col = db["outlet_stocks"]
        col.create_index("code", unique=True)

        operations = []
        for code, info in stock_map.items():
            doc = {
                "_id": code,
                "code": code,
                "soldOut": info["soldOut"],
                "isClosed": info["isClosed"],
                "soldOutCount": info["soldOutCount"],
                "isPremium": info.get("isPremium", False),
                "prices": info.get("prices", {}),
                "updated_at": datetime.now(timezone.utc)
            }
            operations.append(UpdateOne({"_id": code}, {"$set": doc}, upsert=True))

        if operations:
            res = col.bulk_write(operations)
            print(f"[✓] MongoDB Atlas: {len(operations)} outlet stocks synced ({res.upserted_count} new, {res.modified_count} updated).", flush=True)

        # Also store single compact master map in settings
        db["settings"].update_one(
            {"_id": "all_outlet_stocks"},
            {"$set": {
                "_id": "all_outlet_stocks",
                "data": stock_map,
                "updated_at": datetime.now(timezone.utc),
                "count": len(stock_map)
            }},
            upsert=True
        )
        print("[✓] Stored all_outlet_stocks in settings collection for instant single query.", flush=True)
    except Exception as e:
        print(f"[!] MongoDB Atlas upsert error: {e}", flush=True)

    total_time = round(time.time() - start_time, 2)
    print(f"\n[★] Multi-source stock harvesting complete in {total_time}s!", flush=True)
    print(f"    Total Outlets Processed: {total_outlets}")
    print(f"    Total Stock Profiles in Database: {len(stock_map)}", flush=True)

    # Sample audit:
    sample_checks = ["LA.CODEFIN", "KK.BDG.RKPJAJRN", "KK.KWG.GLHMAS", "BP.KARAWANG", "KK.SBY.RKABNGAN"]
    print("\n--- SAMPLE REAL-TIME OUTLET STOCK AUDIT ---", flush=True)
    for sc in sample_checks:
        info = stock_map.get(sc)
        if info:
            print(f"Outlet {sc}: Sold Out = {info['soldOutCount']}, Closed = {info['isClosed']}", flush=True)
            print(f"  Sample sold out keys: {info['soldOut'][:5]}", flush=True)


if __name__ == "__main__":
    harvest_all()
