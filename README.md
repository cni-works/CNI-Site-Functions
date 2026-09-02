# CNI Site Functions

サイト固有PHPを共通子テーマから分離し、WordPressのOptions APIへ保存するための制作管理者向けプラグインです。

## 主な機能

- 「設定 > CNI Site Functions」の単一PHP入力欄
- WordPress標準コードエディター
- `manage_options`と`edit_plugins`の権限確認
- nonceを使用した保存と復元
- `token_get_all()`と`TOKEN_PARSE`による非実行の構文検査
- 初期版で許可しないPHP構文の検出
- 有効・無効状態の保存
- 前回保存版1世代の保持と復元
- `CNI_SITE_FUNCTIONS_DISABLE_EDITOR`による編集停止
- コードエディター直下の専用検証結果枠
- 専用Executorによる保存コードの実行
- セーフモード定数と緊急停止ファイル
- 実行時エラーとFatal Errorの記録・自動停止

コード入力値と検証結果は別データとして扱います。構文エラーや禁止構文のメッセージをtextareaまたはCodeMirrorの値へ追加しません。検証に失敗した入力コードだけは修正を続けられるよう、短時間の管理者別一時データとしてエディターへ再表示します。

- GitHub Releases経由のWordPress標準更新

CNI Lightning Childとは独立して動作します。WordPress.orgからの更新は使用しません。

## Version

正式版 `1.0.0`

## 実行条件とタイミング

次の条件をすべて満たす場合だけ、保存コードを実行します。

- 「サイト固有PHPを有効にする」がオン
- 保存済みコードが空ではない
- `CNI_SITE_FUNCTIONS_SAFE_MODE` が `true` ではない
- `wp-content/.cni-site-functions-safe-mode` が存在しない
- 保存時のハッシュと実行直前のコードが一致する
- 実行直前のValidator検査に合格する

セーフモードは実行フック登録前に最優先で判定し、実行直前にも再確認します。セーフモード中はDBの読み込みとコード評価を行いません。

Executorは `after_setup_theme` の優先度0で動作します。これは `functions.php` との完全互換ではありません。ショートコードの登録、`init`、および一般的なfilter/action登録を主対象とします。保存コード内から `after_setup_theme` 自体へ登録したコールバックは、そのリクエストでは実行されません。

## 緊急停止

次のいずれかを使用すると、保存された有効状態を変更せずExecutorを完全に迂回できます。

```php
define( 'CNI_SITE_FUNCTIONS_SAFE_MODE', true );
```

または、FTP・サーバーのファイル管理画面で次の空ファイルを作成します。

```text
wp-content/.cni-site-functions-safe-mode
```

復旧後は管理画面でコードを修正または無効化してから、定数を削除するか停止ファイルを削除します。

## 実行エラーと自動停止

初回評価中に捕捉した `Throwable`、ハッシュ不一致、実行直前の検証失敗は、保存コードを削除せず `enabled=false` にして停止理由を保存します。

shutdown監視でFatal Errorを検知した場合は、発生ファイルまたはメッセージにこのExecutorのeval由来情報が確認できる場合だけ自動停止します。他プラグイン・テーマ由来か判定できないFatal Errorは警告として記録しますが、自動停止しません。原因不明のFatalが繰り返される場合は、上記のセーフモードを使用してください。

## 最初の実機テスト

最初は、テスト用固定ページだけで次のショートコードを確認します。入力欄には先頭の `<?php` を付けません。

```php
function cni_site_functions_test_shortcode() {
	return '<p>CNI Site Functions is running.</p>';
}
add_shortcode( 'cni_site_functions_test', 'cni_site_functions_test_shortcode' );
```

コードを有効にして保存し、固定ページへ `[cni_site_functions_test]` を配置します。表示・無効化・再有効化・セーフモードを確認してから、実案件コードの移行へ進みます。

## 保存データ

`cni_site_functions_state`という1つのoptionに保存します。プラグインフォルダ内やuploadsへPHPファイルを生成しません。

プラグインの無効化・更新・削除だけでは、このoptionを自動削除しない方針です。コードへパスワード、APIキーなどの秘密情報を記載しないでください。

## 初期版で禁止する構文

- 入力欄先頭のPHP開始タグと、PHPへ戻らない末尾の終了タグ
- `eval`
- `exit` / `die`
- `__halt_compiler`
- `namespace`
- `include` / `include_once`
- `require` / `require_once`
- `declare`

関数内部でHTMLを出力するための `?> ... <?php` は使用できます。入力欄自体はPHPモードから始まるため、先頭の `<?php` は入力しません。

構文検査は実行時の正常動作を保証しません。未定義関数や依存プラグインの停止などは実行時に自動停止できる場合がありますが、PHPプロセスの強制終了、メモリ枯渇など、記録や自動停止が保証できない障害もあります。

## 更新と保存データ

更新元は `cni-works/CNI-Site-Functions` の正式なGitHub Releaseです。Updaterは厳密な `vX.Y.Z` Tagと、`cni-site-functions-X.Y.Z.zip` という専用Release Assetだけを使用します。

プラグイン更新ではファイルが置き換わりますが、`cni_site_functions_state` optionは削除しません。そのため、保存コード、有効状態、前回保存版、ハッシュ、自動停止情報は更新後も保持されます。
