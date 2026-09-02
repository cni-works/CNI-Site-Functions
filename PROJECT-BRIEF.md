# CNI Site Functions プロジェクト方針

## 目的

案件ごとに子テーマの`functions.php`へ追記していたサイト固有PHPを、子テーマ更新の影響を受けないDBへ保存し、制作管理者が管理できるようにします。

## 基本方針

- CNI Blocks、CNI Motion、CNI Lightning Childとは独立したWordPressプラグインとする
- 1サイトにつき1つのPHP入力欄に限定する
- PHP、CSS、JavaScriptの統合管理ツールにはしない
- 保存コードはOptions APIで保持し、プラグイン更新対象のファイルへ書き込まない
- PHPをuploads配下へ生成しない
- 構文検証済みコードだけを専用Executorが扱う
- `functions.php`との完全互換は目標にしない
- ショートコード、action、filter、ACF、WooCommerce、Contact Form 7などの一般的なフック中心のコードを対象とする
- 入力欄はPHPモードから開始し、関数内部でHTMLを出力するための`?> ... <?php`は許可する

## 段階

### 第1段階（Version 0.1.0）

- DB保存
- 非実行のPHP構文検査
- 危険・対象外構文の制限
- 有効・無効状態
- 前回保存版の保持と復元
- 管理画面とWordPress標準コードエディター
- 権限・nonce確認

保存コードはまだ実行しません。

### 第2段階（実装・実機確認済み）

- 専用Executorへ隔離した検証済みコードの実行
- 実行タイミングの確定
- `CNI_SITE_FUNCTIONS_SAFE_MODE`対応
- `wp-content/.cni-site-functions-safe-mode`対応
- 捕捉可能な実行時エラーの記録と自動停止
- 実案件相当の混在PHP／HTMLショートコードで実地検証

Executorは`after_setup_theme`優先度0で実行します。`after_setup_theme`自体へ保存コードから後付け登録する処理は対象外です。shutdown時に原因をExecutor由来と判定できないFatal Errorは記録のみ行い、自動停止しません。

### 第3段階（Version 1.0.0へ実装済み・GitHub公開前）

- 配布ZIP生成と検証
- GitHub Releases Updater
- 更新後のDB保存内容維持テスト

GitHub公開後に1.0.0から1.0.1へのWordPress実機更新を行い、保存状態一式が維持されることを最終確認します。

### 第4段階

- CNI Lightning Childからの任意導入案内

## 対象外

- 複数スニペット
- PHP以外のコード管理
- ページ単位の実行条件
- クラウド同期
- WordPress.org公開
- 入力PHPの安全性を保証するサンドボックス
