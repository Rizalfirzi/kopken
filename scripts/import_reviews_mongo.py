import json
from pymongo import MongoClient

def main():
    uri = 'mongodb+srv://technologyswamp_db_user:EmmGSARebSpWS0Sw@cluster-utama.eyzthyo.mongodb.net/kyuti?retryWrites=true&w=majority'
    client = MongoClient(uri, serverSelectionTimeoutMS=5000)
    db = client['kyuti']

    with open('data/reviews.json', 'r', encoding='utf-8') as f:
        reviews = json.load(f)

    print(f"Loaded {len(reviews)} reviews from data/reviews.json")

    # 1. Update settings collection
    raw_str = json.dumps(reviews, ensure_ascii=False)
    db['settings'].update_one(
        {'_id': 'customer_reviews'},
        {'$set': {'data': reviews, 'raw_json': raw_str}},
        upsert=True
    )
    print("[✓] Updated settings.customer_reviews in MongoDB Atlas")

    # 2. Update reviews collection
    for r in reviews:
        rid = r.get('id', 'rev_' + r.get('name', 'cust'))
        doc = dict(r)
        doc['_id'] = rid
        db['reviews'].replace_one({'_id': rid}, doc, upsert=True)

    print(f"[✓] reviews collection now has {db['reviews'].count_documents({})} documents")
    for r in db['reviews'].find():
        print(f"  - {r.get('id')} | {r.get('name')} | Rating: {r.get('rating')} | {r.get('date')}")

if __name__ == '__main__':
    main()
