# 🚀 Bulk import & Discunt package

This repository demonstrates two advanced Laravel 12 features:

* **Task A** → Bulk CSV Import & Chunked Drag-and-Drop Image Upload (with resumable uploads + image variants).
* **Task B** → A reusable Laravel Package: User Discounts with idempotent discount application.

---

## 📦 Installation

```bash
git clone <repo-url>
cd <repo>
cp .env.example .env
composer install
php artisan migrate --seed
php artisan storage:link
php artisan queue:work
php artisan serve
```

---

## 🛠 Mock Data Generator

Generate **10,000+ rows & 300+ images** for testing:

```bash
php artisan mock:generate {rows=10000} {images=300}
```

---

## 📝 Task A — Bulk Import + Image Upload

* [📂 Uploads UI](http://127.0.0.1:8000/uploads)
* [📊 Import UI](http://127.0.0.1:8000/imports)

### Features

* ✅ CSV **upsert** (by email for Users / by SKU for Products)
* ✅ Import summary → `total, imported, updated, invalid, duplicates`
* ✅ Drag-and-drop **chunked uploads** with resume + checksum validation
* ✅ Automatic **image variants** (256px, 512px, 1024px, aspect ratio preserved)
* ✅ Safe re-attachment (idempotent)
* ✅ Concurrency-safe + resumable

---

## 🎁 Task B — Laravel Package: User Discounts

👉 Package Repo: [hipstersg-demo-laravel-user-discounts-package](https://github.com/vishaljagani08/hipstersg-demo-laravel-user-discounts-package)

### Example Usage

```php
$user = User::firstOrCreate(['email' => 'demo@example.com'], [
    'name' => 'Demo User',
    'password' => bcrypt('password'),
]);

$discount1 = Discount::firstOrCreate(['code' => 'WELCOME10'], [
    'type' => 'percentage', 'value' => 10, 'active' => true, 'stacking_priority' => 10,
]);

$discount2 = Discount::firstOrCreate(['code' => 'FLAT50'], [
    'type' => 'fixed', 'value' => 50, 'active' => true, 'stacking_priority' => 5,
]);

Discounts::assign($user, $discount1);
Discounts::assign($user, $discount2);

$result = Discounts::apply($user, 500.00, ['idempotency_key' => 'order-demo-001']);
echo $result['amount']; // discounted amount
```

---

## ✅ Tests

Run:

```bash
php artisan test
```

### Sample Output

```text
PASS  Tests\Unit\CsvImportTest
✓ it upserts users and returns correct summary
✓ it should upsert CSV data and produce result summary

PASS  Tests\Unit\DiscountManagerTest
✓ it applies a discount and respects per user cap

PASS  Tests\Unit\GenerateImageVariantsTest
✓ it generates in-memory image variants
✓ it generates image variants preserving aspect ratio

PASS  Tests\Unit\UploadServiceTest
✓ it assigns upload id and validates checksum

Tests: 8 passed (29 assertions)
Duration: 2.35s
```

---

## 👨‍💻 Developer

* **Vishal Jagani**
* 📧 [vish2patel08@gmail.com](mailto:vish2patel08@gmail.com)
* 📞 +91 90995 46953
