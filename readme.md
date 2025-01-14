# Docusaurus Content Generator

このスクリプトは、任意のディレクトリ構造をDocusaurus v3用のドキュメントに変換するPHPスクリプトです

## 機能

- ディレクトリ構造をそのまま保持したMarkdownファイルの生成
- ドットファイル（.gitignore等）の適切な処理
- sidebar_positionの自動生成
- ディレクトリ構造の可視化
- _category_.jsonの自動生成

## 要件

- PHP 8.0以上
- Docusaurus v3.x

## 使用方法

1. 基本的な使用方法:
```bash
php generate-docs.php
```

2. ディレクトリ構成:
```
プロジェクト/
├── input/          # 変換元ディレクトリ
│   ├── src/        # ソースファイル
│   └── docs/       # ドキュメント
└── output/         # 生成されるDocusaurusドキュメント
```

## 特記事項

1. ファイル名の処理:
    - 通常のファイル: `example.php` → `example.php.md`
    - ドットファイル: `.env` → `dotfiles-env.md`
    - 同名異拡張子: `main.c`、`main.h` → `main.c.md`、`main.h.md`

2. サイドバーの位置:
    - ファイルはアルファベット順にソートされ、順番に番号が振られます
    - ディレクトリの深さに応じて100単位で番号が増加します

## カスタマイズ

入力・出力ディレクトリを変更する場合は、スクリプト末尾の以下の部分を編集してください：

```php
$generator = new DocusaurusContentGenerator(
    './input',   // 入力ディレクトリ
    './output'   // 出力ディレクトリ