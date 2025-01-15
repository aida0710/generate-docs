<?php

declare(strict_types = 1);

class DocusaurusContentGenerator {

    private string $sourceDir;
    private string $docsDir;
    private array $processedFiles = [];
    private int $totalFiles = 0;
    private int $processedCount = 0;

    public function __construct(string $sourceDir, string $docsDir) {
        $this->sourceDir = rtrim($sourceDir, '/');
        $this->docsDir = rtrim($docsDir, '/');
    }

    public function generate(): void {
        if (!is_dir($this->sourceDir)) {
            throw new RuntimeException("Source directory does not exist: {$this->sourceDir}");
        }

        if (!is_dir($this->docsDir)) {
            mkdir($this->docsDir, 0755, true);
        }

        $this->processedFiles = [];
        $this->processedCount = 0;

        // 総ファイル数をカウント
        $this->totalFiles = $this->countFiles($this->sourceDir);
        echo "変換対象: {$this->totalFiles} ファイル\n";

        $this->processDirectory($this->sourceDir);
        $this->generateDirectoryStructure();
        echo "\n完了しました！\n";
    }

    private function processDirectory(string $currentDir, int $depth = 0): void {
        $items = scandir($currentDir);
        $position = 0;

        sort($items, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '_category_.json') {
                continue;
            }

            $sourcePath = $currentDir . '/' . $item;
            $relativePath = substr($sourcePath, strlen($this->sourceDir) + 1);

            if (is_dir($sourcePath)) {
                $targetPath = $this->docsDir . '/' . $relativePath;
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                $this->processedFiles[] = [
                    'type' => 'directory',
                    'path' => $relativePath,
                    'depth' => $depth,
                ];
                $this->processDirectory($sourcePath, $depth + 1);
            } else {
                $position++;
                $this->processedFiles[] = [
                    'type' => 'file',
                    'path' => $relativePath,
                    'depth' => $depth,
                ];
                $targetPath = $this->getTargetPath($relativePath);
                $this->processFile($sourcePath, $this->docsDir . '/' . $targetPath, $position);
                $this->processedCount++;
                $this->showProgress();
            }
        }

        $this->generateCategoryFile($currentDir, $depth);
    }

    private function getTargetPath(string $relativePath): string {
        $info = pathinfo($relativePath);
        $fileName = $info['filename'];
        $extension = $info['extension'] ?? '';
        $baseDir = $info['dirname'];
        $originalName = basename($relativePath);

        if (str_starts_with($originalName, '.')) {
            $cleanName = 'dotfiles-' . substr($originalName, 1);
            return ($baseDir === '.' ? '' : $baseDir . '/') . $cleanName . '.md';
        }

        // 拡張子付きのファイル名を生成（例: main.c.md）
        $newFileName = $extension ? "{$fileName}.{$extension}.md" : "{$fileName}.md";
        return ($baseDir === '.' ? '' : $baseDir . '/') . $newFileName;
    }

    private function countFiles(string $dir): int {
        $count = 0;
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '_category_.json') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $count += $this->countFiles($path);
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function showProgress(): void {
        $percent = ($this->processedCount / $this->totalFiles) * 100;
        $bar = str_repeat('=', (int) ($percent / 2)) . str_repeat(' ', 50 - (int) ($percent / 2));
        echo sprintf("\r[%s] %.1f%% (%d/%d)", $bar, $percent, $this->processedCount, $this->totalFiles);
    }

    private function processFile(string $sourcePath, string $targetPath, int $position): void {
        $content = file_get_contents($sourcePath);
        $originalName = basename($sourcePath);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'txt';

        $markdown = "# {$originalName}\n\n";
        $markdown .= "### ファイル情報\n\n";
        $markdown .= "- パス: `{$this->getRelativePath($sourcePath)}`\n\n";
        $markdown .= "### コンテンツ\n\n";
        $markdown .= "```{$extension}\n";
        $markdown .= $content;
        $markdown .= "\n```\n";

        $frontmatter = $this->generateFrontmatter($position);
        $finalContent = $frontmatter . $markdown;

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        file_put_contents($targetPath, $finalContent);
    }

    private function generateDirectoryStructure(): void {
        $content = "# プロジェクト構造\n\n";
        $content .= "### ディレクトリ階層\n\n";

        foreach ($this->processedFiles as $item) {
            $indent = str_repeat("  ", $item['depth']);
            $marker = $item['type'] === 'directory' ? '📁 ' : '📄 ';
            $path = $item['path'];
            $name = basename($path);

            // Generate absolute paths instead of relative paths
            $displayPath = $marker . ($item['type'] === 'directory'
                    ? "[{$name}](/docs/{$path}/)"
                    : "[{$name}](/docs/{$path}.md)");

            $content .= $indent . $displayPath . "\n";
        }

        $frontmatter = $this->generateFrontmatter(0);
        $finalContent = $frontmatter . $content;

        file_put_contents($this->docsDir . '/structure.md', $finalContent);
    }

    private function generateFrontmatter(int $position): string {
        return <<<EOT
---
sidebar_position: {$position}
---

EOT;
    }

    private function generateCategoryFile(string $dirPath, int $depth): void {
        if ($dirPath === $this->sourceDir) {
            return;
        }

        $dirName = basename($dirPath);
        $relativePath = substr($dirPath, strlen($this->sourceDir) + 1);
        $targetPath = $this->docsDir . '/' . $relativePath . '/_category_.json';

        $category = [
            'label' => ucwords(str_replace(['-', '_'], ' ', $dirName)),
            'position' => $depth * 100,
            'link' => [
                'type' => 'generated-index',
                'description' => "Documentation for " . ucwords(str_replace(['-', '_'], ' ', $dirName)),
            ],
        ];

        file_put_contents($targetPath, json_encode($category, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getRelativePath(string $path): string {
        return substr($path, strlen($this->sourceDir) + 1);
    }
}

try {
    $generator = new DocusaurusContentGenerator(
        'C:\Users\aida0\PhpstormProjects\linux',
        'C:\Users\aida0\PhpstormProjects\linux-kernel-explorer\docs\linux-v6.12',
    );
    $generator->generate();
    echo "コンテンツの生成が完了しました！\n";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}