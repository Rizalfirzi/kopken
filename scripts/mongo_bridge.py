#!/usr/bin/env python3
"""
Lightweight MongoDB Atlas CLI Bridge for local PHP
Usage:
  python mongo_bridge.py get <key>
  python mongo_bridge.py set <key> <json_value_or_stdin>
  python mongo_bridge.py del <key>
"""

import sys
import os
import json
from pymongo import MongoClient

MONGODB_URI = os.getenv(
    "MONGODB_URI",
    "mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/?retryWrites=true&w=majority&appName=Cluster-Utama"
)
DB_NAME = "kyuti"


def get_db():
    client = MongoClient(MONGODB_URI, serverSelectionTimeoutMS=4000)
    return client[DB_NAME]


def main():
    if len(sys.argv) < 3:
        sys.exit(1)

    cmd = sys.argv[1].lower()
    key = sys.argv[2]
    db = get_db()
    col = db["settings"]

    if cmd == "get_stock":
        stock_col = db["outlet_stocks"]
        doc = stock_col.find_one({"_id": key})
        if not doc:
            # Check settings all_outlet_stocks
            settings_doc = col.find_one({"_id": "all_outlet_stocks"})
            if settings_doc and "data" in settings_doc and key in settings_doc["data"]:
                doc = settings_doc["data"][key]
        if doc:
            # Remove MongoDB _id if ObjectId for clean JSON
            if "_id" in doc:
                doc["_id"] = str(doc["_id"])
            if "updated_at" in doc and hasattr(doc["updated_at"], "isoformat"):
                doc["updated_at"] = doc["updated_at"].isoformat()
            sys.stdout.write(json.dumps(doc, ensure_ascii=False))
        else:
            sys.exit(2)

    elif cmd == "get":
        doc = col.find_one({"_id": key})
        if doc:
            if "raw_json" in doc and doc["raw_json"]:
                sys.stdout.write(doc["raw_json"])
            elif "data" in doc:
                sys.stdout.write(json.dumps(doc["data"], ensure_ascii=False))
            else:
                sys.stdout.write(json.dumps(doc, ensure_ascii=False))
        else:
            sys.exit(2)

    elif cmd == "set":
        # Value can be arg 3 or read from stdin
        if len(sys.argv) >= 4:
            val_str = sys.argv[3]
        else:
            val_str = sys.stdin.read()

        try:
            val_json = json.loads(val_str)
        except Exception:
            val_json = val_str

        col.update_one(
            {"_id": key},
            {"$set": {
                "_id": key,
                "data": val_json,
                "raw_json": val_str
            }},
            upsert=True
        )
        sys.stdout.write("OK")

    elif cmd == "del":
        col.delete_one({"_id": key})
        sys.stdout.write("OK")


if __name__ == "__main__":
    main()

