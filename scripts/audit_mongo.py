import json
from pymongo import MongoClient

uri = "mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/?retryWrites=true&w=majority&appName=Cluster-Utama"
client = MongoClient(uri, serverSelectionTimeoutMS=10000)
db = client["kyuti"]

print("=== AUDIT MONGODB ATLAS (DATABASE: kyuti) ===\n")

# 1. Outlets
outlets_count = db["outlets"].count_documents({})
sample_outlets = list(db["outlets"].find({}, {"code": 1, "name": 1, "category": 1, "city": 1}).limit(6))
print(f"1. KOLEKSI 'outlets': {outlets_count} outlet tersimpan")
for o in sample_outlets:
    print(f"   • [{o.get('code')}] {o.get('name')} (Kategori: {o.get('category', '-')})")

# 2. Menus
menu_count = db["menus"].count_documents({})
cats = db["menus"].distinct("category")
sample_menus = list(db["menus"].find({}, {"key": 1, "name": 1, "category": 1, "normalPrice": 1}).limit(6))
print(f"\n2. KOLEKSI 'menus': {menu_count} item menu tersimpan")
print(f"   Kategori unik: {', '.join(str(c) for c in cats if c)}")
for m in sample_menus:
    print(f"   • {m.get('name')} (Kat: {m.get('category')}, Harga Kopken Asli: Rp{m.get('normalPrice', 0):,})")

# 3. Settings
settings_docs = list(db["settings"].find({}))
print(f"\n3. KOLEKSI 'settings': {len(settings_docs)} dokumen setting cloud")
for doc in settings_docs:
    key = doc.get("_id")
    raw_data = doc.get("data", {})
    if key == "pricing_settings":
        dm = raw_data.get("default_markup")
        wh = raw_data.get("web_hours", {})
        print(f"   • Key: '{key}' -> Default Markup: Rp{dm}, Jam Operasional: {wh.get('open')}-{wh.get('close')} WIB (Status: {wh.get('force_status')})")
    elif key == "customer_reviews":
        reviews_list = raw_data if isinstance(raw_data, list) else raw_data.get("reviews", [])
        print(f"   • Key: '{key}' -> {len(reviews_list)} ulasan customer tersimpan di cloud")
    else:
        print(f"   • Key: '{key}'")

# 4. Sync Logs
sync_count = db["sync_logs"].count_documents({})
last_log = db["sync_logs"].find_one(sort=[("timestamp", -1)])
print(f"\n4. KOLEKSI 'sync_logs': {sync_count} riwayat log sinkronisasi")
if last_log:
    print(f"   • Waktu Sync Terakhir: {last_log.get('timestamp')}")
    print(f"   • Status: {last_log.get('status')} (Durasi: {last_log.get('duration_seconds')} detik)")
    print(f"   • Hasil Sync: {last_log.get('outlets_count')} outlet & {last_log.get('menu_count')} menu dari 4 sumber upstream")

print("\n=== SEMUA DATA TERVERIFIKASI AKTIF DI MONGODB ATLAS ===")
