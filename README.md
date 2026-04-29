# ec-cart-2
ECかーと PHP。ロリポップサーバーで PAY.JP を使う構成です。

## PAY.JP のAPIキーの書き場所

APIキーは Git 管理外の [ec-app/config/payjp.php](ec-app/config/payjp.php) に書きます。

手順は次のとおりです。

1. [ec-app/config/payjp.example.php](ec-app/config/payjp.example.php) をコピーして [ec-app/config/payjp.php](ec-app/config/payjp.php) を作成する
2. [ec-app/config/payjp.php](ec-app/config/payjp.php) に本番用またはテスト用のキーを記入する
3. `public_key` はフロント側のカードトークン生成に使う
4. `secret_key` は [ec-app/public/checkout.php](ec-app/public/checkout.php) の決済処理で使う
5. `webhook_token` を使う場合は、同じファイルに書く

例:

```php
return [
	'public_key' => 'pk_test_xxxxxxxxx',
	'secret_key' => 'sk_test_xxxxxxxxx',
	'webhook_token' => 'whsec_xxxxxxxxx',
];
```

`config/payjp.php` は Git に含めません。サーバーへ配置するときにだけ作成してください。

## クーポン（割引）の使い方

このブランチではクーポンによる割引が注文時に適用できます。

- マイグレーション: [ec-app/database/004_create_coupons.sql](ec-app/database/004_create_coupons.sql) を実行して `coupons` テーブルを作成してください（mysqladmin や phpMyAdmin を使用）。
- クーポンの主なカラム:
	- `code`: クーポンコード（一意）
	- `type`: `fixed`（固定額）または `percent`（割合）
	- `value`: 金額または割合（percent の場合は 10 で 10%）
	- `usage_limit` / `used_count`: 使用回数管理
	- `min_order_amount`: 適用最小注文額（円）
	- `starts_at` / `ends_at`: 有効期間

- 利用方法: 決済ページ（`/ec-app/public/checkout.php`）にクーポンコード入力欄があります。コードを入力して注文確定すると、サーバー側で検証され、割引が適用されます。

注意: 投稿されたクーポンはサーバー側で検証・カウントされ、注文確定時に `used_count` がインクリメントされます。

## 管理者ログイン

管理者ログインは会員ログインと分けてあります。

- ログイン画面: [ec-app/public/admin/login.php](ec-app/public/admin/login.php)
- ログイン後の画面: [ec-app/public/admin/index.php](ec-app/public/admin/index.php)
- ログアウト: [ec-app/public/admin/logout.php](ec-app/public/admin/logout.php)
- 管理者用テーブル: [ec-app/database/005_create_admin_users.sql](ec-app/database/005_create_admin_users.sql)

サーバーで [ec-app/database/005_create_admin_users.sql](ec-app/database/005_create_admin_users.sql) を実行して `admin_users` テーブルを作成し、最初の管理者アカウントを登録してください。

管理者アカウント例:

```sql
INSERT INTO admin_users (name, email, password, status)
VALUES ('管理者', 'admin@example.com', 'password_hashで保存した文字列', 'active');
```

パスワードは必ず `password_hash()` で作った値を保存してください。

## 商品CRUD（管理者）

管理者ログイン後に [ec-app/public/admin/index.php](ec-app/public/admin/index.php) から [ec-app/public/admin/products/index.php](ec-app/public/admin/products/index.php) へ進めます。

- 一覧: [ec-app/public/admin/products/index.php](ec-app/public/admin/products/index.php)
- 新規作成: [ec-app/public/admin/products/new.php](ec-app/public/admin/products/new.php)
- 編集: [ec-app/public/admin/products/edit.php](ec-app/public/admin/products/edit.php)
- 削除: [ec-app/public/admin/products/delete.php](ec-app/public/admin/products/delete.php)

この商品CRUDは `products` テーブルの基本情報（商品名、slug、ブランド、説明、カテゴリID、状態）を管理します。商品画像やSKUは既存のまま別管理です。

## 注文一覧 / ステータス変更（管理者）

管理者ログイン後に注文一覧を確認し、注文状態・支払状態・配送状態を変更できます。

- 一覧: [ec-app/public/admin/orders/index.php](ec-app/public/admin/orders/index.php)
- 変更: [ec-app/public/admin/orders/edit.php](ec-app/public/admin/orders/edit.php)

表示している主な項目は注文番号、購入者、商品点数、合計金額、支払状態、配送状態、注文状態です。

## ロール（役割）システム

このブランチではロール管理テーブルを追加しました。

- マイグレーション: [ec-app/database/006_create_roles.sql](ec-app/database/006_create_roles.sql) を実行して `roles` テーブルを作成してください（mysqladmin や phpMyAdmin を使用）。

### rolesテーブルの作成手順

**mysqladmin でのコピペ実行:**

```bash
mysql -u root -p your_database_name
```

ログイン後、以下をコピペして実行:

```sql
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, description) VALUES
    ('admin', '管理者（全操作可能）'),
    ('manager', '運用担当（注文・レビュー管理など）'),
    ('user', '一般ユーザー（購入・レビュー投稿）');
```

### ロールの説明

| ロール | 説明 |
|--------|------|
| `admin` | 管理者（全操作可能） |
| `manager` | 運用担当（注文・レビュー管理など） |
| `user` | 一般ユーザー（購入・レビュー投稿） |

### 初期データ

テーブル作成時に上記の3つのロール（admin, manager, user）が自動的に挿入されます。
