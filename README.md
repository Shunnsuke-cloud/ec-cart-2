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
