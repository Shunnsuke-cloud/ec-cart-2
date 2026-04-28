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
