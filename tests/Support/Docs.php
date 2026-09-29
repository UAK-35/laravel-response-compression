<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests\Support;

/**
 * The markdown this package ships, read for the links in it.
 *
 * WHY THIS EXISTS
 * ---------------
 * The `docs/` records are the package's explanation of itself: nine of them, each linked
 * from the README's Design records section, each linked onward from its neighbours, and
 * several of them naming a source file or a test that is supposed to exist. Nothing in the
 * suite noticed when one of those links stopped resolving, and the failure modes are quiet
 * ones — a renamed test, a doc added but never linked, a relative path written from the
 * wrong directory. A reader who follows a dead link learns nothing and reports nothing.
 *
 * So the links are read out of the files as text and resolved against the file they were
 * written in, which is the only way to catch a path that is right relative to the package
 * root and wrong relative to the document.
 *
 * @guards-index reading
 */
final class Docs
{
    /**
     * Every markdown file the package ships: the four at the root that are meant to be
     * read, and each design record under docs/.
     *
     * @return list<string>
     */
    public static function files(string $root): array
    {
        $files = [
            $root.'/README.md',
            $root.'/CHANGELOG.md',
            $root.'/RELEASING.md',
            $root.'/PUSHING.md',
        ];

        foreach (glob($root.'/docs/*.md') ?: [] as $record) {
            $files[] = $record;
        }

        return array_values(array_filter($files, is_file(...)));
    }

    /**
     * The design records, keyed by the name a link would use for them.
     *
     * @return list<string> paths relative to docs/
     */
    public static function records(string $root): array
    {
        $names = [];

        foreach (glob($root.'/docs/*.md') ?: [] as $record) {
            $names[] = basename($record);
        }

        sort($names);

        return $names;
    }

    /**
     * Every inline link in one file: the target as written, and the line it is on, so a
     * failure can be looked up rather than searched for.
     *
     * @return list<array{target: string, line: int}>
     */
    public static function links(string $file): array
    {
        $contents = @file_get_contents($file);

        if ($contents === false) {
            return [];
        }

        return self::linksIn($contents);
    }

    /**
     * Every inline link in a piece of markdown, read from the text rather than from a file.
     *
     * The two callers hold different things and mean the same thing by a link: the docs guard has
     * a whole document, and the guard-index guard has one cell of a table. One reader serves both,
     * so a link cannot come to mean two things — and a row's link can be resolved against the
     * document it was written in, which is only true because both go through here.
     *
     * @return list<array{target: string, line: int}>
     */
    public static function linksIn(string $markdown): array
    {
        $links = [];

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $index => $line) {
            if (preg_match_all('/\]\(([^)\s]+)\)/', $line, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $target) {
                $links[] = ['target' => $target, 'line' => $index + 1];
            }
        }

        return $links;
    }

    /**
     * Where a link points, relative to the package root — or null when it does not point
     * at this repository at all.
     *
     * An absolute URL, a `mailto:`, and a bare `#anchor` are all answered with null: none of
     * them can be resolved against a checkout, and pretending otherwise would make this
     * guard fail for a document that is right.
     */
    public static function resolve(string $root, string $file, string $target): ?string
    {
        if ($target === '' || str_starts_with($target, '#')) {
            return null;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $target) === 1) {
            return null;
        }

        // A link may carry the anchor of the section it wants, which is not part of the path.
        $path = explode('#', $target)[0];

        if ($path === '') {
            return null;
        }

        $from = str_replace('\\', '/', dirname($file));

        return self::normalise($from.'/'.$path);
    }

    /**
     * A path with `.` and `..` resolved, so `docs/../tests/x.php` compares as `tests/x.php`.
     */
    public static function normalise(string $path): string
    {
        $absolute = str_starts_with($path, '/');
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '') {
                continue;
            }
            if ($part === '.') {
                continue;
            }
            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return ($absolute ? '/' : '').implode('/', $parts);
    }

    /**
     * A path as it should be printed: relative to the package root when it is inside it.
     */
    public static function relative(string $root, string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root.'/') ? substr($path, strlen($root) + 1) : $path;
    }
}
