# CNI Site Functions 作業ルール

- このフォルダだけを独立したWordPressプラグインとして扱います。
- 作業前に`PROJECT-BRIEF.md`を最後まで読み、現在の開発段階を確認します。
- CNI Blocks、CNI Motion、CNI Lightning Child、その他のテーマ・プラグインを変更しません。
- DB保存コードの実行処理は専用Executor以外へ追加しません。
- Executor実装前は保存コードを一切実行しません。
- `eval()`を追加する場合は、構文検証、実行時の隔離、セーフモード、復旧経路、実行タイミングを先に明示し、ユーザーの確認を得ます。
- PHPコードをプラグインフォルダまたはuploadsへ生成しません。
- 保存処理では`manage_options`と`edit_plugins`、nonceを確認します。
- 入力コードは一般的なサニタイズ関数へ通して破壊せず、権限・nonce・サイズ・構文・トークンを検証し、出力時に必ずエスケープします。
- 構文検証に失敗した場合は、現在の保存コードを上書きしません。
- 秘密情報、案件固有コード、実サイトの個人情報を開発リポジトリへ含めません。
- 変更後はPHP・JavaScriptの構文と回帰テストを行い、WordPress上の手動確認項目を報告します。
- 明示指示なしにVersion変更、ZIP生成、Git操作、GitHub接続、公開を行いません。

## Git運用

- Repository: `https://github.com/cni-works/CNI-Site-Functions`
- Remote: `origin`
- Branch: `main`
- Release操作は、全テスト合格後にユーザーが明示的にリリースを指示した場合のみ許可します。
- Force Pushは禁止します。
- GitHub操作前にRepository、Remote URL、Branch、対象ファイル、Release ZIPの検証結果を確認します。
