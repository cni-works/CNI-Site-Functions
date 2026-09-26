=== CNI Site Functions ===
Contributors: cni
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

サイト固有PHPをテーマや子テーマの更新から分離し、WordPressのデータベースへ安全に保持します。

WordPress標準コードエディター、保存前の構文・禁止構文検査、前回保存版への復元、セーフモード、実行エラー時の自動停止に対応しています。

functions.phpとの完全互換ではありません。ショートコード、init、一般的なfilter/action登録を主対象とします。

== Installation ==

1. プラグインをインストールして有効化します。
2. 「設定 > CNI Site Functions」を開きます。
3. 先頭のPHP開始タグを付けずにサイト固有PHPを入力します。
4. 構文検査結果を確認してから「サイト固有PHPを有効にする」をオンにします。

== Frequently Asked Questions ==

= 保存コードはプラグイン更新で消えますか？ =

保存コードと実行状態はWordPressのOptions APIに保持されるため、通常のプラグイン更新では削除されません。

= 緊急停止できますか？ =

wp-config.phpでCNI_SITE_FUNCTIONS_SAFE_MODEをtrueにするか、wp-content直下へ.cni-site-functions-safe-modeという空ファイルを配置してください。

== Changelog ==

= 1.0.1 =
* Fatal/実行エラーに初回・最終検知日時、回数、由来判定を追加しました。
* 最近検知・過去記録の表示と、権限・nonce付きの記録削除を追加しました。
* 保存・復元時も記録を保持し、旧データを後方互換で読み込みます。

= 1.0.0 =
* 初回正式リリース。
* PHPコードのDB保存、検証、実行、有効・無効切り替えを追加しました。
* セーフモード、Fatal Error監視、自動停止、前回保存版への復元を追加しました。
* GitHub Releases経由の更新機能を追加しました。
